<?php
/**
 * FreshMart — Become a Retailer landing page
 * Explains the value proposition + funnels into register.php?as=retailer
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Become a Retailer — FreshMart';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- HERO -->
<section class="editorial-hero">
    <div class="container">
        <div class="hero-eyebrow">For Retailers · Cameron Highlands · Sabah · KL</div>
        <div class="hero-grid">
            <div class="hero-text">
                <h1 class="hero-title">
                    Sell your harvest.<br>
                    <em>Save the rest.</em>
                </h1>
                <p class="hero-description">
                    FreshMart helps Malaysian retailers and farmers move fresh produce
                    before it expires. Auto-discount the last-chance batches.
                    Track your inventory by FEFO. Reach customers who care about freshness.
                </p>
                <div class="hero-actions">
                    <a href="<?= url('/auth/register.php?as=retailer') ?>" class="btn-pill btn-pill-primary">
                        Sign up — it's free
                    </a>
                    <a href="#how-it-works" class="btn-pill btn-pill-outline">
                        How it works ↓
                    </a>
                </div>
            </div>
            <div class="hero-image" style="background: var(--color-primary-light);">
                <span class="hero-emoji">🥬</span>
            </div>
        </div>
    </div>
</section>

<!-- 3 PROPS -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-header" style="justify-content: center; text-align: center; margin-bottom: var(--space-8);">
            <div>
                <div class="banner-eyebrow">What you get</div>
                <h2 style="margin: 4px 0;">Tools built for fresh-produce retailers</h2>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-6);">
            <div class="level-card" style="--c: #16a34a; padding: var(--space-5);">
                <div style="font-size: 2rem; margin-bottom: var(--space-2);">📦</div>
                <h3 style="margin: 0 0 var(--space-2); font-size: 1.125rem;">FEFO Inventory</h3>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6; margin: 0;">
                    First-Expired-First-Out batch tracking. Customers always get the earliest-expiry items first, with full traceability per batch code.
                </p>
            </div>
            <div class="level-card" style="--c: #ea580c; padding: var(--space-5);">
                <div style="font-size: 2rem; margin-bottom: var(--space-2);">💰</div>
                <h3 style="margin: 0 0 var(--space-2); font-size: 1.125rem;">Auto-Discount</h3>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6; margin: 0;">
                    Items entering "Last Chance" (≤25% freshness) automatically get 15% off. No manual price changes. Move stock before it expires.
                </p>
            </div>
            <div class="level-card" style="--c: #0ea5e9; padding: var(--space-5);">
                <div style="font-size: 2rem; margin-bottom: var(--space-2);">📊</div>
                <h3 style="margin: 0 0 var(--space-2); font-size: 1.125rem;">Reports + CSV Export</h3>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6; margin: 0;">
                    Track units sold, revenue, conversion rate, and "saved from waste" KPI. Export to CSV for Excel. Filter by date range.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="freshness-banner">
    <div class="container">
        <div style="max-width: 720px; margin: 0 auto;">
            <div class="banner-eyebrow" style="text-align: center;">5-step process</div>
            <h2 style="text-align: center; margin: 4px 0 var(--space-6);">From signup to your first sale</h2>
            <div style="display: grid; gap: var(--space-3);">
                <?php
                $steps = [
                    ['1', 'Sign up', 'Provide your company name, SSM number, and contact info. Free, no monthly fees.'],
                    ['2', 'Get approved', 'Our admin reviews your registration within 24 hours. You receive an in-app notification.'],
                    ['3', 'Add products', 'Upload up to 5 photos per product. Set base price, shelf life, low-stock threshold. Pick category.'],
                    ['4', 'Add stock batches', 'For each product, log incoming batches with batch code, received date, expiry date, quantity.'],
                    ['5', 'Fulfill orders', 'Customers place orders. FEFO picks batches for you. Advance status: Placed → Processing → Packed → Delivered.'],
                ];
                foreach ($steps as $step):
                ?>
                <div style="display: flex; gap: var(--space-3); align-items: flex-start; padding: var(--space-3); background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius);">
                    <div style="background: var(--color-primary); color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
                        <?= $step[0] ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 2px;"><?= e($step[1]) ?></div>
                        <div style="font-size: 0.875rem; color: var(--color-text-muted); line-height: 1.5;"><?= e($step[2]) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section" style="background: var(--color-primary); color: #fff;">
    <div class="container" style="text-align: center; padding: var(--space-10) 0;">
        <h2 style="color: #fff; font-size: 2rem; margin: 0 0 var(--space-2);">Ready to start selling?</h2>
        <p style="color: rgba(255,255,255,0.85); margin-bottom: var(--space-5); font-size: 1rem;">
            Free signup. No credit card. Approved within 24 hours.
        </p>
        <a href="<?= url('/auth/register.php?as=retailer') ?>"
           class="btn-pill"
           style="background: #fff; color: var(--color-primary); font-weight: 600;">
            Sign up as Retailer →
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
