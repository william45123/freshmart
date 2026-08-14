<?php
/**
 * Demo Date Refresh — re-anchor stock batch dates to today.
 *
 * The shipped demo data has fixed dates. Once today passes the last expiry
 * date the whole catalogue reads as EXPIRED, browse goes empty, and nothing
 * freshness-related can be demonstrated. This re-anchors it to CURDATE() so
 * every freshness level is represented again.
 *
 * Run it before a demo, as often as you like. It is idempotent: the level a
 * given batch is assigned depends only on its id, so two runs on the same day
 * produce identical dates.
 *
 * Usage:
 *   php tools/refresh_demo_dates.php            apply
 *   php tools/refresh_demo_dates.php --dry-run  show the plan, write nothing
 *
 * Then run:  php cron/update_freshness.php
 *
 * ---------------------------------------------------------------------------
 * HOW THE DATES ARE DERIVED
 *
 * Not by spacing batches evenly across elapsed days. Freshness is a power law,
 *
 *     freshness% = 100 x (1 - elapsed/total)^n
 *
 * so the same elapsed fraction lands on wildly different levels depending on
 * the category's exponent. At 80% freshness seafood (n=2.5) is only 8.5%
 * through its shelf life while fruit (n=1.1) is 18.4% through; at Last Chance
 * it is 53% versus 82%. Spacing by days would bunch everything into two or
 * three levels.
 *
 * So this solves the curve backwards. For a target freshness p:
 *
 *     elapsed_ratio = 1 - (p/100)^(1/n)
 *
 * n comes from the database per product —
 * COALESCE(products.decay_exponent_override, categories.decay_exponent, 1.00) —
 * never from a hardcoded table, so category tuning in admin is respected.
 * The target percentages are the midpoints of the bands in freshness_config,
 * also read live, so editing thresholds in Settings moves the targets with them.
 *
 * Day-granularity rounding can still push a batch over a band edge, especially
 * on a 2-3 day shelf life where one day is a third of the span. After solving,
 * each batch is checked with freshness_level() — the same function the app
 * uses — and nudged a day at a time until it lands on the intended level.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT TOUCHES
 *
 * Only batches with NO order_items referencing them. Rebasing a batch attached
 * to a historical order would produce an order placed weeks ago against stock
 * received tomorrow, which confuses FEFO and the order detail pages.
 *
 * Of those, only status ACTIVE or EXPIRED. DEPLETED (sold out) and RECALLED
 * (pulled for safety) are left alone — resurrecting them would be wrong.
 *
 * NOTE ON status: batches are reset to ACTIVE. The instruction this was built
 * from said to rebase only status='ACTIVE', but after cron/update_freshness.php
 * has run once the demo data is almost entirely EXPIRED, so that filter would
 * make this tool a no-op on exactly the databases that need it. The substantive
 * safety rule — never touch order-linked batches — is unchanged, and the
 * DEPLETED/RECALLED exclusion above replaces what the ACTIVE filter was
 * protecting. Batches given a past expiry are left ACTIVE deliberately so the
 * next cron run expires them and fires the retailer alerts.
 *
 * For each rebased batch: received_date, expiry_date, status -> ACTIVE,
 * selling_price_override -> NULL (so discounts recompute), and the F1 cache
 * columns -> NULL (so cron or the lazy heal repopulate them).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/../includes/freshness.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

// How many batches land on each level. Sums to the eligible batch count; any
// shortfall or surplus is absorbed by LAST_CHANCE, which is the level F2's
// pagination test needs most of.
$PLAN = [
    'VERY_FRESH'  => 5,
    'FRESH'       => 5,
    'ENJOY_SOON'  => 4,
    'EXPIRED'     => 3,
    'LAST_CHANCE' => null,   // remainder
];

echo "[" . date('Y-m-d H:i:s') . "] Refreshing demo dates, anchored to " . date('Y-m-d') . "\n";
if ($dryRun) echo "  DRY RUN — nothing will be written.\n";

// --- target freshness per level: midpoint of the live band -------------------
$cfg = freshness_config();
$targetPct = [];
foreach (['VERY_FRESH', 'FRESH', 'ENJOY_SOON', 'LAST_CHANCE'] as $lvl) {
    if (!isset($cfg[$lvl])) continue;
    $min = (float) $cfg[$lvl]['min_percent'];
    $max = (float) $cfg[$lvl]['max_percent'];
    $targetPct[$lvl] = round(($min + $max) / 2, 2);
}

// --- eligible batches --------------------------------------------------------
$batches = db_all(
    "SELECT sb.id, sb.received_date, sb.expiry_date, sb.product_id,
            p.name AS product_name, p.shelf_life_days,
            c.name AS category_name,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent
       FROM stock_batches sb
       JOIN products p   ON p.id = sb.product_id
       JOIN categories c ON c.id = p.category_id
      WHERE sb.status IN ('ACTIVE','EXPIRED')
        AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.stock_batch_id = sb.id)
      ORDER BY c.decay_exponent DESC, sb.id"
);

$total = count($batches);
if ($total === 0) {
    echo "  No eligible batches. Nothing to do.\n";
    exit(0);
}

// --- assign levels, interleaved by category so exponents are visible ---------
$fixed = array_filter($PLAN, fn($v) => $v !== null);
$remainder = max(0, $total - array_sum($fixed));
$slots = [];
foreach ($fixed as $lvl => $count) {
    for ($i = 0; $i < $count; $i++) $slots[] = $lvl;
}
for ($i = 0; $i < $remainder; $i++) $slots[] = 'LAST_CHANCE';
$slots = array_slice($slots, 0, $total);

// $batches is ordered by exponent DESC then id, so striding the slot list puts
// each level across several categories rather than clustering it in one.
$assignment = [];
$n = count($slots);
foreach ($batches as $i => $b) {
    $assignment[$b['id']] = $slots[($i * 7) % $n];   // 7 is coprime with most n
}
// striding can collide; fall back to filling any level whose quota is unmet
$want = array_count_values($slots);
$got  = array_count_values(array_values($assignment));
foreach ($batches as $b) {
    foreach ($want as $lvl => $q) {
        if (($got[$lvl] ?? 0) < $q) {
            $cur = $assignment[$b['id']];
            if (($got[$cur] ?? 0) > ($want[$cur] ?? 0)) {
                $got[$cur]--; $assignment[$b['id']] = $lvl; $got[$lvl] = ($got[$lvl] ?? 0) + 1;
            }
        }
    }
}

// --- solve dates -------------------------------------------------------------
$today   = new DateTimeImmutable('today', new DateTimeZone(APP_TIMEZONE));
$updates = [];
$missed  = [];

foreach ($batches as $b) {
    $level = $assignment[$b['id']];
    $exp   = max(0.1, (float) $b['decay_exponent']);

    // preserve this batch's own shelf life; fall back to the product's
    $span = (int) max(2, (int) round(
        (strtotime((string) $b['expiry_date']) - strtotime((string) $b['received_date'])) / 86400
    ));
    if ($span < 2) $span = max(2, (int) $b['shelf_life_days']);

    if ($level === 'EXPIRED') {
        // a few days past expiry, so the next cron run expires it and alerts
        $daysPast = 1 + ($b['id'] % 3);
        $expiry   = $today->modify("-{$daysPast} days");
        $received = $expiry->modify("-{$span} days");
    } else {
        $p = $targetPct[$level] ?? 50.0;
        $ratio = 1 - pow($p / 100, 1 / $exp);          // elapsed / total
        $ratio = min(0.999, max(0.0, $ratio));

        // received sits at 00:00 and expiry at 23:59:59, so the window a batch
        // occupies is (span + 1) days minus a second.
        $totalSeconds   = ($span + 1) * 86400 - 1;
        $elapsedSeconds = (int) round($ratio * $totalSeconds);
        $received = $today->modify('-' . (int) round($elapsedSeconds / 86400) . ' days');
        $expiry   = $received->modify("+{$span} days");

        // Day rounding can cross a band edge. Nudge until the canonical
        // function agrees, then stop.
        $tries = 0;
        while (freshness_level($received->format('Y-m-d'), $expiry->format('Y-m-d'), $exp) !== $level
               && $tries < 8) {
            $achieved = freshness_percent($received->format('Y-m-d'), $expiry->format('Y-m-d'), $exp);
            $step = $achieved > $p ? '+1 day' : '-1 day';   // older = less fresh
            $received = $received->modify($step === '+1 day' ? '-1 day' : '+1 day');
            $expiry   = $received->modify("+{$span} days");
            $tries++;
        }
        if (freshness_level($received->format('Y-m-d'), $expiry->format('Y-m-d'), $exp) !== $level) {
            $missed[] = "batch {$b['id']} ({$b['category_name']}, n={$exp}, span={$span}d) "
                      . "wanted {$level}, got "
                      . freshness_level($received->format('Y-m-d'), $expiry->format('Y-m-d'), $exp);
        }
    }

    $updates[] = [
        'id'       => (int) $b['id'],
        'level'    => $level,
        'received' => $received->format('Y-m-d'),
        'expiry'   => $expiry->format('Y-m-d'),
        'span'     => $span,
        'n'        => $exp,
        'category' => $b['category_name'],
    ];
}

// --- apply -------------------------------------------------------------------
$hasCache = (bool) db_scalar(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'stock_batches'
        AND column_name = 'freshness_level'"
);

if (!$dryRun) {
    foreach ($updates as $u) {
        $sql = "UPDATE stock_batches
                   SET received_date = ?, expiry_date = ?, status = 'ACTIVE',
                       selling_price_override = NULL";
        if ($hasCache) {
            $sql .= ", freshness_pct = NULL, freshness_level = NULL, freshness_synced_at = NULL";
        }
        $sql .= " WHERE id = ?";
        db_run($sql, [$u['received'], $u['expiry'], $u['id']]);
    }
}

// --- report ------------------------------------------------------------------
$byLevel = [];
foreach ($updates as $u) {
    $byLevel[$u['level']]['n'] = ($byLevel[$u['level']]['n'] ?? 0) + 1;
    $byLevel[$u['level']]['cats'][$u['category']] = true;
}
$order = ['VERY_FRESH', 'FRESH', 'ENJOY_SOON', 'LAST_CHANCE', 'EXPIRED'];

echo "\n  " . str_pad('LEVEL', 14) . str_pad('BATCHES', 9) . "CATEGORIES\n";
echo "  " . str_repeat('-', 60) . "\n";
foreach ($order as $lvl) {
    if (!isset($byLevel[$lvl])) continue;
    printf("  %-14s%-9d%s\n", $lvl, $byLevel[$lvl]['n'],
        implode(', ', array_keys($byLevel[$lvl]['cats'])));
}
echo "  " . str_repeat('-', 60) . "\n";
printf("  %-14s%-9d\n", 'TOTAL', count($updates));

if ($missed) {
    echo "\n  Could not hit the target level for " . count($missed) . " batch(es):\n";
    foreach ($missed as $m) echo "    - $m\n";
    echo "  (usually a very short shelf life where one day spans a whole band)\n";
}

$skipped = (int) db_scalar(
    "SELECT COUNT(*) FROM stock_batches sb
      WHERE EXISTS (SELECT 1 FROM order_items oi WHERE oi.stock_batch_id = sb.id)
         OR sb.status IN ('DEPLETED','RECALLED')"
);
echo "\n  Left untouched: {$skipped} batch(es) — order-linked, depleted or recalled.\n";

if ($dryRun) {
    echo "\n  DRY RUN — no changes written.\n";
} else {
    echo "\n  Done. Now run:  php cron/update_freshness.php\n";
}
exit(0);
