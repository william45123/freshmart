<?php
/**
 * CSV Export (R-APP-38).
 * Admin-only endpoint that exports business data as CSV.
 *
 * Usage:  /admin/export.php?type=orders&from=2026-01-01&to=2026-12-31
 *
 * Types: orders, users, products, retailers, low_stock, freshness_audit
 */

require_once __DIR__ . '/../../includes/admin_layout.php';

admin_check();  // require ADMIN role

$type = (string) input('type', 'orders');
$from = (string) input('from', date('Y-m-d', strtotime('-30 days')));
$to   = (string) input('to', date('Y-m-d'));

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Audit log this export
db_run(
    "INSERT INTO audit_logs (user_id, action, entity_type, new_values)
     VALUES (?, 'CSV_EXPORT', ?, ?)",
    [auth_id(), $type, json_encode(['from' => $from, 'to' => $to])]
);

// Resolve rows + columns based on type
[$columns, $rows, $filename] = match ($type) {

    'orders' => [
        ['Order Number', 'Customer', 'Customer Email', 'Status', 'Items', 'Subtotal',
         'Shipping', 'Discount', 'Total', 'Payment', 'Delivery Date', 'Placed At'],
        db_all(
            "SELECT o.order_number, p.full_name AS customer, u.email AS customer_email,
                    o.status,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items,
                    o.subtotal, o.shipping_fee, o.discount_amount, o.total,
                    pm.payment_method, o.preferred_delivery_date, o.placed_at
             FROM orders o
             JOIN users u ON u.id = o.user_id
             LEFT JOIN profiles p ON p.user_id = o.user_id
             LEFT JOIN payments pm ON pm.order_id = o.id
             WHERE DATE(o.placed_at) BETWEEN ? AND ?
             ORDER BY o.placed_at DESC",
            [$from, $to]
        ),
        "orders_{$from}_to_{$to}.csv",
    ],

    'users' => [
        ['ID', 'Email', 'Name', 'Phone', 'Role', 'Status', 'Joined', 'Order Count', 'Total Spent'],
        db_all(
            "SELECT u.id, u.email, p.full_name, p.phone, u.role, u.status, u.created_at,
                    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
                    COALESCE((SELECT SUM(total) FROM orders
                              WHERE user_id = u.id AND status IN ('PROCESSING','PACKED','OUT_FOR_DELIVERY','DELIVERED')), 0) AS total_spent
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE u.deleted_at IS NULL
             ORDER BY u.created_at DESC"
        ),
        "users_" . date('Y-m-d') . ".csv",
    ],

    'products' => [
        ['SKU', 'Name', 'Category', 'Retailer', 'Base Price (MYR)', 'Total Stock',
         'Low Stock Threshold', 'View Count', 'Status'],
        db_all(
            "SELECT p.sku, p.name, c.name AS category, r.company_name AS retailer,
                    p.base_price,
                    (SELECT COALESCE(SUM(quantity_remaining), 0)
                     FROM stock_batches sb WHERE sb.product_id = p.id AND sb.status = 'ACTIVE') AS total_stock,
                    p.low_stock_threshold, p.view_count,
                    CASE WHEN p.is_active = 1 THEN 'ACTIVE' ELSE 'INACTIVE' END AS status
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN retailers r ON r.id = p.retailer_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.name"
        ),
        "products_" . date('Y-m-d') . ".csv",
    ],

    'retailers' => [
        ['Company', 'SSM No.', 'Contact Email', 'Phone', 'Address', 'Status', 'Joined',
         'Product Count', 'Total Sales (MYR)'],
        db_all(
            "SELECT r.company_name, r.business_reg_no, u.email, r.contact_phone, r.business_address,
                    r.approval_status, r.created_at,
                    (SELECT COUNT(*) FROM products WHERE retailer_id = r.id AND deleted_at IS NULL) AS product_count,
                    COALESCE((SELECT SUM(oi.subtotal) FROM order_items oi
                              JOIN products p ON p.id = oi.product_id
                              JOIN orders o ON o.id = oi.order_id
                              WHERE p.retailer_id = r.id
                                AND o.status IN ('PROCESSING','PACKED','OUT_FOR_DELIVERY','DELIVERED')), 0) AS total_sales
             FROM retailers r
             JOIN users u ON u.id = r.user_id
             ORDER BY r.created_at DESC"
        ),
        "retailers_" . date('Y-m-d') . ".csv",
    ],

    'low_stock' => [
        ['SKU', 'Product', 'Retailer', 'Current Stock', 'Threshold', 'Status'],
        db_all(
            "SELECT p.sku, p.name AS product, r.company_name AS retailer,
                    COALESCE(stock.total_qty, 0) AS current_stock,
                    p.low_stock_threshold,
                    CASE
                        WHEN COALESCE(stock.total_qty,0) <= 0 THEN 'OUT_OF_STOCK'
                        WHEN COALESCE(stock.total_qty,0) <= 5 THEN 'CRITICAL'
                        WHEN COALESCE(stock.total_qty,0) <= p.low_stock_threshold THEN 'LOW'
                        ELSE 'OK'
                    END AS status
             FROM products p
             JOIN retailers r ON r.id = p.retailer_id
             LEFT JOIN (
                SELECT product_id, SUM(quantity_remaining) AS total_qty
                FROM stock_batches WHERE status = 'ACTIVE'
                GROUP BY product_id
             ) stock ON stock.product_id = p.id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND COALESCE(stock.total_qty,0) <= p.low_stock_threshold
             ORDER BY current_stock ASC"
        ),
        "low_stock_" . date('Y-m-d') . ".csv",
    ],

    'freshness_audit' => [
        ['Batch Code', 'Product', 'Received', 'Expiry', 'Status',
         'Quantity Remaining', 'Original', 'Sold'],
        db_all(
            "SELECT sb.batch_code, p.name AS product, sb.received_date, sb.expiry_date,
                    sb.status, sb.quantity_remaining, sb.original_quantity,
                    (sb.original_quantity - sb.quantity_remaining) AS sold
             FROM stock_batches sb
             JOIN products p ON p.id = sb.product_id
             WHERE sb.created_at BETWEEN ? AND ?
             ORDER BY sb.expiry_date ASC",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        ),
        "freshness_audit_{$from}_to_{$to}.csv",
    ],

    default => [['Error'], [['Error' => 'Unknown export type']], 'error.csv'],
};

// Stream as CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$fp = fopen('php://output', 'w');

// UTF-8 BOM so Excel reads MYR symbol etc. correctly
fputs($fp, "\xEF\xBB\xBF");

// Header row
fputcsv($fp, $columns);

// Data rows
foreach ($rows as $row) {
    fputcsv($fp, array_values($row));
}

fclose($fp);
exit;
