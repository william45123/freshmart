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
 *   EXPIRED      <= 0    Red     #dc2626   (hidden from catalog)
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
        'color_hex'         => '#dc2626',
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
 * Cron-driven automation:
 *   - Mark EXPIRED batches and notify retailers
 *   - Auto-discount LAST_CHANCE batches
 */
function freshness_run_automation(): array
{
    $summary = ['scanned' => 0, 'expired' => 0, 'discounted' => 0, 'alerts' => 0];

    $batches = db_all(
        "SELECT sb.id, sb.product_id, sb.received_date, sb.expiry_date,
                sb.selling_price_override, sb.status,
                p.base_price, p.retailer_id, p.name AS product_name,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent
         FROM stock_batches sb
         JOIN products p   ON p.id = sb.product_id
         JOIN categories c ON c.id = p.category_id
         WHERE sb.status = 'ACTIVE'"
    );

    foreach ($batches as $b) {
        $summary['scanned']++;
        $level = freshness_level($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']);

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
                        '/retailer/batches.php?id=' . $b['id'],
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
