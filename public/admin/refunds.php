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

<div style="margin-bottom: var(--space-5);">
    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
        <a href="?status=escalated" class="btn btn-sm <?= $statusFilter==='escalated'?'btn-primary':'btn-outline' ?>">
            Escalated<?= $escalatedCount>0 ? ' ('.$escalatedCount.')' : '' ?>
        </a>
        <a href="?status=all" class="btn btn-sm <?= $statusFilter==='all'?'btn-primary':'btn-outline' ?>">All</a>
        <a href="?status=APPROVED" class="btn btn-sm <?= $statusFilter==='APPROVED'?'btn-primary':'btn-outline' ?>">Approved</a>
        <a href="?status=REJECTED" class="btn btn-sm <?= $statusFilter==='REJECTED'?'btn-primary':'btn-outline' ?>">Rejected</a>
    </div>
</div>

<?php if (empty($refunds)): ?>
    <div class="empty-state" style="padding: var(--space-10) var(--space-6);">
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
                            <span style="color:var(--color-text-light);">× <?= rtrim(rtrim(number_format((float)$it['quantity'],2),'0'),'.') ?></span>
                            <span style="float:right;"><?= format_myr((float)$it['line_amount']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($r['detail'])): ?>
                <div class="refund-detail">Customer: "<?= e($r['detail']) ?>"</div>
            <?php endif; ?>
            <?php if (!empty($r['decision_note'])): ?>
                <div class="refund-detail" style="color:var(--color-text-muted);">Retailer note: <?= e($r['decision_note']) ?></div>
            <?php endif; ?>

            <?php if ($isEscalated): ?>
                <div class="refund-actions">
                    <form method="post" style="display:contents;">
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

<style>
.refund-card { background: var(--color-surface); border:1px solid var(--color-border); border-radius:14px; padding:var(--space-5); margin-bottom:var(--space-4); }
.refund-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-2); }
.refund-card-order { font-weight:700; font-size:1rem; margin-right:var(--space-2); }
.refund-card-amount { font-family:var(--font-serif); font-weight:600; font-size:1.3rem; color:var(--color-primary); }
.refund-card-meta { font-size:0.85rem; color:var(--color-text-muted); margin-bottom:var(--space-3); }
.refund-badge { font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:999px; text-transform:uppercase; letter-spacing:0.04em; }
.refund-badge-requested { background:#fff7e6; color:#8a6d1a; }
.refund-badge-escalated { background:#eae4f5; color:#5b3f8a; }
.refund-badge-approved  { background:#e6f4ea; color:#1a7a3a; }
.refund-badge-rejected  { background:#fbeee8; color:#a5432a; }
.refund-badge-cancelled { background:#eee; color:#666; }
.refund-items { background:var(--color-bg-warm); border-radius:8px; padding:var(--space-3); margin-bottom:var(--space-3); }
.refund-item-line { font-size:0.85rem; padding:2px 0; }
.refund-detail { font-size:0.875rem; font-style:italic; margin-bottom:var(--space-3); }
.refund-actions { display:flex; gap:var(--space-2); align-items:center; flex-wrap:wrap; padding-top:var(--space-3); border-top:1px solid var(--color-border); }
.refund-note-input { flex:1; min-width:140px; padding:6px 10px; border:1px solid var(--color-border); border-radius:8px; font-size:0.85rem; }
</style>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
?>
