# Migrations — SETUP

Run these on your local XAMPP database (`freshmart`).

**Sequence: backup → migrate → refresh dates → cron → verify.** The order
matters; step 4 is destructive on stale data and step 3 is what prevents that.

---

## Step 0 — once, before anything

```
mkdir storage\logs
```

Not in git (only `.gitkeep` is) and `storage/logs/*.log` is gitignored, so
`php-error.log` will never be committed. With `APP_DEBUG=false`, PHP errors now
land there instead of disappearing into Apache's own log.

---

## Step 1 — back up

phpMyAdmin → `freshmart` → Export → Quick → SQL. Steps 3 and 4 both change data
and only step 2 is covered by a rollback script.

---

## Step 2 — apply the F1 migration

phpMyAdmin → select `freshmart` → **SQL** tab → paste
`database/migrations/f1_freshness_cache.sql` → **Go**.

Adds three nullable columns and one index to `stock_batches`. No existing column
or row is modified. Immediately afterwards every ACTIVE batch has
`freshness_level = NULL`; step 4 fills it.

Re-running errors with *Duplicate column name* — harmless, it means the
migration is already applied.

Rollback: `f1_freshness_cache_rollback.sql`. Index first, then columns —
MariaDB will not drop a column an index still references. No data loss; the
columns are a derived cache. If F2 is already deployed, revert its code commit
too, or browse will fatal on *Unknown column*.

---

## Step 3 — refresh the demo dates

```
php tools\refresh_demo_dates.php --dry-run     preview
php tools\refresh_demo_dates.php               apply
```

The shipped demo data has fixed dates, with a window ending **2026-08-15**.
Once today passes that, the whole catalogue reads as EXPIRED, browse goes empty
and nothing freshness-related can be shown. This re-anchors the batches to
`CURDATE()` so all five levels are represented again.

Re-run it before every demo. It is idempotent — the level a batch gets depends
only on its id, so two runs on the same day produce identical dates.

It does **not** space batches evenly across days. Freshness is a power law, so
the same elapsed fraction lands on different levels per category: at Last Chance
seafood (n=2.5) is 33% through its shelf life while eggs & tofu (n=1.0) is 89%
through. It solves each date backwards from a target freshness using
`elapsed = 1 - (target/100)^(1/n)`, with `n` and the level bands both read from
the database, then checks each result with the same `freshness_level()` the app
uses and nudges by a day until it lands.

**What it will not touch:** batches referenced by `order_items` (rebasing those
gives you an order placed weeks ago against stock received tomorrow, which
confuses FEFO and the order detail pages), and anything `DEPLETED` or
`RECALLED`. On the shipped data that leaves 10 batches alone.

---

## Step 4 — populate the cache

```
php cron\update_freshness.php
```

> **Warning — this step is not covered by the rollback.**
> The cron does more than fill the cache. It has always also marked expired
> batches `EXPIRED`, applied Last Chance discounts and notified retailers.
> Dropping the three F1 columns does **not** undo any of that: `status`,
> `selling_price_override`, `notifications` and `inventory_logs` all stay
> changed.
>
> **Run step 3 first.** On data that has aged past its expiry window, running
> this without refreshing the dates will expire nearly the whole catalogue in
> one pass, and only your step 1 backup will bring it back.

Expected output shape:

```
  Scanned:       <n> batches
  Cache synced:  <n> batches      <- should equal Scanned
  Expired:       ...
  Discounted:    ...
```

---

## Step 5 — verify

The commented queries at the bottom of `f1_freshness_cache.sql` cover this. Two
are invariants that must hold on any dataset:

| Check | Expected |
|---|---|
| `SHOW COLUMNS FROM stock_batches LIKE 'freshness%'` | **3 rows** |
| `SHOW INDEX ... WHERE Key_name='idx_freshness'` | **2 rows** (one per column) |
| ACTIVE batches with `freshness_level IS NULL` | **0** |
| Cached `freshness_pct` outside its level's `freshness_config` band | **0** |

Then confirm the index is actually being used:

```sql
EXPLAIN SELECT id FROM stock_batches
 WHERE freshness_level = 'LAST_CHANCE' ORDER BY expiry_date;
```

`key` should read `idx_freshness`. If it says `NULL`, the index did not apply
and F2's filtering falls back to a table scan.

Level distribution is a sanity read, not a fixed number. After steps 3 and 4 on
the shipped data it comes out as:

| Level | Batches | Products (what browse shows) |
|---|---|---|
| VERY_FRESH | 5 | 5 |
| FRESH | 5 | 5 |
| ENJOY_SOON | 4 | 4 |
| LAST_CHANCE | 30 | 30 |
| EXPIRED | 13 | — |

The 13 expired are the 3 the refresh tool deliberately places in the past (so
the cron has something to expire and alert on) plus the 10 order-linked batches
it leaves alone.
