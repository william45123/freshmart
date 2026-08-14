# Migrations — SETUP

Run these on your local XAMPP database (`freshmart`). Everything before this
point was code only; this is the first change that touches your data.

**Back up `freshmart` first** (phpMyAdmin → Export → Quick → SQL). Read the
warning in step 2 before you run it.

---

## One-time, before anything else

Create the log directory — it is not in git, only a `.gitkeep` is:

```
mkdir storage\logs
```

`storage/logs/*.log` is gitignored, so `php-error.log` will never be committed.
With `APP_DEBUG=false`, PHP errors now land in `storage/logs/php-error.log`
instead of disappearing into Apache's own log.

---

## Step 1 — apply the migration

phpMyAdmin → select `freshmart` → **SQL** tab → paste
`database/migrations/f1_freshness_cache.sql` → **Go**.

Adds three nullable columns and one index to `stock_batches`. No existing
column or row is modified. Immediately afterwards every ACTIVE batch has
`freshness_level = NULL` — that is expected, step 2 fills it.

Re-running this errors with *Duplicate column name* — harmless, it means the
migration is already applied.

## Step 2 — populate the cache

```
php cron\update_freshness.php
```

> **Warning — this step is not covered by the rollback.**
> The cron does more than fill the cache. It has always also marked genuinely
> expired batches `EXPIRED`, applied Last Chance discounts, and notified
> retailers. On the shipped demo data those batches are *already past their
> expiry date*, so the first run will expire most of them. Dropping the three
> columns does **not** undo that — `status`, `selling_price_override`,
> `notifications` and `inventory_logs` all stay changed.
>
> If your local data still has the shipped dates (batches expiring
> 2026-07-17 → 2026-08-15), refresh the demo dates *before* running this, or
> restore from your backup afterwards.

Expected output shape:

```
  Scanned:       <n> batches
  Cache synced:  <n> batches      <- should equal Scanned
  Expired:       ...
  Discounted:    ...
```

## Step 3 — verify

The commented queries at the bottom of `f1_freshness_cache.sql` do this. Two
of them are invariants that must hold on any dataset:

| Check | Expected |
|---|---|
| `SHOW COLUMNS FROM stock_batches LIKE 'freshness%'` | **3 rows** |
| `SHOW INDEX ... WHERE Key_name='idx_freshness'` | **2 rows** (one per column) |
| ACTIVE batches with `freshness_level IS NULL` | **0** |
| Cached `freshness_pct` outside its level's `freshness_config` band | **0** |

The last two are the ones that matter — they say the cache is complete and
agrees with the admin-editable thresholds.

Distribution is a sanity read, not a fixed number; it depends on how fresh
your data is. On the shipped demo data as of 2026-08-14 it came out as
1 ACTIVE/LAST_CHANCE and 56 EXPIRED, because that data has aged out.

Confirm the index is actually being used:

```sql
EXPLAIN SELECT id FROM stock_batches
 WHERE freshness_level = 'LAST_CHANCE' ORDER BY expiry_date;
```

`key` should read `idx_freshness`. If it says `NULL`, the index did not apply
and F2's filtering will fall back to a table scan.

---

## Rolling back

`database/migrations/f1_freshness_cache_rollback.sql`. Index first, then
columns. No data loss — the three columns are a derived cache, rebuilt by
re-applying step 1 and step 2.

If F2 is already deployed, revert the F2 code commit too: its browse query
reads `freshness_level` directly and will fatal on *Unknown column* once the
columns are gone.
