<?php
/**
 * Checkout — the most important page for FEFO demo.
 *
 * Flow:
 *   1. Get cart items
 *   2. For each line item, fefo_plan_allocation() — pick batches
 *   3. Show summary with allocation preview
 *   4. POST → wrap everything in db_transaction:
 *      - INSERT orders
 *      - INSERT order_items per allocation (snapshot batch + freshness)
 *      - fefo_commit_allocation() decrements stock & writes inventory_logs
 *      - INSERT payment (simulated SUCCESS)
 *      - INSERT shipment
 *      - cart_clear()
 *      - INSERT order_history transition
 *   5. Redirect to order confirmation
 */

require_once __DIR__ . '/../../includes/cart_helpers.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';
require_once __DIR__ . '/../../includes/fefo.php';

require_login();

$user   = auth_user();
$userId = (int) $user['id'];
$errors = [];

$cart = cart_totals();
if ($cart['count'] === 0) {
    flash_set('info', 'Your cart is empty.');
    redirect('/shop/cart.php');
}

/**
 * Validate a voucher code against the current cart subtotal.
 * Returns ['promo'=>row, 'discount'=>float] on success, or ['error'=>string].
 */
function validate_voucher(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') return ['error' => ''];

    $promo = db_one(
        "SELECT * FROM promo_codes WHERE UPPER(code) = ? AND is_active = 1",
        [$code]
    );
    if (!$promo) {
        return ['error' => "Voucher \"$code\" is not valid."];
    }
    if (!empty($promo['starts_at']) && strtotime($promo['starts_at']) > time()) {
        return ['error' => "Voucher \"$code\" is not active yet."];
    }
    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
        return ['error' => "Voucher \"$code\" has expired."];
    }
    if ($promo['usage_limit'] !== null && (int) $promo['usage_count'] >= (int) $promo['usage_limit']) {
        return ['error' => "Voucher \"$code\" has been fully redeemed."];
    }
    if ($subtotal < (float) $promo['min_order_value']) {
        return ['error' => "Spend " . format_myr((float) $promo['min_order_value']) . " to use \"$code\" (you need "
            . format_myr((float) $promo['min_order_value'] - $subtotal) . " more)."];
    }

    // Compute discount
    if ($promo['discount_type'] === 'PERCENTAGE') {
        $discount = $subtotal * ((float) $promo['discount_value'] / 100);
        if (!empty($promo['max_discount'])) {
            $discount = min($discount, (float) $promo['max_discount']);
        }
    } else { // FIXED_AMOUNT
        $discount = (float) $promo['discount_value'];
    }
    $discount = min(round($discount, 2), $subtotal); // never exceed subtotal

    return ['promo' => $promo, 'discount' => $discount];
}

// Apply voucher from form (kept across the page via the input field)
$voucherCode  = strtoupper(trim((string) input('voucher_code', '')));
$voucher      = null;       // the validated promo row
$voucherError = '';
$discount     = 0.0;

if ($voucherCode !== '') {
    $result = validate_voucher($voucherCode, (float) $cart['subtotal']);
    if (isset($result['error'])) {
        $voucherError = $result['error'];
    } else {
        $voucher  = $result['promo'];
        $discount = $result['discount'];
    }
}

// Recompute totals with discount applied (shipping unchanged — still free over RM50)
$finalTotal = round((float) $cart['subtotal'] - $discount + (float) $cart['shipping'], 2);

// Get user's addresses
$addresses = db_all(
    'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
    [$userId]
);

// If user has no address, force them to create one inline
$creatingAddress = empty($addresses);

// Plan FEFO allocations for the cart (preview before commit)
$allocations = [];
$insufficient = [];
foreach ($cart['items'] as $item) {
    try {
        $allocs = fefo_plan_allocation((int) $item['product_id'], (float) $item['quantity']);
        $allocations[$item['product_id']] = $allocs;
    } catch (Throwable $e) {
        $insufficient[] = $item['name'] . ': ' . $e->getMessage();
    }
}

if (!empty($insufficient)) {
    foreach ($insufficient as $msg) flash_set('error', $msg);
    redirect('/shop/cart.php');
}

// ---- POST: Place order ----
if (is_post() && input('action') === 'place_order') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {

        // Handle inline address creation
        $shippingAddressId = (int) input('shipping_address_id', 0);

        if ($creatingAddress || $shippingAddressId === 0) {
            $newAddr = [
                'recipient_name' => trim((string) input('recipient_name', '')),
                'phone'          => trim((string) input('phone', '')),
                'line1'          => trim((string) input('line1', '')),
                'city'           => trim((string) input('city', '')),
                'state'          => trim((string) input('state', '')),
                'postcode'       => trim((string) input('postcode', '')),
            ];
            foreach ($newAddr as $k => $v) {
                if ($v === '') $errors[] = ucfirst(str_replace('_', ' ', $k)) . ' is required.';
            }
            if (empty($errors)) {
                db_run(
                    'INSERT INTO addresses (user_id, label, recipient_name, phone, line1, city, state, postcode, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $userId, 'Home', $newAddr['recipient_name'], $newAddr['phone'],
                        $newAddr['line1'], $newAddr['city'], $newAddr['state'], $newAddr['postcode'],
                        empty($addresses) ? 1 : 0,
                    ]
                );
                $shippingAddressId = db_last_id();
            }
        }

        $paymentMethod = (string) input('payment_method', 'FPX');
        $deliveryDate  = (string) input('preferred_delivery_date', date('Y-m-d', strtotime('+1 day')));

        if (empty($errors)) {
            try {
                // Re-validate voucher server-side at submit time (price integrity)
                $commitDiscount = 0.0;
                $commitPromoId  = null;
                if ($voucherCode !== '') {
                    $revalidate = validate_voucher($voucherCode, (float) $cart['subtotal']);
                    if (isset($revalidate['error'])) {
                        if ($revalidate['error'] !== '') $errors[] = $revalidate['error'];
                    } else {
                        $commitDiscount = $revalidate['discount'];
                        $commitPromoId  = (int) $revalidate['promo']['id'];
                    }
                }
                $commitTotal = round((float) $cart['subtotal'] - $commitDiscount + (float) $cart['shipping'], 2);

                if (empty($errors)) {
                $orderId = db_transaction(function () use ($cart, $allocations, $userId, $shippingAddressId, $paymentMethod, $deliveryDate, $commitDiscount, $commitPromoId, $commitTotal) {

                    // 1. Create order
                    $orderNumber = generate_order_number();
                    db_run(
                        "INSERT INTO orders
                            (order_number, user_id, shipping_address_id, preferred_delivery_date,
                             billing_address_id, promo_code_id,
                             subtotal, discount_amount, shipping_fee, tax_amount, total,
                             status, placed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'PLACED', NOW())",
                        [
                            $orderNumber, $userId, $shippingAddressId, $deliveryDate, $shippingAddressId,
                            $commitPromoId,
                            $cart['subtotal'], $commitDiscount, $cart['shipping'], $commitTotal,
                        ]
                    );
                    $orderId = db_last_id();

                    // 2. For each item, create order_items (one per batch allocation)
                    foreach ($cart['items'] as $item) {
                        $productId = (int) $item['product_id'];
                        foreach ($allocations[$productId] as $alloc) {

                            // Snapshot the freshness at this moment
                            $batchRow = db_one(
                                'SELECT received_date, expiry_date FROM stock_batches WHERE id = ?',
                                [$alloc['stock_batch_id']]
                            );
                            $level = freshness_level(
                                $batchRow['received_date'],
                                $batchRow['expiry_date'],
                                (float) $item['decay_exponent']
                            );

                            db_run(
                                "INSERT INTO order_items
                                    (order_id, product_id, stock_batch_id, product_name,
                                     quantity, unit_price, subtotal,
                                     freshness_at_order, expiry_at_order)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $orderId, $productId, $alloc['stock_batch_id'], $item['name'],
                                    $alloc['quantity'], $alloc['unit_price'], $alloc['subtotal'],
                                    $level === 'EXPIRED' ? 'LAST_CHANCE' : $level,
                                    $batchRow['expiry_date'],
                                ]
                            );
                        }

                        // FEFO commit — decrement stock & log
                        fefo_commit_allocation($allocations[$productId], $userId, $orderId);
                    }

                    // 3. Payment (simulated success)
                    db_run(
                        "INSERT INTO payments
                            (order_id, payment_method, amount, status, transaction_ref, paid_at)
                         VALUES (?, ?, ?, 'SUCCESS', ?, NOW())",
                        [$orderId, $paymentMethod, $commitTotal, 'SIM-' . strtoupper(random_token(6))]
                    );

                    // 3b. Increment voucher usage if one was applied
                    if ($commitPromoId !== null) {
                        db_run(
                            "UPDATE promo_codes SET usage_count = usage_count + 1 WHERE id = ?",
                            [$commitPromoId]
                        );
                    }

                    // 4. Shipment (auto-created)
                    db_run(
                        "INSERT INTO shipments
                            (order_id, tracking_number, carrier, estimated_delivery)
                         VALUES (?, ?, 'FreshMart Express', DATE_ADD(CURDATE(), INTERVAL 2 DAY))",
                        [$orderId, 'FM-' . strtoupper(random_token(5))]
                    );

                    // 5. Order history
                    db_run(
                        "INSERT INTO order_history (order_id, previous_status, new_status, changed_by, notes)
                         VALUES (?, NULL, 'PLACED', ?, 'Order placed')",
                        [$orderId, $userId]
                    );

                    // 6. Notify customer
                    db_run(
                        "INSERT INTO notifications (user_id, type, title, body, link)
                         VALUES (?, 'ORDER_UPDATE', ?, ?, ?)",
                        [
                            $userId,
                            'Order ' . $orderNumber . ' placed!',
                            'Your order is now being processed. Estimated delivery in 2 days.',
                            '/shop/orders.php?id=' . $orderId,
                        ]
                    );

                    return $orderId;
                });

                cart_clear();
                flash_set('success', 'Order placed! Thank you for shopping with FreshMart.');
                redirect('/shop/order_confirm.php?id=' . $orderId);
                } // end if (empty($errors)) inner

            } catch (Throwable $e) {
                $errors[] = 'Checkout failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Checkout — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container" style="padding: var(--space-6) 0 var(--space-12);">

    <h1>Checkout</h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 380px; gap: var(--space-6); margin-top: var(--space-4);">

            <!-- Left: forms -->
            <div>
                <!-- Shipping Address -->
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4);">
                    <h3 style="margin-top: 0; font-size: 1.125rem;">Shipping Address</h3>

                    <?php if (!empty($addresses)): ?>
                        <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-bottom: var(--space-4);">
                            <?php foreach ($addresses as $i => $a): ?>
                                <label style="display: flex; gap: var(--space-3); padding: var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius); cursor: pointer;">
                                    <input type="radio" name="shipping_address_id" value="<?= $a['id'] ?>"
                                           <?= $i === 0 ? 'checked' : '' ?>>
                                    <div>
                                        <strong><?= e($a['label']) ?></strong> — <?= e($a['recipient_name']) ?><br>
                                        <span style="color: var(--color-text-muted); font-size: 0.875rem;">
                                            <?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['postcode']) ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                            Add your shipping address below:
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                            <div class="form-group">
                                <label>Recipient name *</label>
                                <input type="text" name="recipient_name" required class="form-control"
                                       value="<?= attr($user['full_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone *</label>
                                <input type="tel" name="phone" required class="form-control"
                                       value="<?= attr($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address line *</label>
                            <input type="text" name="line1" required class="form-control"
                                   placeholder="No. 12, Persiaran Cyberia">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-3);">
                            <div class="form-group">
                                <label>City *</label>
                                <input type="text" name="city" required class="form-control" value="Cyberjaya">
                            </div>
                            <div class="form-group">
                                <label>State *</label>
                                <input type="text" name="state" required class="form-control" value="Selangor">
                            </div>
                            <div class="form-group">
                                <label>Postcode *</label>
                                <input type="text" name="postcode" required class="form-control" value="63000">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Method (simulated) -->
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4);">
                    <h3 style="margin-top: 0; font-size: 1.125rem;">Delivery Day</h3>
                    <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-top: 0; margin-bottom: var(--space-3);">
                        Choose your preferred delivery day. Earliest available: tomorrow.
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: var(--space-2);">
                        <?php for ($i = 1; $i <= 7; $i++):
                            $d = date('Y-m-d', strtotime("+$i days"));
                            $label = $i === 1 ? 'Tomorrow' : ($i === 2 ? 'In 2 days' : date('D', strtotime($d)));
                        ?>
                            <label style="display: block; padding: var(--space-2); border: 1px solid var(--color-border); border-radius: var(--radius); cursor: pointer; text-align: center; font-size: 0.8125rem;">
                                <input type="radio" name="preferred_delivery_date" value="<?= $d ?>"
                                       <?= $i === 1 ? 'checked' : '' ?> style="display: block; margin: 0 auto 4px;">
                                <div style="font-weight: 600;"><?= $label ?></div>
                                <div style="color: var(--color-text-muted); font-size: 0.75rem;"><?= date('d M', strtotime($d)) ?></div>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Payment Method (simulated) -->
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4);">
                    <h3 style="margin-top: 0; font-size: 1.125rem;">Payment Method</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: var(--space-2);">
                        <?php foreach ([
                            'FPX'           => '🏦 FPX',
                            'CREDIT_CARD'   => '💳 Card',
                            'EWALLET'       => '📱 E-Wallet',
                            'BANK_TRANSFER' => '🏧 Transfer',
                            'COD'           => '💵 Cash on Delivery',
                        ] as $code => $label): ?>
                            <label style="display: block; padding: var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius); cursor: pointer; text-align: center;">
                                <input type="radio" name="payment_method" value="<?= $code ?>"
                                       <?= $code === 'FPX' ? 'checked' : '' ?> style="margin-right: 6px;">
                                <?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: var(--color-text-muted); font-size: 0.8125rem; margin-top: var(--space-3); margin-bottom: 0;">
                        💡 Payment is simulated — no real charge will be made.
                    </p>
                </div>

                <!-- FEFO Allocation Preview (the FYP demo highlight!) -->
                <div style="background: var(--color-primary-light); border: 1px solid var(--color-primary); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4);">
                    <h3 style="margin-top: 0; font-size: 1.125rem; color: var(--color-primary-dark);">
                        📦 FEFO Allocation Preview
                    </h3>
                    <p style="font-size: 0.9375rem; color: var(--color-text); margin-bottom: var(--space-3);">
                        Your order will be fulfilled from these specific batches (earliest expiry first):
                    </p>
                    <?php foreach ($cart['items'] as $item):
                        $allocs = $allocations[$item['product_id']] ?? [];
                    ?>
                        <div style="margin-bottom: var(--space-3);">
                            <strong><?= e($item['name']) ?></strong>
                            <span style="color: var(--color-text-muted);">— <?= number_format((float) $item['quantity'], 2) ?> <?= e($item['unit_code']) ?></span>
                            <div style="margin-top: var(--space-1); padding-left: var(--space-3); font-size: 0.875rem; border-left: 2px solid var(--color-primary);">
                                <?php foreach ($allocs as $a): ?>
                                    <div>
                                        Batch <code><?= e($a['batch_code']) ?></code>:
                                        <?= number_format($a['quantity'], 2) ?> units
                                        @ <?= format_myr($a['unit_price']) ?>
                                        <span style="color: var(--color-text-muted);">
                                            · expires <?= format_date($a['expiry_date']) ?>
                                            · <?= e($a['freshness_level']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: order summary -->
            <aside style="position: sticky; top: calc(var(--header-h) + var(--space-4)); align-self: start;">
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                    <h3 style="margin-top: 0; font-size: 1.125rem;">Order Summary</h3>
                    <?php foreach ($cart['items'] as $item): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.9375rem;">
                            <span>
                                <?= e($item['name']) ?>
                                <span style="color: var(--color-text-muted);">× <?= number_format((float) $item['quantity'], 2) ?></span>
                            </span>
                            <span><?= format_myr((float) $item['quantity'] * (float) $item['price_snapshot']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div style="border-top: 1px solid var(--color-border); margin-top: var(--space-3); padding-top: var(--space-3); display: flex; flex-direction: column; gap: var(--space-2);">
                        <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                            <span>Subtotal</span><span><?= format_myr($cart['subtotal']) ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div style="display: flex; justify-content: space-between; color: var(--color-accent); font-weight: 600;">
                            <span>Voucher (<?= e($voucher['code']) ?>)</span><span>−<?= format_myr($discount) ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                            <span>Shipping</span>
                            <span><?= $cart['shipping'] == 0 ? 'FREE' : format_myr($cart['shipping']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: var(--space-2); border-top: 1px solid var(--color-border); font-weight: 700; font-size: 1.125rem;">
                            <span>Total</span><span><?= format_myr($finalTotal) ?></span>
                        </div>
                    </div>

                    <!-- Voucher input -->
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px dashed var(--color-border);">
                        <details <?= ($voucherCode !== '') ? 'open' : '' ?>>
                            <summary style="cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--color-primary-dark); user-select: none;">
                                🎟️ Have a voucher code?
                            </summary>
                            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-3);">
                                <input type="text" name="voucher_code"
                                       value="<?= attr($voucherCode) ?>"
                                       placeholder="e.g. WELCOME10"
                                       style="flex: 1; text-transform: uppercase; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius); font-size: 0.9375rem;">
                                <button type="submit" name="action" value="apply_voucher"
                                        class="btn btn-secondary btn-sm">Apply</button>
                            </div>
                            <?php if ($voucherError !== ''): ?>
                                <div style="font-size: 0.8125rem; color: var(--color-danger); margin-top: var(--space-2);">
                                    ⚠️ <?= e($voucherError) ?>
                                </div>
                            <?php elseif ($discount > 0): ?>
                                <div style="font-size: 0.8125rem; color: var(--color-primary); margin-top: var(--space-2);">
                                    ✓ <?= e($voucher['code']) ?> applied — you saved <?= format_myr($discount) ?>!
                                </div>
                            <?php endif; ?>
                        </details>
                    </div>

                    <button type="submit" name="action" value="place_order" class="btn btn-primary btn-lg" style="width: 100%; margin-top: var(--space-4);">
                        Place order
                    </button>
                </div>
            </aside>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
