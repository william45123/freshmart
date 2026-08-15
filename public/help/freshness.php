<?php
/**
 * Public "How Freshness Works" explainer page.
 * Shows the 4 levels + explains the power-law decay per category.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

$pageTitle = 'How Freshness Works — FreshMart';

$config     = db_all('SELECT * FROM freshness_config ORDER BY display_order');
$categories = db_all('SELECT name, slug, decay_exponent, decay_rationale FROM categories WHERE is_active = 1 ORDER BY decay_exponent DESC');

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="hero u-py-12">
    <div class="container">
        <h1>How freshness works</h1>
        <p class="u-t-17 u-maxw-680">
            Every product on FreshMart shows you exactly how fresh it is — calculated from
            the batch's age, never guessed. Here's the science behind the badge.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>The four levels</h2>
        <p class="u-muted u-maxw-620">
            We map each batch's remaining shelf life to one of four levels, based on the
            percentage of its total shelf life that's left.
        </p>

        <div class="u-grid u-cols-fit-220 u-gap-4 u-mt-6">
            <?php foreach ($config as $row): ?>
                <div class="fresh-level-card fresh-level-card-sm" style="--fresh: <?= e($row['color_hex']) ?>">
                    <div class="u-mb-3">
                        <?= freshness_badge_html($row['level_name']) ?>
                    </div>
                    <div class="u-t-14 u-muted u-mb-2">
                        <?= number_format((float) $row['min_percent'], 0) ?>% – <?= number_format((float) $row['max_percent'], 0) ?>% of shelf life remaining
                    </div>
                    <?php if ((float) $row['auto_discount_pct'] > 0): ?>
                        <div class="u-t-14 u-fg-accent u-fw-600">
                            Auto -<?= (int) $row['auto_discount_pct'] ?>% discount
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section u-bg-surface u-by">
    <div class="container">
        <h2>Different foods, different decay</h2>
        <p class="u-muted u-maxw-720">
            Seafood at 50% of its shelf life elapsed is <em>not</em> the same as bread at 50%.
            Bacteria multiply exponentially on fish; bread just gets a bit stale. FreshMart
            uses a category-aware <strong>power-law decay model</strong>:
        </p>
        <pre class="u-bg-page u-p-5 u-r u-m-4-0 u-mono u-t-15">
freshness%  =  (1 − t/T)<sup>n</sup>  ×  100%

t = days since received
T = total shelf life (days)
n = category decay exponent</pre>

        <table class="data-table data-table u-mt-6">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="u-ta-c">Exponent (n)</th>
                    <th>Why</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td data-label="Category"><strong><?= e($c['name']) ?></strong></td>
                        <td data-label="Exponent (n)" class="u-ta-c u-mono u-t-16">
                            <?= number_format((float) $c['decay_exponent'], 2) ?>
                        </td>
                        <td data-label="Why" class="u-muted"><?= e($c['decay_rationale'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>What happens automatically</h2>
        <div class="u-grid u-cols-fit-260 u-gap-4 u-mt-4">
            <div class="panel u-p-5">
                <h3 class="u-t-17 u-mb-2"><?= icon('clock', 18) ?> Every 5 minutes</h3>
                <p class="u-muted u-m-0">
                    A background job recalculates freshness for every active batch.
                </p>
            </div>
            <div class="panel u-p-5">
                <h3 class="u-t-17 u-mb-2"><?= icon('chart', 16) ?> Last chance = -15%</h3>
                <p class="u-muted u-m-0">
                    Items entering the <em>Last Chance</em> tier automatically get a 15% discount
                    to help them sell before they expire.
                </p>
            </div>
            <div class="panel u-p-5">
                <h3 class="u-t-17 u-mb-2"><?= icon('leaf', 16) ?> FEFO sells first</h3>
                <p class="u-muted u-m-0">
                    When you check out, the system picks the batch with the earliest expiry —
                    First-Expired-First-Out — to minimise waste.
                </p>
            </div>
            <div class="panel u-p-5">
                <h3 class="u-t-17 u-mb-2"><?= icon('alert', 18) ?> Expired = hidden</h3>
                <p class="u-muted u-m-0">
                    Expired batches are removed from the catalog. Retailers get an alert
                    so they can act on the loss.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
