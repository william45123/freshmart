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
require_once __DIR__ . '/../../includes/wallet_helpers.php';

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

/**
 * (b) The earliest-expiring batch in a line's allocation.
 *
 * A quantity can span several batches. The line is only deliverable while its
 * SHORTEST-lived batch is still good, so one bad batch fails the whole line —
 * averaging or taking the first would let an order through that then ships
 * something already expired.
 */
function allocation_earliest_expiry(array $allocs): ?string
{
    $earliest = null;
    foreach ($allocs as $a) {
        $exp = db_scalar('SELECT expiry_date FROM stock_batches WHERE id = ?',
                         [(int) $a['stock_batch_id']]);
        if ($exp !== null && ($earliest === null || $exp < $earliest)) {
            $earliest = (string) $exp;
        }
    }
    return $earliest;
}

/** Latest date every line in the cart is still good on. */
function cart_latest_deliverable(array $items, array $allocations): ?string
{
    $limit = null;
    foreach ($items as $it) {
        $e = allocation_earliest_expiry($allocations[$it['product_id']] ?? []);
        if ($e !== null && ($limit === null || $e < $limit)) $limit = $e;
    }
    return $limit;
}

$deliveryLimit = cart_latest_deliverable($cart['items'], $allocations);

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
        $deliveryDate  = (string) input('preferred_delivery_date', date('Y-m-d', strtotime('+' . (int) DELIVERY_LEAD_DAYS . ' days')));

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

                // If paying by wallet, make sure the balance covers the order
                if ($paymentMethod === 'WALLET') {
                    $walletBal = wallet_balance($userId);
                    if ($walletBal < $commitTotal) {
                        $errors[] = 'Your wallet balance (' . format_myr($walletBal)
                            . ') is not enough for this order (' . format_myr($commitTotal)
                            . '). Please top up or choose another payment method.';
                    }
                }

                if (empty($errors)) {
                $orderId = db_transaction(function () use ($cart, $allocations, $userId, $shippingAddressId, $paymentMethod, $deliveryDate, $commitDiscount, $commitPromoId, $commitTotal) {

                    // ----------------------------------------------------------
                    // (b) Delivery-date guard. Runs FIRST, before any row is
                    // written, so a failure writes nothing at all rather than
                    // writing and rolling back.
                    //
                    // Validated against the allocation, not the display batch:
                    // FEFO assigns at checkout, so the customer may be getting a
                    // different and more urgent batch than the product page
                    // showed. Within a line it is the EARLIEST-expiring batch
                    // that decides, because that is the one that would arrive
                    // expired.
                    //
                    // Ordering note: the wallet is debited further down, after
                    // allocation. Throwing here means no order, no stock
                    // decrement, no payment row, no wallet debit and no wallet
                    // ledger entry — wallet_apply() nests inside this same
                    // transaction via the depth-counted db_transaction wrapper.
                    // ----------------------------------------------------------
                    foreach ($cart['items'] as $item) {
                        $earliest = allocation_earliest_expiry($allocations[(int) $item['product_id']] ?? []);
                        if ($earliest !== null && $earliest < $deliveryDate) {
                            throw new RuntimeException(
                                'DELIVERY_DATE_UNAVAILABLE|' . $item['name'] . '|' . $earliest
                            );
                        }
                    }

                    // Commission: platform takes a % of goods subtotal (after discount).
                    // Rate = the retailer's override, else the global default in settings.
                    $goodsNet = round((float) $cart['subtotal'] - (float) $commitDiscount, 2);
                    $retailerId = null;
                    foreach ($cart['items'] as $ci) {
                        $rid = db_scalar('SELECT retailer_id FROM products WHERE id = ?', [(int) $ci['product_id']]);
                        if ($rid) { $retailerId = (int) $rid; break; }
                    }
                    $globalRate = (float) (db_scalar("SELECT config_value FROM system_config WHERE config_key = 'commission_rate'") ?? 10.0);
                    $rateOverride = $retailerId
                        ? db_scalar('SELECT commission_rate FROM retailers WHERE id = ?', [$retailerId])
                        : null;
                    $commissionRate   = $rateOverride !== null ? (float) $rateOverride : $globalRate;
                    $commissionAmount = round($goodsNet * $commissionRate / 100, 2);
                    $retailerPayout   = round($goodsNet - $commissionAmount, 2);

                    // 1. Create order (with commission snapshot)
                    $orderNumber = generate_order_number();
                    db_run(
                        "INSERT INTO orders
                            (order_number, user_id, shipping_address_id, preferred_delivery_date,
                             billing_address_id, promo_code_id,
                             subtotal, discount_amount, shipping_fee, tax_amount, total,
                             commission_rate, commission_amount, retailer_payout,
                             status, placed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, 'PLACED', NOW())",
                        [
                            $orderNumber, $userId, $shippingAddressId, $deliveryDate, $shippingAddressId,
                            $commitPromoId,
                            $cart['subtotal'], $commitDiscount, $cart['shipping'], $commitTotal,
                            $commissionRate, $commissionAmount, $retailerPayout,
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

                    // 3a. If paying by wallet, actually debit the wallet balance.
                    //     (Balance was already checked above; this happens inside
                    //     the same transaction so it's all-or-nothing.)
                    if ($paymentMethod === 'WALLET') {
                        wallet_apply(
                            $userId,
                            'DEBIT',
                            $commitTotal,
                            'ORDER_PAYMENT',
                            $orderNumber,
                            'Paid with wallet for order ' . $orderNumber
                        );
                    }

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
                if (str_starts_with($e->getMessage(), 'DELIVERY_DATE_UNAVAILABLE|')) {
                    [, $itemName, $itemExpiry] = explode('|', $e->getMessage(), 3);
                    $errors[] = 'We can\'t deliver "' . $itemName . '" on '
                        . format_date($deliveryDate) . '. The stock we\'d send you is best before '
                        . format_date($itemExpiry) . '. Pick an earlier delivery day and your '
                        . 'basket will go through unchanged.';

                    // Nothing was written — the guard runs before the first
                    // INSERT — so the cart is intact. Re-plan against current
                    // stock before re-rendering: whatever changed may also have
                    // moved the price or availability, and the customer should
                    // not be shown a stale figure and fail again on retry.
                    $cart        = cart_totals();
                    $allocations = [];
                    $gone        = [];
                    foreach ($cart['items'] as $item) {
                        try {
                            $allocations[$item['product_id']] =
                                fefo_plan_allocation((int) $item['product_id'], (float) $item['quantity']);
                        } catch (Throwable $inner) {
                            $gone[] = $item['name'] . ': ' . $inner->getMessage();
                        }
                    }
                    if ($gone) {
                        foreach ($gone as $msg) flash_set('error', $msg);
                        redirect('/shop/cart.php');
                    }
                    $deliveryLimit = cart_latest_deliverable($cart['items'], $allocations);
                } else {
                    $errors[] = 'Checkout failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Checkout — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container u-page-head">

    <h1>Checkout</h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>

        <div class="u-grid u-cols-1-380 u-gap-6 u-mt-4">

            <!-- Left: forms -->
            <div>
                <!-- Shipping Address -->
                <div class="panel u-p-5 u-mb-4">
                    <h3 class="u-mt-0 u-t-18 disclosure-head co-head" data-disclosure data-open-default>Shipping Address<span class="disclosure-mark" aria-hidden="true"></span></h3>

                    <?php if (!empty($addresses)): ?>
                        <div class="u-flex u-col u-gap-2 u-mb-4">
                            <?php foreach ($addresses as $i => $a): ?>
                                <label class="u-flex u-gap-3 u-p-3 u-bordered u-r u-pointer">
                                    <input type="radio" name="shipping_address_id" value="<?= $a['id'] ?>"
                                           <?= $i === 0 ? 'checked' : '' ?>>
                                    <div>
                                        <strong><?= e($a['label']) ?></strong> — <?= e($a['recipient_name']) ?><br>
                                        <span class="u-muted u-t-14">
                                            <?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['postcode']) ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="u-muted u-t-15">
                            Add your shipping address below:
                        </p>
                        <div class="u-grid u-cols-2 u-gap-3">
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
                        <div class="u-grid u-cols-3 u-gap-3">
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
                <div class="panel u-p-5 u-mb-4">
                    <h3 class="u-mt-0 u-t-18 disclosure-head co-head" data-disclosure data-open-default>Delivery Day<span class="disclosure-mark" aria-hidden="true"></span></h3>
                    <p class="u-muted u-t-14 u-mt-0 u-mb-3">
                        Choose your preferred delivery day. Earliest available:
                        <?= DELIVERY_LEAD_DAYS === 1 ? 'tomorrow' : 'in ' . (int) DELIVERY_LEAD_DAYS . ' days' ?>.
                    </p>
                    <div class="u-grid u-cols-7 u-gap-2">
                        <?php // Starts at DELIVERY_LEAD_DAYS, the same constant the
                              // catalogue's expiry predicate uses.
                              // (b) A date is offered only while every line in the
                              // cart is still good on it. The reason shown says
                              // nothing about stock movement — why a batch is no
                              // longer on offer is not this customer's business.
                              $firstOpen = null;
                        for ($i = DELIVERY_LEAD_DAYS; $i < DELIVERY_LEAD_DAYS + 7; $i++):
                            $d = date('Y-m-d', strtotime("+$i days"));
                            $label = $i === 1 ? 'Tomorrow' : ($i === 2 ? 'In 2 days' : date('D', strtotime($d)));
                            $blocked = ($deliveryLimit !== null && $d > $deliveryLimit);
                            if (!$blocked && $firstOpen === null) $firstOpen = $d;
                        ?>
                            <label class="u-block u-p-2 u-bordered u-r u-ta-c u-t-13<?= $blocked ? ' date-blocked' : ' u-pointer' ?>"
                                   <?= $blocked ? 'title="Not available for this delivery date"' : '' ?>>
                                <input type="radio" name="preferred_delivery_date" value="<?= $d ?>"
                                       <?= $blocked ? 'disabled' : '' ?>
                                       <?= (!$blocked && $d === $firstOpen) ? 'checked' : '' ?> class="u-block u-m-auto-1">
                                <div class="u-fw-600"><?= $label ?></div>
                                <div class="u-muted u-t-12"><?= date('d M', strtotime($d)) ?></div>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Payment Method (simulated) -->
                <div class="panel u-p-5 u-mb-4">
                    <h3 class="u-mt-0 u-t-18 disclosure-head co-head" data-disclosure data-open-default>Payment Method<span class="disclosure-mark" aria-hidden="true"></span></h3>
                    <?php $walletBalance = wallet_balance($userId); ?>
                    <!-- Wallet payment (real balance) -->
                    <label class="u-block u-p-3 u-bordered-2-primary u-r u-pointer u-mb-2 u-bg-mint">
                        <input type="radio" name="payment_method" value="WALLET" class="u-mr-6px"
                               <?= $walletBalance <= 0 ? 'disabled' : '' ?>>
                        <?= icon('coins', 16) ?> <strong>FreshMart Wallet</strong>
                        <span class="u-float-r u-fw-600 u-fg-primary">
                            Balance: <?= format_myr($walletBalance) ?>
                        </span>
                        <?php if ($walletBalance <= 0): ?>
                            <div class="u-t-125 u-sage u-mt-1">
                                Your wallet is empty — top up on the Wallet page, or use another method.
                            </div>
                        <?php endif; ?>
                    </label>
                    <div class="u-grid u-cols-fit-120 u-gap-2">
                        <?php foreach ([
                            'FPX'           => 'FPX',
                            'CREDIT_CARD'   => 'Card',
                            'EWALLET'       => 'E-Wallet',
                            'BANK_TRANSFER' => 'Transfer',
                            'COD'           => 'Cash on Delivery',
                        ] as $code => $label): ?>
                            <label class="u-block u-p-3 u-bordered u-r u-pointer u-ta-c">
                                <input type="radio" name="payment_method" value="<?= $code ?>"
                                       <?= $code === 'FPX' ? 'checked' : '' ?> class="u-mr-6px">
                                <?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="u-muted u-t-13 u-mt-3 u-mb-0">
                        <?= icon('lightbulb', 16) ?> Payment is simulated — no real charge will be made.
                    </p>
                </div>

                <!-- FEFO Allocation Preview (the FYP demo highlight!) -->
                <div class="u-bg-primary-lt u-bordered-primary u-r-lg u-p-5 u-mb-4">
                    <h3 class="u-mt-0 u-t-18 u-fg-primary-dk">
                        <?= icon('package', 16) ?> FEFO Allocation Preview
                    </h3>
                    <p class="u-t-15 u-ink u-mb-3">
                        Your order will be fulfilled from these specific batches (earliest expiry first):
                    </p>
                    <?php foreach ($cart['items'] as $item):
                        $allocs = $allocations[$item['product_id']] ?? [];
                    ?>
                        <div class="u-mb-3">
                            <strong><?= e($item['name']) ?></strong>
                            <span class="u-muted">— <?= number_format((float) $item['quantity'], 2) ?> <?= e($item['unit_code']) ?></span>
                            <div class="u-mt-1 u-pl-3 u-t-14 u-bl-primary-2">
                                <?php foreach ($allocs as $a): ?>
                                    <div>
                                        Batch <code><?= e($a['batch_code']) ?></code>:
                                        <?= number_format($a['quantity'], 2) ?> units
                                        @ <?= format_myr($a['unit_price']) ?>
                                        <span class="u-muted">
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
            <aside class="u-sticky u-top-sticky u-as-start">
                <div class="panel u-p-5">
                    <h3 class="u-mt-0 u-t-18">Order Summary</h3>
                    <?php foreach ($cart['items'] as $item): ?>
                        <div class="u-flex u-jc-between u-mb-2 u-t-15">
                            <span>
                                <?= e($item['name']) ?>
                                <span class="u-muted">× <?= number_format((float) $item['quantity'], 2) ?></span>
                            </span>
                            <span><?= format_myr((float) $item['quantity'] * (float) $item['price_snapshot']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="u-bt u-mt-3 u-pt-3 u-flex u-col u-gap-2">
                        <div class="u-flex u-jc-between u-muted">
                            <span>Subtotal</span><span><?= format_myr($cart['subtotal']) ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div class="u-flex u-jc-between u-fg-accent u-fw-600">
                            <span>Voucher (<?= e($voucher['code']) ?>)</span><span>−<?= format_myr($discount) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="u-flex u-jc-between u-muted">
                            <span>Shipping</span>
                            <span><?= $cart['shipping'] == 0 ? 'FREE' : format_myr($cart['shipping']) ?></span>
                        </div>
                        <div class="u-flex u-jc-between u-pt-2 u-bt u-fw-700 u-t-18">
                            <span>Total</span><span><?= format_myr($finalTotal) ?></span>
                        </div>
                    </div>

                    <!-- Voucher input -->
                    <div class="u-mt-4 u-pt-4 u-bt-dashed">
                        <details <?= ($voucherCode !== '') ? 'open' : '' ?>>
                            <summary class="u-pointer u-t-14 u-fw-600 u-fg-primary-dk u-noselect">
                                <?= icon('ticket', 16) ?>️ Have a voucher code?
                            </summary>
                            <div class="u-flex u-gap-2 u-mt-3">
                                <input type="text" name="voucher_code"
                                       value="<?= attr($voucherCode) ?>"
                                       placeholder="e.g. WELCOME10"
                                       class="u-flex-1 u-upper u-p-8-10 u-bordered u-r u-t-15">
                                <button type="submit" name="action" value="apply_voucher"
                                        class="btn btn-secondary btn-sm">Apply</button>
                            </div>
                            <?php if ($voucherError !== ''): ?>
                                <div class="u-t-13 u-fg-danger u-mt-2">
                                    <?= icon('alert', 16) ?> <?= e($voucherError) ?>
                                </div>
                            <?php elseif ($discount > 0): ?>
                                <div class="u-t-13 u-fg-primary u-mt-2">
                                    ✓ <?= e($voucher['code']) ?> applied — you saved <?= format_myr($discount) ?>!
                                </div>
                            <?php endif; ?>
                        </details>
                    </div>

                    <button type="submit" name="action" value="place_order" class="btn btn-primary btn-lg u-w-full u-mt-4">
                        Place order
                    </button>
                </div>
            </aside>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// §7.3 — checkout sections collapse on mobile so the page is not one long
// scroll. Every section starts OPEN: a checkout that hides what you are about
// to agree to is worse than a long page. Collapsing is the customer's choice,
// and the summary is never collapsible.
(function () {
    var mq = matchMedia('(max-width: 1023px)');
    document.querySelectorAll('[data-disclosure]').forEach(function (head, i) {
        var body = head.nextElementSibling;
        if (!body) return;
        body.id = 'co-' + i;
        head.setAttribute('role', 'button');
        head.setAttribute('tabindex', '0');
        head.setAttribute('aria-controls', body.id);
        function set(open) {
            head.classList.toggle('is-open', open);
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
            body.hidden = !open;
        }
        set(true);
        mq.addEventListener('change', function () { set(true); });
        head.addEventListener('click', function () { if (mq.matches) set(body.hidden); });
        head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); head.click(); }
        });
    });
})();
</script>
