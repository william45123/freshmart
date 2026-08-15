<?php
/**
 * Public "How Freshness Works" explainer page.
 *
 * Location: public/shop/freshness.php
 * URL:      /shop/freshness.php
 *
 * Shows:
 *   1. The 4 freshness levels with colors and discounts
 *   2. Why different foods decay differently (power-law formula)
 *   3. The 8 category-specific decay exponents with food-science rationale
 *   4. What happens automatically (cron, FEFO, auto-discount)
 *
 * This is the page customers click "Learn more →" to read.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

$pageTitle = 'How Freshness Works — FreshMart';

// Load the 4 levels from freshness_config
$config = db_all('SELECT * FROM freshness_config ORDER BY display_order ASC');

// Load all 8 categories with their decay exponents + rationale
$categories = db_all(
    'SELECT name, slug, decay_exponent, decay_rationale
     FROM categories
     WHERE is_active = 1
     ORDER BY decay_exponent DESC'
);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ============ Hero ============ -->
<section class="u-bg-grad-down u-py-12">
    <div class="container">
        <h1 class="u-t-36 u-m-0-0-3 u-maxw-720">
            How Freshness Works
        </h1>
        <p class="u-t-18 u-maxw-680 u-muted u-m-0">
            Every product on FreshMart shows you exactly how fresh it is — calculated from
            the batch's age, never guessed. Here's the science behind the badge.
        </p>
    </div>
</section>

<!-- ============ The 4 levels ============ -->
<section class="section u-py-10">
    <div class="container">
        <h2 class="u-t-28">The four freshness levels</h2>
        <p class="u-muted u-maxw-620 u-m-2-0-6">
            We map each batch's remaining shelf life to one of four levels, based on the
            percentage of its total shelf life that's left.
        </p>

        <div class="u-grid u-cols-fit-220 u-gap-4">
            <?php foreach ($config as $row): ?>
                <div class="fresh-level-card" style="--fresh: <?= e($row['color_hex']) ?>">
                    <div class="fresh-level-card-title" style="--fresh: <?= e($row['color_hex']) ?>">
                        ● <?= e($row['label_en']) ?>
                    </div>
                    <div class="u-t-14 u-muted u-mb-3 u-lh-14">
                        <?= number_format((float) $row['min_percent'], 0) ?>%
                        – <?= number_format((float) $row['max_percent'], 0) ?>%
                        of shelf life remaining
                    </div>
                    <?php if ((float) $row['auto_discount_pct'] > 0): ?>
                        <div class="u-t-14 u-fw-600 u-fg-accent">
                            ⚡ Auto -<?= (int) $row['auto_discount_pct'] ?>% discount
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Expired (not in DB, hardcoded for explanation) -->
            <div class="u-bg-surface u-bordered u-bl-rust u-p-5 u-r-lg-right u-op-70">
                <div class="u-t-17 u-fw-700 u-mb-1 u-fg-rust">
                    ● Expired
                </div>
                <div class="u-t-14 u-muted u-lh-14">
                    0% or below — hidden from catalog automatically
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ Power-law formula ============ -->
<section class="section u-bg-surface u-by u-py-10">
    <div class="container">
        <h2 class="u-t-28">Different foods, different decay</h2>
        <p class="u-muted u-maxw-720 u-m-2-0-5">
            Seafood at 50% of its shelf life elapsed is <em>not</em> the same as bread at 50%.
            Bacteria multiply exponentially on fish; bread just gets a bit stale. FreshMart
            uses a category-aware <strong>power-law decay model</strong>:
        </p>

        <div class="u-bg-page u-p-5-6 u-r-lg u-m-4-0 u-maxw-480 u-bordered">
            <div class="u-mono u-t-17 u-ta-c u-mb-3">
                freshness% = (1 − t/T)<sup>n</sup> × 100%
            </div>
            <div class="u-t-14 u-muted u-lh-18">
                <strong>t</strong> = days since received<br>
                <strong>T</strong> = total shelf life (days)<br>
                <strong>n</strong> = category-specific decay exponent
            </div>
        </div>

        <p class="u-muted u-m-4-0-2 u-t-15">
            Higher <strong>n</strong> = freshness drops faster as expiry approaches (fast-spoiling food).<br>
            Lower <strong>n</strong> = gentler decline (hardy or refrigerated food).
        </p>

        <h3 class="u-t-20 u-mt-6">Category decay exponents</h3>
        <p class="u-t-14 u-muted u-mb-4">
            Each <strong>n</strong> below is grounded in food-science literature, not chosen arbitrarily.
        </p>

        <table class="data-table data-table u-maxw-900">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="u-ta-c u-w-110">Exponent (n)</th>
                    <th>Why this value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td data-label="Category"><strong><?= e($c['name']) ?></strong></td>
                        <td data-label="Exponent (n)" class="u-ta-c u-mono u-t-16 u-fw-600">
                            <?= number_format((float) $c['decay_exponent'], 2) ?>
                        </td>
                        <td data-label="Why this value" class="u-muted u-t-15">
                            <?= e($c['decay_rationale'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ============ What happens automatically ============ -->
<section class="section u-py-10">
    <div class="container">
        <h2 class="u-t-28">What happens automatically</h2>
        <p class="u-muted u-maxw-620 u-m-2-0-6">
            FreshMart doesn't just <em>show</em> freshness — it acts on it.
        </p>

        <div class="u-grid u-cols-fit-240 u-gap-4">
            <div class="panel u-p-5">
                <div class="u-t-24 u-mb-2">⏰</div>
                <h3 class="u-t-17 u-m-0-0-2">Every 5 minutes</h3>
                <p class="u-muted u-m-0 u-t-15 u-lh-15">
                    A background job recalculates freshness for every active batch — so the
                    badge you see is at most 5 minutes old.
                </p>
            </div>

            <div class="panel u-p-5">
                <div class="u-t-24 u-mb-2"><?= icon('chart', 16) ?></div>
                <h3 class="u-t-17 u-m-0-0-2">Last Chance = -15%</h3>
                <p class="u-muted u-m-0 u-t-15 u-lh-15">
                    Items entering the Last Chance tier automatically get a 15% discount —
                    so they sell before they expire, saving good food from going to waste.
                </p>
            </div>

            <div class="panel u-p-5">
                <div class="u-t-24 u-mb-2"><?= icon('leaf', 16) ?></div>
                <h3 class="u-t-17 u-m-0-0-2">FEFO sells first</h3>
                <p class="u-muted u-m-0 u-t-15 u-lh-15">
                    When you check out, the system picks the batch with the earliest expiry —
                    <strong>F</strong>irst-<strong>E</strong>xpired-<strong>F</strong>irst-<strong>O</strong>ut — to minimise waste.
                </p>
            </div>

            <div class="panel u-p-5">
                <div class="u-t-24 u-mb-2">⚠️</div>
                <h3 class="u-t-17 u-m-0-0-2">Expired = hidden</h3>
                <p class="u-muted u-m-0 u-t-15 u-lh-15">
                    Expired batches are removed from the catalog automatically. Retailers
                    get an alert so they can act on the loss.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============ CTA back to browse ============ -->
<section class="u-bg-primary-lt u-py-8 u-ta-c">
    <div class="container">
        <h2 class="u-m-0-0-3 u-t-24">Ready to shop with confidence?</h2>
        <p class="u-muted u-m-0-0-4 u-maxw-520 u-ml-auto u-mr-auto">
            Every product page shows its live freshness — so you always know what you're buying.
        </p>
        <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary btn-lg">Browse products →</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
