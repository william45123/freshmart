<?php
/**
 * Admin: User management.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Authorisation gate. Runs before any request handling or output so the
// redirect/403 can still be issued; admin_layout_start() calls this again
// further down, which is harmless (require_role is idempotent).
admin_check();

if (is_post() && csrf_verify()) {
    $uid = (int) input('user_id');
    $action = (string) input('action', '');
    if (in_array($action, ['SUSPEND', 'ACTIVATE'], true) && $uid !== auth_id()) {
        $newStatus = $action === 'SUSPEND' ? 'SUSPENDED' : 'ACTIVE';
        db_run('UPDATE users SET status = ? WHERE id = ?', [$newStatus, $uid]);
        db_run(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id)
             VALUES (?, ?, 'user', ?)",
            [auth_id(), "USER_$action", $uid]
        );
        flash_set('success', "User $action.");
        redirect('/admin/users.php');
    }
}

$roleFilter = (string) input('role', '');
$where = ['1=1'];
$args  = [];
if ($roleFilter !== '') { $where[] = 'u.role = ?'; $args[] = $roleFilter; }

$users = db_all(
    "SELECT u.*, p.full_name,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.created_at DESC",
    $args
);

$pageTitle = 'Users — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('users', 'Users');
?>

<div class="u-flex u-gap-2 u-mb-4">
    <a href="<?= url('/admin/users.php') ?>" class="btn <?= $roleFilter === '' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
    <a href="<?= url('/admin/users.php?role=CUSTOMER') ?>" class="btn <?= $roleFilter === 'CUSTOMER' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Customers</a>
    <a href="<?= url('/admin/users.php?role=RETAILER') ?>" class="btn <?= $roleFilter === 'RETAILER' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Retailers</a>
    <a href="<?= url('/admin/users.php?role=ADMIN') ?>" class="btn <?= $roleFilter === 'ADMIN' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Admins</a>
</div>

<table class="data-table data-table">
    <thead>
        <tr><th>User</th><th>Role</th><th>Status</th><th>Orders</th><th>Joined</th><th>Action</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td data-label="User">
                <strong><?= e($u['full_name'] ?? $u['email']) ?></strong>
                <br><small class="u-muted"><?= e($u['email']) ?></small>
            </td>
            <td data-label="Role"><?= e($u['role']) ?></td>
            <td data-label="Status"><span class="status-pill status-<?= strtolower($u['status']) === 'active' ? 'active' : 'suspended' ?>"><?= e($u['status']) ?></span></td>
            <td data-label="Orders"><?= (int) $u['order_count'] ?></td>
            <td data-label="Joined"><?= format_date($u['created_at']) ?></td>
            <td data-label="Action">
                <?php if ($u['id'] !== auth_id()): ?>
                    <form method="post" class="u-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <?php if ($u['status'] === 'ACTIVE'): ?>
                            <input type="hidden" name="action" value="SUSPEND">
                            <button type="submit" class="btn btn-secondary btn-sm"
                                    onclick="return confirm('Suspend user?')">Suspend</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="ACTIVATE">
                            <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
