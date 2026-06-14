<?php
// ============================================================
//  FitSync — Shop Handler (v2 — Full Checkout)
//  /handlers/shop_handler.php
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// ── Global error/exception → JSON (prevents HTML error pages breaking fetch) ──
set_exception_handler(function(Throwable $e): void {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});
set_error_handler(function(int $errno, string $errstr): bool {
    throw new ErrorException($errstr, 0, $errno);
});

// ── Helpers ────────────────────────────────────────────────
function jsonOk(mixed $data = []): never {
    // Use array_merge instead of spread (...) for PHP < 8.1 compatibility
    echo json_encode(array_merge(['success' => true], is_array($data) ? $data : ['data' => $data]));
    exit;
}
function jsonErr(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function verifyCsrf(): void {
    $token = trim((string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) jsonErr('Invalid CSRF token.', 403);
}
function requireMember(): void {
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'member') jsonErr('Authentication required.', 401);
}
function requireAdmin(): void {
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') jsonErr('Admin access required.', 403);
}
function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}
function deliveryFee(string $region): float {
    $ncr = ['ncr','metro manila','national capital region','manila','quezon city',
            'makati','pasig','taguig','pasay','caloocan','malabon','mandaluyong',
            'marikina','muntinlupa','navotas','paranaque','pateros','san juan',
            'valenzuela','las pinas'];
    return in_array(strtolower(trim($region)), $ncr, true) ? 80.0 : 150.0;
}

// ── Router ─────────────────────────────────────────────────
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$pdo    = db();
$uid    = (int)($_SESSION['user_id'] ?? 0);

// ── Auto-migration: optimize database & setup branch stocks ──
(function(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        // 1. Create product_stocks table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `product_stocks` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `product_id` INT UNSIGNED NOT NULL,
            `branch_id` SMALLINT UNSIGNED NOT NULL,
            `stock` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_product_branch` (`product_id`, `branch_id`),
            CONSTRAINT `fk_prod_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_prod_stock_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Migrate existing stocks if products.stock still exists
        $prodCols = $pdo->query('DESCRIBE products')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('stock', $prodCols, true)) {
            $prods = $pdo->query('SELECT id, stock FROM products')->fetchAll();
            $stmtMigrate = $pdo->prepare('INSERT IGNORE INTO product_stocks (product_id, branch_id, stock) VALUES (?, 1, ?)');
            foreach ($prods as $p) {
                $stmtMigrate->execute([(int)$p['id'], (int)$p['stock']]);
            }
            $pdo->exec('ALTER TABLE products DROP COLUMN stock');
        }

        // 3. Drop password_resets table if exists
        $pdo->exec('DROP TABLE IF EXISTS `password_resets`');

        // 4. Drop remember_token column from users if exists
        $userCols = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('remember_token', $userCols, true)) {
            $pdo->exec('ALTER TABLE users DROP COLUMN remember_token');
        }

        // 5. Ensure orders columns exist
        $existing = $pdo->query('DESCRIBE orders')->fetchAll(PDO::FETCH_COLUMN);
        $alters = [];
        $map = [
            'fulfillment_method' => "ADD COLUMN fulfillment_method ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery' AFTER status",
            'delivery_fee'       => "ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fulfillment_method",
            'shipping_provider'  => "ADD COLUMN shipping_provider VARCHAR(40) NULL AFTER delivery_fee",
            'delivery_address'   => "ADD COLUMN delivery_address TEXT NULL AFTER shipping_provider",
            'pickup_branch_id'   => "ADD COLUMN pickup_branch_id INT NULL AFTER delivery_address",
            'pickup_date'        => "ADD COLUMN pickup_date DATE NULL AFTER pickup_branch_id",
            'pickup_time'        => "ADD COLUMN pickup_time VARCHAR(20) NULL AFTER pickup_date",
            'payment_method'     => "ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'cash_on_pickup' AFTER pickup_time",
            'payment_status'     => "ADD COLUMN payment_status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending' AFTER payment_method",
            'proof_of_payment'   => "ADD COLUMN proof_of_payment VARCHAR(255) NULL AFTER payment_status",
            'order_notes'        => "ADD COLUMN order_notes TEXT NULL AFTER proof_of_payment",
            'recipient_name'     => "ADD COLUMN recipient_name VARCHAR(120) NOT NULL DEFAULT '' AFTER order_notes",
            'recipient_contact'  => "ADD COLUMN recipient_contact VARCHAR(30) NOT NULL DEFAULT '' AFTER recipient_name",
            'recipient_email'    => "ADD COLUMN recipient_email VARCHAR(120) NOT NULL DEFAULT '' AFTER recipient_contact",
            'cancel_reason'      => "ADD COLUMN cancel_reason TEXT NULL AFTER recipient_email",
        ];
        foreach ($map as $col => $ddl) {
            if (!in_array($col, $existing, true)) {
                $alters[] = $ddl;
            }
        }
        if ($alters) {
            $pdo->exec('ALTER TABLE orders ' . implode(', ', $alters));
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `order_reviews` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `body` TEXT NOT NULL,
            `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_order_review` (`order_id`, `user_id`),
            KEY `idx_order_reviews_user` (`user_id`),
            CONSTRAINT `fk_order_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_order_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // Fail silently — migration errors must not break requests
    }
})($pdo);


match ($action) {
    // Member
    'get_products'        => actionGetProducts($pdo),
    'get_cart'            => actionGetCart($pdo, $uid),
    'add_to_cart'         => actionAddToCart($pdo, $uid),
    'update_cart'         => actionUpdateCart($pdo, $uid),
    'remove_from_cart'    => actionRemoveFromCart($pdo, $uid),
    'get_checkout_data'   => actionGetCheckoutData($pdo, $uid),
    'upload_proof'        => actionUploadProof($pdo, $uid),
    'checkout'            => actionCheckout($pdo, $uid),
    'get_orders'          => actionGetOrders($pdo, $uid),
    'mark_order_received' => actionMarkOrderReceived($pdo, $uid),
    'submit_order_review' => actionSubmitOrderReview($pdo, $uid),
    'cancel_order'        => actionCancelOrder($pdo, $uid),
    // Admin
    'admin_get_products'   => adminGetProducts($pdo),
    'admin_save_product'   => adminSaveProduct($pdo),
    'admin_delete_product' => adminDeleteProduct($pdo),
    'admin_get_orders'     => adminGetOrders($pdo),
    'admin_update_order'   => adminUpdateOrder($pdo),
    'admin_verify_payment' => adminVerifyPayment($pdo),
    'admin_get_proof'      => adminGetProof($pdo),
    default => jsonErr('Unknown action.'),
};

// ══════════════════════════════════════════════════════════
//  MEMBER ACTIONS
// ══════════════════════════════════════════════════════════

function actionGetProducts(PDO $pdo): never {
    $cat    = clean((string)($_GET['category'] ?? ''));
    $search = clean((string)($_GET['search'] ?? ''));
    $where  = ['p.is_active = 1'];
    $params = [];
    if ($cat !== '')    { $where[] = 'p.category = ?'; $params[] = $cat; }
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)';
        $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    $stmt = $pdo->prepare('SELECT p.id,p.name,p.description,p.category,p.price,
                                 COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = p.id), 0) AS stock,
                                 p.image FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id');
    $stmt->execute($params);
    $cats = $pdo->query('SELECT DISTINCT category FROM products WHERE is_active=1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    jsonOk(['products' => $stmt->fetchAll(), 'categories' => $cats]);
}

function actionGetCart(PDO $pdo, int $uid): never {
    requireMember();
    $stmt = $pdo->prepare(
        'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.price,
                COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = p.id), 0) AS stock, p.image
         FROM cart c JOIN products p ON p.id=c.product_id
         WHERE c.user_id=? AND p.is_active=1 ORDER BY c.added_at'
    );
    $stmt->execute([$uid]);
    $items = $stmt->fetchAll();
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
    jsonOk(['items' => $items, 'total' => $total, 'count' => count($items)]);
}

function actionAddToCart(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    if ($pid <= 0) jsonErr('Invalid product.');
    $p = $pdo->prepare('SELECT id, COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = products.id), 0) AS stock FROM products WHERE id=? AND is_active=1');
    $p->execute([$pid]);
    $prod = $p->fetch();
    if (!$prod) jsonErr('Product not found.');
    $ex = $pdo->prepare('SELECT quantity FROM cart WHERE user_id=? AND product_id=?');
    $ex->execute([$uid, $pid]);
    $cur = (int)($ex->fetchColumn() ?: 0);
    if ($cur + $qty > $prod['stock']) jsonErr("Only {$prod['stock']} units in stock (you have {$cur} in cart).");
    $pdo->prepare('INSERT INTO cart (user_id,product_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?')
        ->execute([$uid, $pid, $qty, $qty]);
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM cart WHERE user_id=?');
    $cnt->execute([$uid]);
    jsonOk(['message' => 'Added to cart!', 'cart_count' => (int)$cnt->fetchColumn()]);
}

function actionUpdateCart(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $qty    = (int)($_POST['quantity'] ?? 0);
    if ($cartId <= 0) jsonErr('Invalid cart item.');
    if ($qty <= 0) {
        $pdo->prepare('DELETE FROM cart WHERE id=? AND user_id=?')->execute([$cartId, $uid]);
        jsonOk(['message' => 'Item removed.']);
    }
    $s = $pdo->prepare('SELECT COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = p.id), 0) AS stock FROM cart c JOIN products p ON p.id=c.product_id WHERE c.id=? AND c.user_id=?');
    $s->execute([$cartId, $uid]);
    $row = $s->fetch();
    if (!$row) jsonErr('Cart item not found.');
    if ($qty > $row['stock']) jsonErr("Only {$row['stock']} units in stock.");
    $pdo->prepare('UPDATE cart SET quantity=? WHERE id=? AND user_id=?')->execute([$qty, $cartId, $uid]);
    jsonOk(['message' => 'Cart updated.']);
}

function actionRemoveFromCart(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    $pdo->prepare('DELETE FROM cart WHERE id=? AND user_id=?')->execute([(int)($_POST['cart_id'] ?? 0), $uid]);
    jsonOk(['message' => 'Item removed.']);
}

// ── get_checkout_data ─────────────────────────────────────
function actionGetCheckoutData(PDO $pdo, int $uid): never {
    requireMember();
    $stmt = $pdo->prepare(
        'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.price,
                COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = p.id), 0) AS stock, p.image
         FROM cart c JOIN products p ON p.id=c.product_id
         WHERE c.user_id=? AND p.is_active=1 ORDER BY c.added_at'
    );
    $stmt->execute([$uid]);
    $items = $stmt->fetchAll();
    $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
    $branches = $pdo->query('SELECT id,name,address,city FROM branches WHERE is_active=1 ORDER BY id')->fetchAll();
    $user = $pdo->prepare('SELECT first_name,last_name,email,phone FROM users WHERE id=?');
    $user->execute([$uid]);
    $u = $user->fetch() ?: [];
    jsonOk([
        'items'    => $items,
        'subtotal' => $subtotal,
        'branches' => $branches,
        'user'     => $u,
    ]);
}

// ── upload_proof ──────────────────────────────────────────
function actionUploadProof(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    if (empty($_FILES['proof']['tmp_name'])) jsonErr('No file uploaded.');
    $file  = $_FILES['proof'];
    $info  = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!$info || !in_array($info['mime'], $allowed, true)) jsonErr('Invalid image type.');
    if ($file['size'] > 8 * 1024 * 1024) jsonErr('File exceeds 8MB.');
    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$info['mime']];
    $dir  = __DIR__ . '/../uploads/proof/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'proof_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) jsonErr('Upload failed.');
    jsonOk(['path' => 'uploads/proof/' . $name]);
}

// ── checkout ─────────────────────────────────────────────
function actionCheckout(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();

    $token = (string)($_POST['checkout_token'] ?? '');
    if ($token && isset($_SESSION['last_checkout_token']) && $_SESSION['last_checkout_token'] === $token) {
        jsonErr('Duplicate submission. Your order was already placed.');
    }

    $fulfillment = clean($_POST['fulfillment_method'] ?? '');
    if (!in_array($fulfillment, ['delivery', 'pickup'], true)) jsonErr('Invalid fulfillment method.');

    $payMethod = clean($_POST['payment_method'] ?? '');
    $validPay  = ['gcash','maya','bank_transfer','cash_on_pickup','cash_on_delivery'];
    if (!in_array($payMethod, $validPay, true)) jsonErr('Invalid payment method.');
    if ($payMethod === 'cash_on_pickup' && $fulfillment !== 'pickup') jsonErr('Cash on Pickup is only available for Branch Pick-Up orders.');
    if ($payMethod === 'cash_on_delivery' && $fulfillment !== 'delivery') jsonErr('Cash on Delivery is only available for Delivery orders.');

    $deliveryAddr     = null;
    $shippingProvider = null;
    $pickupBranch     = null;
    $pickupDate       = null;
    $pickupTime       = null;
    $deliveryFeeAmt   = 0.0;
    $recipientName    = clean($_POST['recipient_name']    ?? '');
    $recipientContact = clean($_POST['recipient_contact'] ?? '');
    $recipientEmail   = clean($_POST['recipient_email']   ?? '');

    if ($fulfillment === 'delivery') {
        $shippingProvider = clean($_POST['shipping_provider'] ?? '');
        $validShipping = ['jnt','flash','lalamove','grab','standard'];
        if (!in_array($shippingProvider, $validShipping, true)) jsonErr('Please select a valid shipping option.');

        $region   = clean($_POST['region']   ?? '');
        $province = clean($_POST['province'] ?? '');
        $city     = clean($_POST['city']     ?? '');
        $barangay = clean($_POST['barangay'] ?? '');
        $street   = clean($_POST['street']   ?? '');
        $zip      = clean($_POST['zip']      ?? '');
        $landmark = clean($_POST['landmark'] ?? '');
        $notes    = clean($_POST['order_notes'] ?? '');
        foreach (['recipient_name'=>$recipientName,'recipient_contact'=>$recipientContact,
                  'recipient_email'=>$recipientEmail,'region'=>$region,'city'=>$city,
                  'barangay'=>$barangay,'street'=>$street,'zip'=>$zip] as $f => $v) {
            if ($v === '') jsonErr("Field '$f' is required.");
        }
        $deliveryFeeAmt = deliveryFee($region);
        $deliveryAddr   = json_encode(compact('region','province','city','barangay','street','zip','landmark','notes'), JSON_UNESCAPED_UNICODE);
    } else {
        $pickupBranch = (int)($_POST['pickup_branch_id'] ?? 0);
        $pickupDate   = clean($_POST['pickup_date'] ?? '');
        $pickupTime   = clean($_POST['pickup_time'] ?? '');
        if ($pickupBranch <= 0) jsonErr('Please select a branch.');
        if ($pickupDate === '') jsonErr('Please select a pickup date.');
        if ($pickupTime === '') jsonErr('Please select a pickup time.');
    }

    $proofPath = clean($_POST['proof_path'] ?? '');
    $targetBranchId = ($fulfillment === 'pickup') ? $pickupBranch : 1;

    $cartStmt = $pdo->prepare(
        'SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price,
                COALESCE((SELECT stock FROM product_stocks WHERE product_id = p.id AND branch_id = ?), 0) AS stock
         FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=? AND p.is_active=1'
    );
    $cartStmt->execute([$targetBranchId, $uid]);
    $items = $cartStmt->fetchAll();
    if (empty($items)) jsonErr('Your cart is empty.');

    foreach ($items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $branchWord = ($fulfillment === 'pickup') ? 'at the selected branch' : 'at the main branch';
            jsonErr("'{$item['name']}' only has {$item['stock']} units left {$branchWord}.");
        }
    }

    $subtotal    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
    $totalAmount = $subtotal + $deliveryFeeAmt;

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO orders
             (user_id, total_amount, status, fulfillment_method, delivery_fee, shipping_provider, delivery_address,
              pickup_branch_id, pickup_date, pickup_time, payment_method, payment_status,
              proof_of_payment, order_notes, recipient_name, recipient_contact, recipient_email)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $uid, $totalAmount, 'pending', $fulfillment, $deliveryFeeAmt, $shippingProvider, $deliveryAddr,
            $pickupBranch ?: null, $pickupDate ?: null, $pickupTime ?: null,
            $payMethod, 'pending',
            $proofPath ?: null,
            clean($_POST['order_notes'] ?? ''),
            $recipientName, $recipientContact, $recipientEmail,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $insItem   = $pdo->prepare('INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)');
        $deduStock = $pdo->prepare('UPDATE product_stocks SET stock=stock-? WHERE product_id=? AND branch_id=? AND stock>=?');
        foreach ($items as $item) {
            $insItem->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            $deduStock->execute([$item['quantity'], $item['product_id'], $targetBranchId, $item['quantity']]);
            if ($deduStock->rowCount() === 0) throw new RuntimeException("Insufficient stock for '{$item['name']}'.");
        }

        $pdo->prepare('DELETE FROM cart WHERE user_id=?')->execute([$uid]);
        $pdo->commit();
        if ($token) $_SESSION['last_checkout_token'] = $token;
        jsonOk(['message' => 'Order placed!', 'order_id' => $orderId]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonErr($e->getMessage() ?: 'Checkout failed.');
    }
}

// ── get_orders ────────────────────────────────────────────
function actionGetOrders(PDO $pdo, int $uid): never {
    requireMember();
    $stmt = $pdo->prepare(
        'SELECT o.id, o.total_amount, o.delivery_fee, o.status, o.fulfillment_method,
                o.shipping_provider, o.payment_method, o.payment_status,
                o.pickup_date, o.pickup_time, o.delivery_address,
                o.recipient_name, o.cancel_reason, o.created_at,
                b.name AS branch_name, b.address AS branch_address,
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS reviewed,
                COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN branches b ON b.id=o.pickup_branch_id
         LEFT JOIN order_reviews r ON r.order_id=o.id AND r.user_id=o.user_id
         JOIN order_items oi ON oi.order_id=o.id
         WHERE o.user_id=? GROUP BY o.id ORDER BY o.created_at DESC'
    );
    $stmt->execute([$uid]);
    $orders = $stmt->fetchAll();
    $details = [];
    if ($orders) {
        $ids  = implode(',', array_map('intval', array_column($orders, 'id')));
        $rows = $pdo->query("SELECT oi.order_id,oi.quantity,oi.price,p.name,p.image FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ids)")->fetchAll();
        foreach ($rows as $r) $details[$r['order_id']][] = $r;
    }
    jsonOk(['orders' => $orders, 'details' => $details]);
}

function actionSubmitOrderReview(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $body    = clean((string)($_POST['body'] ?? ''));

    if ($orderId <= 0) jsonErr('Invalid order.');
    if ($rating < 1 || $rating > 5) jsonErr('Please select a rating from 1 to 5.');
    if ($body === '') jsonErr('Please write your review before submitting.');

    $stmt = $pdo->prepare('SELECT id, status FROM orders WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$orderId, $uid]);
    $order = $stmt->fetch();
    if (!$order) jsonErr('Order not found.');
    if ($order['status'] !== 'completed') jsonErr('Only completed orders can be reviewed.');

    try {
        $pdo->prepare(
            'INSERT INTO order_reviews (order_id, user_id, rating, body, is_visible, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())'
        )->execute([$orderId, $uid, $rating, $body]);
    } catch (Throwable $e) {
        jsonErr('This order has already been reviewed.');
    }

    jsonOk(['message' => 'Thank you! Your order review has been submitted.']);
}

// ══════════════════════════════════════════════════════════
//  ADMIN ACTIONS
// ══════════════════════════════════════════════════════════

function actionMarkOrderReceived(PDO $pdo, int $uid): never {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId <= 0) jsonErr('Invalid order ID.');

    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $uid]);
    $order = $stmt->fetch();

    if (!$order) jsonErr('Order not found.');

    $receivable = ['processing','out_for_delivery','ready_for_pickup','delivered','picked_up'];
    if (!in_array($order['status'], $receivable, true)) {
        jsonErr('This order cannot be marked as received in its current state.');
    }

    $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ?")
        ->execute([$orderId]);

    jsonOk(['message' => 'Order #' . $orderId . ' marked as completed. Thank you!']);
}

// ── cancel_order ──────────────────────────────────────────
function actionCancelOrder(PDO $pdo, int $uid): never {
    requireMember(); verifyCsrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId <= 0) jsonErr('Invalid order ID.');

    $stmt = $pdo->prepare('SELECT id, status, fulfillment_method, pickup_branch_id FROM orders WHERE id = ? AND user_id = ?');
    $stmt->execute([$orderId, $uid]);
    $order = $stmt->fetch();

    if (!$order) jsonErr('Order not found.');
    if ($order['status'] !== 'pending') jsonErr('Only pending orders can be cancelled.');

    $targetBranchId = ($order['fulfillment_method'] === 'pickup') ? (int)$order['pickup_branch_id'] : 1;

    $pdo->beginTransaction();
    try {
        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([$orderId]);
        $restoreStmt = $pdo->prepare('INSERT INTO product_stocks (product_id, branch_id, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)');
        foreach ($items->fetchAll() as $item) {
            $restoreStmt->execute([$item['product_id'], $targetBranchId, $item['quantity']]);
        }

        $reason = trim((string)($_POST['cancel_reason'] ?? ''));
        $pdo->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$reason ?: null, $orderId]);

        $pdo->commit();
        jsonOk(['message' => 'Order #' . $orderId . ' has been cancelled.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonErr($e->getMessage() ?: 'Failed to cancel order.');
    }
}

function adminGetProducts(PDO $pdo): never {
    requireAdmin();
    $products = $pdo->query('SELECT id,name,description,category,price,image,is_active,created_at FROM products ORDER BY id DESC')->fetchAll();

    $stocksRaw = $pdo->query('SELECT product_id, branch_id, stock FROM product_stocks')->fetchAll();
    $stocks = [];
    foreach ($stocksRaw as $row) {
        $stocks[(int)$row['product_id']][(int)$row['branch_id']] = (int)$row['stock'];
    }

    foreach ($products as &$p) {
        $p['stocks'] = $stocks[(int)$p['id']] ?? [];
        $p['stock'] = array_sum($p['stocks']);
    }
    unset($p);

    $cats = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    jsonOk(['products' => $products, 'categories' => $cats]);
}

function adminSaveProduct(PDO $pdo): never {
    requireAdmin(); verifyCsrf();
    $id    = (int)($_POST['product_id'] ?? 0);
    $name  = clean($_POST['name'] ?? '');
    $desc  = clean($_POST['description'] ?? '');
    $cat   = clean($_POST['category'] ?? 'Supplement');
    $price = max(0, (float)($_POST['price'] ?? 0));
    $stocks = $_POST['stocks'] ?? [];
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') jsonErr('Product name is required.');
    if ($price <= 0)  jsonErr('Price must be greater than 0.');
    $imagePath = clean($_POST['existing_image'] ?? '');
    if (!empty($_FILES['image']['tmp_name'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], $allowed, true)) jsonErr('Invalid image type.');
        if ($file['size'] > 5 * 1024 * 1024) jsonErr('Image exceeds 5MB.');
        $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$info['mime']];
        $dir  = __DIR__ . '/../uploads/products/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) jsonErr('Image upload failed.');
        $imagePath = 'uploads/products/' . $fname;
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $pdo->prepare('UPDATE products SET name=?,description=?,category=?,price=?,image=?,is_active=? WHERE id=?')
                ->execute([$name,$desc,$cat,$price,$imagePath,$active,$id]);
        } else {
            $pdo->prepare('INSERT INTO products (name,description,category,price,image,is_active) VALUES (?,?,?,?,?,?)')
                ->execute([$name,$desc,$cat,$price,$imagePath,$active]);
            $id = (int)$pdo->lastInsertId();
        }

        $stmtStock = $pdo->prepare('INSERT INTO product_stocks (product_id, branch_id, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)');
        $activeBranches = $pdo->query('SELECT id FROM branches WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($activeBranches as $bid) {
            $stk = max(0, (int)($stocks[$bid] ?? 0));
            $stmtStock->execute([$id, $bid, $stk]);
        }

        $pdo->commit();
        jsonOk(['message' => $id > 0 ? 'Product updated.' : 'Product added.', 'product_id' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonErr($e->getMessage() ?: 'Save failed.');
    }
}

function adminDeleteProduct(PDO $pdo): never {
    requireAdmin(); verifyCsrf();
    $id = (int)($_POST['product_id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid product ID.');
    $pdo->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
    jsonOk(['message' => 'Product removed from shop.']);
}

function adminGetOrders(PDO $pdo): never {
    requireAdmin();
    $stmt = $pdo->query(
        'SELECT o.id, o.total_amount, o.delivery_fee, o.status, o.fulfillment_method,
                o.payment_method, o.payment_status, o.proof_of_payment,
                o.shipping_provider, o.delivery_address, o.pickup_date, o.pickup_time,
                o.recipient_name, o.recipient_contact, o.recipient_email,
                o.cancel_reason, o.created_at,
                CONCAT(u.first_name," ",u.last_name) AS customer_name,
                u.email AS customer_email,
                b.name AS branch_name, b.address AS branch_address,
                COUNT(oi.id) AS item_count
         FROM orders o
         JOIN users u ON u.id=o.user_id
         JOIN order_items oi ON oi.order_id=o.id
         LEFT JOIN branches b ON b.id=o.pickup_branch_id
         GROUP BY o.id ORDER BY o.created_at DESC LIMIT 300'
    );
    $orders = $stmt->fetchAll();
    $details = [];
    if ($orders) {
        $ids  = implode(',', array_map('intval', array_column($orders, 'id')));
        $rows = $pdo->query("SELECT oi.order_id,oi.quantity,oi.price,p.name,p.image FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ids)")->fetchAll();
        foreach ($rows as $r) $details[$r['order_id']][] = $r;
    }
    jsonOk(['orders' => $orders, 'details' => $details]);
}

function adminUpdateOrder(PDO $pdo): never {
    requireAdmin(); verifyCsrf();
    $id     = (int)($_POST['order_id'] ?? 0);
    $status = clean($_POST['status'] ?? '');
    $allowed = ['processing','out_for_delivery','delivered','ready_for_pickup','picked_up','cancelled'];
    if ($id <= 0 || !in_array($status, $allowed, true)) jsonErr('Invalid request.');
    $cur = $pdo->prepare('SELECT status FROM orders WHERE id=?');
    $cur->execute([$id]);
    $currentStatus = (string)($cur->fetchColumn() ?: '');
    if ($currentStatus === 'completed') jsonErr('Completed orders cannot be changed by admin.');
    if ($currentStatus === 'pending' && $status !== 'cancelled') jsonErr('Pending orders must be accepted or rejected first.');
    $pdo->prepare('UPDATE orders SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
    jsonOk(['message' => 'Order status updated to ' . str_replace('_', ' ', ucfirst($status)) . '.']);
}

function adminVerifyPayment(PDO $pdo): never {
    requireAdmin(); verifyCsrf();
    $id     = (int)($_POST['order_id'] ?? 0);
    $action = clean($_POST['verify_action'] ?? '');
    if ($id <= 0 || !in_array($action, ['approve','reject'], true)) jsonErr('Invalid request.');
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status=IF(payment_method='cod', 'pending', 'paid'), status='processing', updated_at=NOW() WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) jsonErr('Only pending orders can be accepted.');
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status='rejected', status='cancelled', cancel_reason=COALESCE(NULLIF(cancel_reason, ''), 'Rejected by admin'), updated_at=NOW() WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) jsonErr('Only pending orders can be rejected.');
    }
    jsonOk(['message' => 'Order ' . ($action === 'approve' ? 'accepted and moved to processing' : 'rejected') . '.']);
}

function adminGetProof(PDO $pdo): never {
    requireAdmin();
    $id   = (int)($_GET['order_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT proof_of_payment FROM orders WHERE id=?');
    $stmt->execute([$id]);
    $path = $stmt->fetchColumn();
    if (!$path) jsonErr('No proof uploaded.');
    jsonOk(['path' => $path]);
}
