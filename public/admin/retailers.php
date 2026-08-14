<?php
/**
 * Admin: Retailers approval queue.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$errors = [];

if (is_post() && csrf_verify()) {
    $rid     = (int) input('retailer_id');
    $action  = (string) input('action', '');
    $reason  = trim((string) input('reason', ''));

    if (in_array($action, ['APPROVED', 'REJECTED', 'SUSPENDED'], true)) {
        db_transaction(function () use ($rid, $action, $reason) {
            db_run(
                'UPDATE retailers SET approval_status = ?, approved_by = ?, approved_at = NOW(),
                                       rejection_reason = ? WHERE id = ?',
                [$action, auth_id(), $action === 'REJECTED' ? $reason : null, $rid]
            );
            // If approved, activate the user too
            if ($action === 'APPROVED') {
                db_run(
                    "UPDATE users SET status = 'ACTIVE'
                     WHERE id = (SELECT user_id FROM retailers WHERE id = ?)",
                    [$rid]
                );
            } elseif ($action === 'REJECTED' || $action === 'SUSPENDED') {
                db_run(
                    "UPDATE users SET status = 'SUSPENDED'
                     WHERE id = (SELECT user_id FROM retailers WHERE id = ?)",
                    [$rid]
                );
            }
            // Audit log
            db_run(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
                 VALUES (?, ?, 'retailer', ?, ?)",
                [auth_id(), "RETAILER_$action", $rid, json_encode(['reason' => $reason])]
            );
            // Notify the retailer
            $userId = db_scalar('SELECT user_id FROM retailers WHERE id = ?', [$rid]);
            db_run(
                "INSERT INTO notifications (user_id, type, title, body)
                 VALUES (?, 'APPROVAL', ?, ?)",
                [
                    $userId,
                    "Account $action",
                    $action === 'APPROVED'
                        ? 'Welcome! Your retailer account has been approved. You can now log in and start selling.'
                        : ($action === 'REJECTED' ? "Your application was rejected. Reason: $reason" : 'Your account has been suspended.')
                ]
            );
        });
        flash_set('success', "Retailer $action.");
        redirect('/admin/retailers.php');
    }
}

$filter = (string) input('filter', 'PENDING');
$where  = $filter !== 'ALL' ? 'r.approval_status = ?' : '1=1';
$args   = $filter !== 'ALL' ? [$filter] : [];

$retailers = db_all(
    "SELECT r.*, u.email, p.full_name, u.created_at AS user_created
     FROM retailers r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN profiles p ON p.user_id = r.user_id
     WHERE $where
     ORDER BY r.created_at DESC",
    $args
);

$counts = db_all("SELECT approval_status AS s, COUNT(*) AS c FROM retailers GROUP BY approval_status");
$cMap = [];
foreach ($counts as $r) $cMap[$r['s']] = (int) $r['c'];

$pageTitle = 'Retailers — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('retailers', 'Retailer Management');
?>

<div class="u-flex u-gap-2 u-mb-4">
    <?php foreach (['PENDING','APPROVED','REJECTED','SUSPENDED','ALL'] as $f):
        $c = $f === 'ALL' ? array_sum($cMap) : ($cMap[$f] ?? 0);
    ?>
        <a href="<?= url('/admin/retailers.php?filter=' . $f) ?>"
           class="btn <?= $filter === $f ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= $f ?> <?= $c > 0 ? "($c)" : '' ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($retailers)): ?>
    <div class="empty-state">No retailers in this filter.</div>
<?php else: ?>
    <div class="u-flex u-col u-gap-3">
        <?php foreach ($retailers as $r): ?>
            <div class="panel u-p-5">
                <div class="u-flex u-jc-between u-ai-start u-mb-3">
                    <div>
                        <h3 class="u-m-0-0-1"><?= e($r['company_name']) ?></h3>
                        <div class="u-muted u-t-15">
                            SSM: <code><?= e($r['business_reg_no']) ?></code> ·
                            <?= e($r['email']) ?> ·
                            <?= e($r['contact_phone'] ?? '—') ?>
                        </div>
                        <div class="u-muted u-t-13 u-mt-1">
                            <?= e($r['business_address'] ?? '') ?>
                        </div>
                        <div class="u-muted u-t-13 u-mt-1">
                            Applied: <?= format_datetime($r['user_created']) ?>
                        </div>
                    </div>
                    <span class="status-pill status-<?= strtolower($r['approval_status']) === 'approved' ? 'active' : 'pending' ?>">
                        <?= e($r['approval_status']) ?>
                    </span>
                </div>

                <?php if ($r['approval_status'] === 'PENDING'): ?>
                    <div class="u-flex u-gap-2 u-mt-3">
                        <form method="post" class="u-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="retailer_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="APPROVED">
                            <button type="submit" class="btn btn-primary btn-sm"
                                    onclick="return confirm('Approve <?= e($r['company_name']) ?>?')">
                                ✓ Approve
                            </button>
                        </form>
                        <details class="u-inline">
                            <summary class="btn btn-secondary btn-sm u-inline-block">✗ Reject</summary>
                            <form method="post" class="u-mt-2 u-flex u-gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="retailer_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="action" value="REJECTED">
                                <input type="text" name="reason" placeholder="Reason" required
                                       class="form-control u-w-240">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </details>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
