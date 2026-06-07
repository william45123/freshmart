<?php
/**
 * FEFO — First-Expired-First-Out Inventory Algorithm
 * ================================================================
 * THE CORE INVENTORY ENGINE OF FRESHMART.
 *
 * When a customer buys X units of a product, we need to fulfil the
 * order from the BATCH with the earliest expiry date (still ACTIVE).
 * If that batch can't satisfy the full quantity, we move to the next
 * earliest-expiring batch, and so on, until X is fulfilled.
 *
 * This minimizes waste — items closest to expiry are sold first.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/freshness.php';

/**
 * Compute the total available stock for a product across all
 * non-expired ACTIVE batches.
 */
function fefo_total_stock(int $productId): float
{
    $total = db_scalar(
        "SELECT COALESCE(SUM(quantity_remaining), 0)
         FROM stock_batches
         WHERE product_id = ?
           AND status = 'ACTIVE'
           AND expiry_date > CURDATE()",
        [$productId]
    );
    return (float) $total;
}

/**
 * Plan how to fulfil $quantity units of $productId using FEFO.
 *
 * Returns an array of allocations:
 *   [
 *     ['stock_batch_id'=>..., 'quantity'=>..., 'unit_price'=>..., 'freshness_level'=>...],
 *     ...
 *   ]
 * Or throws RuntimeException if insufficient stock.
 *
 * This is a PLANNING function — it does not modify the database.
 * Call fefo_commit_allocation() after the plan is accepted.
 */
function fefo_plan_allocation(int $productId, float $quantity): array
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Quantity must be positive.');
    }

    // 1. Get the product's base price + decay exponent (for freshness pricing logic)
    $product = db_one(
        'SELECT p.id, p.base_price,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.is_active = 1 AND p.deleted_at IS NULL',
        [$productId]
    );
    if (!$product) {
        throw new RuntimeException("Product #{$productId} not found or inactive.");
    }
    $basePrice    = (float) $product['base_price'];
    $decayExponent = (float) $product['decay_exponent'];

    // 2. Get all eligible batches ordered by earliest expiry first.
    //    Tiebreaker: earlier received_date → batch_code ASC for deterministic order.
    $batches = db_all(
        "SELECT id, product_id, batch_code, received_date, expiry_date,
                quantity_remaining, selling_price_override
         FROM stock_batches
         WHERE product_id = ?
           AND status = 'ACTIVE'
           AND quantity_remaining > 0
           AND expiry_date > CURDATE()
         ORDER BY expiry_date ASC, received_date ASC, id ASC
         FOR UPDATE",                          // Row-level lock during checkout
        [$productId]
    );

    $allocations = [];
    $remaining   = $quantity;

    foreach ($batches as $batch) {
        if ($remaining <= 0) break;

        $available = (float) $batch['quantity_remaining'];
        $take      = min($available, $remaining);

        $level = freshness_level($batch['received_date'], $batch['expiry_date'], $decayExponent);
        if ($level === 'EXPIRED') continue;     // Safety guard

        // Apply freshness-based pricing
        $effectivePrice = $batch['selling_price_override'] !== null
            ? (float) $batch['selling_price_override']
            : apply_freshness_discount($basePrice, $level)['final_price'];

        $allocations[] = [
            'stock_batch_id'  => (int) $batch['id'],
            'batch_code'      => $batch['batch_code'],
            'quantity'        => $take,
            'unit_price'      => $effectivePrice,
            'subtotal'        => round($take * $effectivePrice, 2),
            'expiry_date'     => $batch['expiry_date'],
            'freshness_level' => $level,
        ];

        $remaining -= $take;
    }

    if ($remaining > 0) {
        throw new RuntimeException(
            sprintf(
                'Insufficient stock for product #%d. Requested %s, short by %s.',
                $productId, $quantity, $remaining
            )
        );
    }

    return $allocations;
}

/**
 * Commit a previously-planned allocation to the database.
 * Decrements stock_batches.quantity_remaining and writes inventory_logs.
 *
 * MUST be called inside a transaction (typically the order-creation transaction).
 */
function fefo_commit_allocation(array $allocations, int $userId, int $orderId): void
{
    foreach ($allocations as $alloc) {
        // 1. Re-check current quantity_remaining (avoid race conditions)
        $current = db_scalar(
            'SELECT quantity_remaining FROM stock_batches WHERE id = ? FOR UPDATE',
            [$alloc['stock_batch_id']]
        );
        if ($current === null) {
            throw new RuntimeException("Batch #{$alloc['stock_batch_id']} disappeared.");
        }
        if ((float) $current < (float) $alloc['quantity']) {
            throw new RuntimeException(
                "Batch #{$alloc['stock_batch_id']} no longer has enough stock. "
                . "Available: {$current}, Required: {$alloc['quantity']}"
            );
        }

        $newQty = (float) $current - (float) $alloc['quantity'];

        // 2. Decrement stock
        db_run(
            'UPDATE stock_batches
             SET quantity_remaining = ?,
                 status = CASE WHEN ? <= 0 THEN ' . "'DEPLETED'" . ' ELSE status END
             WHERE id = ?',
            [$newQty, $newQty, $alloc['stock_batch_id']]
        );

        // 3. Audit log
        db_run(
            "INSERT INTO inventory_logs
                (stock_batch_id, user_id, movement_type,
                 quantity_change, quantity_after, related_order_id, reason)
             VALUES (?, ?, 'SOLD', ?, ?, ?, ?)",
            [
                $alloc['stock_batch_id'],
                $userId,
                -1 * (float) $alloc['quantity'],
                $newQty,
                $orderId,
                "Order #{$orderId} fulfilled from batch {$alloc['batch_code']}",
            ]
        );
    }
}

/**
 * Restock: add a new batch to inventory.
 */
function fefo_restock(
    int $productId,
    ?int $supplierId,
    string $batchCode,
    string $receivedDate,
    string $expiryDate,
    float $quantity,
    float $costPerUnit,
    int $performedByUserId,
    ?string $storageLocation = null
): int {
    return db_transaction(function () use (
        $productId, $supplierId, $batchCode, $receivedDate, $expiryDate,
        $quantity, $costPerUnit, $performedByUserId, $storageLocation
    ) {
        db_run(
            "INSERT INTO stock_batches
                (product_id, supplier_id, batch_code, received_date, expiry_date,
                 original_quantity, quantity_remaining, cost_per_unit,
                 storage_location, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
            [
                $productId, $supplierId, $batchCode, $receivedDate, $expiryDate,
                $quantity, $quantity, $costPerUnit, $storageLocation,
            ]
        );
        $batchId = db_last_id();

        db_run(
            "INSERT INTO inventory_logs
                (stock_batch_id, user_id, movement_type,
                 quantity_change, quantity_after, reason)
             VALUES (?, ?, 'RESTOCK', ?, ?, ?)",
            [$batchId, $performedByUserId, $quantity, $quantity,
             "Restocked batch {$batchCode}"]
        );
        return $batchId;
    });
}

/**
 * Adjust a batch (manual correction by retailer).
 */
function fefo_adjust(
    int $batchId,
    float $newQuantity,
    string $reason,
    int $performedByUserId
): void {
    db_transaction(function () use ($batchId, $newQuantity, $reason, $performedByUserId) {
        $current = db_scalar(
            'SELECT quantity_remaining FROM stock_batches WHERE id = ? FOR UPDATE',
            [$batchId]
        );
        if ($current === null) {
            throw new RuntimeException("Batch #{$batchId} not found.");
        }

        $delta = $newQuantity - (float) $current;
        db_run(
            'UPDATE stock_batches SET quantity_remaining = ? WHERE id = ?',
            [$newQuantity, $batchId]
        );
        db_run(
            "INSERT INTO inventory_logs
                (stock_batch_id, user_id, movement_type,
                 quantity_change, quantity_after, reason)
             VALUES (?, ?, 'ADJUSTMENT', ?, ?, ?)",
            [$batchId, $performedByUserId, $delta, $newQuantity, $reason]
        );
    });
}

/**
 * Get the "display batch" for a product card.
 * Returns the earliest-expiry ACTIVE batch (i.e. the one customers
 * would buy first if they bought 1 unit right now).
 *
 * Used to display freshness badge on the catalog page.
 */
function fefo_display_batch(int $productId): ?array
{
    return db_one(
        "SELECT id, batch_code, received_date, expiry_date,
                quantity_remaining, selling_price_override
         FROM stock_batches
         WHERE product_id = ?
           AND status = 'ACTIVE'
           AND quantity_remaining > 0
           AND expiry_date > CURDATE()
         ORDER BY expiry_date ASC, received_date ASC, id ASC
         LIMIT 1",
        [$productId]
    );
}
