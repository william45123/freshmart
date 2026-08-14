<?php
/**
 * Admin: final decision on ESCALATED refund requests
 * (retailers escalate here when they can't decide).
 * Admin can Approve (credit wallet) or Reject.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/wallet_helpers.php';

// ---- POST: admin decision ----
if (is_post() && csrf_verify()) {
    $action   = (string) input('action', '');
    $refundId = (int) input('refund_id', 0);
    $note     = trim((string) input('note', '')) ?: null;

    try {
        if ($action === 'approve') {
            refund_approve($refundId, auth_id(), $note);
            flash_set('success', 'Refund approved and credited to the customer\'s wallet.');
        } elseif ($action === 'reject') {
            refund_reject($refundId, auth_id(), $note);
            flash_set('success', 'Refund rejected.');
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/admin/refunds.php');
}

// ---- Load refunds (escalated first, then all) ----
$statusFilter = (string) input('status', 'escalated');
if ($statusFilter === 'all') {
    $statusSql = '1=1';
} elseif ($statusFilter === 'escalated') {
    $statusSql = "rr.status = 'ESCALATED'";
} else {
    $allowed = ['APPROVED','REJECTED','REQUESTED','CANCELLED'];
    $s = strtoupper($statusFilter);
    $s = in_array($s, $allowed, true) ? $s : 'ESCALATED';
    $statusSql = "rr.status = '$s'";
}

$refunds = db_all(
    "SELECT rr.*, o.order_number,
            cust.full_name AS customer_name,
            ret.company_name AS retailer_name
     FROM refund_requests rr
     JOIN orders o ON o.id = rr.order_id
     LEFT JOIN profiles cust ON cust.user_id = rr.user_id
     LEFT JOIN retailers ret ON ret.id = rr.retailer_id
     WHERE $statusSql
     ORDER BY
        CASE rr.status WHEN 'ESCALATED' THEN 0 ELSE 1 END,
        rr.created_at DESC"
);

$escalatedCount = (int) db_scalar("SELECT COUNT(*) FROM refund_requests WHERE status = 'ESCALATED'");

$reasonLabels = [
    'NOT_FRESH'=>'Not fresh','DAMAGED'=>'Damaged','MISSING_ITEM'=>'Missing item',
    'WRONG_ITEM'=>'Wrong item','OTHER'=>'Other',
];

require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('refunds', 'Refund Requests');
?>

<div class="u-mb-5">
    <div class="u-flex u-gap-2 u-wrap">
        <a href="?status=escalated" class="btn btn-sm <?= $statusFilter==='escalated'?'btn-primary':'btn-outline' ?>">
            Escalated<?= $escalatedCount>0 ? ' ('.$escalatedCount.')' : '' ?>
        </a>
        <a href="?status=all" class="btn btn-sm <?= $statusFilter==='all'?'btn-primary':'btn-outline' ?>">All</a>
        <a href="?status=APPROVED" class="btn btn-sm <?= $statusFilter==='APPROVED'?'btn-primary':'btn-outline' ?>">Approved</a>
        <a href="?status=REJECTED" class="btn btn-sm <?= $statusFilter==='REJECTED'?'btn-primary':'btn-outline' ?>">Rejected</a>
    </div>
</div>

<?php if (empty($refunds)): ?>
    <div class="empty-state u-p-10-6">
        <div class="empty-state-icon">✅</div>
        <div class="empty-state-title">Nothing to review</div>
        <div class="empty-state-text">Escalated refund requests from retailers will appear here for a final decision.</div>
    </div>
<?php else: ?>
    <?php foreach ($refunds as $r):
        $isEscalated = $r['status'] === 'ESCALATED';
        $items = [];
        if ($r['scope'] === 'PARTIAL') {
            $items = db_all(
                "SELECT rri.*, oi.product_name FROM refund_request_items rri
                 JOIN order_items oi ON oi.id = rri.order_item_id
                 WHERE rri.refund_request_id = ?",
                [$r['id']]
            );
        }
    ?>
        <div class="refund-card">
            <div class="refund-card-head">
                <div>
                    <span class="refund-card-order">Order <?= e($r['order_number']) ?></span>
                    <span class="refund-badge refund-badge-<?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span>
                </div>
                <div class="refund-card-amount"><?= format_myr((float)$r['amount']) ?></div>
            </div>

            <div class="refund-card-meta">
                Customer: <?= e($r['customer_name'] ?? '—') ?> ·
                Seller: <?= e($r['retailer_name'] ?? '—') ?> ·
                <?= $r['scope']==='FULL'?'Full order':'Partial' ?> ·
                <?= e($reasonLabels[$r['reason']] ?? $r['reason']) ?> ·
                <?= format_date($r['created_at']) ?>
            </div>

            <?php if (!empty($items)): ?>
                <div class="refund-items">
                    <?php foreach ($items as $it): ?>
                        <div class="refund-item-line">
                            <?= e($it['product_name']) ?>
                            <span class="u-sage">× <?= rtrim(rtrim(number_format((float)$it['quantity'],2),'0'),'.') ?></span>
                            <span class="u-float-r"><?= format_myr((float)$it['line_amount']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($r['detail'])): ?>
                <div class="refund-detail">Customer: "<?= e($r['detail']) ?>"</div>
            <?php endif; ?>
            <?php if (!empty($r['decision_note'])): ?>
                <div class="refund-detail u-muted">Retailer note: <?= e($r['decision_note']) ?></div>
            <?php endif; ?>

            <?php if ($isEscalated): ?>
                <div class="refund-actions">
                    <form method="post" class="u-contents">
                        <?= csrf_field() ?>
                        <input type="hidden" name="refund_id" value="<?= $r['id'] ?>">
                        <input type="text" name="note" placeholder="Decision note (optional)" class="refund-note-input">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve & refund</button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm">Reject</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
?>
