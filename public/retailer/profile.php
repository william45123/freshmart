<?php
/**
 * Retailer profile — view + edit business info and personal details.
 * Updates: profiles (full_name, phone) + retailers (company_name, contact_phone, business_address).
 * Note: business_reg_no is read-only (SSM number is verified at approval).
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer = retailer_current();
$userId   = (int) auth_id();
$errors   = [];

// ---- Handle password change ----
if (is_post() && csrf_verify() && input('action') === 'change_password') {
    $current = (string) input('current_password', '');
    $newPw   = (string) input('new_password', '');
    $confirm = (string) input('confirm_password', '');

    $userRow = db_one('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    if (!$userRow || !password_verify($current, $userRow['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($newPw) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($newPw !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        db_run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($newPw, PASSWORD_BCRYPT), $userId]
        );
        flash_set('success', 'Password updated.');
        redirect('/retailer/profile.php');
    }
}

// ---- Handle profile update ----
if (is_post() && csrf_verify() && input('action') === 'update_profile') {
    $fullName        = trim((string) input('full_name', ''));
    $phone           = trim((string) input('phone', ''));
    $companyName     = trim((string) input('company_name', ''));
    $contactPhone    = trim((string) input('contact_phone', ''));
    $businessAddress = trim((string) input('business_address', ''));

    if ($fullName === '')        $errors[] = 'Full name is required.';
    if ($companyName === '')     $errors[] = 'Company name is required.';
    if ($businessAddress === '') $errors[] = 'Business address is required.';

    if (empty($errors)) {
        try {
            db_transaction(function () use ($userId, $retailer, $fullName, $phone, $companyName, $contactPhone, $businessAddress) {
                // Update or insert profile (in case profile row missing for older users)
                $hasProfile = db_scalar('SELECT id FROM profiles WHERE user_id = ?', [$userId]);
                if ($hasProfile) {
                    db_run('UPDATE profiles SET full_name = ?, phone = ? WHERE user_id = ?',
                           [$fullName, $phone, $userId]);
                } else {
                    db_run('INSERT INTO profiles (user_id, full_name, phone) VALUES (?, ?, ?)',
                           [$userId, $fullName, $phone]);
                }

                db_run(
                    'UPDATE retailers SET company_name = ?, contact_phone = ?, business_address = ? WHERE id = ?',
                    [$companyName, $contactPhone, $businessAddress, $retailer['id']]
                );

                // Sync the session full_name so header shows updated name
                $_SESSION['full_name'] = $fullName;
            });
            flash_set('success', 'Profile updated.');
            redirect('/retailer/profile.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Reload retailer row (in case it was just updated)
$retailer = retailer_current();
$userRow  = db_one('SELECT email, created_at FROM users WHERE id = ?', [$userId]);

// Quick business stats
$totalProducts = (int) db_scalar(
    'SELECT COUNT(*) FROM products WHERE retailer_id = ? AND deleted_at IS NULL',
    [$retailer['id']]
);
$totalOrders = (int) db_scalar(
    "SELECT COUNT(DISTINCT o.id) FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN products p ON p.id = oi.product_id
     WHERE p.retailer_id = ?",
    [$retailer['id']]
);
$totalRevenue = (float) db_scalar(
    "SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o   ON o.id = oi.order_id
     WHERE p.retailer_id = ?
       AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')",
    [$retailer['id']]
);

$pageTitle = 'Retailer Profile — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('profile', 'My Profile');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- Account overview -->
<div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4); max-width: 720px;">
    <div style="display: flex; justify-content: space-between; align-items: start; gap: var(--space-3); flex-wrap: wrap;">
        <div>
            <h3 style="margin-top: 0; font-size: 1.0625rem;">Account</h3>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 0.9375rem;">
                <strong style="color: var(--color-text);"><?= e($retailer['full_name'] ?? '—') ?></strong><br>
                <?= e($userRow['email'] ?? '') ?><br>
                Joined <?= format_date($userRow['created_at'] ?? '') ?>
            </p>
        </div>
        <div style="text-align: right;">
            <?php
                $status = $retailer['approval_status'];
                $pillColor = match ($status) {
                    'APPROVED'  => 'background: var(--color-primary); color: white;',
                    'PENDING'   => 'background: var(--color-mustard, #c9a55a); color: white;',
                    'REJECTED'  => 'background: var(--color-danger, #dc2626); color: white;',
                    'SUSPENDED' => 'background: var(--color-accent, #b85c38); color: white;',
                    default     => '',
                };
            ?>
            <span style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; <?= $pillColor ?>">
                ✓ <?= e($status) ?>
            </span>
            <?php if (!empty($retailer['approved_at'])): ?>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;">
                    Approved <?= format_date($retailer['approved_at']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Business stats -->
<div class="kpi-grid" style="margin-bottom: var(--space-5); max-width: 720px;">
    <div class="kpi-card">
        <div class="kpi-label">Products</div>
        <div class="kpi-value"><?= number_format($totalProducts) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Orders Received</div>
        <div class="kpi-value"><?= number_format($totalOrders) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Revenue (paid)</div>
        <div class="kpi-value"><?= format_myr($totalRevenue) ?></div>
    </div>
</div>

<!-- Personal info form -->
<form method="post" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4); max-width: 720px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">

    <h3 style="margin-top: 0; font-size: 1.0625rem; margin-bottom: var(--space-4);">👤 Personal info</h3>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
        <div class="form-group">
            <label>Full name *</label>
            <input type="text" name="full_name" required class="form-control"
                   value="<?= attr($retailer['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Personal phone</label>
            <input type="tel" name="phone" class="form-control"
                   placeholder="+60 12-345 6789"
                   value="<?= attr($retailer['contact_phone'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" class="form-control" value="<?= attr($userRow['email'] ?? '') ?>" disabled
               style="background: var(--color-bg);">
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;">
            Email can't be changed. Contact admin if you need to update it.
        </div>
    </div>

    <h3 style="font-size: 1.0625rem; margin: var(--space-5) 0 var(--space-4);">🏢 Business info</h3>

    <div class="form-group">
        <label>Company name *</label>
        <input type="text" name="company_name" required class="form-control"
               value="<?= attr($retailer['company_name'] ?? '') ?>">
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
        <div class="form-group">
            <label>SSM business reg. no.</label>
            <input type="text" class="form-control" value="<?= attr($retailer['business_reg_no'] ?? '') ?>" disabled
                   style="background: var(--color-bg);">
            <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;">
                Verified at approval. Contact admin to change.
            </div>
        </div>
        <div class="form-group">
            <label>Business contact phone</label>
            <input type="tel" name="contact_phone" class="form-control"
                   value="<?= attr($retailer['contact_phone'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Business address *</label>
        <textarea name="business_address" rows="3" required class="form-control"><?= e($retailer['business_address'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>

<!-- Change password -->
<form method="post" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); max-width: 720px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <h3 style="margin-top: 0; font-size: 1.0625rem; margin-bottom: var(--space-4);">🔒 Change password</h3>

    <div class="form-group">
        <label>Current password *</label>
        <input type="password" name="current_password" required class="form-control" autocomplete="current-password">
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
        <div class="form-group">
            <label>New password *</label>
            <input type="password" name="new_password" required minlength="8" class="form-control" autocomplete="new-password">
            <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;">
                At least 8 characters.
            </div>
        </div>
        <div class="form-group">
            <label>Confirm new password *</label>
            <input type="password" name="confirm_password" required minlength="8" class="form-control" autocomplete="new-password">
        </div>
    </div>

    <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
        <button type="submit" class="btn btn-primary">Update password</button>
    </div>
</form>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
