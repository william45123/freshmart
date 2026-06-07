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
<section style="background: linear-gradient(180deg, var(--color-primary-light, #e7eadf) 0%, var(--color-surface, #faf8f3) 100%); padding: var(--space-12) 0;">
    <div class="container">
        <h1 style="font-size: 2.25rem; margin: 0 0 var(--space-3); max-width: 720px;">
            How Freshness Works
        </h1>
        <p style="font-size: 1.125rem; max-width: 680px; color: var(--color-text-muted); margin: 0;">
            Every product on FreshMart shows you exactly how fresh it is — calculated from
            the batch's age, never guessed. Here's the science behind the badge.
        </p>
    </div>
</section>

<!-- ============ The 4 levels ============ -->
<section class="section" style="padding: var(--space-10) 0;">
    <div class="container">
        <h2 style="font-size: 1.75rem;">The four freshness levels</h2>
        <p style="color: var(--color-text-muted); max-width: 620px; margin: var(--space-2) 0 var(--space-6);">
            We map each batch's remaining shelf life to one of four levels, based on the
            percentage of its total shelf life that's left.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4);">
            <?php foreach ($config as $row): ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border);
                            border-left: 5px solid <?= e($row['color_hex']) ?>;
                            padding: var(--space-5); border-radius: 0 var(--radius-lg) var(--radius-lg) 0;">
                    <div style="font-size: 1.0625rem; font-weight: 700; margin-bottom: var(--space-1); color: <?= e($row['color_hex']) ?>;">
                        ● <?= e($row['label_en']) ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-3); line-height: 1.4;">
                        <?= number_format((float) $row['min_percent'], 0) ?>%
                        – <?= number_format((float) $row['max_percent'], 0) ?>%
                        of shelf life remaining
                    </div>
                    <?php if ((float) $row['auto_discount_pct'] > 0): ?>
                        <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-accent, #b85c38);">
                            ⚡ Auto -<?= (int) $row['auto_discount_pct'] ?>% discount
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Expired (not in DB, hardcoded for explanation) -->
            <div style="background: var(--color-surface); border: 1px solid var(--color-border);
                        border-left: 5px solid #9a3b22;
                        padding: var(--space-5); border-radius: 0 var(--radius-lg) var(--radius-lg) 0; opacity: 0.7;">
                <div style="font-size: 1.0625rem; font-weight: 700; margin-bottom: var(--space-1); color: #9a3b22;">
                    ● Expired
                </div>
                <div style="font-size: 0.875rem; color: var(--color-text-muted); line-height: 1.4;">
                    0% or below — hidden from catalog automatically
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ Power-law formula ============ -->
<section class="section" style="background: var(--color-surface); border-block: 1px solid var(--color-border); padding: var(--space-10) 0;">
    <div class="container">
        <h2 style="font-size: 1.75rem;">Different foods, different decay</h2>
        <p style="color: var(--color-text-muted); max-width: 720px; margin: var(--space-2) 0 var(--space-5);">
            Seafood at 50% of its shelf life elapsed is <em>not</em> the same as bread at 50%.
            Bacteria multiply exponentially on fish; bread just gets a bit stale. FreshMart
            uses a category-aware <strong>power-law decay model</strong>:
        </p>

        <div style="background: var(--color-bg, #faf8f3); padding: var(--space-5) var(--space-6);
                    border-radius: var(--radius-lg); margin: var(--space-4) 0; max-width: 480px;
                    border: 1px solid var(--color-border);">
            <div style="font-family: var(--font-mono, monospace); font-size: 1.0625rem; text-align: center; margin-bottom: var(--space-3);">
                freshness% = (1 − t/T)<sup>n</sup> × 100%
            </div>
            <div style="font-size: 0.875rem; color: var(--color-text-muted); line-height: 1.8;">
                <strong>t</strong> = days since received<br>
                <strong>T</strong> = total shelf life (days)<br>
                <strong>n</strong> = category-specific decay exponent
            </div>
        </div>

        <p style="color: var(--color-text-muted); margin: var(--space-4) 0 var(--space-2); font-size: 0.9375rem;">
            Higher <strong>n</strong> = freshness drops faster as expiry approaches (fast-spoiling food).<br>
            Lower <strong>n</strong> = gentler decline (hardy or refrigerated food).
        </p>

        <h3 style="font-size: 1.25rem; margin-top: var(--space-6);">Category decay exponents</h3>
        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
            Each <strong>n</strong> below is grounded in food-science literature, not chosen arbitrarily.
        </p>

        <table class="data-table" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align: center; width: 110px;">Exponent (n)</th>
                    <th>Why this value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td style="text-align: center; font-family: var(--font-mono, monospace); font-size: 1rem; font-weight: 600;">
                            <?= number_format((float) $c['decay_exponent'], 2) ?>
                        </td>
                        <td style="color: var(--color-text-muted); font-size: 0.9375rem;">
                            <?= e($c['decay_rationale'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ============ What happens automatically ============ -->
<section class="section" style="padding: var(--space-10) 0;">
    <div class="container">
        <h2 style="font-size: 1.75rem;">What happens automatically</h2>
        <p style="color: var(--color-text-muted); max-width: 620px; margin: var(--space-2) 0 var(--space-6);">
            FreshMart doesn't just <em>show</em> freshness — it acts on it.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4);">
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);">⏰</div>
                <h3 style="font-size: 1.0625rem; margin: 0 0 var(--space-2);">Every 5 minutes</h3>
                <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9375rem; line-height: 1.5;">
                    A background job recalculates freshness for every active batch — so the
                    badge you see is at most 5 minutes old.
                </p>
            </div>

            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);">📉</div>
                <h3 style="font-size: 1.0625rem; margin: 0 0 var(--space-2);">Last Chance = -15%</h3>
                <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9375rem; line-height: 1.5;">
                    Items entering the Last Chance tier automatically get a 15% discount —
                    so they sell before they expire, saving good food from going to waste.
                </p>
            </div>

            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);">🥬</div>
                <h3 style="font-size: 1.0625rem; margin: 0 0 var(--space-2);">FEFO sells first</h3>
                <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9375rem; line-height: 1.5;">
                    When you check out, the system picks the batch with the earliest expiry —
                    <strong>F</strong>irst-<strong>E</strong>xpired-<strong>F</strong>irst-<strong>O</strong>ut — to minimise waste.
                </p>
            </div>

            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <div style="font-size: 1.5rem; margin-bottom: var(--space-2);">⚠️</div>
                <h3 style="font-size: 1.0625rem; margin: 0 0 var(--space-2);">Expired = hidden</h3>
                <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9375rem; line-height: 1.5;">
                    Expired batches are removed from the catalog automatically. Retailers
                    get an alert so they can act on the loss.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============ CTA back to browse ============ -->
<section style="background: var(--color-primary-light, #e7eadf); padding: var(--space-8) 0; text-align: center;">
    <div class="container">
        <h2 style="margin: 0 0 var(--space-3); font-size: 1.5rem;">Ready to shop with confidence?</h2>
        <p style="color: var(--color-text-muted); margin: 0 0 var(--space-4); max-width: 520px; margin-left: auto; margin-right: auto;">
            Every product page shows its live freshness — so you always know what you're buying.
        </p>
        <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary btn-lg">Browse products →</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
