<?php
/**
 * Admin: System Configuration / Settings.
 * Edit system_config key-value pairs (shipping, tax, site info, etc.)
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$errors  = [];
$success = false;

// Settings grouped for nicer display. Keys must match system_config.config_key.
$groups = [
    'Store Information' => [
        'site_name'       => ['label' => 'Site Name', 'type' => 'text'],
        'site_email'      => ['label' => 'Support Email', 'type' => 'email'],
        'currency'        => ['label' => 'Currency Code', 'type' => 'text'],
        'currency_symbol' => ['label' => 'Currency Symbol', 'type' => 'text'],
        'timezone'        => ['label' => 'Timezone', 'type' => 'text'],
    ],
    'Shipping & Tax' => [
        'shipping_fee_default'    => ['label' => 'Default Shipping Fee (MYR)', 'type' => 'number', 'step' => '0.01'],
        'shipping_free_threshold' => ['label' => 'Free Shipping Above (MYR)', 'type' => 'number', 'step' => '0.01'],
        'tax_rate'                => ['label' => 'Tax Rate (%)', 'type' => 'number', 'step' => '0.01'],
    ],
    'Platform Revenue' => [
        'commission_rate'         => ['label' => 'Commission Rate (%)', 'type' => 'number', 'step' => '0.01'],
    ],
    'Cart & Products' => [
        'guest_cart_hours'        => ['label' => 'Guest Cart Lifetime (hours)', 'type' => 'number', 'step' => '1'],
        'product_image_max_size'  => ['label' => 'Max Image Size (bytes)', 'type' => 'number', 'step' => '1'],
        'product_image_max_count' => ['label' => 'Max Images Per Product', 'type' => 'number', 'step' => '1'],
    ],
    'Freshness & System' => [
        'freshness_recalc_minutes' => ['label' => 'Freshness Recalc Interval (minutes)', 'type' => 'number', 'step' => '1'],
        'maintenance_mode'         => ['label' => 'Maintenance Mode', 'type' => 'toggle'],
    ],
];

// Flatten all known keys
$knownKeys = [];
foreach ($groups as $items) {
    foreach ($items as $key => $_) $knownKeys[] = $key;
}

// ---- Handle save ----
if (is_post() && csrf_verify() && input('action') === 'save') {
    try {
        db_transaction(function () use ($knownKeys) {
            foreach ($knownKeys as $key) {
                // Toggle fields send "1" when checked, nothing when unchecked
                if ($key === 'maintenance_mode') {
                    $val = input('cfg_' . $key) ? '1' : '0';
                } else {
                    $val = trim((string) input('cfg_' . $key, ''));
                }
                // Upsert
                db_run(
                    "INSERT INTO system_config (config_key, config_value, updated_by)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)",
                    [$key, $val, auth_id()]
                );
            }
            db_run(
                "INSERT INTO audit_logs (user_id, action, entity_type, new_values)
                 VALUES (?, 'SETTINGS_UPDATE', 'system_config', ?)",
                [auth_id(), json_encode(['keys' => count($knownKeys)])]
            );
        });
        flash_set('success', 'Settings saved.');
        redirect('/admin/settings.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// ---- Handle freshness-level config save ----
if (is_post() && csrf_verify() && input('action') === 'save_freshness') {
    try {
        $levels = $_POST['fresh'] ?? [];
        db_transaction(function () use ($levels) {
            foreach ((array) $levels as $levelName => $f) {
                db_run(
                    "UPDATE freshness_config
                        SET label_en = ?, min_percent = ?, max_percent = ?,
                            color_hex = ?, auto_discount_pct = ?
                      WHERE level_name = ?",
                    [
                        trim((string) ($f['label'] ?? '')),
                        (float) ($f['min'] ?? 0),
                        (float) ($f['max'] ?? 0),
                        trim((string) ($f['color'] ?? '#000000')),
                        (float) ($f['discount'] ?? 0),
                        (string) $levelName,
                    ]
                );
            }
            db_run(
                "INSERT INTO audit_logs (user_id, action, entity_type, new_values)
                 VALUES (?, 'FRESHNESS_CONFIG_UPDATE', 'freshness_config', ?)",
                [auth_id(), json_encode(['levels' => count((array) $levels)])]
            );
        });
        flash_set('success', 'Freshness levels updated.');
        redirect('/admin/settings.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// ---- Load current values ----
$rows = db_all('SELECT config_key, config_value, description FROM system_config');
$cfg  = [];
$desc = [];
foreach ($rows as $r) {
    $cfg[$r['config_key']]  = $r['config_value'];
    $desc[$r['config_key']] = $r['description'];
}

// Freshness levels (their own table) — admin-tunable boundaries/colours/discount
$freshLevels = db_all("SELECT * FROM freshness_config WHERE level_name <> 'EXPIRED' ORDER BY display_order ASC");

$pageTitle = 'Settings — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('settings', 'System Settings');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<p class="u-muted u-maxw-640 u-mb-5">
    These settings control store-wide behaviour. Changes take effect immediately.
    Be careful with shipping, tax, and maintenance mode.
</p>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <?php foreach ($groups as $groupName => $items): ?>
        <div class="panel u-p-5 u-mb-4 u-maxw-720">
            <h3 class="u-mt-0 u-t-17 u-mb-4"><?= e($groupName) ?></h3>

            <?php foreach ($items as $key => $meta): ?>
                <div class="form-group u-mb-4">
                    <label class="u-block u-fw-600 u-mb-1 u-t-15">
                        <?= e($meta['label']) ?>
                    </label>

                    <?php if ($meta['type'] === 'toggle'): ?>
                        <label class="u-inline-flex u-ai-center u-gap-2 u-pointer">
                            <input type="checkbox" name="cfg_<?= e($key) ?>" value="1"
                                   <?= (($cfg[$key] ?? '0') === '1') ? 'checked' : '' ?>>
                            <span class="u-t-15 u-muted">
                                Enable (site shows maintenance page to non-admins)
                            </span>
                        </label>
                    <?php else: ?>
                        <input type="<?= e($meta['type']) ?>"
                               name="cfg_<?= e($key) ?>"
                               value="<?= attr($cfg[$key] ?? '') ?>"
                               class="form-control u-maxw-360"
                               <?= isset($meta['step']) ? 'step="' . attr($meta['step']) . '"' : '' ?>>
                    <?php endif; ?>

                    <?php if (!empty($desc[$key])): ?>
                        <div class="u-t-12 u-muted u-mt-1">
                            <?= e($desc[$key]) ?> · <code class="u-t-11"><?= e($key) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="u-maxw-720 u-flex u-gap-2">
        <button type="submit" class="btn btn-primary btn-lg">Save all settings</button>
        <a href="<?= url('/admin/settings.php') ?>" class="btn btn-ghost">Cancel</a>
    </div>
</form>

<h2 class="u-t-18 u-m-8-0-2">Freshness levels</h2>
<p class="u-muted u-maxw-660 u-mb-4">
    This is the core of FreshMart. <strong>Min %</strong> is the shelf-life-remaining
    threshold at which an item enters each level; <strong>Auto-discount %</strong> is applied
    automatically to that level's price (e.g. Last Chance 15%). Changes apply store-wide immediately.
</p>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_freshness">
    <div class="panel u-p-4 u-mb-4 u-maxw-760 u-ovx-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Label</th>
                    <th class="u-ta-r">Min %</th>
                    <th class="u-ta-r">Max %</th>
                    <th>Colour</th>
                    <th class="u-ta-r">Auto-discount %</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($freshLevels as $lv): $ln = $lv['level_name']; ?>
                <tr>
                    <td><code class="u-t-12"><?= e($ln) ?></code></td>
                    <td><input type="text" name="fresh[<?= e($ln) ?>][label]" value="<?= attr($lv['label_en']) ?>" class="form-control u-w-130" maxlength="50"></td>
                    <td class="u-ta-r"><input type="number" step="0.01" min="0" max="100" name="fresh[<?= e($ln) ?>][min]" value="<?= attr((string) $lv['min_percent']) ?>" class="form-control u-w-80"></td>
                    <td class="u-ta-r"><input type="number" step="0.01" min="0" max="100" name="fresh[<?= e($ln) ?>][max]" value="<?= attr((string) $lv['max_percent']) ?>" class="form-control u-w-80"></td>
                    <td>
                        <span class="u-inline-flex u-ai-center u-gap-2">
                            <input type="color" name="fresh[<?= e($ln) ?>][color]" value="<?= attr($lv['color_hex']) ?>" class="u-w-42 u-h-30 u-bordered u-r-6 u-p-2px u-bg-none">
                        </span>
                    </td>
                    <td class="u-ta-r"><input type="number" step="0.01" min="0" max="100" name="fresh[<?= e($ln) ?>][discount]" value="<?= attr((string) $lv['auto_discount_pct']) ?>" class="form-control u-w-80"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn btn-primary btn-lg">Save freshness levels</button>
</form>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
