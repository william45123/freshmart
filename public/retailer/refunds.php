<?php
/**
 * Retailer: review refund requests on MY orders.
 * Retailer can Approve, Reject, or Escalate to admin.
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/wallet_helpers.php';

$retailer   = retailer_current();
$retailerId = (int) $retailer['id'];

// ---- POST: decide on a refund ----
if (is_post() && csrf_verify()) {
    $action   = (string) input('action', '');
    $refundId = (int) input('refund_id', 0);
    $note     = trim((string) input('note', '')) ?: null;

    // Security: the refund must belong to this retailer
    $owns = (int) db_scalar(
        'SELECT COUNT(*) FROM refund_requests WHERE id = ? AND retailer_id = ?',
        [$refundId, $retailerId]
    );

    if ($owns) {
        try {
            if ($action === 'approve') {
                refund_approve($refundId, auth_id(), $note);
                flash_set('success', 'Refund approved and credited to the customer\'s wallet.');
            } elseif ($action === 'reject') {
                refund_reject($refundId, auth_id(), $note);
                flash_set('success', 'Refund request rejected.');
            } elseif ($action === 'escalate') {
                refund_escalate($refundId, auth_id(), $note);
                flash_set('success', 'Refund escalated to admin for a final decision.');
            }
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }
    } else {
        flash_set('error', 'Refund request not found.');
    }
    redirect('/retailer/refunds.php');
}

// ---- Load refund requests for this retailer ----
$statusFilter = (string) input('status', 'open');
$allowedStatuses = ['APPROVED','REJECTED','CANCELLED','REQUESTED','ESCALATED'];
if ($statusFilter === 'all') {
    $statusSql = '1=1';
} elseif ($statusFilter === 'open') {
    $statusSql = "rr.status IN ('REQUESTED','ESCALATED')";
} else {
    $s = strtoupper($statusFilter);
    $s = in_array($s, $allowedStatuses, true) ? $s : 'REQUESTED';
    $statusSql = "rr.status = '$s'";
}

$refunds = db_all(
    "SELECT rr.*, o.order_number, o.total AS order_total,
            pr.full_name AS customer_name
     FROM refund_requests rr
     JOIN orders o ON o.id = rr.order_id
     LEFT JOIN profiles pr ON pr.user_id = rr.user_id
     WHERE rr.retailer_id = ? AND ($statusSql)
     ORDER BY
        CASE rr.status WHEN 'REQUESTED' THEN 0 WHEN 'ESCALATED' THEN 1 ELSE 2 END,
        rr.created_at DESC",
    [$retailerId]
);

// counts for the filter tabs
$openCount = (int) db_scalar(
    "SELECT COUNT(*) FROM refund_requests WHERE retailer_id = ? AND status IN ('REQUESTED','ESCALATED')",
    [$retailerId]
);

$reasonLabels = [
    'NOT_FRESH' => 'Not fresh', 'DAMAGED' => 'Damaged', 'MISSING_ITEM' => 'Missing item',
    'WRONG_ITEM' => 'Wrong item', 'OTHER' => 'Other',
];

require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('refunds', 'Refund Requests');
?>

<div class="u-mb-5">
    <div class="u-flex u-gap-2 u-wrap">
        <a href="?status=open" class="btn btn-sm <?= $statusFilter==='open'?'btn-primary':'btn-outline' ?>">
            Open<?= $openCount>0 ? ' ('.$openCount.')' : '' ?>
        </a>
        <a href="?status=all" class="btn btn-sm <?= $statusFilter==='all'?'btn-primary':'btn-outline' ?>">All</a>
        <a href="?status=APPROVED" class="btn btn-sm <?= $statusFilter==='APPROVED'?'btn-primary':'btn-outline' ?>">Approved</a>
        <a href="?status=REJECTED" class="btn btn-sm <?= $statusFilter==='REJECTED'?'btn-primary':'btn-outline' ?>">Rejected</a>
    </div>
</div>

<?php if (empty($refunds)): ?>
    <div class="empty-state u-p-10-6">
        <div class="empty-state-icon">📋</div>
        <div class="empty-state-title">No refund requests</div>
        <div class="empty-state-text">When a customer requests a refund on one of your orders, it'll appear here for review.</div>
    </div>
<?php else: ?>
    <?php foreach ($refunds as $r):
        $isOpen = in_array($r['status'], ['REQUESTED','ESCALATED'], true);
        $items = [];
        if ($r['scope'] === 'PARTIAL') {
            $items = db_all(
                "SELECT rri.*, oi.product_name
                 FROM refund_request_items rri
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
                    <?php if ($r['status'] === 'ESCALATED'): ?>
                        <span class="u-t-12 u-muted">· awaiting admin</span>
                    <?php endif; ?>
                </div>
                <div class="refund-card-amount"><?= format_myr((float)$r['amount']) ?></div>
            </div>

            <div class="refund-card-meta">
                <?= e($r['customer_name'] ?? 'Customer') ?> ·
                <?= $r['scope'] === 'FULL' ? 'Full order' : 'Partial refund' ?> ·
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
                <div class="refund-detail">"<?= e($r['detail']) ?>"</div>
            <?php endif; ?>

            <?php if (!empty($r['decision_note']) && !$isOpen): ?>
                <div class="refund-detail u-muted">Note: <?= e($r['decision_note']) ?></div>
            <?php endif; ?>

            <?php if ($r['status'] === 'REQUESTED'): ?>
                <div class="refund-actions">
                    <form method="post" class="u-contents">
                        <?= csrf_field() ?>
                        <input type="hidden" name="refund_id" value="<?= $r['id'] ?>">
                        <input type="text" name="note" placeholder="Note (optional)" class="refund-note-input">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve & refund</button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm">Reject</button>
                        <button type="submit" name="action" value="escalate" class="btn btn-ghost btn-sm">Escalate to admin</button>
                    </form>
                </div>
            <?php elseif ($r['status'] === 'ESCALATED'): ?>
                <div class="u-t-136 u-muted u-pt-2">
                    You escalated this to admin. Waiting for their decision.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
?>
