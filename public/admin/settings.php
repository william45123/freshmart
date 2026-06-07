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

// ---- Load current values ----
$rows = db_all('SELECT config_key, config_value, description FROM system_config');
$cfg  = [];
$desc = [];
foreach ($rows as $r) {
    $cfg[$r['config_key']]  = $r['config_value'];
    $desc[$r['config_key']] = $r['description'];
}

$pageTitle = 'Settings — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('settings', 'System Settings');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<p style="color: var(--color-text-muted); max-width: 640px; margin-bottom: var(--space-5);">
    These settings control store-wide behaviour. Changes take effect immediately.
    Be careful with shipping, tax, and maintenance mode.
</p>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <?php foreach ($groups as $groupName => $items): ?>
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4); max-width: 720px;">
            <h3 style="margin-top: 0; font-size: 1.0625rem; margin-bottom: var(--space-4);"><?= e($groupName) ?></h3>

            <?php foreach ($items as $key => $meta): ?>
                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.9375rem;">
                        <?= e($meta['label']) ?>
                    </label>

                    <?php if ($meta['type'] === 'toggle'): ?>
                        <label style="display: inline-flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                            <input type="checkbox" name="cfg_<?= e($key) ?>" value="1"
                                   <?= (($cfg[$key] ?? '0') === '1') ? 'checked' : '' ?>>
                            <span style="font-size: 0.9375rem; color: var(--color-text-muted);">
                                Enable (site shows maintenance page to non-admins)
                            </span>
                        </label>
                    <?php else: ?>
                        <input type="<?= e($meta['type']) ?>"
                               name="cfg_<?= e($key) ?>"
                               value="<?= attr($cfg[$key] ?? '') ?>"
                               class="form-control"
                               <?= isset($meta['step']) ? 'step="' . attr($meta['step']) . '"' : '' ?>
                               style="max-width: 360px;">
                    <?php endif; ?>

                    <?php if (!empty($desc[$key])): ?>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;">
                            <?= e($desc[$key]) ?> · <code style="font-size: 0.6875rem;"><?= e($key) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="max-width: 720px; display: flex; gap: var(--space-2);">
        <button type="submit" class="btn btn-primary btn-lg">Save all settings</button>
        <a href="<?= url('/admin/settings.php') ?>" class="btn btn-ghost">Cancel</a>
    </div>
</form>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
