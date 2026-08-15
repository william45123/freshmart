# MASTER IMPLEMENTATION PROMPT v2 — FreshMart Complete Product Redesign

> Supersedes v1. Copy everything below the line into your AI coding agent.
> Covers: visual redesign + UX redesign + functional fixes + feature work + Android mobile experience + motion + performance.

---

You are a senior frontend engineer, product engineer, and creative UI/UX developer. Carry out a complete redesign of the existing **FreshMart** application — a freshness-first online grocery for Malaysia built as a university Final Year Project.

This is **not** a CSS reskin. It is: visual redesign + UX redesign + functional repair + targeted feature work + a proper Android mobile experience, delivered as one coherent product.

**Preserve the business logic.** Do not change the freshness formula, FEFO allocation, auth, wallet/refund/commission maths, or routing. You *will* make specific, listed database and PHP changes — every one of them is enumerated in §3 and §5. Do nothing beyond that list without reporting first.

---

## 0. HARD RULES

1. **Do not start with CSS.** Start with Phase 1 (de-inlining) — there are **835 inline `style=""` attributes** and they override everything you write.
2. **Work in the phases in §4, in order.** The site must run after every phase.
3. **No new runtime dependencies** beyond §8.1. No build step, no npm, no bundler exists.
4. **Never break the database-driven freshness colours** (§3, C3).
5. **Animate only** `transform`, `opacity`, `filter`, `clip-path`.
6. **No native mobile app.** No Android Studio, Kotlin, Java, Flutter, React Native, or APK. FreshMart stays one responsive web app; Android Chrome is the priority target.
7. **Do not invent data.** Every number shown to a user must come from a real query. No placeholder analytics, no fake AI.
8. If a change would require altering business logic beyond §3/§5, **stop and report**.

---

## 1. PROJECT FACTS

**Stack:** PHP 8 + MariaDB, vanilla CSS/JS, no framework, no build step. XAMPP, web root `public/`. Database `freshmart`, 38 tables.

```
includes/header.php        <head>, header, nav, mobile drawer          (235 lines)
includes/footer.php        footer + all shared inline JS               (133 lines)
includes/helpers.php       e(), url(), asset(), format_myr(), icon()   (238 lines)
includes/freshness.php     freshness engine, freshness_ring_html(),
                           freshness_badge_html(), decorate_with_freshness(),
                           freshness_run_automation()
includes/fefo.php          FEFO batch allocation
includes/recommendations.php  FBT / You-May-Like / Popular / Similar (rule-based)
includes/cart_helpers.php  cart_add(), cart_update_quantity(), cart_totals()…
includes/wallet_helpers.php  wallet + refund state machine
public/assets/css/main.css   2,947 lines — to be replaced
public/assets/js/chart.umd.min.js   vendored Chart.js 4.4.1, currently unused
cron/update_freshness.php  CLI automation, runs every 5–15 min
docs/screenshots/          78 screenshots of the current UI — review first
```

**24 pages:** `index.php` · `shop/{browse,product,cart,checkout,order_confirm,orders,review,freshness}.php` · `{wishlist,wallet,notifications,profile,become-retailer}.php` · `help/freshness.php` · `auth/{login,register}.php` · `retailer/{dashboard,products,product_edit,inventory,orders,refunds,reviews,reports,discounts,profile}.php` · `admin/{dashboard,users,retailers,orders,refunds,reviews,promos,settings}.php`

**Existing class vocabulary to re-skin (PHP echoes these — keep the names):**
`.site-header .brand .nav-main .nav-search .nav-actions .mobile-nav` · `.btn -primary -secondary -ghost -outline -danger -sm -lg -icon` · `.form-control .form-group .form-row .form-help .form-actions` · `.product-card-v2 .product-card-image .product-card-body .product-card-name .product-card-origin .product-card-pricing .price-final .price-base-strike .expiry-hint .discount-tag .discount-tag-tr` · `.freshness-ring .freshness-badge .forecast-card .level-card` · `.kpi-card .kpi-grid .kpi-value .kpi-label .kpi-meta` · `.data-table .status-pill .pagination .page-btn .empty-state .flash` · `.auth-page .auth-card` · body role classes `.role-customer .role-retailer .role-admin`

**First action:** read `docs/screenshots/01-home.png`, `02-browse.png`, `03-product-detail.png`, `04-cart.png`, `20-retailer-dashboard.png`, `admin/31-admin-dashboard.png`.

---

## 2. THE CONCEPT — "PEAK WINDOW"

FreshMart's material is not vegetables. It is **time**. Every product moves along a decay curve toward a deadline at a speed set by its food category, and FreshMart is the only grocery that shows the customer where on that curve they stand and what it is worth today.

Built from three things already in the code:

1. **The curve** — `freshness% = 100 × (1 − t/T)ⁿ` (n = 2.5 seafood, 2.0 bakery, 1.8 herbs, 1.5 vegetables, 1.3 dairy, 1.1 fruit, 1.0 eggs/tofu). The curve is the brand mark, divider, loader, gauge, and hero.
2. **The window** — the band where the item is at its best, and the point where the price drops.
3. **The queue** — FEFO. **Layered/overlapping cards must always mean "batch order," never decoration.**

**Atmosphere: cold-chain light** — cool directional light on dark surfaces, produce the only warm thing in frame. **No neon, no gaming cues, no floating 3D objects.**

**Spend boldness in one place:** the Freshness Arc, whose full-scale form is the homepage hero. Everything else is quiet, precise, and fast.

---

## 3. TECHNICAL CONSTRAINTS

**C1 — 835 inline `style=""` attributes across 25 files will defeat your stylesheet.** Worst: `shop/orders.php` (73), `shop/product.php` (66), `shop/checkout.php` (52), `shop/freshness.php` (50), `admin/orders.php` (46), `shop/cart.php` (40), `retailer/reports.php` (33), `retailer/products.php` (32), `shop/browse.php` (29), `index.php` (29), `become-retailer.php` (29).

**C2 — `main.css` is nine stacked "PATCH/FIX/OVERRIDE" blocks, 21 `!important`, 26 breakpoints.** Do not append a tenth. Rebuild with `@layer tokens, base, components, pages, utilities;` and zero `!important`.

**C3 — Freshness colours live in the database.** `freshness_config.color_hex` is admin-editable via a colour picker in `admin/settings.php`. Update the seed rows, then inject the value as a custom property — `style="--fresh: <?= e($hex) ?>"` — and consume `var(--fresh)` in CSS. **Never hard-code the scale in CSS** or the picker silently stops working.

```sql
UPDATE freshness_config SET color_hex='#1FB574' WHERE level_name='VERY_FRESH';
UPDATE freshness_config SET color_hex='#8CC63F' WHERE level_name='FRESH';
UPDATE freshness_config SET color_hex='#F0A81E' WHERE level_name='ENJOY_SOON';
UPDATE freshness_config SET color_hex='#F0522D' WHERE level_name='LAST_CHANCE';
```
Also change the hard-coded `'#dc2626'` EXPIRED fallback in `includes/freshness.php` to `#B3341F`.

**C4 — Chart.js loads from the jsDelivr CDN** in `admin/dashboard.php:400`, `retailer/dashboard.php:172`, `shop/product.php:500` while a local copy sits unused. **Switch all three to the local file** — this project is demoed live and must work offline.

**C5 — Google Fonts is CDN-only with no fallback.** Self-host woff2 in `public/assets/fonts/` for the same reason.

**C6 — Product images cap at 650×650**; some `.jpg` files contain PNG data. A full-bleed hero photo cannot be sharp on desktop — the hero must be composed of **multiple images displayed at ≤180px**.

**C7 — Global `a { border-bottom: .5px solid currentColor }`** puts full-width underlines on block links (visible in cart). Scope to prose/inline links only.

**C8 — `icon()` has only 16 glyphs**, so emoji fill the gaps (`💳 🚚 🌿 ♻️ 🔒 📧 📞 📍 🎫 🔥 🌱 👋`). The header `💳` renders as a broken rectangle. Extend to ~30 Lucide glyphs; **remove every emoji from UI chrome** (body copy may keep them).

**C9 — `prefers-reduced-motion` only partially handled.** Cover every new animation; the reduced path shows **final state**, never blank.

**C10 — 14 `<table>` elements have no mobile treatment** — no card conversion, no overflow wrapper. They break the viewport on Android.

**C11 — Touch targets are ~34px** (`.btn-sm { padding: 8px 14px }` is used for every header icon button). Android Material minimum is 48dp.

---

## 4. PHASES

Finish each phase completely; the site must run after each.

**Phase 1 — De-inline.** New `main.css` with `@layer` + tokens (§6). Convert all 835 inline styles: decoration → classes; dynamic PHP values → custom properties (`style="--fresh: …"`, `style="--pct: …%"`). Move the six `<style>` blocks into `main.css` under `pages`. Delete `.section-gap`. Scope the anchor underline (C7).
**Acceptance:** project-wide `style="` count **under 40**.

**Phase 2 — Data foundation (§5 F1–F4).** Materialise freshness, fix the browse filter, fix the dead link, add pre-expiry alerts. **Do this before any feature work** — everything else depends on it.
**Acceptance:** `browse.php?freshness=LAST_CHANCE` returns *every* Last Chance product with a correct count and correct pagination.

**Phase 3 — Visual foundation.** Tokens, self-hosted fonts (C5), extended `icon()` + emoji removal (C8), elevation, focus rings, local Chart.js (C4), the SQL in C3.

**Phase 4 — Mobile architecture (§7).** Bottom tab bar, mobile search view, filter bottom sheet, tables→cards, 48px targets, 5 breakpoints. **Build mobile before desktop polish** — it is the harder constraint and it is currently the weakest part of the product.

**Phase 5 — Core components.** Header, drawer, footer, buttons, forms, tables, pagination, empty states, flash→toast, **product card** (§9.1), **freshness arc** (§9.2). Consolidate `.product-grid`, `.product-grid-4`, `.reco-grid`, `.fresh-picks-carousel` into one `.product-grid`.

**Phase 6 — Customer pages** (homepage last): browse, product, cart, checkout, orders, freshness explainer, auth, wallet/wishlist/notifications/profile, then the homepage hero.

**Phase 7 — Features** (§5 F5–F10): quick add-to-cart, plain-language expiry, customer freshness alerts, freshness in cart, password reset, freshness timeline, order timeline.

**Phase 8 — Retailer & admin consoles.**

**Phase 9 — Motion, accessibility, performance, PWA.**

---

## 5. FUNCTIONAL WORK — exact specifications

### F1 — Materialise freshness *(unblocks everything else)*
```sql
ALTER TABLE stock_batches
  ADD COLUMN freshness_pct   DECIMAL(5,2) NULL AFTER status,
  ADD COLUMN freshness_level ENUM('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE','EXPIRED') NULL AFTER freshness_pct,
  ADD COLUMN freshness_synced_at TIMESTAMP NULL AFTER freshness_level,
  ADD INDEX idx_freshness (freshness_level, expiry_date);
```
In `freshness_run_automation()` (`includes/freshness.php`), inside the loop that **already** computes `$level` for every ACTIVE batch, also compute `freshness_percent()` and write all three columns.
**Do not change the formula.** These columns are a cache written by automation that already runs; live display still calls `decorate_with_freshness()`.

### F2 — Fix the broken freshness/availability filter *(critical bug)*
`shop/browse.php` currently applies `LIMIT/OFFSET` in SQL and *then* filters by freshness in PHP, so `?freshness=LAST_CHANCE` — the Last Chance link in your main nav and footer — returns only the matches that happen to land on the current page, while the count and pagination report the unfiltered total.

Move both filters into the SQL `WHERE` clause using the F1 columns, joined against the batch with the earliest expiry. Then add sorts: **`fresh-desc` (Freshest first)**, **`value` (Best Value Today** = `discount_pct / GREATEST(days_remaining,1)` **)**, alongside the existing newest / expiring / price sorts.
**Acceptance:** filtered count, pagination, and result set all agree.

### F3 — Fix the dead notification link
`includes/freshness.php` links expiry alerts to `/retailer/batches.php?id=…`. That file does not exist. Change to `/retailer/inventory.php?batch=<id>` and make `inventory.php` scroll to / highlight that batch.

### F4 — Alert retailers *before* the waste, not after
Today `freshness_run_automation()` notifies only on `EXPIRED` — after the loss. Add alerts at `ENJOY_SOON` (advance warning) and `LAST_CHANCE` (act now), including quantity and **value at risk** (`quantity_remaining × cost_per_unit`, already on `stock_batches`). De-duplicate: one alert per batch per level (track via `freshness_synced_at` or an alert log). Keep the EXPIRED alert as a record.

### F5 — Customer freshness alerts *(the missing identity feature)*
The `notifications.type` enum already contains `PROMO` and `EXPIRY_ALERT`; `PROMO` is never created anywhere and `EXPIRY_ALERT` goes only to retailers.
In the cron, when a batch enters `LAST_CHANCE`, notify every customer who has that product **wishlisted** or **in an active cart**: *"Sekaki Papaya is now 15% off — RM 5.85, best within 2 days."* Type `PROMO`, link to the product. Cap at one alert per user per product per 24h.

### F6 — Quick add-to-cart from every product card
Small POST endpoint (`shop/cart_add.php`) calling the existing `cart_add()`, returning JSON. Card button posts via `fetch`, updates the header cart badge (`aria-live`), shows a toast with **Undo**. **Full no-JS fallback:** the same button inside a `<form method="post">` that redirects back. Respect `min_order_qty` and unit type.

### F7 — Plain-language expiry everywhere *(highest value-to-effort)*
Replace raw dates with human phrasing derived from existing `days_remaining`: `Best within 3 days` · `Use by tomorrow` · `Last day today`. Keep the exact date as secondary/`title` text. Apply on: product cards, product page, **cart**, **checkout**, order confirmation, order detail, wishlist, retailer inventory.

### F8 — Freshness in cart and checkout
Add the freshness chip + plain-language expiry to every cart line and every checkout review line. Last Chance items get a `--fresh-last` left border and a short note. This is the only place expiry has direct commercial force and it is currently absent.

### F9 — Password reset
The `password_resets` table exists and is used by **zero** files; there is no forgot-password link, page, or flow. Build: `auth/forgot.php` (email → token row → link), `auth/reset.php` (token validation, expiry, single use, password update). No mail server is configured, so **write the reset link to a log file and display it in dev mode**, and document this as a known limitation. Reuse `random_token()` and the existing password hashing.

### F10 — Timelines
- **Freshness timeline** (product page): `Very Fresh → Fresh → Enjoy Soon → Last Chance → Expired` with the current position marked and the price at each stage, from data already computed.
- **Order tracking timeline** (order detail): the 8-state enum `PLACED → PROCESSING → QUALITY_CHECK → PACKED → OUT_FOR_DELIVERY → DELIVERED` rendered as a progress timeline with timestamps from `order_history`. Currently only a status pill is shown.

### F11 — Notification priority *(if time allows)*
`ALTER TABLE notifications ADD COLUMN priority ENUM('INFO','WARNING','IMPORTANT','CRITICAL') NOT NULL DEFAULT 'INFO';`
Retailer expiry alerts → `CRITICAL`/`IMPORTANT`; customer discount alerts → `INFO`; order updates → `INFO`. Group the notifications page by day, colour the left edge by priority, add a type filter.

### Do NOT build
AI chatbot or "AI recommendations" (the existing engine is rule-based and honest — keep it that way), loyalty points, live courier tracking, live chat, social features, multi-language, subscriptions. None reinforce freshness; all add complexity.

---

## 6. DESIGN TOKENS — paste under `@layer tokens`

```css
@layer tokens, base, components, pages, utilities;

@layer tokens {
:root{
  /* Neutrals: light */
  --paper:#F4F7F2; --surface:#FFFFFF; --surface-sunk:#E8EDE5;
  --mist:#EDF1EA; --line:#D9E0D5; --line-strong:#B9C4B4;
  --ink:#0C1210; --ink-muted:#5A6660; --ink-faint:#8A968F;
  /* Neutrals: dark canvas */
  --canvas:#0B1512; --canvas-raised:#132420; --canvas-line:#22352E;
  --on-canvas:#F4F7F2; --on-canvas-muted:#9BAAA2;
  /* Brand */
  --pine:#14453A; --pine-deep:#0D2F28; --pine-tint:#DCE8E1;
  /* Freshness — graphic (DARK GROUNDS ONLY) */
  --fresh-very:#1FB574; --fresh-ok:#8CC63F;
  --fresh-soon:#F0A81E; --fresh-last:#F0522D; --fresh-expired:#B3341F;
  /* Freshness — text on light */
  --fresh-very-ink:#0B6B44; --fresh-ok-ink:#4A6B14;
  --fresh-soon-ink:#8A5A06; --fresh-last-ink:#B33A16; --fresh-expired-ink:#8E2412;
  /* Freshness — chip tints on light */
  --fresh-very-tint:#DFF3E8; --fresh-ok-tint:#EDF5DC;
  --fresh-soon-tint:#FCEFD3; --fresh-last-tint:#FCE3DA;
  /* Semantic */
  --success:#1FB574; --warning:#F0A81E; --danger:#D93A22; --info:#2F7D96;
  /* Type */
  --font-display:"Archivo",system-ui,sans-serif;
  --font-sans:"Instrument Sans","Inter",system-ui,-apple-system,sans-serif;
  --font-mono:"IBM Plex Mono",ui-monospace,"SF Mono",monospace;
  --t-display:clamp(3.5rem,10vw,9rem);  --t-h1:clamp(2.5rem,5.5vw,4.25rem);
  --t-h2:clamp(1.875rem,3.4vw,2.75rem); --t-h3:clamp(1.375rem,2vw,1.75rem);
  --t-h4:1.125rem; --t-body-lg:1.0625rem; --t-body:1rem; --t-sm:.875rem;
  --t-eyebrow:.6875rem; --t-data:.9375rem; --t-data-xl:clamp(2rem,4vw,3.25rem);
  /* Space */
  --space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;
  --space-6:24px;--space-8:32px;--space-10:40px;--space-12:48px;
  --space-16:64px;--space-20:96px;--space-24:128px;--space-32:160px;
  --section-y:clamp(4rem,9vw,8rem);
  --container:1320px; --container-wide:1440px; --measure:68ch;
  /* Radius */
  --r-xs:6px;--r-sm:10px;--r-md:14px;--r-lg:20px;--r-xl:28px;--r-full:999px;
  /* Elevation (pine-tinted, not black) */
  --e-1:0 1px 2px rgba(12,18,16,.04), 0 2px 6px rgba(12,18,16,.04);
  --e-2:0 2px 4px rgba(12,18,16,.05), 0 8px 20px rgba(12,18,16,.06);
  --e-3:0 4px 8px rgba(12,18,16,.06), 0 18px 40px rgba(12,18,16,.09);
  --e-4:0 8px 16px rgba(12,18,16,.08), 0 32px 72px rgba(12,18,16,.13);
  --glow-fresh:0 0 0 1px color-mix(in srgb,var(--fresh) 35%,transparent),
               0 6px 28px color-mix(in srgb,var(--fresh) 22%,transparent);
  /* Motion */
  --ease-out:cubic-bezier(.22,.61,.36,1);
  --ease-inout:cubic-bezier(.65,.05,.36,1);
  --ease-decay:cubic-bezier(.16,.84,.44,1);
  --d-fast:140ms; --d-base:240ms; --d-slow:520ms; --d-cinematic:900ms;
  /* Layout */
  --header-h:68px; --header-h-mobile:56px; --tabbar-h:64px;
  --tap:48px;   /* Android Material minimum */
}
.role-retailer{           /* dark control room */
  --paper:var(--canvas); --surface:var(--canvas-raised); --surface-sunk:#0E1C18;
  --mist:#0F1D19; --line:var(--canvas-line); --line-strong:#2E453C;
  --ink:var(--on-canvas); --ink-muted:var(--on-canvas-muted); --ink-faint:#6E8079;
}
.role-admin{              /* cool slate + amber */
  --paper:#F2F4F6; --surface:#FFFFFF; --surface-sunk:#E6EAEE; --mist:#EAEEF2;
  --line:#D5DBE1; --line-strong:#B4BDC6; --ink:#0E1418; --ink-muted:#5A646E;
  --pine:#C9971F; --pine-deep:#A87D14; --pine-tint:#F5E9CC;
  --canvas:#0E1418; --canvas-raised:#18212A; --canvas-line:#2A3540;
}
}
```

**Type rules:** body base 16px (up from 15px). Every price, %, quantity, batch code, date and KPI uses `--font-mono` with `font-variant-numeric: tabular-nums`. Display type once per page maximum. Measure capped at `--measure`.

**Colour rules:** vivid `--fresh-*` on **dark grounds only** (1.9–3.3:1 on paper — they fail WCAG). On light use `--fresh-*-ink` for text and `--fresh-*-tint` for fills. **Never encode freshness by hue alone** — always colour **+ number + label + arc length**.

---

## 7. ANDROID MOBILE ARCHITECTURE — build this in Phase 4, before desktop polish

### 7.1 Navigation
**Bottom tab bar**, fixed, `padding-bottom: env(safe-area-inset-bottom)`, shown `< 900px`, hidden on scroll-down / restored on scroll-up, hidden entirely on checkout.

- Customer: `Home · Browse · Cart(n) · Orders · Account`
- Retailer: `Dashboard · Inventory · Orders · Alerts · Account`
- Admin: `Dashboard · Orders · Users · Reviews · Account`

Wishlist and Wallet live under Account — keep the bar at five.
**Mobile top bar:** 56px, brand + **search icon** + notification bell. Search opens a **full-screen search view** with instant results and recent searches. (Currently `@media (max-width:900px){ .nav-search{display:none} }` deletes search on mobile entirely — fix it properly, don't just re-show the desktop box.)
Keep the existing drawer for the long tail (categories, help, freshness, logout).

### 7.2 Touch rules
- **Every interactive element ≥ 48×48px**, 8px minimum gap. This includes all header icon buttons, currently ~34px.
- **Nothing important behind hover.** Add-to-cart, wishlist and quick actions are always visible under `@media (hover:none)`.
- Primary actions in the **bottom third** (sticky CTAs).
- Swipe only where it maps to a real action (cart remove, gallery, notification read) and **always with a visible button equivalent**.
- No long-press actions.
- `touch-action: manipulation`; `-webkit-tap-highlight-color: transparent` with a real `:active` state.
- **No horizontal page scroll.** Intentional rails only, with `overscroll-behavior-x: contain`.
- Body text ≥16px.

### 7.3 Per-page mobile behaviour
| Page | Treatment |
|---|---|
| Home | Hero curve rotates to a **vertical descent**, 3 products not 6, display type at 3.5rem. Sections become horizontal scroll-snap rails. Status numbers 2×2. |
| Browse | Sticky filter/sort bar. **Filters in a bottom sheet** — drag handle, scrollable body, sticky `Apply filters (N)` footer, live count as options toggle. Active chips scroll horizontally. **2-column grid.** "Load more" instead of numbered pagination. |
| Product | Swipeable full-bleed gallery with dots. Freshness instrument full-width beneath. Collapsible description/storage/reviews. **Sticky bottom bar: price + stepper + Add to cart**, above the tab bar. |
| Cart | Cards with 72px thumbs, freshness chip per line, 48px stepper controls, **swipe-left to remove + Undo toast**, sticky total + Checkout. |
| Checkout | One page, three collapsible sections (Delivery → Payment → Review), one open at a time. 52px inputs, `inputmode="numeric"` on phone/postcode, `autocomplete` throughout, sticky total. **Tab bar hidden.** |
| Orders | Cards not tables; status chip + timeline; expand for items; Reorder primary. |
| Dashboards | **All 14 tables → cards below 900px**: 2–3 key fields visible, rest behind a disclosure. KPIs 2×2. Charts in fixed-height responsive wrappers (`maintainAspectRatio:false`). At-risk block pinned at top with its action. |
| Forms | Single column, 52px fields, labels above, inline validation, `enterkeyhint`; segmented controls or sheets instead of `<select>` where clearer. |
| Notifications | Grouped by day, priority colour on the left edge, swipe to mark read. |

### 7.4 Breakpoints — 26 → 5, all `min-width`, mobile-first
`<400` small mobile (fluid clamp, no separate rules where avoidable) · `400–639` mobile · `640–1023` tablet (3-up, slide-over filters, drawer replaces tabs at the top of the range) · `1024–1279` laptop (full nav, sticky filter rail, 3-col dashboards) · `1280–1599` desktop (4-up, sticky product image, full parallax) · `≥1600` wide (container 1440px, measure still 68ch).

### 7.5 Mobile performance
Simplify on mobile, don't delete: hero keeps its scroll-driven motion with 3 products and no blur layers; `backdrop-filter` on the header only, solid `rgba` fallback below 640px; parallax limited to two planes. Images WebP + `srcset` 320/480/650 with `sizes="(max-width:640px) 50vw, 25vw"`, `loading="lazy"`, explicit dimensions. **Load Chart.js per-page, not globally** (205KB, three pages need it).
Targets on mid-range Android over 4G: **LCP < 2.5s, CLS < 0.05, INP < 200ms**, JS < 60KB excluding Chart.js.

### 7.6 PWA — in scope, scoped tight
`manifest.json` (theme `#14453A`, `display: standalone`, maskable icons 192/512), a service worker caching app shell + fonts + CSS + placeholder images, an offline fallback page, and an install prompt shown on a **second** visit.
**Out of scope:** push notifications, background sync, offline checkout.
**Never:** a native app, Android Studio project, or APK.

---

## 8. MOTION

### 8.1 Allowed dependencies
**Yes:** CSS scroll-driven animations (`animation-timeline: view()` / `scroll()`) as the core technique; IntersectionObserver (already in `footer.php`) as fallback and for stagger; inline SVG + CSS for all curves and gauges; Chart.js (already vendored) restyled.
**No:** Three.js/WebGL (no asset budget at 650×650, no build step, Android target), GSAP/ScrollTrigger (~70KB duplicating native CSS), Framer Motion (no React).
**Optional:** Lenis (~3KB) only if scroll feel is unsatisfying after everything else ships.
**New hand-written JS budget: ~8KB** (6KB motion + 2KB mobile nav/sheet).

### 8.2 System
- **Hover** (`--d-fast`): cards lift 4px + `--e-3`, inner image `scale(1.06)`/700ms; buttons lift 2px; arc brightens and counts up; rows tint; FEFO stack fans. **Retire the `rotateX/rotateY` tilt.**
- **Reveals** (`--d-slow`): extend `.reveal` to `.reveal-left/-right/-scale/-stagger` (children +60ms, cap 8), trigger at 15%, once, drop `will-change` on end. Currently applied to **2 elements on 1 page** — apply site-wide.
- **Parallax:** three planes desktop (`.15×/.4×/1×`), two on mobile, via `animation-timeline: scroll()`, `translate3d` only.
- **Sticky storytelling:** pinned visual column; curve redraws via `stroke-dashoffset`; marker moves per active step. Used on the homepage freshness beat and the freshness explainer.
- **Page transitions:** `@view-transition { navigation: auto; }` plus `view-transition-name: product-<id>` on product images so the grid card **morphs** into the product hero.
- **Loading:** no spinners — skeletons in `--surface-sunk` with a 1.4s shimmer; arcs draw from 0 on `--ease-decay`; KPIs count up over 250ms.
- **Micro-interactions:** add-to-cart morphs to a check + badge pulse + ghost toward the cart; stepper value slides; chips scale-in 120ms; wishlist heart 300ms; error shake 3px; valid-field check in `--fresh-very`.

### 8.3 Guardrails
Transform/opacity/filter/clip-path only. Max 3 simultaneously animating elements outside the hero. Every animation inside `@media (prefers-reduced-motion: no-preference)`; reduced path shows **final state**. `will-change` on hover-intent, removed on end. **Zero motion budget on `cart.php`, `checkout.php`, `order_confirm.php` and all form pages** beyond 140ms hover feedback and focus rings. No `offsetTop` reads in scroll handlers.

---

## 9. KEY COMPONENTS

### 9.1 Product card — one component replacing all four current grids
```
1:1 image, --r-md, overflow hidden, hover scale(1.06)/700ms
  ├ discount chip   top-right, --fresh-last, mono
  └ FRESHNESS CHIP  bottom-left: glass over dark tint,
                    arc + "86%" (mono) + "Very Fresh"
Body: name (--t-h4) · "Best within 4 days" (mono, --ink-muted)   ← F7
      price (--t-data-xl; --fresh-last-ink if discounted) + struck base + unit
Footer: [ Add to cart ]  [♡]   ← F6; hover-revealed on desktop, ALWAYS visible on touch
Card: --surface, --r-lg, 1px --line, --e-1 → hover --e-3 + translateY(-4px)
```
Add `view-transition-name: product-<id>` to the image.

### 9.2 Freshness Arc — the signature element
Upgrade `freshness_ring_html()`: keep the existing circle-gauge maths (`r=42`, dasharray/dashoffset); **add the product's own decay curve as a sparkline inside the ring**, generated in PHP from `100×(1−t/T)^n` using the `decay_exponent` already on the decorated row — so seafood's cliff and fruit's glide become visible. Add `%`, a level label, mono tabular figures, and `--glow-fresh`. Colour via `style="--fresh: …"` (C3). Draws on load over `--d-cinematic` on `--ease-decay`. Sizes: 32 / 56 / 120 / 200px. Keep the existing `aria-label`.

### 9.3 FEFO stack
Three cards offset 6px/4px, `scale(.98)/.96`, decreasing opacity; front card = **the batch that ships next**. Hover fans by 14px. Use on retailer inventory and the product page batch disclosure. **Never decorative.**

### 9.4 Homepage — 13 sections → 7 beats
**① Hero "The Descent"** — full-bleed `--canvas`, `radial-gradient(120% 80% at 50% 0%, #16302A, #0B1512 60%)`, `min-height:100svh`. Display type **"Still good."** / **"Priced accordingly."** in Archivo Expanded 800 at `--t-display`, `-0.035em`, `line-height:.90`; the word *good* in `--fresh-very`. An SVG decay curve spans the width, drawing itself over 1400ms. **Six live products sit on the curve positioned by their real freshness %** (92% high-left → 18% low-right), each ≤180px with its arc. **On scroll they descend**: translate along the path, arc depletes, colour shifts green→amber→coral, **price drops** at the Last Chance threshold. Three parallax planes. Two CTAs, one line of copy, **no stats block**. Reduced-motion/no-JS: everything renders at live positions, static.
**② Live Now strip** — the KPI numbers moved *below* the hero, reframed as live status, mono `--t-data-xl`, count-up. **Fix the `"1 items"` pluralisation bug.**
**③ Category shaped by decay** — cards each carrying a miniature curve at that category's own exponent.
**④ Today's Peak Picks** — new card, scroll-snap, keyboard arrows, stagger.
**⑤ Last Chance — the rescue** — full-bleed canvas, `--fresh-last`, live count, countdowns, one urgent CTA.
**⑥ How the curve works** — sticky storytelling.
**⑦ Closing** — parallax CTA band.
**Delete:** trust-badge emoji row (fold into footer), voucher strip (move to cart), testimonials, service banners.

### 9.5 Other pages
**Browse** — grouped filter rail (Category with counts · **Freshness as five colour chips** · Availability · Price · Origin), sticky desktop / bottom sheet mobile; active-filter chips with × and Clear all; count in mono; new sorts from F2; one `.product-grid` at 4/3/2/2; illustrated empty state.
**Product** — **move the 7-day forecast above the price and make it the page hero**: `--canvas` "Peak Window instrument" with a large arc, the curve with a shaded peak-window band, a pulsing "you are here" marker, and the price-drop point annotated. Keep the `n=2.5 for Seafood` note as tap-to-expand. Add FEFO batch disclosure and the F10 freshness timeline. Sticky image column desktop / sticky CTA mobile.
**Cart** — F7 + F8; unit-aware stepper `[− 1.5 kg +]`, never raw `1.00`; sticky summary; voucher entry moves here; remove → toast with Undo. **Hover feedback only.**
**Checkout** — two columns desktop, collapsible steps mobile; FEFO allocation as a per-line chip; wallet panel; inline validation with focus management. **Zero decorative motion.**
**Auth** — split layout **outside the site chrome**: 45% `--canvas` panel with the curve motif, 55% form on `--paper`, 420px max, 48px inputs. Remove `👋`. Add the F9 forgot-password link. Register: password strength meter using the freshness scale; role selection as segmented cards.
**Retailer console** — dark control room. Top block states **money at risk and the action**: `AT RISK RIGHT NOW — RM 412.80 expires within 72 hours · 10 batches · [Discount all →]` (F4). Then 4 KPIs → freshness distribution (stacked bar) + expiry runway (14-day area chart with danger band) → FEFO-sorted expiring-batches table with inline arc, value at risk, and one-click `[Discount]` per row. Inventory: FEFO stack rows, bulk actions, batch-add modal with a **live curve preview**. Product edit: live card preview; decay-exponent override shown with its curve. Reports: waste cost in RM from `cost_per_unit`. Discounts: draggable Last Chance threshold with live affected-batch count.
**Admin console** — **resolve the theme contradiction** (hero KPI is olive while the role theme declares slate+amber — make it slate+amber). **Remove the "Welcome back, …" flash bar** from dashboards; greeting goes in the header. Tiers: commission hero → 4 KPIs with sparklines → waste-and-rescue panel. Shared table component + filter chips + right-hand detail drawer for approvals. Promos as ticket-shaped cards. Settings: grouped panels, sticky sub-nav, **live curve preview** as thresholds move, colour pickers previewing on light *and* dark (keep the C3 mechanism).
**Global chrome** — header 68px desktop / 56px mobile, `--paper` at rest → glass + `--e-2` past 40px; **fix the wrapping "Last Chance" nav item** by moving Vegetables/Fruits into a Categories menu; icon buttons get `aria-label` + 48px targets; **wallet gets a real SVG icon**. Footer on `--canvas`, real SVG social icons (currently the literal characters `f`, `◎`, `♪`), curve divider. Flash → toasts, bottom-right, `role="status"`.

### 9.6 Chart.js restyle
Local file (C4), loaded per-page. No grid lines; axes/ticks `--ink-faint` 11px; series in `--pine` + the freshness ramp; `borderRadius: 6` bars; `tension: .35` lines; no legend boxes; tooltips on `--canvas`; `maintainAspectRatio:false` in fixed-height wrappers. **Fix the axis scaling** — the current revenue chart renders a `0.0–1.0` y-axis.

---

## 10. ACCESSIBILITY

- Text ≥4.5:1; UI components and freshness graphics ≥3:1 (that is what `--fresh-*-ink` is for).
- Freshness never by colour alone — colour + number + label + arc length.
- `:focus-visible` on **every** interactive element: 2px `--pine`, 2px offset.
- Keyboard: carousels arrow-navigable, drawer and bottom sheet focus-trapped, modals `Esc`-closable, skip-link to `<main>`.
- `prefers-reduced-motion` fully honoured.
- Semantic landmarks, real `<button>` for actions, `aria-live` on cart count and toasts, `alt` on all product images.
- Charts get a text summary or data-table alternative.

---

## 11. DO NOT CHANGE

- The freshness **calculation** (`freshness_percent`, `freshness_level`, `freshness_get_exponent`, `apply_freshness_discount`, `decorate_with_freshness`). You may change the HTML-rendering functions (`freshness_ring_html`, `freshness_badge_html`) and **add** the F1 persistence writes inside `freshness_run_automation()`.
- FEFO allocation in `includes/fefo.php`.
- Auth, sessions, CSRF (`csrf_field()`, `csrf_verify()`), or any `db_*()` signature.
- Wallet, refund, commission, or voucher maths.
- The recommendation engine's algorithms — restyle its output only.
- URL structure and route filenames (new files may be added: `auth/forgot.php`, `auth/reset.php`, `shop/cart_add.php`, `shop/search_suggest.php`).
- The `.role-*` body-class mechanism — restyle it, keep it.
- **Every `e()` / `attr()` escaping call must survive the refactor. Do not introduce XSS while moving markup.**
- **Database changes are limited to:** the four `freshness_config.color_hex` updates (C3), the `stock_batches` freshness columns (F1), and the optional `notifications.priority` column (F11). Nothing else.

---

## 12. DEFINITION OF DONE

**Functional**
- [ ] `browse.php?freshness=LAST_CHANCE` returns **every** Last Chance product, with count and pagination agreeing
- [ ] Freshness sorting and "Best Value Today" work across the full catalogue
- [ ] Retailers are alerted **before** expiry, with value at risk and a one-click discount from the alert
- [ ] The expiry notification link resolves to a real page
- [ ] A customer with a wishlisted item is notified when it enters Last Chance
- [ ] Add-to-cart works from any product card, and still works with JavaScript disabled
- [ ] Password reset works end to end
- [ ] No raw expiry dates without a plain-language equivalent
- [ ] Freshness is visible in cart and checkout

**Visual / technical**
- [ ] Inline `style=""` count under 40 (from 835)
- [ ] Zero `!important`; `@layer` structure in place
- [ ] Five breakpoints, all `min-width` (from 26)
- [ ] One `.product-grid` (from four)
- [ ] Zero emoji in UI chrome; `icon()` at ~30 glyphs; wallet icon fixed
- [ ] Fonts and Chart.js served locally — **verify with the network disconnected**
- [ ] Admin colour picker still changes freshness colours site-wide
- [ ] Freshness state legible at a glance on every card, chip, row and gauge

**Mobile (test on a real Android phone or Chrome DevTools at 360×800)**
- [ ] No horizontal scrolling on any page
- [ ] Every interactive element ≥48×48px
- [ ] All 14 tables render as cards below 900px
- [ ] Bottom tab bar works, respects safe areas, hidden on checkout
- [ ] Filters open as a bottom sheet with a live result count
- [ ] Search reachable in one tap from any page
- [ ] Product page has a sticky add-to-cart bar
- [ ] Charts fit the viewport

**Quality**
- [ ] Hero descent runs at 60fps and degrades gracefully with JS off and with reduced motion
- [ ] Visible focus ring on every interactive element
- [ ] Keyboard-only pass: browse → filter → product → add to cart → checkout
- [ ] Lighthouse ≥90 Performance / ≥95 Accessibility, homepage, mobile emulation
- [ ] All 24 pages correct at 360, 414, 768, 1280, 1920px
- [ ] Regression pass: login as all three roles, browse, filter, add to cart, apply voucher, checkout with wallet, request refund, retailer batch add, admin approve review, admin edit freshness config

---

**Working order:** Phase 1 de-inline → Phase 2 data foundation → Phase 3 visual foundation → Phase 4 mobile architecture → Phase 5 components → Phase 6 customer pages → Phase 7 features → Phase 8 consoles → Phase 9 motion/a11y/perf/PWA. **Report after each phase.** If any instruction conflicts with preserving business logic, preserve the logic and report the conflict.
