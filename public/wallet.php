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
    <div class="container u-maxw-760">

        <div class="u-mb-6">
            <h1 class="u-mb-2">My Wallet</h1>
            <p class="u-muted u-m-0">
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
        <h2 class="u-t-192 u-m-8-0-4">Transaction history</h2>

        <?php if (empty($transactions)): ?>
            <div class="empty-state u-p-10-6">
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


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
