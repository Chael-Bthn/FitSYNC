<?php
// ============================================================
//  FitSync — Checkout & Order Summary
//  /checkout.php
// ============================================================
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config/auth_guard.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'member';

require_once __DIR__ . '/config/db.php';
$pdo = db();

// Fetch user data
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();
$fullName = trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? ''));
$initials = strtoupper(substr($userRow['first_name'] ?? '', 0, 1) . substr($userRow['last_name'] ?? '', 0, 1));

// Check active membership
require_once __DIR__ . '/includes/membership_helpers.php';
$mem = getLatestMembership($pdo, $userId);
$activeMembership = getActiveMembership($pdo, $userId);
$hasActiveMembership = (bool) $activeMembership;
if ($activeMembership) {
    $mem = $activeMembership;
}

// Generate CSRF token
$csrf = $_SESSION['csrf_token'] ?? '';
if (empty($csrf)) {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf;
}

// Check if we are viewing a specific order
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId > 0) {
    header('Location: profile.php#orders');
    exit;
}
$viewOrder = null;
$viewOrderItems = [];
$errorMsg = '';

if (false) {
    // Fetch order details
    $orderQuery = $pdo->prepare(
        'SELECT o.*, b.name AS branch_name, b.address AS branch_address
         FROM orders o
         LEFT JOIN branches b ON b.id=o.pickup_branch_id
         WHERE o.id = ?'
    );
    $orderQuery->execute([$orderId]);
    $viewOrder = $orderQuery->fetch();
    
    if ($viewOrder) {
        // Enforce ownership: members can only view their own orders
        if ($userRole === 'member' && (int)$viewOrder['user_id'] !== $userId) {
            $viewOrder = null;
            $errorMsg = 'Access Denied. You do not have permission to view this order.';
        } else {
            // Fetch order items
            $itemsQuery = $pdo->prepare(
                'SELECT oi.quantity, oi.price, p.name, p.image 
                 FROM order_items oi 
                 JOIN products p ON p.id=oi.product_id 
                 WHERE oi.order_id = ?'
            );
            $itemsQuery->execute([$orderId]);
            $viewOrderItems = $itemsQuery->fetchAll();
        }
    } else {
        $errorMsg = 'Order not found.';
    }
}

// Fetch active cart count for dynamic badge
$cartCountStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?');
$cartCountStmt->execute([$userId]);
$shopCartCount = (int)$cartCountStmt->fetchColumn();

// Fetch checkout data if we are going to do checkout
$cartItems = [];
$subtotal = 0;
$branches = [];
$showCheckoutForm = false;

if (!$viewOrder && $errorMsg === '' && $shopCartCount > 0) {
    $showCheckoutForm = true;
    
    // Fetch cart items
    $cartStmt = $pdo->prepare(
        'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.price,
                COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_id = p.id), 0) AS stock, p.image
         FROM cart c JOIN products p ON p.id=c.product_id
         WHERE c.user_id=? AND p.is_active=1 ORDER BY c.added_at'
    );
    $cartStmt->execute([$userId]);
    $cartItems = $cartStmt->fetchAll();
    
    $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
    
    // Fetch branches
    $branches = $pdo->query('SELECT id,name,address,city FROM branches WHERE is_active=1 ORDER BY id')->fetchAll();

    // Fetch branch stocks for the cart items
    $cartProductIds = array_column($cartItems, 'product_id');
    $branchStocks = [];
    if (!empty($cartProductIds)) {
        $placeholders = implode(',', array_fill(0, count($cartProductIds), '?'));
        $stocksRaw = $pdo->prepare("SELECT product_id, branch_id, stock FROM product_stocks WHERE product_id IN ($placeholders)");
        $stocksRaw->execute($cartProductIds);
        foreach ($stocksRaw->fetchAll() as $row) {
            $branchStocks[(int)$row['product_id']][(int)$row['branch_id']] = (int)$row['stock'];
        }
    }
}

// Helper functions for labels
function payLabel(string $method): string {
    return [
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'bank_transfer' => 'Bank Transfer',
        'cash_on_pickup' => 'Cash on Pickup',
        'cash_on_delivery' => 'Cash on Delivery',
        'cash' => 'Cash'
    ][$method] ?? $method;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — Checkout Summary</title>
    <link rel="icon" href="assets/FitSYNC Emblem Light.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <script>
        /* Apply saved theme & fix logos before first paint */
        (function() {
            var saved = localStorage.getItem('fs-theme');
            if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
            document.addEventListener('DOMContentLoaded', function() {
                var isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
                document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(function(logo) {
                    logo.src = isLight ? logo.dataset.logoLight : logo.dataset.logoDark;
                });
            });
        })();
    </script>
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204, 26, 26, .28);
            --fs-red-soft: rgba(204, 26, 26, .12);
            --sidebar-w: 270px;
            --sidebar-bg: #0d0d0d;
            --sidebar-border: rgba(255, 255, 255, .07);
            --card-bg: #111111;
            --card-border: rgba(255, 255, 255, .07);
            --page-bg: #0a0a0a;
            --input-bg: rgba(255, 255, 255, .05);
            --input-border: rgba(255, 255, 255, .08);
            --input-color: #fff;
            --input-ph: rgba(255, 255, 255, .3);
            --text-primary: #fff;
            --text-muted: rgba(255, 255, 255, .45);
            --text-dimmed: rgba(255, 255, 255, .25);
            --row-hover: rgba(255, 255, 255, .025);
            --th-bg: rgba(255, 255, 255, .03);
            --td-border: rgba(255, 255, 255, .04);
        }

        [data-bs-theme="light"] {
            --sidebar-bg: #fff;
            --sidebar-border: rgba(0, 0, 0, .08);
            --card-bg: #fff;
            --card-border: rgba(0, 0, 0, .07);
            --page-bg: #f4f2ef;
            --input-bg: rgba(0, 0, 0, .04);
            --input-border: rgba(0, 0, 0, .1);
            --input-color: #111;
            --input-ph: rgba(0, 0, 0, .3);
            --text-primary: #111;
            --text-muted: rgba(0, 0, 0, .45);
            --text-dimmed: rgba(0, 0, 0, .25);
            --row-hover: rgba(0, 0, 0, .02);
            --th-bg: rgba(0, 0, 0, .03);
            --td-border: rgba(0, 0, 0, .05);
        }

        * {
            font-family: 'Outfit', system-ui, sans-serif;
            box-sizing: border-box;
        }

        body {
            background: var(--page-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: background .25s;
        }

        /* ── NAVBAR ── */
        .checkout-navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--sidebar-bg);
            border-bottom: 1px solid var(--sidebar-border);
            padding: 0.85rem 2rem;
            backdrop-filter: blur(10px);
            transition: background .25s, border-color .25s;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: 1px;
            color: var(--text-primary);
        }

        .theme-pill {
            width: 44px;
            height: 24px;
            border-radius: 50px;
            border: 1px solid var(--sidebar-border);
            background: var(--input-bg);
            position: relative;
            cursor: pointer;
            transition: background .3s;
            padding: 0;
            flex-shrink: 0;
        }

        .theme-pill-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--fs-red);
            transition: transform .3s;
        }

        [data-bs-theme="light"] .theme-pill-knob {
            transform: translateX(20px);
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            min-height: calc(100vh - 60px);
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        @media (max-width: 767.98px) {
            .main-content {
                padding: 1.25rem 1rem 2.5rem;
            }
        }

        /* ── CARDS ── */
        .fs-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .fs-card-title {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.2px;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── FORM ELEMENTS ── */
        .fs-label {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-muted);
            margin-bottom: .4rem;
            display: block;
        }

        .fs-input, .fs-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-color);
            padding: .65rem 1rem;
            border-radius: 12px;
            width: 100%;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            font-size: .9rem;
        }

        .fs-input:focus, .fs-select:focus {
            border-color: var(--fs-red);
            box-shadow: 0 0 0 3px var(--fs-red-glow);
        }
        
        /* Toggle Grid Buttons (Fulfillment & Payment Methods) */
        .toggle-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .85rem;
            margin-bottom: 1.25rem;
        }

        .toggle-card {
            border: 2px solid var(--card-border);
            border-radius: 14px;
            padding: 1rem .75rem;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
            background: transparent;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .toggle-card:hover {
            border-color: rgba(204,26,26,.45);
            background: rgba(204,26,26,.04);
            transform: translateY(-2px);
        }

        .toggle-card.active {
            border-color: var(--fs-red);
            background: rgba(204,26,26,.08);
            color: var(--text-primary);
        }

        .toggle-card i {
            font-size: 1.8rem;
            margin-bottom: .4rem;
            color: var(--text-muted);
            transition: color .2s;
        }

        .toggle-card.active i {
            color: var(--fs-red);
        }

        .toggle-card-title {
            font-size: .85rem;
            font-weight: 700;
        }

        .toggle-card-sub {
            font-size: .68rem;
            opacity: .8;
            margin-top: .15rem;
        }

        /* Payment Method Grid (Renew layout styling) */
        .payment-method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: .65rem;
            margin-bottom: 1.25rem;
        }

        .payment-method-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: 1.15rem .75rem 0.95rem;
            border: 2px solid var(--card-border);
            border-radius: 14px;
            background: var(--input-bg);
            cursor: pointer;
            transition: all .25s ease;
            position: relative;
            outline: none;
        }

        .payment-method-btn:hover {
            border-color: rgba(204, 26, 26, .5);
            background: rgba(204, 26, 26, .04);
        }

        .payment-method-btn.active {
            border-color: var(--fs-red);
            background: rgba(204, 26, 26, .1);
        }

        .payment-method-icon {
            font-size: 1.7rem;
            color: var(--fs-red);
        }

        .payment-method-label {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--text-primary);
            text-align: center;
            line-height: 1.2;
        }

        .payment-method-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--fs-red);
            border: 2px solid var(--card-bg);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: .55rem;
            color: #fff;
        }

        .payment-method-btn.active .payment-method-badge {
            display: flex;
        }

        /* Payment info blocks */
        .pay-info-block {
            display: none;
            animation: pmSlideDown .25s ease;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 14px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .pay-info-block.active {
            display: block;
        }

        @keyframes pmSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .pay-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .6rem 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
            font-size: .82rem;
        }

        .pay-detail-row:last-child {
            border-bottom: none;
        }

        /* ── UPLOAD ZONE ── */
        .proof-zone {
            border: 2px dashed var(--card-border);
            border-radius: 14px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            position: relative;
            background: var(--input-bg);
        }

        .proof-zone:hover, .proof-zone.dragover {
            border-color: var(--fs-red);
            background: rgba(204,26,26,.04);
        }

        .proof-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* ── SUMMARY / RECEIPT ROW ── */
        .sum-block {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .sum-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .45rem 0;
            font-size: .85rem;
        }

        .sum-row.total {
            border-top: 1px solid var(--input-border);
            margin-top: .5rem;
            padding-top: .75rem;
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--fs-red);
        }
        
        .co-sum-title {
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--fs-red);
            margin-bottom: .6rem;
            border-bottom: 1px solid rgba(255,255,255,.05);
            padding-bottom: .25rem;
        }

        /* ── ITEM ROWS ── */
        .item-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 0;
            border-bottom: 1px solid var(--card-border);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .item-thumb-ph {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: rgba(204,26,26,.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* ── BUTTONS ── */
        .btn-fs {
            border-radius: 12px;
            padding: .65rem 1.5rem;
            font-size: .9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #cc1a1a, #ff4040);
            color: #fff !important;
            border: none;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            text-decoration: none;
        }

        .btn-fs:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(204, 26, 26, 0.4);
        }

        .btn-fs:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-outline-fs {
            border-radius: 12px;
            padding: .65rem 1.5rem;
            font-size: .9rem;
            font-weight: 700;
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--card-border);
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            text-decoration: none;
        }

        .btn-outline-fs:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--text-primary);
        }

        /* ── BADGES ── */
        .fs-badge {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: .25rem .6rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }

        .fs-badge-pending {
            background: rgba(255,193,7,.15);
            color: #ffc107;
        }

        .fs-badge-processing {
            background: rgba(74,158,255,.15);
            color: #4a9eff;
        }

        .fs-badge-delivered {
            background: rgba(46,204,113,.15);
            color: #2ecc71;
        }

        .fs-badge-cancelled {
            background: rgba(255,80,80,.15);
            color: #ff6b6b;
        }

        .fs-badge-shipped {
            background: rgba(111,66,193,.15);
            color: #a066f5;
        }

        .fs-badge-ready_for_pickup {
            background: rgba(74,158,255,.15);
            color: #4a9eff;
        }

        .fs-badge-picked_up {
            background: rgba(46,204,113,.15);
            color: #2ecc71;
        }

        .fs-badge-out_for_delivery {
            background: rgba(111,66,193,.15);
            color: #a066f5;
        }

        .fs-badge-completed {
            background: rgba(46,204,113,.18);
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,.35);
        }
        
        .hamburger {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            transition: all .2s;
            padding: 0;
        }

        .hamburger:hover {
            color: var(--text-primary);
            background: rgba(255,255,255,.04);
        }

        /* Table wrap */
        .fs-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--card-border);
            border-radius: 12px;
        }

        .fs-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .fs-table th {
            background: var(--th-bg);
            color: var(--text-muted);
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: .9rem 1rem;
            border-bottom: 1px solid var(--card-border);
        }

        .fs-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--td-border);
            font-size: .88rem;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .fs-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>

<body>

    <!-- ════════ TOP NAVBAR ════════ -->
    <header class="checkout-navbar">
        <div class="nav-container">
            <a class="nav-brand" href="profile.php">
                <img src="assets/FitSYNC Emblem Light.svg" alt="FitSync" width="28" height="28" data-logo-dark="assets/FitSYNC Emblem Light.svg" data-logo-light="assets/FitSYNC Emblem.svg" />
                <span style="font-weight:900;"><span style="color:var(--text-primary)">FIT</span><span style="color:var(--fs-red)">SYNC</span> SHOP</span>
            </a>
            <div class="nav-actions">
                <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle Theme">
                    <div class="theme-pill-knob"></div>
                </button>
                <a href="profile.php" class="btn-outline-fs px-3 py-1.5" style="border-radius:10px;text-decoration:none;font-size:.85rem"><i class="ti ti-arrow-left"></i> Portal</a>
            </div>
        </div>
    </header>

    <!-- ════════ MAIN CONTENT ════════ -->
    <main class="main-content">



        <?php if ($errorMsg !== ''): ?>
            <!-- Error message state -->
            <div class="fs-card text-center" style="padding: 3rem 1.5rem;">
                <i class="ti ti-alert-triangle" style="font-size:3.5rem;color:var(--fs-red);display:block;margin-bottom:1.25rem"></i>
                <h3 style="font-weight:800;margin-bottom:.5rem"><?= htmlspecialchars($errorMsg) ?></h3>
                <p style="color:var(--text-muted);font-size:.9rem;max-width:400px;margin:0 auto 1.5rem">There was an issue processing your request. It may be due to insufficient permissions or a missing resource.</p>
                <a href="checkout.php" class="btn-fs"><i class="ti ti-arrow-left"></i> View Order History</a>
            </div>

        <?php elseif ($viewOrder): ?>
            <!-- CASE A: ORDER RECEIPT / INVOICE VIEW -->
            <div style="max-width: 850px; margin: 0 auto;">
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h2 style="font-weight:900;letter-spacing:-0.5px;margin:0">Order Summary</h2>
                        <span style="font-size:.8rem;color:var(--text-muted)">ID: #<?= $viewOrder['id'] ?> &middot; Placed on <?= date('M j, Y g:i A', strtotime($viewOrder['created_at'])) ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="checkout.php" class="btn-outline-fs px-3 py-2"><i class="ti ti-list"></i> History</a>
                        <a href="profile.php#shop" class="btn-fs px-3 py-2"><i class="ti ti-shopping-bag"></i> Shop</a>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Details column -->
                    <div class="col-lg-7">
                        <!-- Status Card -->
                        <div class="fs-card">
                            <div class="fs-card-title"><i class="ti ti-info-circle"></i> Status & Info</div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="fs-badge fs-badge-<?= $viewOrder['status'] ?>"><i class="ti ti-package"></i> Order: <?= str_replace('_', ' ', ucfirst($viewOrder['status'])) ?></span>
                                <span class="fs-badge" style="background:rgba(0,0,0,0.18);color:<?= payColor($viewOrder['payment_status']) ?>"><i class="ti ti-credit-card"></i> Payment: <?= ucfirst($viewOrder['payment_status']) ?></span>
                            </div>
                            <div class="pay-detail-row">
                                <span style="color:var(--text-muted)">Fulfillment Method</span>
                                <span style="font-weight:700"><?= $viewOrder['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Branch Pick-Up' ?></span>
                            </div>
                            <div class="pay-detail-row">
                                <span style="color:var(--text-muted)">Payment Mode</span>
                                <span style="font-weight:700"><?= payLabel($viewOrder['payment_method']) ?></span>
                            </div>
                            <?php if ($viewOrder['order_notes'] !== ''): ?>
                                <div class="pay-detail-row" style="flex-direction:column;align-items:flex-start;gap:.25rem">
                                    <span style="color:var(--text-muted)">Order Notes / Notes</span>
                                    <span style="font-style:italic;color:var(--text-primary)"><?= htmlspecialchars($viewOrder['order_notes']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Fulfillment Details Card -->
                        <div class="fs-card">
                            <?php if ($viewOrder['fulfillment_method'] === 'delivery'): ?>
                                <div class="fs-card-title"><i class="ti ti-truck"></i> Delivery Information</div>
                                <?php
                                $addr = ['region'=>'','province'=>'','city'=>'','barangay'=>'','street'=>'','zip'=>'','landmark'=>''];
                                try {
                                    if (!empty($viewOrder['delivery_address'])) {
                                        $parsed = json_decode($viewOrder['delivery_address'], true);
                                        if (is_array($parsed)) $addr = array_merge($addr, $parsed);
                                    }
                                } catch (Exception $e) {}
                                ?>
                                <div class="pay-detail-row"><span style="color:var(--text-muted)">Recipient Name</span><span style="font-weight:600"><?= htmlspecialchars($viewOrder['recipient_name'] ?: '-') ?></span></div>
                                <div class="pay-detail-row"><span style="color:var(--text-muted)">Contact Phone</span><span><?= htmlspecialchars($viewOrder['recipient_contact'] ?: '-') ?></span></div>
                                <div class="pay-detail-row"><span style="color:var(--text-muted)">Email Address</span><span><?= htmlspecialchars($viewOrder['recipient_email'] ?: '-') ?></span></div>
                                
                                <?php if ($addr['city'] === '-' || empty($addr['city'])): ?>
                                    <div class="pay-detail-row">
                                        <span style="color:var(--text-muted)">Delivery Address</span>
                                        <span style="text-align:right;max-width:280px;line-height:1.4"><?= htmlspecialchars($addr['street']) ?></span>
                                    </div>
                                    <div class="pay-detail-row">
                                        <span style="color:var(--text-muted)">Destination Region</span>
                                        <span style="text-align:right"><?= htmlspecialchars($addr['region']) === 'NCR' ? 'Metro Manila (NCR)' : 'Provincial' ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="pay-detail-row">
                                        <span style="color:var(--text-muted)">Street Address</span>
                                        <span style="text-align:right"><?= htmlspecialchars($addr['street']) ?>, <?= htmlspecialchars($addr['barangay']) ?></span>
                                    </div>
                                    <div class="pay-detail-row">
                                        <span style="color:var(--text-muted)">City & Region</span>
                                        <span style="text-align:right"><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['province'] ?: '') ?> <?= htmlspecialchars($addr['region']) ?> (<?= htmlspecialchars($addr['zip']) ?>)</span>
                                    </div>
                                    <?php if (!empty($addr['landmark'])): ?>
                                        <div class="pay-detail-row"><span style="color:var(--text-muted)">Landmark</span><span style="text-align:right"><?= htmlspecialchars($addr['landmark']) ?></span></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                        <?php else: ?>
                            <div class="fs-card-title"><i class="ti ti-building-store"></i> Branch Pick-Up details</div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Recipient Name</span><span style="font-weight:600"><?= htmlspecialchars($viewOrder['recipient_name'] ?: '-') ?></span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Contact Phone</span><span><?= htmlspecialchars($viewOrder['recipient_contact'] ?: '-') ?></span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Pick-up Location</span><span style="font-weight:600;text-align:right"><?= htmlspecialchars($viewOrder['branch_name'] ?: 'Default Branch') ?></span></div>
                            <?php if (!empty($viewOrder['branch_address'])): ?>
                                <div class="pay-detail-row"><span style="color:var(--text-muted)">Branch Address</span><span style="font-size:.78rem;text-align:right;max-width:260px;color:var(--text-muted)"><?= htmlspecialchars($viewOrder['branch_address']) ?></span></div>
                            <?php endif; ?>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Pickup Date</span><span style="font-weight:600"><?= date('l, M j, Y', strtotime($viewOrder['pickup_date'])) ?></span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Pickup Time Slot</span><span style="font-weight:600;color:var(--fs-red)"><?= htmlspecialchars($viewOrder['pickup_time']) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Details Card (Proof upload display) -->
                    <?php if ($viewOrder['payment_method'] !== 'cash_on_pickup' && $viewOrder['payment_method'] !== 'cash_on_delivery'): ?>
                        <div class="fs-card">
                            <div class="fs-card-title"><i class="ti ti-credit-card"></i> Payment Details</div>
                            <?php if ($viewOrder['proof_of_payment']): ?>
                                <span style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:.55rem">Uploaded Proof of Payment:</span>
                                <div class="text-center" style="background:rgba(0,0,0,0.2);padding:1rem;border-radius:12px;border:1px solid var(--card-border)">
                                    <img src="<?= htmlspecialchars($viewOrder['proof_of_payment']) ?>" alt="Proof of payment" style="max-width:100%;max-height:300px;border-radius:8px;object-fit:contain">
                                </div>
                            <?php else: ?>
                                <div class="text-center" style="padding:1.5rem 0;color:var(--text-muted)">
                                    <i class="ti ti-photo-off" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                                    <span style="font-size:.82rem">No proof of payment uploaded or verified yet.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Items & total column -->
                <div class="col-lg-5">
                    <div class="fs-card">
                        <div class="fs-card-title"><i class="ti ti-shopping-cart"></i> Order Items</div>
                        <div style="margin-bottom:1.25rem">
                            <?php foreach ($viewOrderItems as $it): ?>
                                <div class="item-row">
                                    <?php if ($it['image']): ?>
                                        <img src="<?= htmlspecialchars($it['image']) ?>" class="item-thumb" alt="">
                                    <?php else: ?>
                                        <div class="item-thumb-ph"><i class="ti ti-package"></i></div>
                                    <?php endif; ?>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:.85rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($it['name']) ?></div>
                                        <span style="font-size:.75rem;color:var(--text-muted)">&#8369;<?= number_format((float)$it['price'], 2) ?> each</span>
                                    </div>
                                    <div style="text-align:right">
                                        <div style="font-size:.85rem;font-weight:700">&#8369;<?= number_format((float)$it['price'] * $it['quantity'], 2) ?></div>
                                        <span style="font-size:.72rem;color:var(--text-muted)">Qty: <?= $it['quantity'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="sum-block">
                            <div class="co-sum-title">Pricing Details</div>
                            <?php
                            $sub = (float)($viewOrder['total_amount'] - $viewOrder['delivery_fee']);
                            ?>
                            <div class="sum-row">
                                <span style="color:var(--text-muted)">Subtotal</span>
                                <span style="font-weight:700">&#8369;<?= number_format($sub, 2) ?></span>
                            </div>
                            <div class="sum-row">
                                <span style="color:var(--text-muted)">Delivery Fee</span>
                                <span><?= $viewOrder['delivery_fee'] > 0 ? '&#8369;' . number_format((float)$viewOrder['delivery_fee'], 2) : 'FREE' ?></span>
                            </div>
                            <div class="sum-row total">
                                <span>Grand Total</span>
                                <span>&#8369;<?= number_format((float)$viewOrder['total_amount'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

        <?php elseif ($showCheckoutForm): ?>
            <!-- CASE B: CHECKOUT FORM -->
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h2 style="font-weight:900;letter-spacing:-0.5px;margin:0">Secure Checkout</h2>
                    <span style="font-size:.8rem;color:var(--text-muted)">Review and finalize your order items</span>
                </div>
                <button onclick="window.location.href='profile.php#shop'" class="btn-outline-fs px-3 py-2"><i class="ti ti-arrow-left"></i> Back to Shop</button>
            </div>

            <div class="row g-4">
                <!-- Form Inputs -->
                <div class="col-lg-7">
                    <!-- Step 1: Fulfillment -->
                    <div class="fs-card">
                        <div class="fs-card-title"><i class="ti ti-truck"></i> 1. Fulfillment Method</div>
                        <div class="toggle-grid">
                            <button type="button" class="toggle-card active" id="f-delivery-btn" onclick="selectFulfillment('delivery')">
                                <i class="ti ti-truck"></i>
                                <span class="toggle-card-title">Delivery</span>
                                <span class="toggle-card-sub">Ship to my address</span>
                            </button>
                            <button type="button" class="toggle-card" id="f-pickup-btn" onclick="selectFulfillment('pickup')">
                                <i class="ti ti-building-store"></i>
                                <span class="toggle-card-title">Branch Pick-Up</span>
                                <span class="toggle-card-sub">Collect at gym branch</span>
                            </button>
                        </div>

                        <!-- Recipient fields (shared) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="fs-label">Recipient Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="fs-input" id="co-fullname" placeholder="Juan Dela Cruz" value="<?= htmlspecialchars($fullName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="fs-label">Contact Mobile Phone <span class="text-danger">*</span></label>
                                <input type="text" class="fs-input" id="co-contact" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($userRow['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="fs-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="fs-input" id="co-email" placeholder="you@email.com" value="<?= htmlspecialchars($userRow['email'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Delivery Address form -->
                        <div id="co-delivery-fields">
                            <hr style="border-color:var(--card-border);margin:1.25rem 0">
                            <div class="fs-card-title" style="font-size:.9rem;margin-bottom:1rem"><i class="ti ti-map-pin"></i> Shipping Address</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="fs-label">Shipping Destination <span class="text-danger">*</span></label>
                                    <select class="fs-select" id="co-region" onchange="updateDeliveryFee()">
                                        <option value="">Select Destination</option>
                                        <option value="NCR">Metro Manila (NCR) — ₱80.00 delivery</option>
                                        <option value="Provincial">Outside Metro Manila (Provincial) — ₱150.00 delivery</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="fs-label">Shipping Option <span class="text-danger">*</span></label>
                                    <select class="fs-select" id="co-shipping-provider">
                                        <option value="">Select Courier</option>
                                        <option value="jnt">J&amp;T Express</option>
                                        <option value="flash">Flash Express</option>
                                        <option value="lalamove">Lalamove</option>
                                        <option value="grab">Grab Express</option>
                                        <option value="standard">FitSync Standard Delivery</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="fs-label">Full Shipping Address <span class="text-danger">*</span></label>
                                    <textarea class="fs-input" id="co-street" rows="3" placeholder="House No., Street name, Subdivision, Barangay, City, Province, Zip Code" style="resize:none"></textarea>
                                </div>
                                <!-- Hidden legacy fields for complete backward compatibility -->
                                <input type="hidden" id="co-province" value="-">
                                <input type="hidden" id="co-city" value="-">
                                <input type="hidden" id="co-barangay" value="-">
                                <input type="hidden" id="co-zip" value="-">
                                <input type="hidden" id="co-landmark" value="-">
                            </div>
                        </div>

                        <!-- Branch Pick-Up fields -->
                        <div id="co-pickup-fields" style="display:none">
                            <hr style="border-color:var(--card-border);margin:1.25rem 0">
                            <div class="fs-card-title" style="font-size:.9rem;margin-bottom:1rem"><i class="ti ti-building-store"></i> Gym Pick-Up Details</div>
                            <div class="mb-3">
                                <label class="fs-label">Select Gym Branch <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <?php foreach ($branches as $idx => $br): ?>
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 border rounded-3 text-start" style="cursor:pointer;background:var(--input-bg);border-color:var(--input-border)" onclick="selectBranch(<?= $br['id'] ?>, this)">
                                                <input type="radio" name="pickup_branch" id="br-radio-<?= $br['id'] ?>" value="<?= $br['id'] ?>" style="display:none">
                                                <div style="font-weight:700;font-size:.85rem;display:flex;align-items:center;gap:.35rem">
                                                    <i class="ti ti-building-store" style="color:var(--fs-red)"></i> <?= htmlspecialchars($br['name']) ?>
                                                </div>
                                                <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem"><?= htmlspecialchars($br['address']) ?>, <?= htmlspecialchars($br['city']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="fs-label">Pick-Up Date <span class="text-danger">*</span></label>
                                    <input type="date" class="fs-input" id="co-pickup-date">
                                </div>
                                <div class="col-md-6">
                                    <label class="fs-label">Preferred Time Slot <span class="text-danger">*</span></label>
                                    <select class="fs-select" id="co-pickup-time">
                                        <option value="">Select Slot</option>
                                        <option>8:00 AM</option><option>9:00 AM</option><option>10:00 AM</option>
                                        <option>11:00 AM</option><option>12:00 PM</option><option>1:00 PM</option>
                                        <option>2:00 PM</option><option>3:00 PM</option><option>4:00 PM</option>
                                        <option>5:00 PM</option><option>6:00 PM</option><option>7:00 PM</option>
                                        <option>8:00 PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Payment -->
                    <div class="fs-card">
                        <div class="fs-card-title"><i class="ti ti-credit-card"></i> 2. Payment Method</div>
                        <div class="payment-method-grid" id="rnPaymentMethodGrid">
                            <button type="button" class="payment-method-btn active" id="p-gcash-btn" onclick="selectPayment('gcash')">
                                <i class="ti ti-wallet payment-method-icon"></i>
                                <span class="payment-method-label">GCash</span>
                                <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                            </button>
                            <button type="button" class="payment-method-btn" id="p-maya-btn" onclick="selectPayment('maya')">
                                <i class="ti ti-wallet payment-method-icon"></i>
                                <span class="payment-method-label">Maya</span>
                                <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                            </button>
                            <button type="button" class="payment-method-btn" id="p-bank_transfer-btn" onclick="selectPayment('bank_transfer')">
                                <i class="ti ti-building-bank payment-method-icon"></i>
                                <span class="payment-method-label">Bank Transfer</span>
                                <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                            </button>
                            <button type="button" class="payment-method-btn" id="p-cash_on_delivery-btn" onclick="selectPayment('cash_on_delivery')">
                                <i class="ti ti-truck payment-method-icon"></i>
                                <span class="payment-method-label">Cash on Delivery</span>
                                <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                            </button>
                            <button type="button" class="payment-method-btn" id="p-cash_on_pickup-btn" onclick="selectPayment('cash_on_pickup')" style="display:none">
                                <i class="ti ti-cash payment-method-icon"></i>
                                <span class="payment-method-label">Cash on Pickup</span>
                                <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                            </button>
                        </div>

                        <!-- GCash payment details -->
                        <div class="pay-info-block active" id="pi-gcash">
                            <div style="font-weight:800;font-size:.85rem;margin-bottom:.5rem;color:var(--text-primary)"><i class="ti ti-wallet me-1 text-primary"></i>GCash Payments Gateway</div>
                            <div class="text-center mb-3">
                                <div style="margin:0 auto 0.75rem;">
                                    <img src="qrcodes/qr_sample.png" alt="GCash QR Code" style="width:110px;height:110px;object-fit:contain;border-radius:12px;border:1px solid var(--card-border);background:#fff;padding:4px">
                                </div>
                                <span style="font-size:.68rem;color:var(--text-muted);display:block;margin-top:.4rem">Scan the FitSync merchant QR code to transfer.</span>
                            </div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Account Name</span><span style="font-weight:700">FitSync Gym Corp</span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">GCash Account</span><span style="font-weight:700;font-family:monospace">0917 123 4567</span></div>
                        </div>

                        <!-- Maya payment details -->
                        <div class="pay-info-block" id="pi-maya">
                            <div style="font-weight:800;font-size:.85rem;margin-bottom:.5rem;color:var(--text-primary)"><i class="ti ti-wallet me-1 text-success"></i>Maya Gateway</div>
                            <div class="text-center mb-3">
                                <div style="margin:0 auto 0.75rem;">
                                    <img src="qrcodes/qr_sample.png" alt="Maya QR Code" style="width:110px;height:110px;object-fit:contain;border-radius:12px;border:1px solid var(--card-border);background:#fff;padding:4px">
                                </div>
                                <span style="font-size:.68rem;color:var(--text-muted);display:block;margin-top:.4rem">Scan QR code using Maya app.</span>
                            </div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Account Name</span><span style="font-weight:700">FitSync Gym</span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Maya Number</span><span style="font-weight:700;font-family:monospace">0917 765 4321</span></div>
                        </div>

                        <!-- Bank transfer details -->
                        <div class="pay-info-block" id="pi-bank_transfer">
                            <div style="font-weight:800;font-size:.85rem;margin-bottom:.5rem;color:var(--text-primary)"><i class="ti ti-building-bank me-1" style="color:var(--fs-red)"></i>Bank Transfer Details</div>
                            <div class="text-center mb-3">
                                <div style="margin:0 auto 0.75rem;">
                                    <img src="qrcodes/qr_sample.png" alt="Bank Transfer QR Code" style="width:110px;height:110px;object-fit:contain;border-radius:12px;border:1px solid var(--card-border);background:#fff;padding:4px">
                                </div>
                                <span style="font-size:.68rem;color:var(--text-muted);display:block;margin-top:.4rem">Scan QR code or use account details below to transfer.</span>
                            </div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Bank</span><span style="font-weight:700">Metrobank</span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Account Name</span><span style="font-weight:700">FitSync Corp</span></div>
                            <div class="pay-detail-row"><span style="color:var(--text-muted)">Account Number</span><span style="font-weight:700;font-family:monospace">123 456 789 0</span></div>
                        </div>

                        <!-- Cash on pickup note -->
                        <div class="pay-info-block" id="pi-cash_on_pickup">
                            <div style="background:rgba(46,204,113,.08);border:1px solid rgba(46,204,113,.2);border-radius:12px;padding:1rem;color:#2ecc71;font-size:.85rem">
                                <i class="ti ti-circle-check-filled"></i> <strong>Pay on pickup:</strong> You can pay directly at the branch counter when you pick up your items. We accept cash, cards, and Gcash at the desk.
                            </div>
                        </div>

                        <!-- Cash on Delivery payment details -->
                        <div class="pay-info-block" id="pi-cash_on_delivery">
                            <div style="background:rgba(74,158,255,.08);border:1px solid rgba(74,158,255,.2);border-radius:12px;padding:1rem;color:#4a9eff;font-size:.85rem">
                                <i class="ti ti-info-circle-filled"></i> <strong>Pay on delivery:</strong> Please prepare the exact cash amount to hand to our delivery rider when your order arrives.
                            </div>
                        </div>

                        <!-- Proof of payment upload container -->
                        <div id="co-proof-section" style="margin-top:1.25rem">
                            <label class="fs-label">Upload Proof of Payment <span class="text-danger">*</span></label>
                            <div class="proof-zone" id="co-proof-zone" onclick="document.getElementById('co-proof-input').click()">
                                <input type="file" id="co-proof-input" accept="image/*" onchange="uploadProofFile(this)">
                                <div id="co-proof-placeholder">
                                    <i class="ti ti-cloud-upload" style="font-size:2rem;color:var(--text-muted);display:block;margin-bottom:.4rem"></i>
                                    <div style="font-size:.82rem;font-weight:700">Click or drag receipt photo to upload</div>
                                    <div style="font-size:.68rem;color:var(--text-muted);margin-top:.2rem">JPG, PNG, WEBP &middot; Max 8MB</div>
                                </div>
                                <div id="co-proof-loading" style="display:none;padding:1rem;">
                                    <i class="ti ti-loader-2" style="font-size:2rem;color:var(--fs-red);animation:spin 1s linear infinite;display:block;margin:0 auto .5rem"></i>
                                    <div style="font-size:.8rem;font-weight:600">Uploading receipt image...</div>
                                </div>
                                <img id="co-proof-preview" style="max-height:160px;border-radius:8px;display:none;margin:auto" alt="proof">
                            </div>
                            <input type="hidden" id="co-proof-path" value="">
                        </div>
                    </div>
                </div>

                <!-- Review & Totals -->
                <div class="col-lg-5">
                    <div class="fs-card">
                        <div class="fs-card-title"><i class="ti ti-shopping-cart"></i> Review Cart Items</div>
                        <div style="max-height:280px;overflow-y:auto;margin-bottom:1.25rem;padding-right:.25rem">
                            <?php foreach ($cartItems as $it): ?>
                                <div class="item-row">
                                    <?php if ($it['image']): ?>
                                        <img src="<?= htmlspecialchars($it['image']) ?>" class="item-thumb" alt="">
                                    <?php else: ?>
                                        <div class="item-thumb-ph"><i class="ti ti-package"></i></div>
                                    <?php endif; ?>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($it['name']) ?></div>
                                        <div style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;margin-top:.15rem">
                                            <span style="font-size:.72rem;color:var(--text-muted)">&#8369;<?= number_format((float)$it['price'], 2) ?></span>
                                            <span class="item-branch-stock" data-product-id="<?= (int)$it['product_id'] ?>" style="font-size:.65rem;font-weight:700;padding:.05rem .35rem;border-radius:4px;background:rgba(204,26,26,.06)"></span>
                                        </div>
                                    </div>
                                    <div style="text-align:right">
                                        <span style="font-size:.82rem;font-weight:700">&#8369;<?= number_format((float)$it['price'] * $it['quantity'], 2) ?></span>
                                        <div style="font-size:.7rem;color:var(--text-muted)">Qty: <?= $it['quantity'] ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Special instructions notes input -->
                        <div class="mb-3">
                            <label class="fs-label">Delivery Notes / Delivery Instructions</label>
                            <textarea class="fs-input" id="co-notes" placeholder="e.g. Leave at guardhouse, call upon arrival, etc." style="height:60px;resize:none;font-size:.8rem"></textarea>
                        </div>

                        <!-- Totals box -->
                        <div class="sum-block">
                            <div class="co-sum-title">Order Pricing Details</div>
                            <div class="sum-row">
                                <span style="color:var(--text-muted)">Cart Subtotal</span>
                                <span style="font-weight:700">&#8369;<?= number_format($subtotal, 2) ?></span>
                            </div>
                            <div class="sum-row">
                                <span style="color:var(--text-muted)">Shipping/Handling Fee</span>
                                <span id="co-fee-label">&#8369;80.00</span>
                            </div>
                            <div class="sum-row total">
                                <span>Grand Total</span>
                                <span id="co-total-label">&#8369;<?= number_format($subtotal + 80, 2) ?></span>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <button type="button" class="btn-fs w-100 py-3 mt-2" id="co-submit-btn" onclick="submitOrder()">
                            <i class="ti ti-circle-check"></i> Place Secure Order
                        </button>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- CASE C: EMPTY CART -->
            <div class="fs-card text-center" style="padding:4rem 2rem;max-width:620px;margin:0 auto">
                <i class="ti ti-shopping-cart-off" style="font-size:3.5rem;color:var(--text-dimmed);display:block;margin-bottom:1.25rem"></i>
                <h3 style="font-weight:800;margin-bottom:.5rem">Your cart is empty</h3>
                <p style="color:var(--text-muted);font-size:.9rem;max-width:420px;margin:0 auto 1.5rem">Checkout is only for placing new orders. You can review past orders from your profile.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="profile.php#shop" class="btn-fs"><i class="ti ti-shopping-bag"></i> Browse Products</a>
                    <a href="profile.php#orders" class="btn-outline-fs px-3 py-2"><i class="ti ti-package"></i> View Orders</a>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- JS dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const SHOP_CSRF = <?= json_encode($csrf) ?>;
        const subtotal = <?= (float)$subtotal ?>;
        const BRANCH_STOCKS = <?= json_encode($branchStocks ?? []) ?>;
        
        let state = {
            fulfillment: 'delivery',
            payment: 'gcash',
            selectedBranchId: null,
            proofPath: ''
        };

        // Theme handlers
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            const nextTheme = isDark ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nextTheme);
            localStorage.setItem('fs-theme', nextTheme);
            
            // Fix images
            document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
                logo.src = nextTheme === 'light' ? logo.dataset.logoLight : logo.dataset.logoDark;
            });
        }



        // Toast helpers
        function shopToast(type, msg) {
            const colors = {success:'#2ecc71',error:'#ff6b6b',info:'#4a9eff'};
            const t = document.createElement('div');
            t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:${colors[type]||colors.info};color:#fff;padding:.7rem 1.2rem;border-radius:10px;font-size:.88rem;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.3);transition:opacity .3s`;
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3200);
        }

        // Checkout logic
        function selectFulfillment(method) {
            state.fulfillment = method;
            
            // Update toggle cards UI
            document.getElementById('f-delivery-btn').classList.toggle('active', method === 'delivery');
            document.getElementById('f-pickup-btn').classList.toggle('active', method === 'pickup');
            
            // Toggle form views
            document.getElementById('co-delivery-fields').style.display = method === 'delivery' ? 'block' : 'none';
            document.getElementById('co-pickup-fields').style.display = method === 'pickup' ? 'block' : 'none';
            
            // Cash on Delivery is only valid for delivery, Cash on Pickup is only valid for pickup
            const codBtn = document.getElementById('p-cash_on_delivery-btn');
            const copBtn = document.getElementById('p-cash_on_pickup-btn');
            if (codBtn) codBtn.style.display = method === 'delivery' ? 'flex' : 'none';
            if (copBtn) copBtn.style.display = method === 'pickup' ? 'flex' : 'none';
            
            // Reset payment if switching fulfillment makes current selection invalid
            if (method === 'delivery' && state.payment === 'cash_on_pickup') {
                selectPayment('cash_on_delivery');
            } else if (method === 'pickup' && state.payment === 'cash_on_delivery') {
                selectPayment('cash_on_pickup');
            }
            
            updateDeliveryFee();
            updateCheckoutStockDisplays();
        }

        function selectBranch(id, element) {
            state.selectedBranchId = id;
            document.getElementById('br-radio-' + id).checked = true;
            
            // Style active selection
            element.parentElement.parentElement.querySelectorAll('.border').forEach(el => {
                el.style.borderColor = 'var(--input-border)';
                el.style.background = 'var(--input-bg)';
            });
            element.style.borderColor = 'var(--fs-red)';
            element.style.background = 'rgba(204,26,26,.04)';
            updateCheckoutStockDisplays();
        }

        function selectPayment(method) {
            state.payment = method;
            
            // Update buttons active classes
            ['gcash', 'maya', 'bank_transfer', 'cash_on_pickup', 'cash_on_delivery'].forEach(m => {
                const btn = document.getElementById('p-' + m + '-btn');
                if (btn) btn.classList.toggle('active', m === method);
            });
            
            // Toggle display info blocks
            ['gcash', 'maya', 'bank_transfer', 'cash_on_pickup', 'cash_on_delivery'].forEach(m => {
                const block = document.getElementById('pi-' + m);
                if (block) block.classList.toggle('active', m === method);
            });
            
            // Hide or show proof section
            const proofSec = document.getElementById('co-proof-section');
            if (proofSec) {
                proofSec.style.display = (method === 'cash_on_pickup' || method === 'cash_on_delivery') ? 'none' : 'block';
            }
        }

        function updateCheckoutStockDisplays() {
            const targetBranchId = (state.fulfillment === 'delivery') ? 1 : state.selectedBranchId;
            
            document.querySelectorAll('.item-branch-stock').forEach(span => {
                const productId = parseInt(span.dataset.productId);
                let stock = 0;
                if (targetBranchId && BRANCH_STOCKS[productId] && BRANCH_STOCKS[productId][targetBranchId] !== undefined) {
                    stock = BRANCH_STOCKS[productId][targetBranchId];
                }
                
                if (targetBranchId) {
                    if (stock > 0) {
                        span.style.color = '#2ecc71';
                        span.style.background = 'rgba(46,204,113,.12)';
                        span.textContent = `${stock} left`;
                    } else {
                        span.style.color = '#ff6b6b';
                        span.style.background = 'rgba(255,107,107,.12)';
                        span.textContent = 'Out of stock';
                    }
                } else {
                    span.style.color = 'var(--text-muted)';
                    span.style.background = 'rgba(0,0,0,0.06)';
                    span.textContent = 'Select branch';
                }
            });
        }

        function updateDeliveryFee() {
            let fee = 0;
            if (state.fulfillment === 'delivery') {
                const region = document.getElementById('co-region').value;
                const ncr = ['NCR'];
                if (region) {
                    fee = ncr.includes(region) ? 80 : 150;
                } else {
                    fee = 80; // defaultNCR
                }
            }
            
            // Update summary
            const feeLabel = document.getElementById('co-fee-label');
            const totalLabel = document.getElementById('co-total-label');
            if (feeLabel) feeLabel.innerHTML = '&#8369;' + fee.toFixed(2);
            if (totalLabel) totalLabel.innerHTML = '&#8369;' + (subtotal + fee).toLocaleString('en-PH', {minimumFractionDigits: 2});
        }

        // Upload proof of payment file
        async function uploadProofFile(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            
            const placeholder = document.getElementById('co-proof-placeholder');
            const loader = document.getElementById('co-proof-loading');
            const preview = document.getElementById('co-proof-preview');
            
            placeholder.style.display = 'none';
            loader.style.display = 'block';
            preview.style.display = 'none';
            
            const fd = new FormData();
            fd.append('action', 'upload_proof');
            fd.append('csrf_token', SHOP_CSRF);
            fd.append('proof', file);
            
            try {
                const res = await (await fetch('handlers/shop_handler.php', { method: 'POST', body: fd })).json();
                loader.style.display = 'none';
                
                if (res.success) {
                    state.proofPath = res.path;
                    document.getElementById('co-proof-path').value = res.path;
                    preview.src = res.path;
                    preview.style.display = 'block';
                    shopToast('success', 'Receipt uploaded successfully!');
                } else {
                    placeholder.style.display = 'block';
                    shopToast('error', res.message || 'Upload failed.');
                }
            } catch (e) {
                loader.style.display = 'none';
                placeholder.style.display = 'block';
                shopToast('error', 'Network error while uploading.');
            }
        }

        // Submit the checkout form
        async function submitOrder() {
            const btn = document.getElementById('co-submit-btn');
            
            // Base validations
            const fullName = document.getElementById('co-fullname').value.trim();
            const contact = document.getElementById('co-contact').value.trim();
            const email = document.getElementById('co-email').value.trim();
            
            if (fullName === '') return shopToast('error', 'Recipient Full Name is required.');
            if (contact === '')  return shopToast('error', 'Recipient Contact Phone is required.');
            if (email === '')    return shopToast('error', 'Recipient Email Address is required.');
            
            const fd = new FormData();
            fd.append('action', 'checkout');
            fd.append('csrf_token', SHOP_CSRF);
            fd.append('checkout_token', 'co_' + Date.now());
            fd.append('fulfillment_method', state.fulfillment);
            fd.append('payment_method', state.payment);
            fd.append('order_notes', document.getElementById('co-notes').value.trim());
            
            if (state.fulfillment === 'delivery') {
                const region = document.getElementById('co-region').value;
                const shippingProvider = document.getElementById('co-shipping-provider').value;
                const city = document.getElementById('co-city').value.trim();
                const barangay = document.getElementById('co-barangay').value.trim();
                const street = document.getElementById('co-street').value.trim();
                const zip = document.getElementById('co-zip').value.trim();
                
                if (region === '')  return shopToast('error', 'Please select a delivery region.');
                if (shippingProvider === '') return shopToast('error', 'Please select a shipping option.');
                if (city === '')    return shopToast('error', 'Please fill in delivery city.');
                if (barangay === '') return shopToast('error', 'Please fill in delivery barangay.');
                if (street === '')   return shopToast('error', 'Please fill in delivery street.');
                if (zip === '')      return shopToast('error', 'Please fill in delivery zip code.');
                
                fd.append('recipient_name', fullName);
                fd.append('recipient_contact', contact);
                fd.append('recipient_email', email);
                fd.append('shipping_provider', shippingProvider);
                fd.append('region', region);
                fd.append('province', document.getElementById('co-province').value.trim());
                fd.append('city', city);
                fd.append('barangay', barangay);
                fd.append('street', street);
                fd.append('zip', zip);
                fd.append('landmark', document.getElementById('co-landmark').value.trim());
            } else {
                if (!state.selectedBranchId) return shopToast('error', 'Please select a pick-up branch.');
                const pDate = document.getElementById('co-pickup-date').value;
                const pTime = document.getElementById('co-pickup-time').value;
                
                if (pDate === '') return shopToast('error', 'Please select a pick-up date.');
                if (pTime === '') return shopToast('error', 'Please select a pick-up time slot.');
                
                fd.append('pickup_branch_id', state.selectedBranchId);
                fd.append('pickup_date', pDate);
                fd.append('pickup_time', pTime);
                fd.append('recipient_name', fullName);
                fd.append('recipient_contact', contact);
                fd.append('recipient_email', email);
            }
            
            // Require payment proof for non-cash orders
            if (state.payment !== 'cash_on_pickup' && state.payment !== 'cash_on_delivery') {
                if (state.proofPath === '') {
                    return shopToast('error', 'Please upload your proof of payment receipt.');
                }
                fd.append('proof_path', state.proofPath);
            }
            
            // Submit Order
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin 1s linear infinite"></i> Placing Order...';
            
            try {
                const res = await (await fetch('handlers/shop_handler.php', { method: 'POST', body: fd })).json();
                if (res.success) {
                    shopToast('success', 'Order submitted successfully!');
                    setTimeout(() => {
                        window.location.href = 'profile.php#orders';
                    }, 1000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ti ti-circle-check"></i> Place Secure Order';
                    shopToast('error', res.message || 'Checkout failed.');
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-circle-check"></i> Place Secure Order';
                shopToast('error', 'Network error occurred. Please try again.');
            }
        }
        
        // Initializer
        window.addEventListener('DOMContentLoaded', () => {
            // Set min pickup date (tomorrow)
            const dateInput = document.getElementById('co-pickup-date');
            if (dateInput) {
                const tm = new Date();
                tm.setDate(tm.getDate() + 1);
                dateInput.min = tm.toISOString().split('T')[0];
            }
            updateCheckoutStockDisplays();
        });
    </script>
</body>

</html>
