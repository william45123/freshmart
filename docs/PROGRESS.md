# FreshMart redesign — progress

### Standing rule: "verified" means looked at

Any phase touching markup, CSS or templates is not done until the **rendered
output** has been looked at, not just its status code. Run
`tools/validate_markup.py`, take screenshots with `tools/screenshot.py`, and in
the report separate what was **verified visually** from what was only
**verified programmatically**, and name the pages actually screenshotted —
"3 of 36" is a fine answer, implying 36 is not. If a page could not be seen,
say so plainly rather than reporting it as verified.

This exists because a stray `">` rendered at the top-left of every page for a
whole phase. The suite reported 36/36 clean because it only checked HTTP status
and the PHP error log. William found it in a screenshot.

Related: when a claim is broad ("the palette moved"), grep for the **old
values**, not just the token names, before making it. 26 hardcoded hexes
survived a re-point that was reported as wholesale.

---

**Read this and `git log --oneline -15` before doing anything.** Re-orient from
the repository, not from memory. If the log shows work you don't recognise, say
so and verify it — don't redo it. (On 2026-08-14 three commits appeared during a
context gap; they turned out to be sound, but only because they were checked
rather than assumed.)

Never end a turn with a phase partly committed. Commit it, or say plainly what
is uncommitted and why.

---

## Complete

| Commit | What |
|---|---|
| `528d7d6` | **Phase 1** — de-inline templates (835 → 10 inline styles, the 10 being `--fresh` / `--pct` / `--n` / `--step` carriers), `main.css` rebuilt on `@layer tokens, base, components, pages, utilities`, 21 `!important` removed, six `<style>` blocks relocated, `.section-gap` deleted, anchor underline scoped |
| `6e77ad7` | **Admin guard** — the ADMIN role check ran *after* the request was handled; 7 of 8 pages committed POST mutations before it. Reproduced a CUSTOMER suspending another user. `admin_check()` now runs at the top of each page |
| `aae6e5d` | **Error logging** — `error_log` was unset; now `storage/logs/php-error.log` with a fallback if unwritable |
| `b16135c` | **F1** — freshness cache on `stock_batches` (`freshness_pct`, `freshness_level`, `freshness_synced_at`, `idx_freshness`), `freshness_sync_batches()`, `fefo_restock()` hook |
| `227a5f6` | **Demo-date tool** — `tools/refresh_demo_dates.php`, idempotent, solves dates backwards from target freshness |
| `a70dfb9` | Tool: price-override consistency report, opt-in `--clear-notifications` |
| `37237a2` | **F2** — freshness/availability filtering moved into SQL against a joined display batch, pagination restored, `fresh-desc` + `value` sorts, `BROWSE_PAGE_SIZE = 12` |
| `93f1533` | Fix: empty `alt` on order item images (`$item['name']` → `product_name`) |
| `d9d586c` | Fix: cron never withdrew a discount when a batch left LAST_CHANCE |
| `349e696` | **F3** — expiry alerts link to `inventory.php?batch=`, with scroll + focus + non-colour highlight |
| `aa95ef7` | (yours) local `APP_URL` — **do not overwrite**; the test harness now restores `config.php` via a trap |
| `d601485` | **Phase 3** — v4 tokens re-pointed as aliases onto the new scale, self-hosted woff2 (C5), local Chart.js (C4), `icon()` 16 → 47 glyphs and 74 emoji removed (C8), C3 colour migration, focus rings, body 16px |

Verified for all of the above: 56 PHP files lint clean, 36 pages render with no
PHP errors, escaping calls unchanged at 819, no duplicate `class` attributes.

## In progress

Nothing. The tree is clean and every phase above is committed.

## Next, in this order (agreed)

1. ~~**(a) `DELIVERY_LEAD_DAYS`**~~ done in `2f54a78`. Original note: — `config.php`, shared by browse's
   expiry predicate and checkout's delivery-day picker. Behaviour-identical
   refactor: `expiry_date > CURDATE()` already equals
   `>= CURDATE() + 1` on a DATE column, but reads as "not expired today" when it
   is actually enforcing a delivery-lead rule.
2. ~~**F4**~~ done in `2f54a78`. Original note: pre-expiry alerts at ENJOY_SOON and LAST_CHANCE, deduped per batch
   per level, with a **two-state** message: *still sellable* vs *past the
   delivery cut-off*, value at risk shown on both. Today the cron alerts
   retailers about stock browse has already hidden, with no indication it is
   unbuyable.
3. ~~**(b)**~~ done in `8bc8e0d`. Original note: because it is the only item
   touching order logic. Constrain the picker to dates every cart item survives;
   re-check server-side at submit; validate against the **FEFO-allocated** batch,
   not the display batch; roll back cleanly on failure. Rollback UX to be
   proposed and confirmed before building.

## Known pending

- **Manual-price caveat.** The cron's `else` that clears
  `selling_price_override` is safe *only because the cron is its sole writer* —
  verified: two writes, both in `freshness.php`; `fefo_restock()`'s INSERT omits
  the column; no retailer or admin screen sets it; the seed ships zero non-NULL
  values. **If a retailer promo-price feature is ever added, that `else` will
  wipe it on every cron run.** The fix then is a separate column or an
  `is_manual_price` flag — not a condition bolted onto the `else`.
- **F7 delivery-date label.** Items arriving on their expiry day should be
  labelled "Arrives on its last day", computed against the delivery date rather
  than today. Approved, deferred to F7.
- **`BROWSE_PAGE_SIZE`** (`config.php`, 12) is also intended as the mobile
  "Load more" chunk size (§7.3). Read it there rather than adding a second
  number.
- **Verify through the app's connection, not the `mariadb` CLI.** `db.php` pins
  the PDO session to `+08:00`; the CLI runs UTC. Anything using `CURDATE()` or
  `NOW()` disagrees across the two, which cost an hour chasing a phantom
  40-vs-44 discrepancy.
- **Container padding is missing on some page headings** — "Checkout" and "Your
  cart" render flush against the left viewport edge at 1280px. Seen on
  screenshots; layout, so Phase 5/6.
- **17 emoji sites remain**, all in PHP string/array literals: `index.php`
  `$catEmoji`, `reco_render_section()` headings, `notifications.php`,
  `wishlist.php`, `admin/dashboard.php`. Not mechanical swaps.
- **Emoji still in PHP array literals** (`index.php` `$catEmoji` category map,
  `reco_render_section()` heading glyphs). Not mechanical swaps — the category
  circles need real imagery and that is a Phase 6 component decision, not a
  token change.
- **Verify markup, not just status codes.** A 200 with clean PHP logs can still
  render broken. The Phase 3 favicon regression put an `icon()` call inside an
  `href=""`; its quotes closed the attribute and the rest of the tag appeared as
  the visible text `">` on every page. `tools/validate_markup.py` now runs on
  every page in both harnesses and checks for attribute-escape debris, markup
  inside URL attributes, unbalanced quotes in a start tag, and raw `<?` in the
  response.
- **Colour hardcoded inside a `data:` URI cannot be reached by a token.** The
  hero scribble kept the v4 terracotta through the whole Phase 3 re-point for
  exactly this reason. When re-pointing tokens, grep the old palette's literal
  hexes as well as the token names.
- **Any change touching the database ships as a migration file** in
  `database/migrations/`. Standing rule.
- **Demo data ages out.** The seed's window ends 2026-08-15. Run
  `tools/refresh_demo_dates.php` before every demo, and always *before*
  `cron/update_freshness.php` — the cron expires stale batches and that is not
  covered by the F1 rollback.

## Open decisions

None outstanding. (b)'s rollback UX is the next thing needing a decision, and it
will be proposed before any code is written.

---

## Delivering work to William's machine

**The agent cannot push.** There are no GitHub credentials in the container and
there should not be. `git push` fails with *could not read Username*. Read access
to `origin` works, which is only useful for checking what has been pushed.

So every phase ends with a **git bundle**, produced without being asked, and the
handover message states four things. Never write a bare `git pull` — it will
silently do nothing, because the commits do not exist on any remote.

Bundle, incremental from the recipient's current HEAD:

```
git bundle create /tmp/<phase>.bundle <their-HEAD>..main
```

Verify it before sending, by cloning `origin` and applying it — a bundle that
does not apply is worse than no bundle.

The handover message must say:

1. **Apply** — `git pull C:\path\to\<phase>.bundle main`  (NOT `git pull`)
2. **Migrations** — which files to Import via phpMyAdmin, in what order, or
   "none"
3. **Cron** — whether `php cron\update_freshness.php` needs re-running, or
   "not needed"
4. **Confirm** — what should be visible on screen if it worked

Then he pushes to `origin` himself, from his machine, with his credentials.
