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

<section class="hero" style="padding: var(--space-12) 0;">
    <div class="container">
        <h1>How freshness works</h1>
        <p style="font-size: 1.0625rem; max-width: 680px;">
            Every product on FreshMart shows you exactly how fresh it is — calculated from
            the batch's age, never guessed. Here's the science behind the badge.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>The four levels</h2>
        <p style="color: var(--color-text-muted); max-width: 620px;">
            We map each batch's remaining shelf life to one of four levels, based on the
            percentage of its total shelf life that's left.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); margin-top: var(--space-6);">
            <?php foreach ($config as $row): ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border);
                            border-left: 4px solid <?= e($row['color_hex']) ?>; padding: var(--space-5);
                            border-radius: 0 var(--radius-lg) var(--radius-lg) 0;">
                    <div style="margin-bottom: var(--space-3);">
                        <?= freshness_badge_html($row['level_name']) ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
                        <?= number_format((float) $row['min_percent'], 0) ?>% – <?= number_format((float) $row['max_percent'], 0) ?>% of shelf life remaining
                    </div>
                    <?php if ((float) $row['auto_discount_pct'] > 0): ?>
                        <div style="font-size: 0.875rem; color: var(--color-accent); font-weight: 600;">
                            Auto -<?= (int) $row['auto_discount_pct'] ?>% discount
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background: var(--color-surface); border-block: 1px solid var(--color-border);">
    <div class="container">
        <h2>Different foods, different decay</h2>
        <p style="color: var(--color-text-muted); max-width: 720px;">
            Seafood at 50% of its shelf life elapsed is <em>not</em> the same as bread at 50%.
            Bacteria multiply exponentially on fish; bread just gets a bit stale. FreshMart
            uses a category-aware <strong>power-law decay model</strong>:
        </p>
        <pre style="background: var(--color-bg); padding: var(--space-5); border-radius: var(--radius); margin: var(--space-4) 0; font-family: var(--font-mono); font-size: 0.9375rem;">
freshness%  =  (1 − t/T)<sup>n</sup>  ×  100%

t = days since received
T = total shelf life (days)
n = category decay exponent</pre>

        <table class="data-table" style="margin-top: var(--space-6);">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align: center;">Exponent (n)</th>
                    <th>Why</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td style="text-align: center; font-family: var(--font-mono); font-size: 1rem;">
                            <?= number_format((float) $c['decay_exponent'], 2) ?>
                        </td>
                        <td style="color: var(--color-text-muted);"><?= e($c['decay_rationale'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>What happens automatically</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <h3 style="font-size: 1.0625rem; margin-bottom: var(--space-2);">⏰ Every 5 minutes</h3>
                <p style="color: var(--color-text-muted); margin: 0;">
                    A background job recalculates freshness for every active batch.
                </p>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <h3 style="font-size: 1.0625rem; margin-bottom: var(--space-2);">📉 Last chance = -15%</h3>
                <p style="color: var(--color-text-muted); margin: 0;">
                    Items entering the <em>Last Chance</em> tier automatically get a 15% discount
                    to help them sell before they expire.
                </p>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <h3 style="font-size: 1.0625rem; margin-bottom: var(--space-2);">🥬 FEFO sells first</h3>
                <p style="color: var(--color-text-muted); margin: 0;">
                    When you check out, the system picks the batch with the earliest expiry —
                    First-Expired-First-Out — to minimise waste.
                </p>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                <h3 style="font-size: 1.0625rem; margin-bottom: var(--space-2);">⚠️ Expired = hidden</h3>
                <p style="color: var(--color-text-muted); margin: 0;">
                    Expired batches are removed from the catalog. Retailers get an alert
                    so they can act on the loss.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
