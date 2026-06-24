<?php
/**
 * _refresh_dates.php  —  ONE-TIME demo helper (no password; DELETE after use)
 * ---------------------------------------------------------------------------
 * Re-dates every stock batch around TODAY so the Freshness indicator shows a
 * healthy spread (Very Fresh -> Last Chance) and nothing is expired. It also
 * re-activates expired batches, restocks sold-out ones, and clears stale
 * price overrides so the whole store looks alive for your demo.
 *
 * HOW TO RUN (browser):
 *   1. Drop this file into your project's public/ folder (next to index.php).
 *   2. Make sure XAMPP Apache + MySQL are running.
 *   3. Open:  http://localhost/freshmart/public/_refresh_dates.php
 *   4. Watch the OK lines + DONE summary.
 *   5. Hard-refresh the site (Ctrl+Shift+R).
 *   6. DELETE this file afterwards.
 *
 * Safe to re-run before each demo (shelf life is category-based, so it won't drift).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';        // pulls in config (timezone, DB)
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/freshness.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font:13px/1.55 ui-monospace,Menlo,Consolas,monospace;padding:18px;max-width:760px;margin:auto;background:#faf6ef;color:#2a2a26;'>";
echo "FreshMart — refreshing batch dates around " . date('Y-m-d') . " (Asia/Kuala_Lumpur)\n";
echo str_repeat('-', 64) . "\n";

/* Realistic shelf life (days) per category — keeps dates sensible + stable. */
$SHELF = [
    'vegetables'  => 6,
    'fruits'      => 9,
    'dairy'       => 14,
    'meat'        => 5,
    'seafood'     => 3,
    'bakery'      => 4,
    'eggs-tofu'   => 18,
    'herbs-spice' => 5,
];
$DEFAULT_SHELF = 10;

/* Target freshness % per band — chosen safely INSIDE each level's range:
   >75 Very Fresh | 50-75 Fresh | 25-50 Enjoy Soon | <25 Last Chance        */
$BANDS = [90, 82, 68, 58, 42, 32, 18, 10];

$rows = db_all(
    "SELECT b.id, b.product_id, p.name AS pname, c.slug AS cat
       FROM stock_batches b
       JOIN products p        ON p.id = b.product_id
       LEFT JOIN categories c ON c.id = p.category_id
      ORDER BY b.product_id, b.id"
);

if (!$rows) {
    echo "No stock batches found. Did you import database/seed.sql?\n</pre>";
    exit;
}

$prodBand = [];   // product_id => target %  (so all batches of a product agree)
$bi       = 0;
$counts   = ['VERY_FRESH' => 0, 'FRESH' => 0, 'ENJOY_SOON' => 0, 'LAST_CHANCE' => 0, 'EXPIRED' => 0];
$n        = 0;

foreach ($rows as $r) {
    $pid   = (int) $r['product_id'];
    $shelf = $SHELF[$r['cat'] ?? ''] ?? $DEFAULT_SHELF;

    // Rotate bands across products for an even spread.
    if (!isset($prodBand[$pid])) {
        $prodBand[$pid] = $BANDS[$bi % count($BANDS)];
        $bi++;
    }
    $targetPct = $prodBand[$pid];

    // Effective decay exponent (product override -> category -> 1.0).
    $exp = freshness_get_exponent($pid);

    // Invert the power-law so the DISPLAYED % lands near the target band.
    $remainingRatio = pow($targetPct / 100, 1 / max(0.1, $exp));
    $remainingDays  = (int) max(1, round($shelf * $remainingRatio));
    if ($remainingDays >= $shelf) {
        $remainingDays = max(1, $shelf - 1);   // never 100% / never expired-at-edge
    }
    $receivedAgo = $shelf - $remainingDays;

    db_run(
        "UPDATE stock_batches
            SET received_date          = DATE_SUB(CURDATE(), INTERVAL :ra DAY),
                expiry_date            = DATE_ADD(CURDATE(), INTERVAL :rd DAY),
                status                 = CASE WHEN status = 'RECALLED' THEN 'RECALLED' ELSE 'ACTIVE' END,
                quantity_remaining     = CASE WHEN quantity_remaining <= 0 THEN original_quantity ELSE quantity_remaining END,
                selling_price_override = NULL
          WHERE id = :id",
        [':ra' => $receivedAgo, ':rd' => $remainingDays, ':id' => (int) $r['id']]
    );

    // Verify what the site will actually show.
    $recv = date('Y-m-d', strtotime("-{$receivedAgo} days"));
    $expd = date('Y-m-d', strtotime("+{$remainingDays} days"));
    $lvl  = freshness_level($recv, $expd, $exp);
    $pct  = freshness_percent($recv, $expd, $exp);
    if (!isset($counts[$lvl])) $counts[$lvl] = 0;
    $counts[$lvl]++;
    $n++;

    $name = (string) $r['pname'];
    if (strlen($name) > 26) $name = substr($name, 0, 25) . '.';
    printf("OK  #%-4d %-26s %3d%%  %-11s expires %s\n",
        (int) $r['id'], $name, (int) round($pct), $lvl, $expd);
}

echo str_repeat('-', 64) . "\n";
printf("DONE — %d batches updated.\n\n", $n);
echo "Spread:\n";
foreach ($counts as $k => $v) {
    if ($v) printf("   %-12s %d\n", $k, $v);
}
echo "\nNow hard-refresh the site (Ctrl+Shift+R).\n";
echo "Then DELETE this file (it has no password protection).\n";
echo "</pre>";
