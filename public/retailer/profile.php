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
<div class="panel u-p-5 u-mb-4 u-maxw-720">
    <div class="u-flex u-jc-between u-ai-start u-gap-3 u-wrap">
        <div>
            <h3 class="u-mt-0 u-t-17">Account</h3>
            <p class="u-m-0 u-muted u-t-15">
                <strong class="u-ink"><?= e($retailer['full_name'] ?? '—') ?></strong><br>
                <?= e($userRow['email'] ?? '') ?><br>
                Joined <?= format_date($userRow['created_at'] ?? '') ?>
            </p>
        </div>
        <div class="u-ta-r">
            <?php
                $status = $retailer['approval_status'];
                $pillClass = match ($status) {
                    'APPROVED'  => ' approval-pill-approved',
                    'PENDING'   => ' approval-pill-pending',
                    'REJECTED'  => ' approval-pill-rejected',
                    'SUSPENDED' => ' approval-pill-suspended',
                    default     => '',
                };
            ?>
            <span class="approval-pill<?= $pillClass ?>">
                ✓ <?= e($status) ?>
            </span>
            <?php if (!empty($retailer['approved_at'])): ?>
                <div class="u-t-12 u-muted u-mt-1">
                    Approved <?= format_date($retailer['approved_at']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Business stats -->
<div class="kpi-grid u-mb-5 u-maxw-720">
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
<form method="post" class="panel u-p-5 u-mb-4 u-maxw-720">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">

    <h3 class="u-mt-0 u-t-17 u-mb-4">👤 Personal info</h3>

    <div class="u-grid u-cols-2 u-gap-3">
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
        <input type="email" class="form-control u-bg-page" value="<?= attr($userRow['email'] ?? '') ?>" disabled>
        <div class="u-t-12 u-muted u-mt-1">
            Email can't be changed. Contact admin if you need to update it.
        </div>
    </div>

    <h3 class="u-t-17 u-m-5-0-4">🏢 Business info</h3>

    <div class="form-group">
        <label>Company name *</label>
        <input type="text" name="company_name" required class="form-control"
               value="<?= attr($retailer['company_name'] ?? '') ?>">
    </div>
    <div class="u-grid u-cols-2 u-gap-3">
        <div class="form-group">
            <label>SSM business reg. no.</label>
            <input type="text" class="form-control u-bg-page" value="<?= attr($retailer['business_reg_no'] ?? '') ?>" disabled>
            <div class="u-t-12 u-muted u-mt-1">
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

    <div class="u-flex u-gap-2 u-mt-4">
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>

<!-- Change password -->
<form method="post" class="panel u-p-5 u-maxw-720">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <h3 class="u-mt-0 u-t-17 u-mb-4">🔒 Change password</h3>

    <div class="form-group">
        <label>Current password *</label>
        <input type="password" name="current_password" required class="form-control" autocomplete="current-password">
    </div>
    <div class="u-grid u-cols-2 u-gap-3">
        <div class="form-group">
            <label>New password *</label>
            <input type="password" name="new_password" required minlength="8" class="form-control" autocomplete="new-password">
            <div class="u-t-12 u-muted u-mt-1">
                At least 8 characters.
            </div>
        </div>
        <div class="form-group">
            <label>Confirm new password *</label>
            <input type="password" name="confirm_password" required minlength="8" class="form-control" autocomplete="new-password">
        </div>
    </div>

    <div class="u-flex u-gap-2 u-mt-4">
        <button type="submit" class="btn btn-primary">Update password</button>
    </div>
</form>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
