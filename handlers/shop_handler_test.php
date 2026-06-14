<?php
// Quick diagnostic — hit this in browser while logged in as member
// http://localhost/FitSYNC/handlers/shop_handler_test.php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: text/plain');

echo "=== SHOP HANDLER DIAGNOSTIC ===\n\n";
echo "Session user_id:   " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n\n";

$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid || ($_SESSION['user_role'] ?? '') !== 'member') {
    echo "ERROR: Not logged in as member!\n";
    exit;
}

$pdo = db();

// Test 1: Cart
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cart WHERE user_id=?');
    $stmt->execute([$uid]);
    echo "Cart items: " . $stmt->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "Cart query ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Branches
try {
    $branches = $pdo->query('SELECT id,name,address,city FROM branches WHERE is_active=1 ORDER BY id')->fetchAll();
    echo "Branches: " . count($branches) . " found\n";
    foreach ($branches as $b) echo "  - [{$b['id']}] {$b['name']}, {$b['city']}\n";
} catch (Throwable $e) {
    echo "Branches query ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Orders table columns
try {
    $cols = $pdo->query('DESCRIBE orders')->fetchAll(PDO::FETCH_COLUMN);
    $needed = ['fulfillment_method','delivery_fee','delivery_address','pickup_branch_id',
               'payment_method','payment_status','proof_of_payment','recipient_name'];
    echo "\nOrders columns check:\n";
    foreach ($needed as $col) {
        echo "  $col: " . (in_array($col, $cols) ? 'OK' : '*** MISSING — RUN MIGRATION ***') . "\n";
    }
} catch (Throwable $e) {
    echo "Orders DESCRIBE ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Products
try {
    $count = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn();
    echo "\nActive products: $count\n";
} catch (Throwable $e) {
    echo "Products ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
