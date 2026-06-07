<?php
/**
 * Submit a review — only allowed for products the user has purchased
 * (verified-purchase reviews only).
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';

require_login();
$userId = auth_id();

$productId = (int) input('product_id', 0);
$errors = [];

// Find the most recent eligible order for this product
$eligibleOrder = db_one(
    "SELECT o.id, o.order_number, oi.product_name
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ? AND oi.product_id = ?
       AND o.status IN ('DELIVERED','OUT_FOR_DELIVERY','PACKED','QUALITY_CHECK','PROCESSING')
       AND NOT EXISTS (SELECT 1 FROM reviews r
                       WHERE r.user_id = o.user_id
                         AND r.product_id = oi.product_id
                         AND r.order_id = o.id)
     ORDER BY o.placed_at DESC LIMIT 1",
    [$userId, $productId]
);

$product = db_one('SELECT id, name, slug FROM products WHERE id = ?', [$productId]);

if (!$product) redirect('/shop/browse.php');
if (!$eligibleOrder) {
    flash_set('error', 'You can only review products you have ordered (and not already reviewed).');
    redirect('/shop/product.php?slug=' . urlencode($product['slug']));
}

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $rating = (int) input('rating', 0);
        $title  = trim((string) input('title', ''));
        $body   = trim((string) input('body', ''));

        if ($rating < 1 || $rating > 5) $errors[] = 'Please pick a 1-5 star rating.';
        if (strlen($body) < 10)         $errors[] = 'Please write at least 10 characters.';

        if (empty($errors)) {
            db_run(
                "INSERT INTO reviews (user_id, product_id, order_id, rating, title, body, is_approved)
                 VALUES (?, ?, ?, ?, ?, ?, 0)",
                [$userId, $productId, $eligibleOrder['id'], $rating, $title, $body]
            );
            flash_set('success', 'Thanks for your review! It will appear after admin approval.');
            redirect('/shop/product.php?slug=' . urlencode($product['slug']));
        }
    }
}

$pageTitle = 'Review · ' . $product['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container" style="padding: var(--space-6) 0 var(--space-12); max-width: 600px;">
    <h1>Review <em><?= e($product['name']) ?></em></h1>
    <p style="color: var(--color-text-muted);">
        From your order <code><?= e($eligibleOrder['order_number']) ?></code>
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-top: var(--space-4);">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Rating *</label>
            <div style="display: flex; gap: var(--space-1); font-size: 2rem;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="cursor: pointer;">
                        <input type="radio" name="rating" value="<?= $i ?>" style="display: none;" required>
                        <span class="star-rating" data-val="<?= $i ?>" style="color: #d4d4d4; user-select: none;">★</span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Title (optional)</label>
            <input type="text" name="title" class="form-control" maxlength="255"
                   placeholder="e.g. Sweet and juicy!">
        </div>

        <div class="form-group">
            <label>Your review *</label>
            <textarea name="body" rows="5" required class="form-control" minlength="10"
                      placeholder="Share your experience..."></textarea>
        </div>

        <div class="form-actions" style="display: flex; gap: var(--space-2);">
            <button type="submit" class="btn btn-primary">Submit review</button>
            <a href="<?= url('/shop/product.php?slug=' . urlencode($product['slug'])) ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</section>

<script>
    document.querySelectorAll('.star-rating').forEach(s => {
        s.addEventListener('click', e => {
            const v = parseInt(s.dataset.val);
            s.closest('label').querySelector('input').checked = true;
            document.querySelectorAll('.star-rating').forEach(t => {
                t.style.color = parseInt(t.dataset.val) <= v ? '#f59e0b' : '#d4d4d4';
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
