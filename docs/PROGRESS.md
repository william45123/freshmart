# FreshMart redesign — PROGRESS

**Start every session by reading this file and `git log --oneline -20`.**
Re-orient from the repository, never from memory. If the log shows work you
don't recognise, say so and **verify** it — don't redo it and don't claim it.
That has happened twice in this project and both times the work was sound.

Never end a session with a phase partly committed. Commit it, or state plainly
what is uncommitted and why.

---

## STANDING RULES

These were all learned the expensive way. Each cost at least one phase.

### 1. Never verify with a metric the fix changes
Phase 4 added `overflow-x: hidden` **and** reported "no horizontal scroll on any
page I measured". Those are the same fact: with `overflow-x: hidden`,
`scrollWidth` can never exceed `clientWidth`, so overflow becomes silent
clipping and the check always passes. 32 of 32 pages were overflowing while the
check said 0.

Measure the thing the user sees — element rectangles against `clientWidth` —
not a proxy that your own change controls. Same failure: the first
duplicate-class detector shared the exact bug it was hunting and reported clean.

### 2. The second occurrence is the signal
When the same class of defect appears twice, stop fixing the instance and find
what they share.
- Headings flush to the viewport edge: fixed once as a container quirk, once
  written off as an intentional full-bleed, and only on the third occurrence
  traced to `.u-page-head` using the `padding` shorthand — one Phase 1
  de-inlining artefact cancelling the gutter on 7 pages from the last layer.
- `[^>]*` matching past `?>`: three separate regressions before it was written
  down.

### 3. Never regex HTML tags in this codebase
`[^>]*` stops at the `>` inside `<?= … ?>`, so any tag regex silently matches
the wrong span. Three regressions: the favicon `href` broken by an `icon()`
substitution, a quick-add form placed outside its product loop, and a card
conversion that matched nothing.

Mask PHP first (`re.sub(r'<\?.*?\?>', …, flags=re.S)`) or use PHP's
`token_get_all()`, then match the closing tag by **balance**. This applies to
checkers as much as to transformers.

### 4. The spec describes the audit, not the code
`MASTER_IMPLEMENTATION_PROMPT_V2.md` documents the state when it was written.
Verify anything carried from it against the code before repeating it. "Chart.js
loads from a CDN globally" was true at audit time, fixed in Phase 3, and still
being reported as outstanding two phases later.

### 5. "Verified" means looked at
A page that returns 200 with an empty error log can still render broken. A stray
`">` sat at the top-left of **every page** for a whole phase behind a green
suite. Run `tools/validate_markup.py`, take screenshots with
`tools/screenshot.py`, and in any report **separate what was verified visually
from what was verified programmatically, and name the pages actually
screenshotted**. "3 of 36" is a fine answer; implying 36 is not.

### 6. Glyph inventories are measured, not recalled
Grepping a list of characters you remember finds only what you already thought
of. Use `\p{Extended_Pictographic}`, and report by category — the total depends
entirely on the definition.

**The C8 line, by function not by character:**
- **Replace with `icon()`** — anything standing alone as a symbol for a concept
  (`✓`/`⚠` in facet labels, `⏳`/`✓`/`✕` in status banners).
- **Keep** — punctuation inside running text (`→` in a link label, `★` in a
  rating widget, `−`/`+` in steppers, `≈` before a number).
- All pictographic characters and their U+FE0F selectors go, wherever they sit.
- Convert as each page is touched. No separate sweep.

### 7. Verify through the app's connection, not the CLI
`db.php` pins the PDO session to `+08:00`; the `mariadb` CLI runs UTC. Anything
using `CURDATE()`/`NOW()` disagrees across the two. This cost an hour chasing a
phantom 40-vs-44 discrepancy that was purely a timezone artefact.

### 8. Any change touching the database ships as a migration file
`database/migrations/`, phpMyAdmin-Import-runnable, idempotent, with
verification and rollback SQL in comments. See `SETUP.md`.

### 9. Never commit `APP_URL` as `127.0.0.1:8899`
The test harnesses rewrite `includes/config.php` and restore it via a `trap`.
A crashed run once leaked the test URL into a commit. Check before committing.

---

## HANDOVER MECHANICS

The previous agent could not push — no credentials, `git push` failed with
*could not read Username*. Work shipped as **git bundles**, verified by cloning
`origin` and applying before sending. Never write a bare `git pull` in
instructions; it silently does nothing. The form is:

```
git pull C:\path\to\<bundle> main
```

Every handover states: apply command · migrations to Import and their order ·
whether cron must re-run · what should be visible if it worked.

**Claude Code running locally can push directly, so this constraint is gone.**

---

## STATE

Working tree clean. All phases below committed.

| Commit | What |
|---|---|
| `528d7d6` | **Phase 1** — de-inline 835 → 10 inline styles (the 10 are `--fresh`/`--pct`/`--n`/`--step` carriers); `main.css` rebuilt on `@layer tokens, base, components, pages, utilities`; 21 `!important` → 0; six `<style>` blocks relocated |
| `6e77ad7` | **Admin guard** — the ADMIN check ran *after* the request was handled; 7 of 8 pages committed POST mutations first. Reproduced a CUSTOMER suspending another user. `admin_check()` now runs at the top of each page |
| `aae6e5d` | **Error logging** — `error_log` was unset; now `storage/logs/php-error.log` |
| `b16135c` | **F1** — freshness cache on `stock_batches` + `idx_freshness`, `freshness_sync_batches()`, `fefo_restock()` hook |
| `227a5f6` `a70dfb9` | **Demo-date tool** — idempotent, solves dates backwards from target freshness |
| `37237a2` | **F2** — freshness/availability filtering into SQL against a joined display batch; pagination restored; `fresh-desc`/`value` sorts; `BROWSE_PAGE_SIZE=12` |
| `93f1533` `d9d586c` `349e696` | alt-text fix · cron withdraws stale discounts · **F3** alert deep links |
| `2f54a78` | **(a) `DELIVERY_LEAD_DAYS`** + **F4** two-state expiry alerts with value at risk |
| `8bc8e0d` | **(b)** checkout validates the delivery date against the earliest-expiring batch in the FEFO allocation |
| `c7ad174` | favicon `href` regression · 26 stranded v4 hexes · `validate_markup.py` |
| `d601485` | **Phase 3** — v4 tokens re-pointed as aliases onto the §6 scale, self-hosted woff2, local Chart.js, `icon()` 16 → 50 glyphs, C3 colour migration, focus rings |
| `b6b4bce` | **Phase 4** — breakpoints 24 → 3 canonical, `--gutter`, 48px touch targets, role-aware tab bar, mobile search, 14 tables → cards, charts wrapped, filter sheet |
| `d506089` `5af4b48` | **Phase 5a** — buybar, freshness arc sparkline, one product card + quick-add on all 6 sites, toasts |
| `58dbb2e` `6a51a48` | **Phase 5b/c chrome** — header 95px → 68px, drawer rebuilt, buttons/forms/table, footer with real brand SVGs |
| `3ddc30e` | **Phase 6 batch 1** — browse facets + sticky bar, product disclosures, cart steppers + swipe-remove; root-caused `.u-page-head` |
| `dcd7a51` | **Phase 6 batch 2** — checkout zero-motion (§5.6) + collapsible sections, orders refund banners, freshness explainer |
| `edf357f` | **Mobile proportion pass** — overflow 32 → 10 pages, clamp floors recalibrated, section shorthands, category circles |
| *(final)* | headings wired to type tokens, overflow 10 → 9, harnesses moved into `tools/` |

---

## NEXT — in priority order

### 1. Headings are wired to the tokens but something still overrides them
`h1/h2/h3/h4` now read `var(--t-h1)` etc. **But checkout's `h1` still measures
24px at 360, 768 and 1280 alike** — flat, so a more specific rule is winning.
Find it (likely a utility class on the element, or a later `pages`-layer rule)
and remove it rather than raising specificity. This is the single change that
most affects mobile proportions; the clamp recalibration alone moved very little
because almost nothing read the tokens.

Recalibrated floors, calibrated against 360px, maxima unchanged:
`--t-display` 2.5rem · `--t-h1` 1.75rem · `--t-h2` 1.375rem · `--t-h3` 1.125rem
· `--t-data-xl` 1.5rem · `--section-y` 2.5rem.

### 2. Nine pages still overflow at 360
Run `python3 tools/check_overflow.py` (needs the dev server on :8899) for the
current list. Last known:
- **8 retailer/admin pages** — `button.nav-toggle +14px`. The role headers carry
  an extra CTA the customer header does not.
- **`/become-retailer.php`** — `div.level-card +85px`, a fixed-width card.

Fix at the rule, not per page. Check the shared cause first (rule 2).

### 3. Phase 6 remaining
Batch 3 was never started: **auth** (login, register, forgot/reset) and the
**account pages** (wallet, wishlist, notifications, profile). Then the
**homepage last**, per the agreed order. Pick up §7.3 per-page treatments as you
go — Home rails, notifications grouping — rather than as a separate pass.

### 4. Phases 7–9, untouched
- **Phase 7** — retailer console (§9.6, F10 order timeline)
- **Phase 8** — admin console, dark `--canvas` role theme
- **Phase 9** — accessibility sweep (§10), performance, `prefers-reduced-motion`
  audit, PWA (§7.6)

### 5. Known open items
- **Footer layout** — hit-areas and colours done; the four-column grid is new but
  the content hierarchy inside it is unchanged.
- **Drawer icon alignment** — rows without an icon sit flush left while iconned
  rows indent. Cosmetic.
- **Product breadcrumb sits outside the gutter container.** The hero is
  deliberately full-bleed; the breadcrumb should not be.
- **Notification bell is auth-gated**, so guests see brand + search + menu. This
  was a deliberate override of §7.1 — a guest bell can only link to a login wall
  and show zero. Agreed with William. Flip it only if a sign-in prompt is wanted.
- **Manual-price hazard.** The cron's `else` clearing `selling_price_override` is
  safe **only because the cron is its sole writer** — verified: two writes, both
  in `freshness.php`; `fefo_restock()`'s INSERT omits the column; no UI sets it;
  the seed ships zero non-NULL values. **If a retailer promo-price feature is
  ever added, that `else` wipes it on every cron run.** The fix then is a
  separate column or an `is_manual_price` flag, not a condition on the `else`.
- **Emoji remaining** — measured by category, not a single number. Convert as
  pages are touched, per rule 6. Batch 1 and 2 pages are already clean.
- **Legacy `max-width` media queries** — values are on the canonical scale but
  legacy rules are still `max-width`. Invert each as its component is rewritten;
  do not do a separate pass.

---

## THINGS THAT ARE NOT OBVIOUS FROM THE CODE

- **Demo data ages out.** The seed's window ends **2026-08-15**. Past that the
  whole catalogue reads EXPIRED and browse goes empty. Run
  `php tools/refresh_demo_dates.php` **before** `php cron/update_freshness.php`,
  every time, before every demo. The cron is destructive on stale data — it
  expires the catalogue in one pass and the F1 rollback does **not** undo it.
- **`freshness_config.color_hex` must never be hardcoded.** It is admin-editable
  and injected at render time as `style="--fresh: <hex>"`. Changing a value in
  the DB must change the site immediately. This is C3 and it is load-bearing.
- **`fefo_restock()` is the only `INSERT INTO stock_batches` in the codebase.**
  That is why the freshness cache hook lives there.
- **The header's flex row is `.site-header > .container`, not `.site-header`.**
  Rules written against the header itself are inert. This wasted an entire
  header rebuild.
- **`u-*` utilities are Phase 1 de-inlining artefacts** holding fixed desktop
  values. `u-cols-2` clipped checkout at 360; `u-page-head` cancelled the gutter
  on 7 pages. Suspect them first for any responsive defect.
- **`.container` is the single owner of the horizontal gutter.** Sections set
  vertical rhythm only (`padding-block`). Do not add inline padding to sections
  and do not zero it on nested containers.
- **Checkout suppresses all decorative motion** via a `page-checkout` body class,
  unconditionally — not via `prefers-reduced-motion`, which is the user's
  setting. Verified against browse as a control: checkout NONE, browse six.
- **Test accounts**: `admin@freshmart.my` · `retailer@cameron.my` ·
  `cherry@example.my`. For local testing, set a known password with
  `UPDATE users SET password_hash='<php password_hash output>';`
  **and restore afterwards** — leaving every account on a test password once
  polluted a database snapshot.
- **The CSRF field is `name="_csrf"`**, not `csrf_token`. Getting this wrong
  makes every scripted login fail with a token mismatch that looks like an auth
  bug.

## TOOLING (all in `tools/`)

| Script | Purpose |
|---|---|
| `refresh_demo_dates.php` | Re-anchor demo dates to `CURDATE()`. Idempotent. `--dry-run`, `--clear-notifications` |
| `validate_markup.py` | Attribute-escape debris, markup in URL attributes, unbalanced quotes, raw `<?` in output |
| `screenshot.py` | Rendered captures + top-of-page text + JS console errors |
| `check_overflow.py` | Per-element horizontal overflow at 360px across all 32 pages |
| `render_public.sh` / `render_auth.sh` | Boot DB + dev server, render every page, fail on PHP errors or markup findings |

The render scripts rewrite `APP_URL` and restore it via a `trap`. Playwright with
headless Chromium is required for `screenshot.py` and `check_overflow.py`.
