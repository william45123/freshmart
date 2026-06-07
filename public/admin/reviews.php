<?php
/**
 * Admin: review moderation.
 * Approve / unapprove / delete customer reviews.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$errors = [];

// ---- POST: approve / unapprove / delete ----
if (is_post() && csrf_verify()) {
    $action   = (string) input('action', '');
    $reviewId = (int) input('review_id', 0);

    if ($reviewId > 0) {
        try {
            if ($action === 'approve') {
                db_run('UPDATE reviews SET is_approved = 1 WHERE id = ?', [$reviewId]);
                db_run(
                    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
                     VALUES (?, 'REVIEW_APPROVE', 'review', ?, ?)",
                    [auth_id(), $reviewId, json_encode(['is_approved' => 1])]
                );
                // Notify reviewer
                $r = db_one('SELECT user_id, product_id FROM reviews WHERE id = ?', [$reviewId]);
                if ($r) {
                    $prod = db_one('SELECT slug, name FROM products WHERE id = ?', [$r['product_id']]);
                    db_run(
                        "INSERT INTO notifications (user_id, type, title, body, link)
                         VALUES (?, 'SYSTEM', ?, ?, ?)",
                        [
                            $r['user_id'],
                            'Your review was approved',
                            'Your review for "' . ($prod['name'] ?? 'a product') . '" is now public.',
                            '/shop/product.php?slug=' . ($prod['slug'] ?? ''),
                        ]
                    );
                }
                flash_set('success', 'Review approved.');
            } elseif ($action === 'unapprove') {
                db_run('UPDATE reviews SET is_approved = 0 WHERE id = ?', [$reviewId]);
                db_run(
                    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
                     VALUES (?, 'REVIEW_UNAPPROVE', 'review', ?, ?)",
                    [auth_id(), $reviewId, json_encode(['is_approved' => 0])]
                );
                flash_set('info', 'Review hidden.');
            } elseif ($action === 'delete') {
                db_run('DELETE FROM reviews WHERE id = ?', [$reviewId]);
                db_run(
                    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id)
                     VALUES (?, 'REVIEW_DELETE', 'review', ?)",
                    [auth_id(), $reviewId]
                );
                flash_set('info', 'Review deleted.');
            }
            redirect('/admin/reviews.php' . (input('filter') ? '?filter=' . urlencode((string) input('filter')) : ''));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// ---- Filters ----
$filter = (string) input('filter', 'pending'); // default: pending
$where  = '1=1';
if ($filter === 'pending')  $where = 'r.is_approved = 0';
elseif ($filter === 'approved') $where = 'r.is_approved = 1';
elseif (in_array($filter, ['5','4','3','2','1'], true)) $where = 'r.rating = ' . (int) $filter;

$reviews = db_all(
    "SELECT r.*,
            p.name AS product_name, p.slug AS product_slug,
            u.email AS reviewer_email,
            pr.full_name AS reviewer_name,
            rt.company_name AS retailer_name
     FROM reviews r
     JOIN products p ON p.id = r.product_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN profiles pr ON pr.user_id = r.user_id
     JOIN retailers rt ON rt.id = p.retailer_id
     WHERE $where
     ORDER BY r.created_at DESC
     LIMIT 200"
);

// KPIs
$totalReviews   = (int) db_scalar('SELECT COUNT(*) FROM reviews');
$pendingCount   = (int) db_scalar('SELECT COUNT(*) FROM reviews WHERE is_approved = 0');
$approvedCount  = (int) db_scalar('SELECT COUNT(*) FROM reviews WHERE is_approved = 1');
$avgRating      = (float) db_scalar('SELECT AVG(rating) FROM reviews WHERE is_approved = 1');

$pageTitle = 'Reviews — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('reviews', 'Review Moderation');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom: var(--space-4);">
    <div class="kpi-card">
        <div class="kpi-label">Total reviews</div>
        <div class="kpi-value"><?= number_format($totalReviews) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Awaiting moderation</div>
        <div class="kpi-value <?= $pendingCount > 0 ? 'is-warn' : '' ?>"><?= number_format($pendingCount) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Approved (visible)</div>
        <div class="kpi-value"><?= number_format($approvedCount) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg rating</div>
        <div class="kpi-value"><?= $avgRating > 0 ? number_format($avgRating, 2) . ' ★' : '—' ?></div>
    </div>
</div>

<!-- Filter pills -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <?php foreach ([
        'pending'  => "⏳ Pending ({$pendingCount})",
        'approved' => "✅ Approved ({$approvedCount})",
        'all'      => "All ({$totalReviews})",
        '1'        => '1 ★', '2' => '2 ★', '3' => '3 ★', '4' => '4 ★', '5' => '5 ★',
    ] as $f => $label): ?>
        <a href="<?= url('/admin/reviews.php?filter=' . $f) ?>"
           class="btn <?= $filter === $f ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($reviews)): ?>
    <div class="empty-state">
        <p style="font-size: 1.0625rem;">⭐ No reviews in this filter.</p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-3); max-width: 900px;">
        <?php foreach ($reviews as $r): ?>
            <div style="background: var(--color-surface); border: 1px solid <?= $r['is_approved'] ? 'var(--color-border)' : 'var(--color-mustard, #c9a55a)' ?>; border-radius: var(--radius-lg); padding: var(--space-4);">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: var(--space-3); flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 260px;">
                        <!-- Status + rating -->
                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); flex-wrap: wrap;">
                            <?php if (!$r['is_approved']): ?>
                                <span style="background: var(--color-mustard, #c9a55a); color: white; font-size: 0.6875rem; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.05em; text-transform: uppercase;">⏳ Pending</span>
                            <?php else: ?>
                                <span style="background: var(--color-primary); color: white; font-size: 0.6875rem; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.05em; text-transform: uppercase;">✓ Approved</span>
                            <?php endif; ?>
                            <span style="color: #c9a55a; font-size: 1rem;">
                                <?= str_repeat('★', (int) $r['rating']) ?><span style="color: var(--color-border);"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span>
                            </span>
                            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= format_datetime($r['created_at'], 'd M Y, H:i') ?>
                            </span>
                        </div>

                        <!-- Reviewer + product -->
                        <div style="font-size: 0.875rem; margin-bottom: var(--space-2);">
                            <strong><?= e($r['reviewer_name'] ?? 'Customer') ?></strong>
                            <span style="color: var(--color-text-muted);"> (<?= e($r['reviewer_email']) ?>)</span>
                            on
                            <a href="<?= url('/shop/product.php?slug=' . urlencode($r['product_slug'])) ?>" style="font-weight: 600;">
                                <?= e($r['product_name']) ?>
                            </a>
                            <span style="color: var(--color-text-muted); font-size: 0.8125rem;"> · sold by <?= e($r['retailer_name']) ?></span>
                        </div>

                        <!-- Body -->
                        <?php if (!empty($r['title'])): ?>
                            <div style="font-weight: 600; margin-bottom: 4px;"><?= e($r['title']) ?></div>
                        <?php endif; ?>
                        <p style="margin: 0; color: var(--color-text); line-height: 1.55;"><?= nl2br(e($r['body'])) ?></p>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); min-width: 110px;">
                        <?php if (!$r['is_approved']): ?>
                            <form method="post" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="filter" value="<?= attr($filter) ?>">
                                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">✓ Approve</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unapprove">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="filter" value="<?= attr($filter) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">Unapprove</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="margin:0;" onsubmit="return confirm('Delete this review permanently?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="filter" value="<?= attr($filter) ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" style="width:100%; color: var(--color-danger);">🗑 Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
