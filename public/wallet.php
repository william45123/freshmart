<?php
/**
 * Customer Wallet — balance + transaction history.
 * Refunds and cancelled-order credits land here (like Shopee Pay).
 */

require_once __DIR__ . '/../includes/wallet_helpers.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
$userId = auth_id();

// Handle simulated top-up
if (is_post() && csrf_verify() && input('action') === 'topup') {
    $amount = (float) input('amount', 0);
    if ($amount <= 0) {
        flash_set('error', 'Please enter a valid top-up amount.');
    } elseif ($amount > 10000) {
        flash_set('error', 'Maximum top-up is RM 10,000 at a time.');
    } else {
        try {
            wallet_apply($userId, 'CREDIT', $amount, 'TOPUP', 'TOPUP-' . strtoupper(bin2hex(random_bytes(3))),
                'Wallet top-up');
            flash_set('success', 'Wallet topped up with ' . format_myr($amount) . '.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }
    }
    redirect('/wallet.php');
}

$balance      = wallet_balance($userId);
$transactions = wallet_transactions($userId, 50);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 760px;">

        <div style="margin-bottom: var(--space-6);">
            <h1 style="margin-bottom: var(--space-2);">My Wallet</h1>
            <p style="color: var(--color-text-muted); margin: 0;">
                Refunds and cancelled orders are credited here. Use your balance at checkout.
            </p>
        </div>

        <!-- Balance card -->
        <div class="wallet-balance-card">
            <div class="wallet-balance-label">Available balance</div>
            <div class="wallet-balance-amount"><?= format_myr($balance) ?></div>
            <a href="<?= url('/shop/browse.php') ?>" class="wallet-shop-btn">Shop now →</a>
        </div>

        <!-- Top up -->
        <div class="wallet-topup">
            <div class="wallet-topup-title">Top up your wallet</div>
            <form method="post" class="wallet-topup-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup">
                <div class="wallet-topup-presets">
                    <?php foreach ([10, 20, 50, 100] as $preset): ?>
                        <button type="submit" name="amount" value="<?= $preset ?>" class="wallet-preset-btn">
                            + RM <?= $preset ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="wallet-topup-custom">
                    <input type="number" name="amount" min="1" max="10000" step="0.01" placeholder="Other amount"
                           class="wallet-topup-input">
                    <button type="submit" class="btn btn-primary btn-sm">Top up</button>
                </div>
            </form>
            <p class="wallet-topup-note">💡 Payment is simulated for this demo — top-up adds to your balance instantly.</p>
        </div>

        <!-- Transactions -->
        <h2 style="font-size: 1.2rem; margin: var(--space-8) 0 var(--space-4);">Transaction history</h2>

        <?php if (empty($transactions)): ?>
            <div class="empty-state" style="padding: var(--space-10) var(--space-6);">
                <div class="empty-state-icon">💳</div>
                <div class="empty-state-title">No transactions yet</div>
                <div class="empty-state-text">When you receive a refund or cancel an order, it'll show up here.</div>
            </div>
        <?php else: ?>
            <div class="wallet-txn-list">
                <?php foreach ($transactions as $t):
                    $isCredit = $t['direction'] === 'CREDIT';
                    $sign = $isCredit ? '+' : '−';
                    $reasonLabel = [
                        'REFUND'        => 'Refund',
                        'ORDER_PAYMENT' => 'Order payment',
                        'TOPUP'         => 'Top-up',
                        'ADJUSTMENT'    => 'Adjustment',
                    ][$t['reason']] ?? $t['reason'];
                ?>
                    <div class="wallet-txn">
                        <div class="wallet-txn-icon <?= $isCredit ? 'credit' : 'debit' ?>">
                            <?= $isCredit ? '↓' : '↑' ?>
                        </div>
                        <div class="wallet-txn-info">
                            <div class="wallet-txn-title"><?= e($reasonLabel) ?><?php
                                if (!empty($t['reference'])): ?> · <span class="wallet-txn-ref"><?= e($t['reference']) ?></span><?php endif; ?></div>
                            <?php if (!empty($t['description'])): ?>
                                <div class="wallet-txn-desc"><?= e($t['description']) ?></div>
                            <?php endif; ?>
                            <div class="wallet-txn-date"><?= format_date($t['created_at']) ?></div>
                        </div>
                        <div class="wallet-txn-amount <?= $isCredit ? 'credit' : 'debit' ?>">
                            <?= $sign ?><?= format_myr((float) $t['amount']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<style>
.wallet-balance-card {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    border-radius: 20px;
    padding: var(--space-8);
    color: #fff;
    position: relative;
    overflow: hidden;
}
.wallet-balance-card::after {
    content: "";
    position: absolute;
    top: -40px; right: -30px;
    width: 200px; height: 200px;
    background: rgba(201, 165, 90, 0.25);
    border-radius: 50%;
    filter: blur(6px);
}
.wallet-balance-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.85;
    margin-bottom: var(--space-2);
    position: relative; z-index: 1;
}
.wallet-balance-amount {
    font-family: var(--font-serif);
    font-size: 2.8rem;
    font-weight: 600;
    line-height: 1;
    margin-bottom: var(--space-5);
    position: relative; z-index: 1;
}
.wallet-shop-btn {
    display: inline-block;
    background: rgba(255,255,255,0.16);
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    padding: 0.7rem 1.4rem;
    border-radius: 999px;
    position: relative; z-index: 1;
    transition: background 0.15s ease;
}
.wallet-shop-btn:hover { background: rgba(255,255,255,0.26); }

.wallet-topup {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: var(--space-5);
    margin-top: var(--space-5);
}
.wallet-topup-title { font-weight: 600; font-size: 1rem; margin-bottom: var(--space-3); }
.wallet-topup-presets { display: flex; gap: var(--space-2); flex-wrap: wrap; margin-bottom: var(--space-3); }
.wallet-preset-btn {
    flex: 1; min-width: 80px;
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--color-primary);
    background: #f4f8ee;
    color: var(--color-primary);
    border-radius: 10px;
    font-weight: 600; font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.15s ease;
}
.wallet-preset-btn:hover { background: var(--color-primary); color: #fff; }
.wallet-topup-custom { display: flex; gap: var(--space-2); }
.wallet-topup-input {
    flex: 1;
    padding: 0.55rem 0.8rem;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    font-size: 0.9rem;
}
.wallet-topup-note { font-size: 0.78rem; color: var(--color-text-light); margin: var(--space-3) 0 0; }

.wallet-txn-list {
    border: 1px solid var(--color-border);
    border-radius: 16px;
    overflow: hidden;
}
.wallet-txn {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid var(--color-border);
    background: var(--color-surface);
}
.wallet-txn:last-child { border-bottom: none; }
.wallet-txn-icon {
    flex: 0 0 auto;
    width: 40px; height: 40px;
    border-radius: 50%;
    display: grid; place-items: center;
    font-size: 1.1rem; font-weight: 700;
}
.wallet-txn-icon.credit { background: #e6f4ea; color: #1a7a3a; }
.wallet-txn-icon.debit  { background: #fbeee8; color: #b85c38; }
.wallet-txn-info { flex: 1; min-width: 0; }
.wallet-txn-title { font-weight: 600; font-size: 0.95rem; color: var(--color-text); }
.wallet-txn-ref { font-family: var(--font-mono, monospace); font-weight: 500; color: var(--color-text-muted); font-size: 0.85rem; }
.wallet-txn-desc { font-size: 0.82rem; color: var(--color-text-muted); margin-top: 2px; }
.wallet-txn-date { font-size: 0.75rem; color: var(--color-text-light); margin-top: 3px; }
.wallet-txn-amount { flex: 0 0 auto; font-weight: 700; font-size: 1rem; }
.wallet-txn-amount.credit { color: #1a7a3a; }
.wallet-txn-amount.debit  { color: #b85c38; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
