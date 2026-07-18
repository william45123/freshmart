<?php
/**
 * Wallet + Refund helpers.
 *
 * Wallet   — each user has a running balance (like Shopee Pay).
 * Refunds  — customer requests → retailer reviews → approve/reject or
 *            escalate to admin → on approval, credit the wallet.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

/* ============================================================
 * WALLET
 * ============================================================ */

/** Get (or lazily create) a user's wallet row. */
function wallet_get(int $userId): array
{
    $w = db_one('SELECT * FROM wallets WHERE user_id = ?', [$userId]);
    if (!$w) {
        db_run('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)', [$userId]);
        $w = db_one('SELECT * FROM wallets WHERE user_id = ?', [$userId]);
    }
    return $w;
}

/** Current balance for a user. */
function wallet_balance(int $userId): float
{
    return (float) (wallet_get($userId)['balance'] ?? 0.0);
}

/**
 * Credit or debit a wallet and write a ledger row, atomically.
 * $direction = 'CREDIT' (money in) or 'DEBIT' (money out).
 * Throws if a debit would overdraw the balance.
 */
function wallet_apply(
    int $userId,
    string $direction,
    float $amount,
    string $reason,
    ?string $reference = null,
    ?string $description = null
): float {
    if ($amount <= 0) {
        throw new InvalidArgumentException('Amount must be positive.');
    }
    $direction = strtoupper($direction);
    if ($direction !== 'CREDIT' && $direction !== 'DEBIT') {
        throw new InvalidArgumentException('Invalid direction.');
    }

    return db_transaction(function () use ($userId, $direction, $amount, $reason, $reference, $description) {
        // Lock the wallet row
        $w = db_one('SELECT * FROM wallets WHERE user_id = ? FOR UPDATE', [$userId]);
        if (!$w) {
            db_run('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)', [$userId]);
            $w = db_one('SELECT * FROM wallets WHERE user_id = ? FOR UPDATE', [$userId]);
        }

        $current = (float) $w['balance'];
        $newBalance = $direction === 'CREDIT'
            ? $current + $amount
            : $current - $amount;

        if ($newBalance < 0) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        db_run('UPDATE wallets SET balance = ? WHERE id = ?', [$newBalance, $w['id']]);
        db_run(
            'INSERT INTO wallet_transactions
                (wallet_id, direction, amount, balance_after, reason, reference, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$w['id'], $direction, $amount, $newBalance, $reason, $reference, $description]
        );

        return $newBalance;
    });
}

/** Recent wallet transactions for display. */
function wallet_transactions(int $userId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $w = wallet_get($userId);
    return db_all(
        "SELECT * FROM wallet_transactions
         WHERE wallet_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT $limit",
        [$w['id']]
    );
}

/* ============================================================
 * REFUNDS
 * ============================================================ */

/**
 * Which retailer owns an order (via its items). If an order somehow
 * spans multiple retailers, returns the first; refunds are still scoped
 * per requested items in the partial case.
 */
function refund_retailer_for_order(int $orderId): ?int
{
    $rid = db_scalar(
        'SELECT p.retailer_id
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC
         LIMIT 1',
        [$orderId]
    );
    return $rid !== null ? (int) $rid : null;
}

/** Does an order already have an open (non-final) refund request? */
function refund_has_open_request(int $orderId): bool
{
    $n = (int) db_scalar(
        "SELECT COUNT(*) FROM refund_requests
         WHERE order_id = ? AND status IN ('REQUESTED','ESCALATED')",
        [$orderId]
    );
    return $n > 0;
}

/**
 * Create a refund request.
 *  $scope = 'FULL' | 'PARTIAL'
 *  $items = for PARTIAL: [ ['order_item_id'=>id, 'quantity'=>q], ... ]
 * Returns the new refund_request id.
 */
function refund_create(
    int $orderId,
    int $userId,
    string $scope,
    string $reason,
    ?string $detail,
    array $items = []
): int {
    $order = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$orderId, $userId]);
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }
    if ($order['status'] !== 'DELIVERED') {
        throw new RuntimeException('Refunds can only be requested for delivered orders.');
    }
    if (refund_has_open_request($orderId)) {
        throw new RuntimeException('There is already an open refund request for this order.');
    }

    $retailerId = refund_retailer_for_order($orderId);

    return db_transaction(function () use ($orderId, $userId, $scope, $reason, $detail, $items, $order, $retailerId) {
        $lineData = [];
        $amount = 0.0;

        if ($scope === 'FULL') {
            // Refund the order's paid subtotal (goods only; shipping not refunded)
            $amount = (float) $order['subtotal'] - (float) $order['discount_amount'];
            if ($amount < 0) $amount = 0.0;
        } else {
            // PARTIAL: sum selected order items × quantity
            if (empty($items)) {
                throw new RuntimeException('Select at least one item to refund.');
            }
            foreach ($items as $it) {
                $oiId = (int) ($it['order_item_id'] ?? 0);
                $qty  = (float) ($it['quantity'] ?? 0);
                if ($oiId <= 0 || $qty <= 0) continue;

                $oi = db_one(
                    'SELECT * FROM order_items WHERE id = ? AND order_id = ?',
                    [$oiId, $orderId]
                );
                if (!$oi) {
                    throw new RuntimeException('Invalid item in refund request.');
                }
                if ($qty > (float) $oi['quantity']) {
                    throw new RuntimeException('Refund quantity exceeds ordered quantity.');
                }
                $lineAmt = round((float) $oi['unit_price'] * $qty, 2);
                $amount += $lineAmt;
                $lineData[] = ['order_item_id' => $oiId, 'quantity' => $qty, 'line_amount' => $lineAmt];
            }
            if ($amount <= 0) {
                throw new RuntimeException('Nothing valid selected to refund.');
            }
        }

        db_run(
            'INSERT INTO refund_requests
                (order_id, user_id, retailer_id, scope, reason, detail, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $userId, $retailerId, $scope, $reason, $detail, $amount, 'REQUESTED']
        );
        $refundId = db_last_id();

        foreach ($lineData as $ld) {
            db_run(
                'INSERT INTO refund_request_items
                    (refund_request_id, order_item_id, quantity, line_amount)
                 VALUES (?, ?, ?, ?)',
                [$refundId, $ld['order_item_id'], $ld['quantity'], $ld['line_amount']]
            );
        }

        // Notify the retailer (if we know who) that a refund needs review
        if ($retailerId) {
            $ruId = db_scalar('SELECT user_id FROM retailers WHERE id = ?', [$retailerId]);
            if ($ruId) {
                refund_notify(
                    (int) $ruId,
                    'New refund request',
                    'Order ' . $order['order_number'] . ' has a refund request awaiting your review.',
                    '/retailer/refunds.php'
                );
            }
        }

        return $refundId;
    });
}

/** Retailer approves a refund → credit customer's wallet. */
function refund_approve(int $refundId, int $handlerUserId, ?string $note = null): void
{
    db_transaction(function () use ($refundId, $handlerUserId, $note) {
        $r = db_one("SELECT * FROM refund_requests WHERE id = ? FOR UPDATE", [$refundId]);
        if (!$r) throw new RuntimeException('Refund request not found.');
        if (!in_array($r['status'], ['REQUESTED', 'ESCALATED'], true)) {
            throw new RuntimeException('This refund has already been resolved.');
        }

        $order = db_one('SELECT * FROM orders WHERE id = ?', [$r['order_id']]);

        // Credit the customer's wallet
        wallet_apply(
            (int) $r['user_id'],
            'CREDIT',
            (float) $r['amount'],
            'REFUND',
            $order['order_number'] ?? ('REFUND-' . $refundId),
            'Refund for order ' . ($order['order_number'] ?? '')
        );

        // Mark request approved
        db_run(
            "UPDATE refund_requests
             SET status = 'APPROVED', handled_by = ?, decision_note = ?, resolved_at = NOW()
             WHERE id = ?",
            [$handlerUserId, $note, $refundId]
        );

        // If it was a full refund, reflect it on the order + payment
        if ($r['scope'] === 'FULL') {
            db_run("UPDATE orders SET status = 'REFUNDED', commission_amount = 0.00, retailer_payout = 0.00 WHERE id = ?", [$r['order_id']]);
            db_run("UPDATE payments SET status = 'REFUNDED' WHERE order_id = ?", [$r['order_id']]);
        }

        // Notify the customer
        refund_notify(
            (int) $r['user_id'],
            'Refund approved',
            'Your refund of ' . money_str((float) $r['amount']) . ' has been credited to your wallet.',
            '/wallet.php'
        );
    });
}

/** Retailer or admin rejects a refund. */
function refund_reject(int $refundId, int $handlerUserId, ?string $note = null): void
{
    $r = db_one('SELECT * FROM refund_requests WHERE id = ?', [$refundId]);
    if (!$r) throw new RuntimeException('Refund request not found.');
    if (!in_array($r['status'], ['REQUESTED', 'ESCALATED'], true)) {
        throw new RuntimeException('This refund has already been resolved.');
    }
    db_run(
        "UPDATE refund_requests
         SET status = 'REJECTED', handled_by = ?, decision_note = ?, resolved_at = NOW()
         WHERE id = ?",
        [$handlerUserId, $note, $refundId]
    );
    refund_notify(
        (int) $r['user_id'],
        'Refund declined',
        'Your refund request for the order was declined.' . ($note ? ' Reason: ' . $note : ''),
        '/shop/orders.php'
    );
}

/** Retailer escalates a refund to admin for a final decision. */
function refund_escalate(int $refundId, int $retailerUserId, ?string $note = null): void
{
    $r = db_one('SELECT * FROM refund_requests WHERE id = ?', [$refundId]);
    if (!$r) throw new RuntimeException('Refund request not found.');
    if ($r['status'] !== 'REQUESTED') {
        throw new RuntimeException('Only a pending request can be escalated.');
    }
    db_run(
        "UPDATE refund_requests
         SET status = 'ESCALATED', decision_note = ?, escalated_at = NOW()
         WHERE id = ?",
        [$note, $refundId]
    );
    // Notify all admins
    $admins = db_all("SELECT id FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'");
    foreach ($admins as $a) {
        refund_notify(
            (int) $a['id'],
            'Refund escalated for review',
            'A retailer escalated a refund request. Please make a final decision.',
            '/admin/refunds.php'
        );
    }
}

/** Customer cancels their own still-pending request. */
function refund_cancel(int $refundId, int $userId): void
{
    $r = db_one('SELECT * FROM refund_requests WHERE id = ? AND user_id = ?', [$refundId, $userId]);
    if (!$r) throw new RuntimeException('Refund request not found.');
    if (!in_array($r['status'], ['REQUESTED', 'ESCALATED'], true)) {
        throw new RuntimeException('This request can no longer be cancelled.');
    }
    db_run("UPDATE refund_requests SET status = 'CANCELLED', resolved_at = NOW() WHERE id = ?", [$refundId]);
}

/* ============================================================
 * ORDER CANCELLATION (before it ships)
 * ============================================================ */

/** Can this order still be cancelled by the customer? */
function order_can_cancel(array $order): bool
{
    // Only before the retailer has packed/shipped it.
    return in_array($order['status'], ['PLACED', 'PROCESSING'], true);
}

/**
 * Customer cancels an order that hasn't shipped yet.
 * Restocks every item, refunds the full paid total to the wallet, and
 * marks the order CANCELLED — all atomically.
 */
function order_cancel(int $orderId, int $userId, ?string $reason = null): void
{
    db_transaction(function () use ($orderId, $userId, $reason) {
        $order = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE', [$orderId, $userId]);
        if (!$order) throw new RuntimeException('Order not found.');
        if (!order_can_cancel($order)) {
            throw new RuntimeException('This order can no longer be cancelled (it may already be packed or shipped).');
        }

        // 1. Restock each order item back to its batch
        $items = db_all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
        foreach ($items as $it) {
            $batchId = (int) $it['stock_batch_id'];
            $qty     = (float) $it['quantity'];

            $current = db_scalar('SELECT quantity_remaining FROM stock_batches WHERE id = ? FOR UPDATE', [$batchId]);
            if ($current === null) {
                // Batch gone; skip restock but keep cancelling
                continue;
            }
            $newQty = (float) $current + $qty;
            db_run(
                "UPDATE stock_batches
                 SET quantity_remaining = ?,
                     status = CASE WHEN status = 'DEPLETED' AND ? > 0 THEN 'ACTIVE' ELSE status END
                 WHERE id = ?",
                [$newQty, $newQty, $batchId]
            );
            db_run(
                "INSERT INTO inventory_logs
                    (stock_batch_id, user_id, movement_type, quantity_change, quantity_after, related_order_id, reason)
                 VALUES (?, ?, 'RETURNED', ?, ?, ?, ?)",
                [$batchId, $userId, $qty, $newQty, $orderId, "Order #{$orderId} cancelled by customer"]
            );
        }

        // 2. Refund the full paid total (incl. shipping — the order never happened) to wallet
        $refundTotal = (float) $order['total'];
        if ($refundTotal > 0) {
            wallet_apply(
                $userId,
                'CREDIT',
                $refundTotal,
                'REFUND',
                $order['order_number'],
                'Cancelled order ' . $order['order_number']
            );
        }

        // 3. Mark order + payment (cancelled order earns no commission)
        db_run("UPDATE orders SET status = 'CANCELLED', commission_amount = 0.00, retailer_payout = 0.00, notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?",
            ["\n[Cancelled by customer" . ($reason ? ': ' . $reason : '') . "]", $orderId]);
        db_run("UPDATE payments SET status = 'REFUNDED' WHERE order_id = ?", [$orderId]);

        // 4. Notify the retailer
        $rid = refund_retailer_for_order($orderId);
        if ($rid) {
            $ruId = db_scalar('SELECT user_id FROM retailers WHERE id = ?', [$rid]);
            if ($ruId) {
                refund_notify((int) $ruId, 'Order cancelled',
                    'Order ' . $order['order_number'] . ' was cancelled by the customer and stock returned.',
                    '/retailer/orders.php');
            }
        }
    });
}

/* ---- small internal helpers ---- */

/** Insert a notification (mirrors the app's notifications table). */
function refund_notify(int $userId, string $title, string $body, string $link): void
{
    // guard: only if notifications table exists / columns match
    try {
        db_run(
            'INSERT INTO notifications (user_id, type, title, body, link)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, 'ORDER_UPDATE', $title, $body, $link]
        );
    } catch (Throwable $e) {
        // notifications are best-effort; never block a refund on them
    }
}

/** Format money for messages (helpers.php has format_myr for display). */
function money_str(float $amount): string
{
    return 'RM ' . number_format($amount, 2);
}
