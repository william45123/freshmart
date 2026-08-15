<?php
/**
 * Retailer Freshness Discounts — set custom auto-discount % per freshness level.
 *
 * Retailers can override the platform default discounts for their own products.
 * They CANNOT change the freshness % thresholds (that stays admin-only).
 *
 * Data:
 *   retailers.use_custom_discounts      (toggle: 0 = use admin default, 1 = use custom)
 *   retailer_freshness_discounts        (per-level discount_pct rows)
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

$retailer   = retailer_current();
$retailerId = (int) $retailer['id'];
$errors     = [];

// The levels a retailer may configure (thresholds stay admin-controlled).
$levelOrder = ['VERY_FRESH', 'FRESH', 'ENJOY_SOON', 'LAST_CHANCE'];

// ---- Handle save ----
if (is_post() && csrf_verify() && input('action') === 'save_discounts') {
    try {
        $useCustom = input('use_custom_discounts') ? 1 : 0;
        $discounts = $_POST['disc'] ?? [];

        db_transaction(function () use ($retailerId, $useCustom, $discounts, $levelOrder) {
            // 1. Update the toggle
            db_run(
                'UPDATE retailers SET use_custom_discounts = ? WHERE id = ?',
                [$useCustom, $retailerId]
            );

            // 2. Upsert each level's discount
            foreach ($levelOrder as $ln) {
                $pct = isset($discounts[$ln]) ? (float) $discounts[$ln] : 0.0;
                if ($pct < 0)   $pct = 0.0;
                if ($pct > 100) $pct = 100.0;

                db_run(
                    "INSERT INTO retailer_freshness_discounts (retailer_id, level_name, discount_pct)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE discount_pct = VALUES(discount_pct)",
                    [$retailerId, $ln, $pct]
                );
            }
        });

        flash_set('success', 'Discount settings saved.');
        redirect('/retailer/discounts.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// ---- Load current values ----
// Retailer's own toggle + saved discounts
$useCustom = (int) db_scalar(
    'SELECT use_custom_discounts FROM retailers WHERE id = ?',
    [$retailerId]
);

$savedRows = db_all(
    'SELECT level_name, discount_pct FROM retailer_freshness_discounts WHERE retailer_id = ?',
    [$retailerId]
);
$saved = [];
foreach ($savedRows as $r) {
    $saved[$r['level_name']] = (float) $r['discount_pct'];
}

// Admin's platform-default discounts + thresholds (read-only reference)
$adminLevels = db_all(
    "SELECT level_name, label_en, min_percent, max_percent, color_hex, auto_discount_pct
     FROM freshness_config
     WHERE level_name <> 'EXPIRED'
     ORDER BY display_order ASC"
);
$adminByLevel = [];
foreach ($adminLevels as $lv) {
    $adminByLevel[$lv['level_name']] = $lv;
}

$pageTitle = 'Freshness Discounts — Retailer';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('discounts', 'Freshness Discounts');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="u-maxw-760">

    <!-- Intro -->
    <p class="u-muted u-mb-4">
        Set your own auto-discount percentage for each freshness level. These apply to
        <strong>all your products</strong>. The freshness thresholds themselves (what counts
        as Last Chance, etc.) are set by the platform administrator and cannot be changed here.
    </p>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_discounts">

        <!-- Toggle -->
        <div class="u-bg-primary-lt u-bordered u-r-lg u-p-4 u-mb-4">
            <label class="u-flex u-ai-center u-gap-3 u-pointer">
                <input type="checkbox" name="use_custom_discounts" value="1"
                       <?= $useCustom === 1 ? 'checked' : '' ?>
                       class="u-w-18 u-h-18">
                <span>
                    <strong>Use my custom discounts</strong><br>
                    <small class="u-muted">
                        When off, your products follow the platform default discounts shown below.
                    </small>
                </span>
            </label>
        </div>

        <!-- Discount table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Freshness Level</th>
                    <th>Threshold (admin-set)</th>
                    <th class="u-ta-r">Platform Default</th>
                    <th class="u-ta-r">My Discount %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($levelOrder as $ln):
                    $admin = $adminByLevel[$ln] ?? null;
                    $adminPct = $admin ? (float) $admin['auto_discount_pct'] : 0.0;
                    $myPct    = $saved[$ln] ?? $adminPct;  // pre-fill with admin default if unset
                    $color    = $admin['color_hex'] ?? '#7a8467';
                    $label    = $admin['label_en'] ?? ucwords(strtolower(str_replace('_', ' ', $ln)));
                    $min      = $admin ? (float) $admin['min_percent'] : 0;
                    $max      = $admin ? (float) $admin['max_percent'] : 0;
                ?>
                    <tr>
                        <td>
                            <span class="fresh-swatch" style="--fresh: <?= e($color) ?>">●</span>
                            <strong><?= e($label) ?></strong>
                            <br><small class="u-muted"><code><?= e($ln) ?></code></small>
                        </td>
                        <td class="u-muted u-t-14">
                            <?= rtrim(rtrim(number_format($min, 2), '0'), '.') ?>% –
                            <?= rtrim(rtrim(number_format($max, 2), '0'), '.') ?>%
                        </td>
                        <td class="u-ta-r u-muted">
                            <?= rtrim(rtrim(number_format($adminPct, 2), '0'), '.') ?>%
                        </td>
                        <td class="u-ta-r">
                            <input type="number" step="0.01" min="0" max="100"
                                   name="disc[<?= e($ln) ?>]"
                                   value="<?= attr(rtrim(rtrim(number_format($myPct, 2), '0'), '.')) ?>"
                                   class="form-control u-w-90 u-ta-r">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="u-muted u-t-13 u-mt-2">
            <?= icon('lightbulb', 16) ?> Tip: A common strategy is to leave Very Fresh and Fresh at 0%, and only discount
            Enjoy Soon and Last Chance items to move stock before it expires.
        </p>

        <button type="submit" class="btn btn-primary btn-lg u-mt-4">
            Save discount settings
        </button>
    </form>
</div>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
