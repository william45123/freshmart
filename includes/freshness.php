<?php
/**
 * Freshness Indicator System — Level 2 (Category-Aware Power-Law Decay)
 * ================================================================
 * THE CORE INNOVATION OF FRESHMART.
 *
 * --- The science ---
 * Different food categories spoil at different rates. We model this with
 * a power-law decay curve:
 *
 *     freshness%  =  100 × (1 - t/T)^n
 *
 *   t = elapsed time since received
 *   T = total shelf life (expiry - received)
 *   n = "decay exponent" — category-specific
 *
 * Higher n = steeper drop near expiry (fast-spoiling food like meat/seafood)
 * Lower  n = gentler drop near expiry (hardy food like apples, dry goods)
 *
 *   n=2.5: seafood / fresh meat   (rapid microbial growth)
 *   n=2.0: bakery                  (Avrami staling kinetics)
 *   n=1.8: herbs                   (wilting via transpiration)
 *   n=1.5: vegetables              (mixed transpiration + respiration)
 *   n=1.3: dairy                   (slow microbial under refrigeration)
 *   n=1.1: fruits                  (slow respiration)
 *   n=1.0: eggs/tofu               (near-linear under refrigeration)
 *
 * --- The 4 freshness levels ---
 *   VERY_FRESH   > 75%   Green   #16a34a
 *   FRESH        50-75%  Lime    #84cc16
 *   ENJOY_SOON   25-50%  Yellow  #eab308
 *   LAST_CHANCE  < 25%   Orange  #ea580c   (auto-discount 15%)
 *   EXPIRED      <= 0    Red     #B3341F   (hidden from catalog)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** Default exponent if category has no override (1.0 = linear / Level 1). */
const FRESHNESS_DEFAULT_EXPONENT = 1.0;

/**
 * Compute freshness percentage remaining (0–100) using power-law decay.
 *
 * @param string|DateTimeInterface $receivedDate
 * @param string|DateTimeInterface $expiryDate
 * @param float $decayExponent  Power-law exponent (default 1.0 = linear).
 * @return float                Freshness % in [0, 100].
 */
function freshness_percent($receivedDate, $expiryDate, float $decayExponent = FRESHNESS_DEFAULT_EXPONENT): float
{
    $tz   = new DateTimeZone(APP_TIMEZONE);
    $rec  = new DateTimeImmutable((string) $receivedDate, $tz);
    $exp  = new DateTimeImmutable((string) $expiryDate,   $tz);
    $now  = new DateTimeImmutable('now', $tz);

    // Normalize: received at start-of-day, expiry at end-of-day
    $rec = $rec->setTime(0, 0, 0);
    $exp = $exp->setTime(23, 59, 59);

    $totalSeconds   = $exp->getTimestamp() - $rec->getTimestamp();
    $elapsedSeconds = $now->getTimestamp() - $rec->getTimestamp();

    if ($totalSeconds   <= 0) return 0.0;
    if ($elapsedSeconds <= 0) return 100.0;
    if ($elapsedSeconds >= $totalSeconds) return 0.0;

    $elapsedRatio = $elapsedSeconds / $totalSeconds;  // [0, 1]

    // Power-law decay
    $exponent = max(0.1, $decayExponent);             // Clamp to avoid weird shapes
    $freshnessFraction = pow(1 - $elapsedRatio, $exponent);

    return round($freshnessFraction * 100, 2);
}

/**
 * Get the freshness level for a given batch.
 * Returns one of: VERY_FRESH | FRESH | ENJOY_SOON | LAST_CHANCE | EXPIRED.
 */
function freshness_level($receivedDate, $expiryDate, float $decayExponent = FRESHNESS_DEFAULT_EXPONENT): string
{
    $pct = freshness_percent($receivedDate, $expiryDate, $decayExponent);

    if ($pct <= 0) return 'EXPIRED';

    // Boundaries come from freshness_config (admin-editable in Settings),
    // evaluated highest level first.
    $cfg = freshness_config();
    foreach (['VERY_FRESH', 'FRESH', 'ENJOY_SOON', 'LAST_CHANCE'] as $lvl) {
        if (isset($cfg[$lvl]['min_percent']) && $pct >= (float) $cfg[$lvl]['min_percent']) {
            return $lvl;
        }
    }
    return 'LAST_CHANCE';
}

/**
 * Get the effective decay exponent for a product:
 *   products.decay_exponent_override  →  categories.decay_exponent  →  1.00
 * Cached per request.
 */
function freshness_get_exponent(int $productId): float
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];

    $row = db_one(
        'SELECT COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS exponent
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?',
        [$productId]
    );

    return $cache[$productId] = (float) ($row['exponent'] ?? FRESHNESS_DEFAULT_EXPONENT);
}

/**
 * Get the retailer_id that owns a product. Cached per request.
 * Used so per-retailer discount overrides apply even when the caller
 * didn't join retailer_id into the row.
 */
function freshness_get_retailer_id(int $productId): ?int
{
    static $cache = [];
    if (array_key_exists($productId, $cache)) return $cache[$productId];

    $rid = db_scalar('SELECT retailer_id FROM products WHERE id = ?', [$productId]);
    return $cache[$productId] = ($rid !== null ? (int) $rid : null);
}

/**
 * Cache the freshness_config table contents (loaded once per request).
 */
function freshness_config(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $rows = db_all('SELECT * FROM freshness_config ORDER BY display_order ASC');
    $cache = [];
    foreach ($rows as $row) {
        $cache[$row['level_name']] = $row;
    }
    $cache['EXPIRED'] = [
        'level_name'        => 'EXPIRED',
        'color_hex'         => '#B3341F',
        'label_en'          => 'Expired',
        'auto_discount_pct' => 0.00,
        'alert_retailer'    => true,
        'min_percent'       => 0.00,
        'max_percent'       => 0.00,
    ];
    return $cache;
}

function freshness_info(string $level): array
{
    $cfg = freshness_config();
    return $cfg[$level] ?? $cfg['VERY_FRESH'];
}

/**
 * Load a retailer's custom discount map, if they have custom discounts enabled.
 * Returns an associative array [level_name => discount_pct], or null if the
 * retailer uses the admin default (toggle off / no rows).
 * Cached per request per retailer.
 */
function retailer_discount_config(int $retailerId): ?array
{
    static $cache = [];
    if (array_key_exists($retailerId, $cache)) return $cache[$retailerId];

    // Only use custom discounts if the retailer has the toggle switched on.
    $enabled = (int) db_scalar(
        'SELECT use_custom_discounts FROM retailers WHERE id = ?',
        [$retailerId]
    );
    if ($enabled !== 1) {
        return $cache[$retailerId] = null;
    }

    $rows = db_all(
        'SELECT level_name, discount_pct FROM retailer_freshness_discounts WHERE retailer_id = ?',
        [$retailerId]
    );
    if (empty($rows)) {
        return $cache[$retailerId] = null;   // Toggle on but nothing set → still fall back
    }

    $map = [];
    foreach ($rows as $r) {
        $map[$r['level_name']] = (float) $r['discount_pct'];
    }
    return $cache[$retailerId] = $map;
}

/**
 * Compute the selling price for a batch given its base price and freshness.
 *
 * @param float    $basePrice
 * @param string   $level        Freshness level (e.g. LAST_CHANCE)
 * @param int|null $retailerId   If given and the retailer has custom discounts
 *                               enabled, their discount % overrides the admin
 *                               default. Otherwise the global freshness_config
 *                               discount is used.
 */
function apply_freshness_discount(float $basePrice, string $level, ?int $retailerId = null): array
{
    // 1. Determine the discount % — retailer override first, else admin default.
    $discountPct = null;

    if ($retailerId !== null) {
        $custom = retailer_discount_config($retailerId);
        if ($custom !== null && isset($custom[$level])) {
            $discountPct = (float) $custom[$level];
        }
    }

    if ($discountPct === null) {
        $info = freshness_info($level);
        $discountPct = (float) $info['auto_discount_pct'];
    }

    // 2. Apply.
    if ($discountPct <= 0) {
        return ['final_price' => $basePrice, 'discount_pct' => 0.0, 'is_discounted' => false];
    }

    $finalPrice = round($basePrice * (1 - $discountPct / 100), 2);
    return ['final_price' => $finalPrice, 'discount_pct' => $discountPct, 'is_discounted' => true];
}

/**
 * Render a freshness badge as HTML (used in product cards).
 */
function freshness_badge_html(string $level, ?int $daysRemaining = null): string
{
    $info  = freshness_info($level);
    $color = e($info['color_hex']);
    $label = e(strtolower($info['label_en']));
    $aria  = $daysRemaining !== null
        ? "Freshness: {$info['label_en']}. {$daysRemaining} days remaining."
        : "Freshness: {$info['label_en']}";

    $tail = $daysRemaining !== null
        ? '<span class="badge-days"> · ' . (int) $daysRemaining . 'd</span>'
        : '';

    // Boutique style: no background fill, just colored dot + dark text
    return <<<HTML
<span class="freshness-badge level-{$level}"
      aria-label="{$aria}"
      title="{$aria}">
    <span class="badge-dot" style="--fresh:{$color}">●</span>{$label}{$tail}
</span>
HTML;
}

/**
 * Decorate a product row with computed freshness fields.
 * Expects keys: received_date, expiry_date, base_price, AND either
 *   - decay_exponent (joined in via SQL), or
 *   - id            (fallback — does an extra DB lookup, cached)
 */
function decorate_with_freshness(array $row): array
{
    // Determine the decay exponent
    if (isset($row['decay_exponent']) && $row['decay_exponent'] !== null) {
        $exponent = (float) $row['decay_exponent'];
    } elseif (isset($row['decay_exponent_override']) && $row['decay_exponent_override'] !== null) {
        $exponent = (float) $row['decay_exponent_override'];
    } elseif (isset($row['id'])) {
        $exponent = freshness_get_exponent((int) $row['id']);
    } else {
        $exponent = FRESHNESS_DEFAULT_EXPONENT;
    }

    $level   = freshness_level($row['received_date'], $row['expiry_date'], $exponent);
    $percent = freshness_percent($row['received_date'], $row['expiry_date'], $exponent);
    $info    = freshness_info($level);
    // Pass retailer_id so per-retailer discount overrides apply.
    // If it's on the row, use it; otherwise look it up from the product id (cached).
    if (isset($row['retailer_id'])) {
        $retailerId = (int) $row['retailer_id'];
    } elseif (isset($row['id'])) {
        $retailerId = freshness_get_retailer_id((int) $row['id']);
    } else {
        $retailerId = null;
    }
    $price   = apply_freshness_discount((float) ($row['base_price'] ?? 0), $level, $retailerId);

    $row['freshness_level']     = $level;
    $row['freshness_percent']   = $percent;
    $row['freshness_color']     = $info['color_hex'];
    $row['freshness_label']     = $info['label_en'];
    $row['freshness_exponent']  = $exponent;                    // For transparency in UI
    $row['days_remaining']      = max(0, days_between(now_my()->format('Y-m-d'), $row['expiry_date']));
    $row['final_price']         = $price['final_price'];
    $row['discount_pct']        = $price['discount_pct'];
    $row['is_discounted']       = $price['is_discounted'];

    return $row;
}

/**
 * Write one batch's freshness cache row (F1).
 *
 * The single place these three columns are written. The values come from
 * freshness_percent() / freshness_level() — the canonical formula — so the
 * cache can never disagree with the live calculation by more than its age.
 */
function _freshness_write_cache(int $batchId, float $pct, string $level): void
{
    db_run(
        "UPDATE stock_batches
            SET freshness_pct = ?, freshness_level = ?, freshness_synced_at = NOW()
          WHERE id = ?",
        [round($pct, 2), $level, $batchId]
    );
}

/**
 * Populate the freshness cache for batches that have none (F1).
 *
 * WHEN TO CALL THIS
 * -----------------
 * Only from a query path that FILTERS OR SORTS on freshness_pct /
 * freshness_level, and only before that query runs. A batch created between
 * cron runs has a NULL cache, which makes it invisible to such a query — not
 * merely stale, absent. This closes that window without waiting for cron.
 *
 * DO NOT call it from display paths. Product cards, the product page and
 * everything else that shows a freshness figure go through
 * decorate_with_freshness(), which computes live from the formula and never
 * reads these columns. Those paths are always accurate and calling the heal
 * from them would add writes to page loads that gain nothing.
 *
 * The probe is `freshness_level IS NULL`, deliberately: that is the leading
 * column of idx_freshness, so NULLs are found via the index.
 * freshness_synced_at is not in any index and would force a full scan.
 *
 * @param int[]|null $ids  Specific batch ids, or null to sweep unsynced ACTIVE ones.
 * @param int $limit       Ceiling on a single sweep. Hitting it means cron is
 *                         not running; the shortfall is logged and the next
 *                         request continues where this one stopped.
 * @return array{scanned:int,updated:int,capped:bool}
 */
function freshness_sync_batches(?array $ids = null, int $limit = 200): array
{
    $out = ['scanned' => 0, 'updated' => 0, 'capped' => false];

    if ($ids !== null) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return $out;
        $in     = implode(',', array_fill(0, count($ids), '?'));
        $where  = "sb.id IN ($in)";
        $params = $ids;
        $cap    = count($ids);
    } else {
        $where  = "sb.status = 'ACTIVE' AND sb.freshness_level IS NULL";
        $params = [];
        $cap    = max(1, $limit);
    }

    $batches = db_all(
        "SELECT sb.id, sb.received_date, sb.expiry_date,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent
           FROM stock_batches sb
           JOIN products p   ON p.id = sb.product_id
           JOIN categories c ON c.id = p.category_id
          WHERE $where
          LIMIT " . (int) ($cap + 1),
        $params
    );

    if (count($batches) > $cap) {
        $out['capped'] = true;
        $batches = array_slice($batches, 0, $cap);
        error_log(
            'freshness_sync_batches: hit the ' . $cap . '-batch cap with more '
            . 'unsynced batches remaining. cron/update_freshness.php is very '
            . 'likely not running on schedule.'
        );
    }

    foreach ($batches as $b) {
        $out['scanned']++;
        $exp   = (float) $b['decay_exponent'];
        $pct   = freshness_percent($b['received_date'], $b['expiry_date'], $exp);
        $level = freshness_level($b['received_date'], $b['expiry_date'], $exp);
        _freshness_write_cache((int) $b['id'], $pct, $level);
        $out['updated']++;
    }

    // Concurrency: two requests may select the same unsynced batch and both
    // write it. That is harmless — the write is idempotent for a given clock
    // reading, both compute from the same formula and the same row, and the
    // result differs only by the sub-second gap between them. No locking is
    // needed and none is taken; the loser of the race simply rewrites the
    // same values.

    return $out;
}

/**
 * Cron-driven automation:
 *   - Mark EXPIRED batches and notify retailers
 *   - Auto-discount LAST_CHANCE batches
 */
function freshness_run_automation(): array
{
    $summary = ['scanned' => 0, 'synced' => 0, 'expired' => 0, 'discounted' => 0, 'undiscounted' => 0, 'alerts' => 0, 'alerts_sellable' => 0, 'alerts_cutoff' => 0];

    $batches = db_all(
        "SELECT sb.id, sb.product_id, sb.received_date, sb.expiry_date,
                sb.selling_price_override, sb.status, sb.quantity_remaining,
                p.base_price, p.retailer_id, p.name AS product_name,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent
         FROM stock_batches sb
         JOIN products p   ON p.id = sb.product_id
         JOIN categories c ON c.id = p.category_id
         WHERE sb.status = 'ACTIVE'"
    );

    foreach ($batches as $b) {
        $summary['scanned']++;
        $exponent = (float) $b['decay_exponent'];
        $level = freshness_level($b['received_date'], $b['expiry_date'], $exponent);

        // F1: refresh the cache for every batch this loop touches. The formula
        // is unchanged — this only records what it already computed, so browse
        // can filter and sort in SQL instead of in PHP after LIMIT/OFFSET.
        $pct = freshness_percent($b['received_date'], $b['expiry_date'], $exponent);
        _freshness_write_cache((int) $b['id'], $pct, $level);
        $summary['synced']++;

        if ($level === 'EXPIRED') {
            db_run("UPDATE stock_batches SET status = 'EXPIRED' WHERE id = ?", [$b['id']]);
            db_run(
                "INSERT INTO inventory_logs
                    (stock_batch_id, user_id, movement_type, quantity_change, quantity_after, reason)
                 SELECT id, NULL, 'EXPIRED', -quantity_remaining, 0, 'Auto-expired by freshness cron'
                 FROM stock_batches WHERE id = ?",
                [$b['id']]
            );
            $retailerUserId = db_scalar(
                "SELECT user_id FROM retailers WHERE id = ?", [$b['retailer_id']]
            );
            if ($retailerUserId) {
                db_run(
                    "INSERT INTO notifications (user_id, type, title, body, link)
                     VALUES (?, 'EXPIRY_ALERT', ?, ?, ?)",
                    [
                        $retailerUserId,
                        'Product expired: ' . $b['product_name'],
                        'A stock batch for "' . $b['product_name'] . '" has expired and is now hidden.',
                        '/retailer/inventory.php?batch=' . $b['id'],
                    ]
                );
                $summary['alerts']++;
            }
            $summary['expired']++;
            continue;
        }

        if ($level === 'LAST_CHANCE') {
            $disc = apply_freshness_discount((float) $b['base_price'], $level, (int) $b['retailer_id']);
            if (empty($b['selling_price_override'])
                || abs((float) $b['selling_price_override'] - $disc['final_price']) > 0.001) {
                db_run(
                    "UPDATE stock_batches SET selling_price_override = ? WHERE id = ?",
                    [$disc['final_price'], $b['id']]
                );
                $summary['discounted']++;
            }

        // ------------------------------------------------------------
        // F4 — warn the retailer BEFORE the loss, not after.
        //
        // Two states, because they need different action and the old
        // single-shape alert conflated them: a batch past the delivery
        // cut-off is no longer purchasable at all, so telling the retailer
        // to "rescue" it is telling them to rescue stock nobody can buy.
        //
        //   sellable  — still reachable by a customer; markdown works
        //   cut-off   — expires inside the delivery lead time, so the
        //               catalogue has already hidden it; only in-store,
        //               donation or write-off remain
        //
        // Value at risk is on both, because that is the number that decides
        // how hard to act. Deduplicated per batch per level, so a batch
        // sitting at ENJOY_SOON for four days produces one alert, not one
        // every five minutes — and crossing into LAST_CHANCE produces a new
        // one, because that is new information.
        // ------------------------------------------------------------
        if (in_array($level, ['ENJOY_SOON', 'LAST_CHANCE'], true)) {
            $qty = (float) $b['quantity_remaining'];
            if ($qty > 0) {
                $unit    = (float) ($b['selling_price_override'] ?? $b['base_price']);
                $atRisk  = $qty * $unit;
                $cutoff  = (new DateTimeImmutable('today', new DateTimeZone(APP_TIMEZONE)))
                             ->modify('+' . (int) DELIVERY_LEAD_DAYS . ' days');
                $expires = new DateTimeImmutable((string) $b['expiry_date'], new DateTimeZone(APP_TIMEZONE));
                $sellable = $expires >= $cutoff;
                $days     = (int) $expires->diff(new DateTimeImmutable('today', new DateTimeZone(APP_TIMEZONE)))->days;

                // one alert per batch per level
                $already = db_scalar(
                    "SELECT COUNT(*) FROM notifications
                      WHERE type = 'EXPIRY_ALERT'
                        AND link = ?
                        AND title LIKE ?",
                    ['/retailer/inventory.php?batch=' . $b['id'], $level . ':%']
                );

                if (!$already) {
                    $retailerUserId = db_scalar(
                        "SELECT user_id FROM retailers WHERE id = ?", [$b['retailer_id']]
                    );
                    if ($retailerUserId) {
                        $qtyLabel = rtrim(rtrim(number_format($qty, 2), '0'), '.');
                        if ($sellable) {
                            $title = $level . ': ' . $b['product_name'];
                            $body  = $qtyLabel . ' units still on sale, '
                                   . format_myr($atRisk) . ' at risk. '
                                   . ($days === 1 ? 'Last day to sell is tomorrow.'
                                                  : 'About ' . $days . ' days of selling time left.');
                        } else {
                            $title = $level . ': ' . $b['product_name'] . ' — past the delivery cut-off';
                            $body  = $qtyLabel . ' units, ' . format_myr($atRisk)
                                   . ' at risk. This batch expires within the '
                                   . (int) DELIVERY_LEAD_DAYS . '-day delivery window, so it is no '
                                   . 'longer purchasable online. Mark down in-store, donate, or write off.';
                        }
                        db_run(
                            "INSERT INTO notifications (user_id, type, title, body, link)
                             VALUES (?, 'EXPIRY_ALERT', ?, ?, ?)",
                            [$retailerUserId, $title, $body, '/retailer/inventory.php?batch=' . $b['id']]
                        );
                        $summary['alerts']++;
                        $summary[$sellable ? 'alerts_sellable' : 'alerts_cutoff']++;
                    }
                }
            }
        }

        } elseif (!empty($b['selling_price_override'])) {
            // The discount rule is "LAST_CHANCE batches are marked down". Applying
            // it was implemented; withdrawing it was not, because in normal
            // operation freshness only ever decreases and the branch is
            // unreachable. It becomes reachable the moment anything moves a batch
            // back up the curve — tools/refresh_demo_dates.php today, a retailer
            // correcting a mistyped expiry date tomorrow. The override would then
            // outlive the level that earned it, and since decorate_with_freshness()
            // derives the displayed price from the level while FEFO and checkout
            // read selling_price_override, the page would show full price while
            // the customer was charged the discounted one.
            //
            // Safe to clear here: the cron is the only writer of this column.
            // fefo.php only reads it, its INSERT omits it, and no retailer screen
            // sets a manual price — so nothing else's pricing can be wiped.
            // EXPIRED batches never reach this branch (the loop continues above),
            // so their last price is preserved as a record.
            db_run(
                "UPDATE stock_batches SET selling_price_override = NULL WHERE id = ?",
                [$b['id']]
            );
            $summary['undiscounted']++;
        }
    }

    return $summary;
}

/**
 * Freshness "ring" — the signature visual.
 * A circular gauge of the freshness % remaining, coloured by level.
 * Drop inside a positioned .product-card-image (or anywhere). Reads
 * the decorated fields off a product row ($p).
 */
function freshness_ring_html(array $p, int $size = 46, bool $inline = false): string
{
    $pct   = (float) ($p['freshness_percent'] ?? $p['freshness_pct'] ?? 0);
    $pct   = max(0, min(100, $pct));
    $color = $p['freshness_color'] ?? '#7a8467';
    $level = $p['freshness_level'] ?? 'FRESH';
    $label = $p['freshness_label'] ?? ucwords(strtolower(str_replace('_', ' ', $level)));
    $days  = isset($p['days_remaining']) ? (int) $p['days_remaining'] : null;

    $r      = 42.0;
    $circ   = 2 * M_PI * $r;
    $offset = $circ * (1 - $pct / 100);
    $num    = (int) round($pct);
    $aria   = "Freshness: {$label}, {$num}%" . ($days !== null ? ", {$days} days left" : '');

    return sprintf(
        '<span class="freshness-ring%8$s level-%1$s" title="%2$s" aria-label="%2$s">'
        . '<svg width="%3$d" height="%3$d" viewBox="0 0 100 100" role="img" aria-hidden="true">'
        . '<circle cx="50" cy="50" r="42" fill="none" stroke="rgba(0,0,0,0.10)" stroke-width="9"/>'
        . '<circle cx="50" cy="50" r="42" fill="none" stroke="%4$s" stroke-width="9" stroke-linecap="round" '
        .   'stroke-dasharray="%5$.1f" stroke-dashoffset="%6$.1f" transform="rotate(-90 50 50)"/>'
        . '<text x="50" y="60" text-anchor="middle" class="ring-num">%7$d</text>'
        . '</svg></span>',
        e($level), e($aria), $size, e($color), $circ, $offset, $num,
        ($inline ? ' freshness-ring-inline' : '')
    );
}
