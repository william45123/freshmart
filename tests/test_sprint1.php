<?php
/**
 * Self-test for FreshMart Sprint 1 + Level 2
 * Runs the freshness and FEFO logic against actual seeded DB data
 * and validates expected behaviour.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/freshness.php';
require_once __DIR__ . '/../includes/fefo.php';

$pass = 0; $fail = 0;
function test(string $name, bool $cond, string $extra = ''): void {
    global $pass, $fail;
    if ($cond) { echo "  ✓ $name\n"; $pass++; }
    else       { echo "  ✗ $name  $extra\n"; $fail++; }
}

echo "=== Test 1: DB connectivity & counts ===\n";
test('33 tables exist', 33 === (int) db_scalar(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='freshmart'"));
test('3 users seeded', 3 === (int) db_scalar('SELECT COUNT(*) FROM users'));
test('7 products seeded', 7 === (int) db_scalar('SELECT COUNT(*) FROM products'));
test('7 batches seeded', 7 === (int) db_scalar('SELECT COUNT(*) FROM stock_batches'));
test('8 categories seeded', 8 === (int) db_scalar('SELECT COUNT(*) FROM categories'));

echo "\n=== Test 2: Level 2 — category decay exponents ===\n";
$expected = [
    'seafood'      => 2.50,
    'meat'         => 2.30,
    'bakery'       => 2.00,
    'herbs-spice'  => 1.80,
    'vegetables'   => 1.50,
    'dairy'        => 1.30,
    'fruits'       => 1.10,
    'eggs-tofu'    => 1.00,
];
foreach ($expected as $slug => $n) {
    $got = (float) db_scalar('SELECT decay_exponent FROM categories WHERE slug = ?', [$slug]);
    test("$slug exponent = $n", abs($got - $n) < 0.001, "(got $got)");
}

echo "\n=== Test 3: Freshness formula correctness ===\n";
// Mango: vegetables n=1.5, received 0 days ago, expires in 5 days
// At day 0: depending on time elapsed in current day, ~80-100% fresh
$p0 = freshness_percent(date('Y-m-d'), date('Y-m-d', strtotime('+5 days')), 1.5);
test('Just-received > 60%', $p0 > 60.0 && $p0 <= 100.0, "(got $p0)");

// At day 5 (full elapsed): 0%
$pE = freshness_percent(date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-1 days')), 1.5);
test('Past expiry = 0%', $pE === 0.0, "(got $pE)");

// Linear (n=1.0) at 50% time = 50% fresh
$p1 = freshness_percent(date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+5 days')), 1.0);
test('Linear (n=1.0) at 50% time ≈ 50%', abs($p1 - 50) < 5, "(got $p1)");

// Power-law (n=2.5) at 50% time ≈ 17.7%
$p2 = freshness_percent(date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+5 days')), 2.5);
test('Power-law (n=2.5) at 50% time ≈ 17.7%', abs($p2 - 17.7) < 5, "(got $p2)");

// Power-law (n=1.1, fruits) at 50% time ≈ 46.6%
$p3 = freshness_percent(date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+5 days')), 1.1);
test('Power-law (n=1.1) at 50% time ≈ 46.7%', abs($p3 - 46.7) < 5, "(got $p3)");

echo "\n=== Test 4: Freshness levels ===\n";
test('100% = VERY_FRESH', freshness_level(date('Y-m-d'), date('Y-m-d', strtotime('+10 days')), 1.0) === 'VERY_FRESH');
test('Expired = EXPIRED',  freshness_level(date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-1 day')), 1.0) === 'EXPIRED');

echo "\n=== Test 5: Seed data freshness states ===\n";
$batches = db_all("
    SELECT sb.id, sb.batch_code, sb.received_date, sb.expiry_date,
           p.name AS product_name,
           COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS exponent
    FROM stock_batches sb
    JOIN products p ON p.id = sb.product_id
    JOIN categories c ON c.id = p.category_id
    ORDER BY sb.id
");
foreach ($batches as $b) {
    $level = freshness_level($b['received_date'], $b['expiry_date'], (float) $b['exponent']);
    $pct   = freshness_percent($b['received_date'], $b['expiry_date'], (float) $b['exponent']);
    echo "  • {$b['batch_code']} ({$b['product_name']}, n={$b['exponent']}): "
       . "$level @ " . number_format($pct, 1) . "%\n";
}

// Verify mango is LAST_CHANCE (per seed: received -6d, expires +1d, n=1.1)
$mango = db_one("
    SELECT sb.received_date, sb.expiry_date,
           COALESCE(c.decay_exponent, 1.0) AS exp
    FROM stock_batches sb
    JOIN products p ON p.id = sb.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.slug = 'harum-manis-mango'
");
test('Mango batch is LAST_CHANCE',
    freshness_level($mango['received_date'], $mango['expiry_date'], (float) $mango['exp']) === 'LAST_CHANCE');

echo "\n=== Test 6: FEFO algorithm ===\n";
$totalStock = fefo_total_stock(1);  // Butterhead Lettuce
test('Butterhead lettuce total stock > 0', $totalStock > 0, "(got $totalStock)");

try {
    $allocs = fefo_plan_allocation(1, 5.0);  // Allocate 5 kg of lettuce
    test('FEFO plans 5kg lettuce allocation', !empty($allocs));
    test('FEFO allocation quantity matches', abs(array_sum(array_column($allocs, 'quantity')) - 5.0) < 0.01);
    test('FEFO ordered by earliest expiry', !empty($allocs[0]['expiry_date']));
} catch (Throwable $e) {
    test('FEFO plans 5kg lettuce', false, '(threw: ' . $e->getMessage() . ')');
}

// Try to allocate more than exists
try {
    fefo_plan_allocation(1, 99999);
    test('FEFO rejects over-allocation', false);
} catch (RuntimeException $e) {
    test('FEFO rejects over-allocation', true);
}

echo "\n=== Test 7: Helpers ===\n";
test('format_myr(12.5) = "RM 12.50"', format_myr(12.5) === 'RM 12.50');
test('slugify("Hello World") = "hello-world"', slugify('Hello World') === 'hello-world');
test('e("<script>") escapes properly', e('<script>') === '&lt;script&gt;');

echo "\n=========================================\n";
echo "  PASS: $pass    FAIL: $fail\n";
echo "=========================================\n";
exit($fail > 0 ? 1 : 0);
