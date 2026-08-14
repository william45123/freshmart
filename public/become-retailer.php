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
            <div class="hero-image u-bg-primary-lt">
                <span class="hero-emoji">🥬</span>
            </div>
        </div>
    </div>
</section>

<!-- 3 PROPS -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-header u-jc-center u-ta-c u-mb-8">
            <div>
                <div class="banner-eyebrow">What you get</div>
                <h2 class="u-m-1-0">Tools built for fresh-produce retailers</h2>
            </div>
        </div>
        <div class="u-grid u-cols-3 u-gap-6">
            <div class="level-card level-card-very u-p-5">
                <div class="u-t-32 u-mb-2">📦</div>
                <h3 class="u-m-0-0-2 u-t-18">FEFO Inventory</h3>
                <p class="u-muted u-t-14 u-lh-16 u-m-0">
                    First-Expired-First-Out batch tracking. Customers always get the earliest-expiry items first, with full traceability per batch code.
                </p>
            </div>
            <div class="level-card level-card-last u-p-5">
                <div class="u-t-32 u-mb-2">💰</div>
                <h3 class="u-m-0-0-2 u-t-18">Auto-Discount</h3>
                <p class="u-muted u-t-14 u-lh-16 u-m-0">
                    Items entering "Last Chance" (≤25% freshness) automatically get 15% off. No manual price changes. Move stock before it expires.
                </p>
            </div>
            <div class="level-card level-card-info u-p-5">
                <div class="u-t-32 u-mb-2">📊</div>
                <h3 class="u-m-0-0-2 u-t-18">Reports + CSV Export</h3>
                <p class="u-muted u-t-14 u-lh-16 u-m-0">
                    Track units sold, revenue, conversion rate, and "saved from waste" KPI. Export to CSV for Excel. Filter by date range.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="freshness-banner">
    <div class="container">
        <div class="u-maxw-720 u-m-auto">
            <div class="banner-eyebrow u-ta-c">5-step process</div>
            <h2 class="u-ta-c u-m-1-0-6">From signup to your first sale</h2>
            <div class="u-grid u-gap-3">
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
                <div class="u-flex u-gap-3 u-ai-flexstart u-p-3 u-bg-white u-bordered u-r">
                    <div class="u-bg-primary u-fg-white u-w-32 u-h-32 u-r-circle u-flex u-ai-center u-jc-center u-fw-700 u-shrink-0">
                        <?= $step[0] ?>
                    </div>
                    <div>
                        <div class="u-fw-600 u-mb-2px"><?= e($step[1]) ?></div>
                        <div class="u-t-14 u-muted u-lh-15"><?= e($step[2]) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section u-bg-primary u-fg-white">
    <div class="container u-ta-c u-py-10">
        <h2 class="u-fg-white u-t-32 u-m-0-0-2">Ready to start selling?</h2>
        <p class="u-fg-white-85 u-mb-5 u-t-16">
            Free signup. No credit card. Approved within 24 hours.
        </p>
        <a href="<?= url('/auth/register.php?as=retailer') ?>"
           class="btn-pill u-bg-white u-fg-primary u-fw-600"
          >
            Sign up as Retailer →
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
