<?php
/**
 * Retailer: view reviews on MY OWN products + reply to them.
 * Replies are stored in review_replies (one reply per review).
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer = retailer_current();
$retailerId = (int) $retailer['id'];
$errors = [];

// ---- POST: add/update/delete reply ----
if (is_post() && csrf_verify()) {
    $action   = (string) input('action', '');
    $reviewId = (int) input('review_id', 0);

    // Verify this review is on one of MY products (security)
    $owns = (int) db_scalar(
        "SELECT COUNT(*) FROM reviews r
         JOIN products p ON p.id = r.product_id
         WHERE r.id = ? AND p.retailer_id = ?",
        [$reviewId, $retailerId]
    );

    if ($reviewId > 0 && $owns > 0) {
        if ($action === 'reply') {
            $body = trim((string) input('reply_body', ''));
            if (strlen($body) < 5) {
                $errors[] = 'Reply must be at least 5 characters.';
            } else {
                try {
                    // Upsert (one reply per review enforced by UNIQUE on review_id)
                    $existing = db_one('SELECT id FROM review_replies WHERE review_id = ?', [$reviewId]);
                    if ($existing) {
                        db_run(
                            'UPDATE review_replies SET body = ? WHERE id = ?',
                            [$body, $existing['id']]
                        );
                        flash_set('success', 'Reply updated.');
                    } else {
                        db_run(
                            'INSERT INTO review_replies (review_id, retailer_id, body) VALUES (?, ?, ?)',
                            [$reviewId, $retailerId, $body]
                        );
                        // Notify the reviewer
                        $reviewerId = db_scalar('SELECT user_id FROM reviews WHERE id = ?', [$reviewId]);
                        $product = db_one(
                            "SELECT p.slug, p.name FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.id = ?",
                            [$reviewId]
                        );
                        if ($reviewerId && $product) {
                            db_run(
                                "INSERT INTO notifications (user_id, type, title, body, link)
                                 VALUES (?, 'REVIEW_REPLY', ?, ?, ?)",
                                [
                                    $reviewerId,
                                    'Retailer replied to your review',
                                    e($retailer['company_name']) . ' replied to your review on "' . $product['name'] . '".',
                                    '/shop/product.php?slug=' . $product['slug'],
                                ]
                            );
                        }
                        flash_set('success', 'Reply posted. The customer will be notified.');
                    }
                    redirect('/retailer/reviews.php');
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($action === 'delete_reply') {
            db_run('DELETE FROM review_replies WHERE review_id = ? AND retailer_id = ?',
                   [$reviewId, $retailerId]);
            flash_set('info', 'Reply removed.');
            redirect('/retailer/reviews.php');
        }
    } else {
        $errors[] = 'Review not found or not yours.';
    }
}

// ---- Filters ----
$filter = (string) input('filter', 'all');

$where = 'p.retailer_id = ?';
$args  = [$retailerId];
if ($filter === 'unanswered') {
    $where .= ' AND rep.id IS NULL';
} elseif ($filter === 'low') {
    $where .= ' AND r.rating <= 3';
} elseif (in_array($filter, ['5','4','3','2','1'], true)) {
    $where .= ' AND r.rating = ?';
    $args[] = (int) $filter;
}

$reviews = db_all(
    "SELECT r.*,
            p.name AS product_name, p.slug AS product_slug,
            pr.full_name AS reviewer_name,
            rep.id AS reply_id, rep.body AS reply_body, rep.created_at AS reply_at
     FROM reviews r
     JOIN products p ON p.id = r.product_id
     LEFT JOIN profiles pr ON pr.user_id = r.user_id
     LEFT JOIN review_replies rep ON rep.review_id = r.id
     WHERE $where AND r.is_approved = 1
     ORDER BY r.created_at DESC
     LIMIT 100",
    $args
);

// KPIs for THIS retailer
$myTotal = (int) db_scalar(
    "SELECT COUNT(*) FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE p.retailer_id = ? AND r.is_approved = 1", [$retailerId]
);
$myAvg = (float) db_scalar(
    "SELECT AVG(r.rating) FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE p.retailer_id = ? AND r.is_approved = 1", [$retailerId]
);
$myUnanswered = (int) db_scalar(
    "SELECT COUNT(*) FROM reviews r
     JOIN products p ON p.id = r.product_id
     LEFT JOIN review_replies rep ON rep.review_id = r.id
     WHERE p.retailer_id = ? AND r.is_approved = 1 AND rep.id IS NULL",
    [$retailerId]
);

$pageTitle = 'Reviews — Retailer';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('reviews', 'Customer Reviews');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="kpi-grid" style="margin-bottom: var(--space-4); max-width: 800px;">
    <div class="kpi-card">
        <div class="kpi-label">My reviews</div>
        <div class="kpi-value"><?= number_format($myTotal) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg rating</div>
        <div class="kpi-value"><?= $myAvg > 0 ? number_format($myAvg, 2) . ' ★' : '—' ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Awaiting reply</div>
        <div class="kpi-value <?= $myUnanswered > 0 ? 'is-warn' : '' ?>"><?= number_format($myUnanswered) ?></div>
    </div>
</div>

<!-- Filter pills -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <?php foreach ([
        'all'         => "All ({$myTotal})",
        'unanswered'  => "⏳ Unanswered ({$myUnanswered})",
        'low'         => '⚠️ Low (≤3★)',
        '5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★',
    ] as $f => $label): ?>
        <a href="<?= url('/retailer/reviews.php?filter=' . $f) ?>"
           class="btn <?= $filter === $f ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($reviews)): ?>
    <div class="empty-state">
        <p style="font-size: 1.0625rem;">⭐ No reviews in this filter yet.</p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-3); max-width: 800px;">
        <?php foreach ($reviews as $r):
            $expandKey = 'r' . $r['id'];
            // Auto-expand the reply box if it was open before (sticky via query string)
            $autoOpen = ((string) input('open') === $expandKey) || (!empty($r['reply_id']));
        ?>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <!-- Review header -->
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); flex-wrap: wrap;">
                    <strong><?= e($r['reviewer_name'] ?? 'Customer') ?></strong>
                    <span style="color: #c9a55a; font-size: 1rem;">
                        <?= str_repeat('★', (int) $r['rating']) ?><span style="color: var(--color-border);"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span>
                    </span>
                    <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                        <?= format_datetime($r['created_at'], 'd M Y') ?>
                    </span>
                </div>
                <div style="font-size: 0.875rem; margin-bottom: var(--space-2); color: var(--color-text-muted);">
                    on <a href="<?= url('/shop/product.php?slug=' . urlencode($r['product_slug'])) ?>" style="font-weight: 600; color: var(--color-text);">
                        <?= e($r['product_name']) ?>
                    </a>
                </div>
                <?php if (!empty($r['title'])): ?>
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= e($r['title']) ?></div>
                <?php endif; ?>
                <p style="margin: 0; color: var(--color-text); line-height: 1.55;"><?= nl2br(e($r['body'])) ?></p>

                <!-- Existing retailer reply (if any) -->
                <?php if (!empty($r['reply_id'])): ?>
                    <div style="margin-top: var(--space-3); padding: var(--space-3); background: var(--color-primary-light); border-left: 3px solid var(--color-primary); border-radius: var(--radius);">
                        <div style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-primary-dark); font-weight: 600; margin-bottom: 4px;">
                            🏢 Your reply · <?= format_datetime($r['reply_at'], 'd M Y') ?>
                        </div>
                        <p style="margin: 0; color: var(--color-text); line-height: 1.5;"><?= nl2br(e($r['reply_body'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Reply form -->
                <details style="margin-top: var(--space-3);" <?= $autoOpen ? 'open' : '' ?>>
                    <summary style="cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--color-primary-dark);">
                        <?= !empty($r['reply_id']) ? '✏️ Edit reply' : '💬 Reply to this review' ?>
                    </summary>
                    <form method="post" style="margin-top: var(--space-3);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                        <textarea name="reply_body" rows="3" required minlength="5"
                                  placeholder="Thank the customer or address their concerns professionally..."
                                  class="form-control"><?= e($r['reply_body'] ?? '') ?></textarea>
                        <div style="display: flex; gap: var(--space-2); margin-top: var(--space-2);">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <?= !empty($r['reply_id']) ? 'Update reply' : 'Post reply' ?>
                            </button>
                            <?php if (!empty($r['reply_id'])): ?>
                                <button type="submit" name="action" value="delete_reply"
                                        formnovalidate
                                        onclick="return confirm('Remove your reply?')"
                                        class="btn btn-ghost btn-sm" style="color: var(--color-danger);">
                                    Remove reply
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
