<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth_guard.php';
requireRole('admin');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/report_helpers.php';

$pdo = db();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$adminUserId = (int) ($_SESSION['user_id'] ?? 0);

function adminScalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function adminRows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function adminPlanClass(?string $label): string
{
    $label = strtolower((string) $label);
    return str_contains($label, '12') || str_contains($label, 'annual') ? 'yr'
        : (str_contains($label, '6') ? 'mo6'
        : (str_contains($label, '3') ? 'mo3' : 'mo1'));
}

function adminEmitReportExport(PDO $pdo, array $source): void
{
    $type = trim((string) ($source['report_export'] ?? ''));
    if (!in_array($type, ['memberships', 'revenue', 'attendance', 'classes'], true)) {
        return;
    }

    $format = trim((string) ($source['format'] ?? 'csv'));
    $format = $format === 'excel' ? 'excel' : 'csv';
    $filters = reportFilters($source);
    $rows = reportExportRows($pdo, $type, $filters);
    $filename = 'fitsync-' . $type . '-' . date('Ymd-His') . ($format === 'excel' ? '.xls' : '.csv');

    header('Content-Type: ' . ($format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    if ($format === 'excel') {
        fwrite($out, "\xEF\xBB\xBF");
    }
    if (!$rows) {
        fputcsv($out, ['No records found']);
        exit;
    }
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    exit;
}

adminEmitReportExport($pdo, $_GET);

$reportFilters = reportFilters($_GET);
$activeReportTab = in_array(($_GET['report_tab'] ?? 'overview'), ['overview', 'memberships', 'revenue', 'attendance', 'classes'], true)
    ? (string) ($_GET['report_tab'] ?? 'overview')
    : 'overview';
$reports = reportsBuild($pdo, $reportFilters);

function adminReportUrl(string $tab, array $extra = []): string
{
    $params = array_merge($_GET, ['report_tab' => $tab], $extra);
    unset($params['report_export'], $params['format']);
    return 'admin.php?' . http_build_query(array_filter($params, static fn($value): bool => $value !== null && $value !== '')) . '#reports';
}

function adminReportExportUrl(string $type, string $format): string
{
    $params = array_merge($_GET, [
        'report_tab' => $type,
        'report_export' => $type,
        'format' => $format,
    ]);
    return 'admin.php?' . http_build_query(array_filter($params, static fn($value): bool => $value !== null && $value !== ''));
}

$adminUser = adminRows($pdo, 'SELECT first_name, last_name, email, role, updated_at FROM users WHERE id = ? LIMIT 1', [$adminUserId])[0] ?? [
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => '',
    'role' => 'admin',
    'updated_at' => null,
];

$membersRaw = adminRows(
    $pdo,
    'SELECT u.id, u.first_name AS fname, u.last_name AS lname, u.email, u.is_active, u.is_approved, u.created_at,
            lm.id AS membership_id, lm.starts_at, lm.ends_at, lm.status, lm.payment_status, lm.amount_paid, lm.payment_method, lm.proof_file_path, lm.proof_uploaded_at,
            p.id AS plan_id, p.label AS plan, b.id AS branch_id, b.name AS branch
     FROM users u
     LEFT JOIN memberships lm ON lm.id = (
        SELECT m2.id FROM memberships m2
        WHERE m2.user_id = u.id
        ORDER BY m2.created_at DESC, m2.id DESC
        LIMIT 1
     )
     LEFT JOIN membership_plans p ON p.id = lm.plan_id
     LEFT JOIN branches b ON b.id = lm.branch_id
     WHERE u.role = "member"
     ORDER BY u.created_at DESC'
);
$members = array_map(static function (array $m): array {
    $status = (string) ($m['status'] ?? '');
    if ((int) ($m['is_approved'] ?? 1) === 0 || ($m['payment_status'] ?? '') === 'pending') {
        $status = 'pending';
    } elseif ((int) ($m['is_active'] ?? 1) === 0) {
        $status = 'inactive';
    } elseif ($status === '') {
        $status = 'inactive';
    }
    return [
        'id' => (int) $m['id'],
        'membership_id' => $m['membership_id'] !== null ? (int) $m['membership_id'] : null,
        'fname' => (string) $m['fname'],
        'lname' => (string) $m['lname'],
        'email' => (string) $m['email'],
        'plan' => (string) ($m['plan'] ?? 'No plan'),
        'plan_id' => $m['plan_id'] !== null ? (int) $m['plan_id'] : null,
        'planCls' => adminPlanClass($m['plan'] ?? null),
        'branch' => (string) ($m['branch'] ?? 'Unassigned'),
        'branch_id' => $m['branch_id'] !== null ? (int) $m['branch_id'] : null,
        'joined' => (string) ($m['starts_at'] ?? $m['created_at']),
        'expiry' => (string) ($m['ends_at'] ?? ''),
        'status' => $status,
        'payment' => (string) ($m['payment_status'] ?? 'none'),
        'payment_method' => (string) ($m['payment_method'] ?? ''),
        'amount' => (float) ($m['amount_paid'] ?? 0),
        'proof_file' => (string) ($m['proof_file_path'] ?? ''),
        'proof_date' => (string) ($m['proof_uploaded_at'] ?? ''),
        'approved' => (int) ($m['is_approved'] ?? 1) === 1,
        'active_account' => (int) ($m['is_active'] ?? 0) === 1,
    ];
}, $membersRaw);

$branches = adminRows(
    $pdo,
    'SELECT b.id, b.name, b.city, b.address, b.is_active,
            COUNT(DISTINCT u.id) AS members,
            COUNT(al.id) AS total_visits,
            SUM(al.check_in_at >= CURDATE()) AS today_visits
     FROM branches b
     LEFT JOIN memberships m ON m.branch_id = b.id AND m.status = "active" AND m.payment_status = "paid"
     LEFT JOIN users u ON u.id = m.user_id AND u.role = "member"
     LEFT JOIN attendance_logs al ON al.branch_id = b.id
     GROUP BY b.id, b.name, b.city, b.address, b.is_active
     ORDER BY b.name'
);
$membershipPlans = adminRows(
    $pdo,
    'SELECT id, label, price, duration_days, is_active
     FROM membership_plans
     WHERE is_active = 1
     ORDER BY duration_days ASC, label ASC'
);
$memberNotes = adminRows(
    $pdo,
    'SELECT n.member_id, n.note_body, n.created_at, CONCAT(a.first_name, " ", a.last_name) AS admin_name
     FROM member_notes n
     LEFT JOIN users a ON a.id = n.admin_id
     ORDER BY n.created_at DESC
     LIMIT 100'
);

$feedbacks = adminRows(
    $pdo,
    'SELECT f.id, f.rating, f.body AS text, f.created_at AS date,
            CONCAT(u.first_name, " ", u.last_name) AS name,
            COALESCE(b.name, "Unassigned") AS branch
     FROM feedback f
     LEFT JOIN users u ON u.id = f.user_id
     LEFT JOIN branches b ON b.id = f.branch_id
     WHERE f.is_visible = 1
     ORDER BY f.created_at DESC'
);

// Order / product reviews (separate table)
$orderReviews = [];
try {
    $orderReviews = adminRows(
        $pdo,
        'SELECT r.id, r.order_id, r.rating, r.body AS text, r.created_at AS date,
                CONCAT(u.first_name, " ", u.last_name) AS name, u.email
         FROM order_reviews r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.is_visible = 1
         ORDER BY r.created_at DESC'
    );
} catch (Throwable) { /* table may not exist yet on first run */ }

$classes = adminRows(
    $pdo,
    'SELECT c.*, b.name AS branch_name
     FROM classes c
     LEFT JOIN branches b ON b.id = c.branch_id
     ORDER BY c.is_active DESC, c.title ASC'
);
$classSchedules = adminRows(
    $pdo,
    'SELECT cs.*, c.title, c.trainer_name, b.name AS branch_name,
            (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_schedule_id = cs.id AND cb.booking_status IN ("booked","attended")) AS booked_count
     FROM class_schedules cs
     INNER JOIN classes c ON c.id = cs.class_id
     INNER JOIN branches b ON b.id = cs.branch_id
     ORDER BY cs.scheduled_date ASC, cs.start_time ASC
     LIMIT 20'
);
$announcements = adminRows(
    $pdo,
    'SELECT a.*, b.name AS branch_name
     FROM branch_announcements a
     LEFT JOIN branches b ON b.id = a.branch_id
     ORDER BY a.is_active DESC, a.starts_at DESC
     LIMIT 50'
);
$operatingHours = adminRows(
    $pdo,
    'SELECT h.*, b.name AS branch_name
     FROM branch_operating_hours h
     INNER JOIN branches b ON b.id = h.branch_id
     ORDER BY b.name ASC, h.day_of_week ASC'
);

$recentAttendance = adminRows(
    $pdo,
    'SELECT CONCAT(u.first_name, " ", u.last_name) AS name, b.name AS branch, al.check_in_at
     FROM attendance_logs al
     INNER JOIN users u ON u.id = al.user_id
     INNER JOIN branches b ON b.id = al.branch_id
     ORDER BY al.check_in_at DESC
     LIMIT 8'
);
$activeMembers = adminRows(
    $pdo,
    'SELECT u.first_name AS fname, u.last_name AS lname, COUNT(al.id) AS visits, MAX(al.check_in_at) AS last_visit
     FROM users u
     INNER JOIN attendance_logs al ON al.user_id = u.id
     WHERE u.role = "member" AND al.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY u.id, u.first_name, u.last_name
     ORDER BY visits DESC
     LIMIT 8'
);
$inactiveMembers = adminRows(
    $pdo,
    'SELECT u.first_name AS fname, u.last_name AS lname, MAX(al.check_in_at) AS last_visit
     FROM users u
     LEFT JOIN attendance_logs al ON al.user_id = u.id
     WHERE u.role = "member"
     GROUP BY u.id, u.first_name, u.last_name
     HAVING last_visit IS NULL OR last_visit < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY last_visit ASC
     LIMIT 8'
);

$months = [];
$signupData = [];
$revenueData = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = (new DateTimeImmutable('first day of this month'))->modify("-{$i} months");
    $monthEnd = $monthStart->modify('last day of this month');
    $months[] = $monthStart->format('M');
    $signupData[] = (int) adminScalar($pdo, 'SELECT COUNT(*) FROM users WHERE role = "member" AND created_at BETWEEN ? AND ?', [$monthStart->format('Y-m-d 00:00:00'), $monthEnd->format('Y-m-d 23:59:59')]);
    $revenueData[] = (float) adminScalar($pdo, 'SELECT COALESCE(SUM(amount_paid), 0) FROM memberships WHERE payment_status = "paid" AND updated_at BETWEEN ? AND ?', [$monthStart->format('Y-m-d 00:00:00'), $monthEnd->format('Y-m-d 23:59:59')]);
}

$dashboard = [
    'total_members' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM users WHERE role = "member"'),
    'active_members' => (int) adminScalar($pdo, 'SELECT COUNT(DISTINCT user_id) FROM memberships WHERE status = "active" AND payment_status = "paid" AND starts_at <= CURDATE() AND ends_at >= CURDATE()'),
    'pending_approvals' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM users WHERE role = "member" AND is_approved = 0'),
    'pending_payments' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM memberships WHERE payment_status = "pending"'),
    'active_memberships' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM memberships WHERE status = "active" AND payment_status = "paid"'),
    'expiring_soon' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM memberships WHERE status = "active" AND payment_status = "paid" AND ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
    'expired_memberships' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM memberships WHERE status = "expired" OR ends_at < CURDATE()'),
    'revenue_month' => (float) adminScalar($pdo, 'SELECT COALESCE(SUM(amount_paid), 0) FROM memberships WHERE payment_status = "paid" AND updated_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'),
    'attendance_today' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at >= CURDATE()'),
    'attendance_month' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'),
    'visible_feedbacks' => count($feedbacks),
    'active_branches' => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM branches WHERE is_active = 1'),
];

$revenueByPlan = adminRows(
    $pdo,
    'SELECT p.label, COALESCE(SUM(m.amount_paid), 0) AS revenue
     FROM membership_plans p
     LEFT JOIN memberships m ON m.plan_id = p.id AND m.payment_status = "paid"
     GROUP BY p.id, p.label
     ORDER BY revenue DESC, p.label'
);

// ── SHOP STATS ─────────────────────────────────────────────
try {
    $shopStats = [
        'total_products'  => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active=1'),
        'total_inventory' => (int) adminScalar($pdo, 'SELECT COALESCE(SUM(ps.stock),0) FROM product_stocks ps JOIN products p ON p.id = ps.product_id WHERE p.is_active=1'),
        'total_orders'    => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM orders'),
        'pending_orders'  => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM orders WHERE status="pending"'),
        'low_stock'       => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM (SELECT p.id, COALESCE(SUM(ps.stock), 0) AS total_stock FROM products p LEFT JOIN product_stocks ps ON ps.product_id = p.id WHERE p.is_active=1 GROUP BY p.id) t WHERE total_stock > 0 AND total_stock <= 5'),
        'out_of_stock'    => (int) adminScalar($pdo, 'SELECT COUNT(*) FROM (SELECT p.id, COALESCE(SUM(ps.stock), 0) AS total_stock FROM products p LEFT JOIN product_stocks ps ON ps.product_id = p.id WHERE p.is_active=1 GROUP BY p.id) t WHERE total_stock = 0'),
        'shop_revenue'    => (float) adminScalar($pdo, 'SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status!="cancelled"'),
    ];
} catch (Throwable) {
    $shopStats = ['total_products'=>0,'total_inventory'=>0,'total_orders'=>0,'pending_orders'=>0,'low_stock'=>0,'out_of_stock'=>0,'shop_revenue'=>0.0];
}

$adminData = [
    'csrf' => $csrf,
    'admin' => $adminUser,
    'members' => $members,
    'branches' => $branches,
    'membershipPlans' => $membershipPlans,
    'memberNotes' => $memberNotes,
    'feedbacks'       => $feedbacks,
    'orderReviews'    => $orderReviews,

    'classes' => $classes,
    'classSchedules' => $classSchedules,
    'announcements' => $announcements,
    'operatingHours' => $operatingHours,
    'recentAttendance' => $recentAttendance,
    'activeMembers' => $activeMembers,
    'inactiveMembers' => $inactiveMembers,
    'dashboard' => $dashboard,
    'shopStats' => $shopStats,
    'months' => $months,
    'signupData' => $signupData,
    'revenueData' => $revenueData,
    'revenueByPlan' => $revenueByPlan,
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync Admin</title>
    <link rel="icon" type="image/svg+xml" href="assets/FitSYNC%20Emblem%20Light.svg" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <style>
        /* ─── TOKENS ─────────────────────────────── */
        :root {
            --red: #cc1a1a;
            --red-soft: rgba(204, 26, 26, .1);
            --red-glow: rgba(204, 26, 26, .22);
            --sidebar-w: 240px;
            --topbar-h: 58px;
        }

        [data-theme="dark"] {
            --bg: #0d0d0d;
            --surface: #111;
            --surface2: #171717;
            --border: rgba(255, 255, 255, .07);
            --border2: rgba(255, 255, 255, .12);
            --text: #f0f0f0;
            --text-2: rgba(255, 255, 255, .5);
            --text-3: rgba(255, 255, 255, .25);
            --input-bg: rgba(255, 255, 255, .05);
            --row-hover: rgba(255, 255, 255, .02);
            --th-bg: rgba(255, 255, 255, .03);
            --sidebar-bg: #080808;
            --topbar-bg: rgba(11, 11, 11, .9);
            --tag-bg: rgba(255, 255, 255, .06);
            color-scheme: dark;
        }

        [data-theme="light"] {
            --bg: #f4f4f5;
            --surface: #fff;
            --surface2: #f9f9f9;
            --border: rgba(0, 0, 0, .07);
            --border2: rgba(0, 0, 0, .13);
            --text: #111;
            --text-2: rgba(0, 0, 0, .48);
            --text-3: rgba(0, 0, 0, .25);
            --input-bg: rgba(0, 0, 0, .04);
            --row-hover: rgba(0, 0, 0, .02);
            --th-bg: rgba(0, 0, 0, .025);
            --sidebar-bg: #fff;
            --topbar-bg: rgba(255, 255, 255, .9);
            --tag-bg: rgba(0, 0, 0, .05);
            color-scheme: light;
        }

        /* ─── RESET ──────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            font-size: 14px;
            line-height: 1.5;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font-family: inherit;
            cursor: pointer;
        }

        select,
        input[type="date"],
        input[type="time"] {
            color-scheme: inherit;
        }

        select option {
            background: var(--surface);
            color: var(--text);
        }

        [data-theme="dark"] select option:checked,
        [data-theme="dark"] select option:hover {
            background: #242424;
            color: var(--text);
        }

        /* ─── LAYOUT ─────────────────────────────── */
        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform .25s cubic-bezier(.4, 0, .2, 1);
        }

        .main {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: var(--topbar-bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: .75rem;
            z-index: 90;
        }

        /* ─── SIDEBAR ────────────────────────────── */
        .sb-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: 1.1rem 1.1rem .9rem;
            border-bottom: 1px solid var(--border);
            font-weight: 900;
            font-size: 1.05rem;
            letter-spacing: .5px;
        }

        .sb-brand .fit {
            color: var(--text);
        }

        .sb-brand .sync {
            color: var(--red);
        }

        .sb-section {
            padding: .6rem 0 .2rem;
        }

        .sb-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            color: var(--text-3);
            padding: .6rem 1.1rem .2rem;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem 1rem;
            margin: .08rem .55rem;
            border-radius: 9px;
            color: var(--text-2);
            font-size: .84rem;
            font-weight: 500;
            transition: background .15s, color .15s;
            border: none;
            background: none;
            width: calc(100% - 1.1rem);
            text-align: left;
        }

        .sb-link i {
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .sb-link:hover {
            background: rgba(128, 128, 128, .1);
            color: var(--text);
        }

        .sb-link.active {
            background: var(--red-soft);
            color: var(--text);
        }

        .sb-link.active i {
            color: var(--red);
        }

        .sb-pill {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: .58rem;
            font-weight: 700;
            padding: .1rem .42rem;
            border-radius: 99px;
            line-height: 1.5;
        }

        .sb-footer {
            margin-top: auto;
            padding: .75rem .55rem;
            border-top: 1px solid var(--border);
        }

        .sb-theme-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .4rem .75rem;
            margin-bottom: .3rem;
        }

        .sb-theme-label {
            font-size: .78rem;
            color: var(--text-2);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .pill-toggle {
            width: 42px;
            height: 22px;
            border-radius: 99px;
            border: 1px solid var(--border2);
            background: var(--input-bg);
            position: relative;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
            transition: background .3s;
        }

        .pill-knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--red);
            transition: transform .3s;
        }

        [data-theme="light"] .pill-knob {
            transform: translateX(20px);
        }

        .sb-link.logout {
            color: rgba(255, 80, 80, .6);
        }

        .sb-link.logout:hover {
            background: rgba(204, 26, 26, .1);
            color: #ff6b6b;
        }

        /* ─── TOPBAR ─────────────────────────────── */
        .topbar-title {
            font-size: .95rem;
            font-weight: 700;
        }

        .topbar-crumb {
            font-size: .7rem;
            color: var(--text-3);
        }

        .topbar-search {
            margin-left: auto;
            position: relative;
        }

        .topbar-search input {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 9px;
            padding: .38rem .8rem .38rem 2rem;
            font-size: .8rem;
            font-family: inherit;
            width: 200px;
            outline: none;
            transition: border-color .2s;
        }

        .topbar-search input:focus {
            border-color: rgba(204, 26, 26, .45);
        }

        .topbar-search .si {
            position: absolute;
            left: .6rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: .9rem;
            pointer-events: none;
        }

        .topbar-search input::placeholder {
            color: var(--text-3);
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red), #7a0f0f);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        /* Notification bell */
        .notif-btn {
            position: relative;
            background: none;
            border: 1px solid var(--border);
            color: var(--text-2);
            border-radius: 9px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all .15s;
        }

        .notif-btn:hover {
            border-color: var(--border2);
            color: var(--text);
        }

        .notif-dot {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--red);
            border: 1.5px solid var(--sidebar-bg);
        }

        /* ─── PAGE SECTIONS ──────────────────────── */
        .page {
            display: none;
            padding: 1.5rem;
        }

        .page.active {
            display: block;
        }

        /* ─── DASHBOARD TABS ─────────────────────── */
        .dash-tabs {
            display: flex;
            gap: .3rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .3rem;
            width: fit-content;
            margin-bottom: 1.5rem;
        }

        .dash-tab {
            padding: .4rem .9rem;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 600;
            border: none;
            background: none;
            color: var(--text-2);
            transition: all .15s;
            cursor: pointer;
        }

        .dash-tab.active {
            background: var(--red-soft);
            color: var(--text);
        }

        .dash-panel {
            display: none;
        }

        .dash-panel.active {
            display: block;
        }

        /* ─── STAT CARDS ─────────────────────────── */
        .grid {
            display: grid;
            gap: .9rem;
        }

        .g-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .g-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .g-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .g-2-1 {
            grid-template-columns: 2fr 1fr;
        }

        .g-1-2 {
            grid-template-columns: 1fr 2fr;
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
        }

        .stat:hover {
            border-color: rgba(204, 26, 26, .25);
            transform: translateY(-2px);
        }

        .stat::after {
            content: '';
            position: absolute;
            top: -25px;
            right: -25px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--red-soft);
            pointer-events: none;
        }

        .stat.urgent {
            border-color: var(--red);
            box-shadow: 0 6px 20px var(--red-glow);
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--red-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--red);
            margin-bottom: .75rem;
        }

        .stat-val {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-lbl {
            font-size: .7rem;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: .25rem;
        }

        .stat-sub {
            font-size: .72rem;
            color: var(--text-3);
            margin-top: .4rem;
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .stat-sub.up {
            color: #4caf87;
        }

        .stat-sub.down {
            color: #e05656;
        }

        /* ─── CARD / PANEL ───────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }

        .card > table {
            overflow-x: auto;
            display: block;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: .9rem;
            font-weight: 700;
        }

        .card-sub {
            font-size: .7rem;
            color: var(--text-3);
        }

        .card-body {
            padding: 1.1rem;
        }

        /* ─── ALERT BANNER ───────────────────────── */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: rgba(204, 26, 26, .08);
            border: 1px solid rgba(204, 26, 26, .25);
            border-radius: 12px;
            padding: .85rem 1rem;
            margin-bottom: 1.25rem;
            font-size: .84rem;
        }

        .alert-banner i {
            color: var(--red);
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert-banner strong {
            color: var(--red);
        }

        .alert-banner a {
            color: var(--red);
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
        }

        /* ─── SECTION HEADER ─────────────────────── */
        .sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .9rem;
        }

        .sec-title {
            font-size: .95rem;
            font-weight: 700;
        }

        .sec-title small {
            font-size: .7rem;
            font-weight: 400;
            color: var(--text-3);
            margin-left: .4rem;
        }

        /* ─── TABLE ──────────────────────────────── */
        .tbl-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: var(--th-bg);
            border-bottom: 1px solid var(--border);
            color: var(--text-3);
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: .7rem 1rem;
            white-space: nowrap;
        }

        tbody td {
            padding: .8rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: .84rem;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: var(--row-hover);
        }

        /* ─── TABLE ACTIONS ──────────────────────── */
        .tbtn {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .88rem;
            cursor: pointer;
            transition: all .15s;
        }

        .tbtn:hover {
            background: var(--red-soft);
            border-color: rgba(204, 26, 26, .3);
            color: var(--red);
        }

        .tbtn.danger:hover {
            background: rgba(220, 53, 69, .12);
            border-color: rgba(220, 53, 69, .3);
            color: #e05656;
        }

        .tbtn.success:hover {
            background: rgba(76, 175, 135, .12);
            border-color: rgba(76, 175, 135, .3);
            color: #4caf87;
        }

        .actions {
            display: flex;
            gap: .3rem;
        }

        /* ─── BADGES ─────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: .18rem .52rem;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .badge.active {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
        }

        .badge.expired {
            background: rgba(150, 150, 150, .12);
            color: #888;
        }

        .badge.frozen {
            background: rgba(100, 160, 255, .12);
            color: #6ea4f0;
        }

        .badge.pending {
            background: rgba(255, 193, 7, .12);
            color: #d6a100;
        }

        .badge.cancelled {
            background: rgba(220, 53, 69, .12);
            color: #e05656;
        }

        .badge.paid {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
        }

        .badge.unpaid {
            background: rgba(255, 193, 7, .12);
            color: #d6a100;
        }

        .plan-badge {
            display: inline-block;
            padding: .18rem .5rem;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .plan-badge.mo1 {
            background: var(--tag-bg);
            color: var(--text-2);
        }

        .plan-badge.mo3 {
            background: rgba(76, 175, 135, .1);
            color: #4caf87;
        }

        .plan-badge.mo6 {
            background: rgba(204, 26, 26, .12);
            color: var(--red);
        }

        .plan-badge.yr {
            background: rgba(255, 193, 7, .1);
            color: #f0b429;
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: .35rem;
        }

        .dot.active {
            background: #4caf87;
            box-shadow: 0 0 5px rgba(76, 175, 135, .45);
        }

        .dot.inactive {
            background: #555;
        }

        .dot.pending {
            background: #d6a100;
        }

        /* ─── AVATAR ─────────────────────────────── */
        .mem-av {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--red), #7a0f0f);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        /* ─── SPARKLINE / BARS ───────────────────── */
        .sparkline {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 70px;
        }

        .spark-bar {
            flex: 1;
            border-radius: 3px 3px 0 0;
            background: var(--red-soft);
            transition: background .15s;
        }

        .spark-bar:hover,
        .spark-bar.hi {
            background: var(--red);
        }

        .spark-labels {
            display: flex;
            justify-content: space-between;
            font-size: .62rem;
            color: var(--text-3);
            margin-top: .4rem;
        }

        /* ─── QUICK ACTIONS ──────────────────────── */
        .qa {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .85rem 1rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all .15s;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .qa:hover {
            border-color: rgba(204, 26, 26, .3);
            background: rgba(204, 26, 26, .04);
            transform: translateY(-1px);
        }

        .qa-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--red-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--red);
            flex-shrink: 0;
        }

        .qa-lbl {
            font-size: .82rem;
            font-weight: 600;
        }

        .qa-sub {
            font-size: .68rem;
            color: var(--text-2);
            margin-top: .1rem;
        }

        /* ─── PENDING ITEM ───────────────────────── */
        .pending-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .7rem 0;
            border-bottom: 1px solid var(--border);
        }

        .pending-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .pending-row:first-child {
            padding-top: 0;
        }

        /* ─── FEEDBACK ───────────────────────────── */
        .fb-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: .6rem;
            transition: all .15s;
        }

        .fb-card:hover {
            border-color: rgba(204, 26, 26, .2);
        }

        .fb-stars {
            color: var(--red);
            font-size: .82rem;
            letter-spacing: 1px;
        }

        .fb-text {
            font-size: .82rem;
            color: var(--text-2);
            line-height: 1.65;
            margin-top: .4rem;
        }

        .fb-meta {
            font-size: .68rem;
            color: var(--text-3);
            margin-top: .5rem;
        }

        /* ─── BUTTONS ────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .9rem;
            border-radius: 99px;
            font-size: .78rem;
            font-weight: 600;
            font-family: inherit;
            border: 1px solid var(--border2);
            background: transparent;
            color: var(--text-2);
            cursor: pointer;
            transition: all .15s;
        }

        .btn:hover {
            background: var(--red-soft);
            border-color: rgba(204, 26, 26, .3);
            color: var(--text);
        }

        .btn.primary {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
        }

        .btn.primary:hover {
            background: #a01212;
            border-color: #a01212;
        }

        .btn.ghost {
            border-color: transparent;
        }

        .btn.ghost:hover {
            background: var(--input-bg);
            border-color: var(--border);
            color: var(--text);
        }

        .btn.sm {
            padding: .28rem .7rem;
            font-size: .72rem;
        }

        .btn.success-btn {
            border-color: rgba(76, 175, 135, .4);
            color: #4caf87;
        }

        .btn.success-btn:hover {
            background: rgba(76, 175, 135, .1);
        }

        /* ─── TOAST SYSTEM ───────────────────────── */
        #toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            z-index: 9999;
        }

        .toast {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: .8rem 1.1rem;
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            min-width: 280px;
            max-width: 360px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
            animation: toastIn .2s ease;
            transition: opacity .3s, transform .3s;
        }

        .toast.out {
            opacity: 0;
            transform: translateX(20px);
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
        }

        .toast-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: .05rem;
        }

        .toast.toast-success .toast-icon {
            color: #4caf87;
        }

        .toast.toast-error .toast-icon {
            color: #e05656;
        }

        .toast.toast-info .toast-icon {
            color: #6ea4f0;
        }

        .toast-body {
            flex: 1;
        }

        .toast-title {
            font-size: .83rem;
            font-weight: 700;
            margin-bottom: .15rem;
        }

        .toast-msg {
            font-size: .77rem;
            color: var(--text-2);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-3);
            font-size: 1rem;
            cursor: pointer;
            padding: 0;
            margin-top: -.05rem;
            transition: color .15s;
        }

        .toast-close:hover {
            color: var(--text);
        }

        /* ─── CONFIRM DIALOG ─────────────────────── */
        .confirm-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .65);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .confirm-box {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 16px;
            padding: 1.5rem;
            width: 320px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
        }

        .confirm-icon {
            font-size: 2rem;
            margin-bottom: .75rem;
        }

        .confirm-icon.warn {
            color: var(--red);
        }

        .confirm-icon.info {
            color: #6ea4f0;
        }

        .confirm-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .4rem;
        }

        .confirm-msg {
            font-size: .83rem;
            color: var(--text-2);
            margin-bottom: 1.2rem;
            line-height: 1.6;
        }

        .confirm-actions {
            display: flex;
            gap: .5rem;
            justify-content: flex-end;
        }

        /* ─── MEMBER DETAIL MODAL ────────────────── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .65);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 18px;
            width: 100%;
            max-width: 500px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
            overflow: hidden;
        }

        .modal-head {
            padding: 1.1rem 1.3rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-head h2 {
            font-size: .95rem;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-2);
            font-size: 1.2rem;
            cursor: pointer;
            padding: .1rem;
        }

        .modal-body {
            padding: 1.3rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-foot {
            padding: .9rem 1.3rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: .45rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        @media (max-width: 520px) {
            .modal-foot { justify-content: stretch; }
            .modal-foot .btn { flex: 1 1 auto; justify-content: center; min-width: 0; }
            .modal-box { border-radius: 14px; }
        }

        /* detail grid inside modal */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .7rem;
            margin-top: .75rem;
        }

        .detail-cell {
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: .8rem;
        }

        .detail-label {
            font-size: .68rem;
            color: var(--text-3);
            margin-bottom: .2rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .detail-val {
            font-size: .88rem;
            font-weight: 600;
        }

        /* ─── BRANCHES ───────────────────────────── */
        .branch-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.1rem 1.2rem;
            transition: all .15s;
        }

        .branch-card:hover {
            border-color: rgba(204, 26, 26, .25);
            transform: translateY(-2px);
        }

        .branch-name {
            font-weight: 700;
            font-size: .95rem;
        }

        .branch-city {
            font-size: .78rem;
            color: var(--text-2);
            margin-top: .15rem;
        }

        .branch-addr {
            font-size: .78rem;
            color: var(--text-3);
            margin-top: .5rem;
        }

        /* ─── REVENUE BAR ────────────────────────── */
        .rev-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .55rem;
        }

        .rev-label {
            font-size: .7rem;
            color: var(--text-2);
            width: 34px;
        }

        .rev-track {
            flex: 1;
            background: var(--input-bg);
            border-radius: 99px;
            height: 8px;
            overflow: hidden;
        }

        .rev-fill {
            height: 100%;
            border-radius: 99px;
            background: var(--red);
        }

        .rev-val {
            font-size: .7rem;
            color: var(--text-2);
            text-align: right;
            min-width: 70px;
        }

        /* ─── RATING BAR ─────────────────────────── */
        .rb-row {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .4rem;
        }

        .rb-lbl {
            font-size: .72rem;
            color: var(--text-2);
            width: 36px;
        }

        .rb-track {
            flex: 1;
            background: var(--input-bg);
            height: 6px;
            border-radius: 99px;
            overflow: hidden;
        }

        .rb-fill {
            height: 100%;
            border-radius: 99px;
            background: var(--red);
        }

        .rb-pct {
            font-size: .7rem;
            color: var(--text-3);
            width: 30px;
            text-align: right;
        }

        /* ─── SETTINGS ───────────────────────────── */
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 0;
            border-bottom: 1px solid var(--border);
        }

        .settings-row:last-child {
            border-bottom: none;
        }

        .settings-lbl {
            font-size: .85rem;
            font-weight: 500;
        }

        .settings-sub {
            font-size: .72rem;
            color: var(--text-2);
            margin-top: .1rem;
        }

        /* ─── EMPTY STATE ────────────────────────── */
        .empty {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--text-3);
            font-size: .83rem;
        }

        .empty i {
            font-size: 2rem;
            margin-bottom: .5rem;
            display: block;
        }

        /* ─── OVERLAY ────────────────────────────── */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 95;
        }

        .overlay.open {
            display: block;
        }

        /* ─── PENDING PULSE ──────────────────────── */
        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(204, 26, 26, .5)
            }

            50% {
                box-shadow: 0 0 0 4px rgba(204, 26, 26, 0)
            }
        }

        .pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--red);
            animation: pulse 2s infinite;
            margin-left: .4rem;
            vertical-align: middle;
        }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media(max-width:900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: none;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                left: 0;
            }

            .topbar-search {
                display: none;
            }

            .topbar .notif-btn {
                margin-left: auto;
            }

            .burger {
                display: flex !important;
            }

            .g-4 {
                grid-template-columns: repeat(2, 1fr);
            }

            .g-2-1,
            .g-1-2 {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:540px) {

            .g-4,
            .g-3 {
                grid-template-columns: 1fr 1fr;
            }

            .g-2 {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 1rem;
            }

            .stat-val {
                font-size: 1.5rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        .burger {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* ─── MEMBERS TAB MOBILE ─────────────────── */
        .members-sec-head {
            flex-wrap: wrap;
            gap: .6rem;
        }

        .members-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        @media (max-width: 540px) {
            .members-sec-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .members-actions {
                width: 100%;
            }

            .members-actions select,
            .members-actions button {
                flex: 1;
                justify-content: center;
            }

            /* Hide less-critical columns on very small screens */
            .members-tbl th:nth-child(2),
            .members-tbl td:nth-child(2),
            .members-tbl th:nth-child(4),
            .members-tbl td:nth-child(4),
            .members-tbl th:nth-child(5),
            .members-tbl td:nth-child(5) {
                display: none;
            }
        }

        /* Pending modal detail highlight */
        .pending-detail-banner {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            background: rgba(214, 161, 0, .08);
            border: 1px solid rgba(214, 161, 0, .25);
            border-radius: 10px;
            padding: .75rem .9rem;
            margin-top: .85rem;
            font-size: .8rem;
            color: rgba(214, 161, 0, .9);
            line-height: 1.55;
        }

        .pending-detail-banner i {
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: .05rem;
        }

        .pending-detail-banner strong {
            color: #d6a100;
        }

        /* ─── SCHEDULES TAB MOBILE ───────────────── */
        .sched-sec-head {
            flex-wrap: wrap;
            gap: .6rem;
        }

        .sched-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .sched-show-sm {
            display: none;
        }

        @media (max-width: 900px) {
            .sched-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            .sched-sec-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .sched-actions {
                width: 100%;
            }

            .sched-actions .btn {
                flex: 1;
                justify-content: center;
                font-size: .72rem;
                padding: .4rem .5rem;
            }

            .sched-hide-sm {
                display: none;
            }

            .sched-show-sm {
                display: block;
            }
        }

        /* ─── SETTINGS TAB MOBILE ────────────────── */
        @media (max-width: 900px) {
            #page-settings .grid.g-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            .settings-row {
                flex-wrap: wrap;
                gap: .5rem;
            }

            .settings-row > div {
                flex: 1 1 0;
                min-width: 0;
            }

            .settings-sub {
                word-break: break-word;
            }

            .settings-row .btn,
            .settings-row .badge {
                flex-shrink: 0;
                align-self: center;
            }
        }

        /* Scrollable tab strip — no wrapping, just swipe */
        #page-reports .dash-tabs {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        #page-reports .dash-tabs::-webkit-scrollbar {
            display: none;
        }

        #page-reports .dash-tab {
            flex-shrink: 0;
        }

        @media (max-width: 900px) {
            /* Single-column report grids */
            #page-reports .grid.g-2,
            #page-reports .grid.g-3,
            #page-reports .grid.g-2-1,
            #page-reports .grid.g-1-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            /* Stat grids: 2 columns max */
            #page-reports .grid.g-4 {
                grid-template-columns: 1fr 1fr;
            }

            /* Filter form: single column on very small screens */
            #page-reports .report-filters-body {
                grid-template-columns: 1fr !important;
            }

            /* Export button rows */
            #page-reports [style*="justify-content:flex-end"] {
                justify-content: flex-start !important;
            }

            /* Tighten stat values */
            #page-reports .stat-val {
                font-size: 1.4rem;
            }

            #page-reports .stat-lbl {
                font-size: .62rem;
            }

            /* Tables inside reports: make them block-scroll */
            #page-reports .tbl-wrap table,
            #page-reports .card > table {
                min-width: 420px;
            }

            /* Report section header stacks on mobile */
            #page-reports .sec-head {
                flex-direction: column;
                align-items: flex-start;
                gap: .4rem;
            }

            /* Report filter buttons stretch full width */
            #page-reports .card-body [style*="display:flex"][style*="flex-wrap:wrap"] {
                flex-direction: column;
            }

            #page-reports .card-body [style*="display:flex"][style*="flex-wrap:wrap"] .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ── INVENTORY – mobile search/filter ── */
        .inv-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .6rem;
        }

        .inv-filters {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        @media (max-width: 600px) {
            #page-inventory .sec-head {
                flex-wrap: wrap;
                gap: .5rem;
            }

            #page-inventory .sec-head .btn {
                width: 100%;
                justify-content: center;
            }

            .inv-card-head {
                flex-direction: column;
                align-items: stretch;
            }

            .inv-filters {
                flex-direction: column;
                width: 100%;
            }

            .inv-filters input,
            .inv-filters select {
                width: 100% !important;
                box-sizing: border-box;
            }

            /* Hide less-critical columns on mobile */
            #page-inventory table th:nth-child(2),
            #page-inventory table td:nth-child(2),
            #page-inventory table th:nth-child(4),
            #page-inventory table td:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 400px) {
            /* Also hide Status on very tiny screens */
            #page-inventory table th:nth-child(5),
            #page-inventory table td:nth-child(5) {
                display: none;
            }
        }

        /* ── ADMIN NOTIFICATION PANEL ── */
        .admin-notif-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 360px;
            max-width: 100vw;
            background: var(--surface);
            border-left: 1px solid var(--border);
            z-index: 500;
            display: flex;
            flex-direction: column;
            transform: translateX(105%);
            transition: transform .3s cubic-bezier(.25,.46,.45,.94);
            box-shadow: -12px 0 50px rgba(0,0,0,.35);
        }

        .admin-notif-panel.open {
            transform: translateX(0);
        }

        .admin-notif-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 499;
        }

        .admin-notif-overlay.active {
            display: block;
        }

        .admin-notif-head {
            padding: 1.1rem 1.25rem .9rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .admin-notif-title {
            font-size: .95rem;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .admin-notif-title i {
            color: var(--red);
        }

        .admin-notif-close {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            transition: all .15s;
        }

        .admin-notif-close:hover {
            color: var(--text);
            border-color: rgba(255,255,255,.2);
        }

        .admin-notif-list {
            flex: 1;
            overflow-y: auto;
            padding: .65rem;
        }

        .admin-notif-list::-webkit-scrollbar { width: 3px; }
        .admin-notif-list::-webkit-scrollbar-track { background: transparent; }
        .admin-notif-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        .admin-notif-item {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            padding: .9rem 1rem;
            border-radius: 12px;
            margin-bottom: .4rem;
            cursor: pointer;
            transition: background .15s;
            background: var(--input-bg);
            border: 1px solid var(--border);
        }

        .admin-notif-item:hover {
            background: var(--surface);
            border-color: rgba(204,26,26,.2);
        }

        .admin-notif-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .admin-notif-item-icon.warn { background: rgba(255,193,7,.12); color: #ffc107; }
        .admin-notif-item-icon.danger { background: rgba(220,53,69,.12); color: #e05656; }
        .admin-notif-item-icon.info { background: rgba(74,158,218,.12); color: #4a9eda; }
        .admin-notif-item-icon.success { background: rgba(46,204,113,.12); color: #2ecc71; }

        .admin-notif-item-body {
            flex: 1;
            min-width: 0;
        }

        .admin-notif-item-title {
            font-size: .84rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: .15rem;
        }

        .admin-notif-item-sub {
            font-size: .74rem;
            color: var(--text-2);
            line-height: 1.5;
        }

        .admin-notif-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-3);
        }

        .admin-notif-empty i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: .75rem;
        }

        @media (max-width: 575px) {
            .admin-notif-panel {
                width: 100vw;
                border-left: none;
            }
        }
    </style>
</head>

<body>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- ══ SIDEBAR ══════════════════════════════ -->
    <aside class="sidebar" id="sidebar">
        <div class="sb-brand">
            <a class="sb-brand" href="admin.php">
                <img class="theme-logo" src="assets/FitSYNC%20Emblem%20Light.svg" alt="FitSync" width="30" height="30" id="sidebarLogo" data-logo-dark="assets/FitSYNC%20Emblem%20Light.svg" data-logo-light="assets/FitSYNC%20Emblem.svg" />
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </a>
        </div>

        <nav style="overflow-y:auto;flex:1">
            <div class="sb-section">
                <div class="sb-label">Overview</div>
                <button class="sb-link active" onclick="showPage('dashboard',this)"><i class="ti ti-layout-dashboard"></i> Dashboard</button>
            </div>
            <div class="sb-section">
                <div class="sb-label">Management</div>
                <button class="sb-link" onclick="showPage('members',this)">
                    <i class="ti ti-users"></i> Members
                    <?php if ($dashboard['pending_approvals'] > 0): ?>
                    <span class="sb-pill"><?= $dashboard['pending_approvals'] ?></span>
                    <?php endif ?>
                </button>
                <button class="sb-link" onclick="showPage('branches',this)"><i class="ti ti-building-store"></i> Branches</button>
                <button class="sb-link" onclick="showPage('schedules',this)"><i class="ti ti-calendar-event"></i> Schedules</button>
                <button class="sb-link" onclick="showPage('feedbacks',this)"><i class="ti ti-message-star"></i> Feedbacks</button>
                <button class="sb-link" onclick="showPage('reports',this)"><i class="ti ti-chart-pie"></i> Reports</button>
            </div>
            <div class="sb-section">
                <div class="sb-label">System</div>
                <button class="sb-link" onclick="showPage('settings',this)"><i class="ti ti-settings"></i> Settings</button>
            </div>
            <div class="sb-section">
                <div class="sb-label">Shop</div>
                <button class="sb-link" onclick="showPage('inventory',this)">
                    <i class="ti ti-box"></i> Inventory
                    <?php if (($shopStats['low_stock'] + $shopStats['out_of_stock']) > 0): ?>
                    <span class="sb-pill"><?= $shopStats['low_stock'] + $shopStats['out_of_stock'] ?></span>
                    <?php endif ?>
                </button>
                <button class="sb-link" onclick="showPage('shop-orders',this)">
                    <i class="ti ti-shopping-bag"></i> Shop Orders
                    <?php if ($shopStats['pending_orders'] > 0): ?>
                    <span class="sb-pill"><?= $shopStats['pending_orders'] ?></span>
                    <?php endif ?>
                </button>
            </div>
        </nav>

        <div class="sb-footer">
            <div class="sb-theme-row">
                <span class="sb-theme-label"><i class="ti ti-moon" style="font-size:.9rem"></i> Dark mode</span>
                <button class="pill-toggle" onclick="toggleTheme()">
                    <div class="pill-knob"></div>
                </button>
            </div>
            <button class="sb-link logout" onclick="logoutAdmin()"><i class="ti ti-logout"></i> Logout</button>
        </div>
    </aside>

    <!-- ══ TOPBAR ════════════════════════════════ -->
    <div class="topbar">
        <button class="burger" onclick="openSidebar()"><i class="ti ti-menu-2"></i></button>
        <div>
            <div class="topbar-title" id="tb-title">Dashboard</div>
            <div class="topbar-crumb">FitSync Admin › <span id="tb-crumb">Overview</span></div>
        </div>
        <div class="topbar-search">
            <i class="ti ti-search si"></i>
            <input type="text" placeholder="Search members…" id="search-input" oninput="filterMembers()" />
        </div>
        <button class="notif-btn" id="adminNotifBell" onclick="toggleAdminNotifPanel()" title="Notifications" aria-label="Notifications">
            <i class="ti ti-bell"></i>
            <div class="notif-dot" id="adminNotifDot"></div>
        </button>
        <div class="avatar">A</div>
    </div>

    <!-- ══ ADMIN NOTIFICATION PANEL ════════════════ -->
    <div class="admin-notif-overlay" id="adminNotifOverlay" onclick="closeAdminNotifPanel()"></div>
    <aside class="admin-notif-panel" id="adminNotifPanel" aria-label="Admin Notifications">
        <div class="admin-notif-head">
            <div class="admin-notif-title"><i class="ti ti-bell"></i> Notifications</div>
            <button class="admin-notif-close" onclick="closeAdminNotifPanel()"><i class="ti ti-x"></i></button>
        </div>
        <div class="admin-notif-list" id="adminNotifList"></div>
    </aside>


    <!-- ══ MAIN ══════════════════════════════════ -->
    <main class="main">

        <!-- ─── DASHBOARD ──────────────────────────── -->
        <div class="page active" id="page-dashboard">

            <!-- Urgent alert (only when there are pending items) -->
            <div class="alert-banner">
                <i class="ti ti-alert-triangle"></i>
                <div>
                    <strong><?= number_format($dashboard['pending_payments']) ?> pending payments</strong> and <strong><?= number_format($dashboard['pending_approvals']) ?> new registrations</strong> require your attention.
                    <a onclick="showPage('members',null)">Review now →</a>
                </div>
            </div>

            <!-- Dashboard sub-tabs -->
            <div class="dash-tabs">
                <button class="dash-tab active" onclick="switchDashTab('overview',this)">Overview</button>
                <button class="dash-tab" onclick="switchDashTab('attendance',this)">Attendance</button>
                <button class="dash-tab" onclick="switchDashTab('memberships',this)">Memberships</button>
            </div>

            <!-- ── OVERVIEW TAB ── -->
            <div class="dash-panel active" id="dt-overview">
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-users"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['total_members']) ?></div>
                        <div class="stat-lbl">Total Members</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> +<?= number_format(end($signupData) ?: 0) ?> this month</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-cash"></i></div>
                        <div class="stat-val">₱<?= number_format($dashboard['revenue_month']) ?></div>
                        <div class="stat-lbl">Monthly Revenue</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> +8% vs last month</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['active_branches']) ?></div>
                        <div class="stat-lbl">Active Branches</div>
                        <div class="stat-sub"><i class="ti ti-point"></i> All operational</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-star"></i></div>
                        <div class="stat-val"><?= $feedbacks ? number_format(array_sum(array_column($feedbacks, 'rating')) / max(1, count($feedbacks)), 1) : '0.0' ?></div>
                        <div class="stat-lbl">Avg. Rating</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> <?= number_format($dashboard['visible_feedbacks']) ?> reviews</div>
                    </div>
                </div>

                <div class="grid g-2-1" style="margin-bottom:1.25rem">
                    <div class="card">
                        <div class="card-head">
                            <div>
                                <div class="card-title">New sign-ups</div>
                                <div class="card-sub">Last 12 months</div>
                            </div>
                            <span class="badge active">+<?= number_format(end($signupData) ?: 0) ?> this month</span>
                        </div>
                        <div class="card-body">
                            <div class="sparkline" id="spark"></div>
                            <div class="spark-labels" id="spark-labels"></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">Quick actions</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:.5rem">
                            <button class="qa" onclick="showPage('members',null)">
                                <div class="qa-icon"><i class="ti ti-user-plus"></i></div>
                                <div>
                                    <div class="qa-lbl">Add Member</div>
                                    <div class="qa-sub">Register a walk-in</div>
                                </div>
                            </button>
                            <button class="qa" onclick="showPage('feedbacks',null)">
                                <div class="qa-icon"><i class="ti ti-message-star"></i></div>
                                <div>
                                    <div class="qa-lbl">Review Feedbacks</div>
                                    <div class="qa-sub"><?= number_format($dashboard['visible_feedbacks']) ?> total reviews</div>
                                </div>
                            </button>
                            <button class="qa">
                                <div class="qa-icon"><i class="ti ti-external-link"></i></div>
                                <div>
                                    <div class="qa-lbl">View Public Site</div>
                                    <div class="qa-sub">Open in new tab</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid g-2">
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">Pending approvals</div>
                            <span class="badge pending"><?= number_format($dashboard['pending_payments']) ?> pending</span>
                        </div>
                        <div class="card-body" id="pending-list"></div>
                    </div>
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">Recent registrations</div>
                            <button class="btn sm ghost" onclick="showPage('members',null)">View all <i class="ti ti-chevron-right"></i></button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recent-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /overview tab -->

            <!-- ── ATTENDANCE TAB ── -->
            <div class="dash-panel" id="dt-attendance">
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-login-2"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['attendance_today']) ?></div>
                        <div class="stat-lbl">Today's Check-ins</div>
                        <div class="stat-sub"><i class="ti ti-calendar-check"></i> <?= date('M j, Y') ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-flame"></i></div>
                        <div class="stat-val"><?= number_format((int) ($activeMembers[0]['visits'] ?? 0)) ?></div>
                        <div class="stat-lbl">Top 30-Day Visits</div>
                        <div class="stat-sub up"><i class="ti ti-run"></i> Most active member</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-user-pause"></i></div>
                        <div class="stat-val"><?= number_format(count($inactiveMembers)) ?></div>
                        <div class="stat-lbl">Inactive Members</div>
                        <div class="stat-sub down"><i class="ti ti-clock"></i> 30+ days no visit</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['attendance_month']) ?></div>
                        <div class="stat-lbl">Visits / 30 Days</div>
                        <div class="stat-sub up"><i class="ti ti-map-pin"></i> All branches</div>
                    </div>
                </div>

                <div class="grid g-2" style="margin-bottom:1.25rem">
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Recent check-ins</div>
                            <span class="badge active">Live</span>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Branch</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="att-tbody"></tbody>
                        </table>
                    </div>
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Branch activity</div>
                            <div class="card-sub">Today / 30 days</div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Today</th>
                                    <th>30d</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="branch-att-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="grid g-2">
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Most active <span style="color:var(--text-3);font-weight:400;font-size:.78rem">last 30 days</span></div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Visits</th>
                                    <th>Last visit</th>
                                </tr>
                            </thead>
                            <tbody id="active-tbody"></tbody>
                        </table>
                    </div>
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Inactive members <span style="color:var(--text-3);font-weight:400;font-size:.78rem">30+ days</span></div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Last visit</th>
                                </tr>
                            </thead>
                            <tbody id="inactive-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /attendance tab -->

            <!-- ── MEMBERSHIPS TAB ── -->
            <div class="dash-panel" id="dt-memberships">
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-id-badge-2"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['active_memberships']) ?></div>
                        <div class="stat-lbl">Active Memberships</div>
                    </div>
                    <div class="stat urgent">
                        <div class="stat-icon"><i class="ti ti-cash-banknote"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['pending_payments']) ?></div>
                        <div class="stat-lbl">Pending Payments</div>
                        <div class="stat-sub down"><i class="ti ti-alert-circle"></i> Needs review</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-calendar-time"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['expiring_soon']) ?></div>
                        <div class="stat-lbl">Expiring in 7 Days</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-id-badge-off"></i></div>
                        <div class="stat-val"><?= number_format($dashboard['expired_memberships']) ?></div>
                        <div class="stat-lbl">Expired</div>
                    </div>
                </div>

                <div class="grid g-2" style="margin-bottom:1.25rem">
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Pending payment approvals</div>
                            <span class="badge pending"><?= number_format($dashboard['pending_payments']) ?></span>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $pendingMembers = array_values(array_filter($members, static fn(array $m): bool => $m['payment'] === 'pending')); ?>
                                <?php if ($pendingMembers): ?>
                                    <?php foreach ($pendingMembers as $pm): ?>
                                        <?php $pmName = trim($pm['fname'] . ' ' . $pm['lname']); ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600"><?= htmlspecialchars($pmName) ?> <span class="pulse-dot"></span></div>
                                                <div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($pm['email']) ?></div>
                                            </td>
                                            <td><span class="plan-badge <?= htmlspecialchars($pm['planCls']) ?>"><?= htmlspecialchars($pm['plan']) ?></span></td>
                                            <td>₱<?= number_format((float) $pm['amount'], 2) ?></td>
                                            <td>
                                                <div class="actions">
                                                    <?php if ($pm['proof_file']): ?>
                                                        <button class="tbtn" title="View Receipt" onclick="viewReceipt('<?= htmlspecialchars($pm['proof_file']) ?>', '<?= htmlspecialchars($pm['proof_date']) ?>', '<?= htmlspecialchars($pmName) ?>')"><i class="ti ti-file-invoice"></i></button>
                                                    <?php endif ?>
                                                    <button class="tbtn success" title="Approve" onclick="paymentAction('approve_payment',<?= (int) ($pm['membership_id'] ?? 0) ?>)"><i class="ti ti-check"></i></button>
                                                    <button class="tbtn danger" title="Reject" onclick="paymentAction('reject_payment',<?= (int) ($pm['membership_id'] ?? 0) ?>)"><i class="ti ti-x"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <tr><td colspan="4"><div class="empty"><i class="ti ti-check"></i>All payments approved</div></td></tr>
                                <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Expiring soon</div>
                            <div class="card-sub">Next 7 days</div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Carlos Tan</td>
                                    <td><span class="plan-badge mo1">1 Month</span></td>
                                    <td style="color:#d6a100">Jun 5</td>
                                </tr>
                                <tr>
                                    <td>Liza Gomez</td>
                                    <td><span class="plan-badge mo3">3 Months</span></td>
                                    <td style="color:#d6a100">Jun 7</td>
                                </tr>
                                <tr>
                                    <td>Mark Uy</td>
                                    <td><span class="plan-badge mo6">6 Months</span></td>
                                    <td>Jun 9</td>
                                </tr>
                                <tr>
                                    <td>Nina Bautista</td>
                                    <td><span class="plan-badge yr">12 Months</span></td>
                                    <td>Jun 10</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /memberships tab -->

        </div><!-- /dashboard page -->

        <!-- ─── MEMBERS ────────────────────────────── -->
        <div class="page" id="page-members">
            <div class="sec-head members-sec-head">
                <div class="sec-title">All Members <small id="member-count"></small></div>
                <div class="members-actions">
                    <select class="btn" id="status-filter" onchange="filterMembers()" style="padding:.38rem .8rem;border-radius:9px;background:var(--surface);color:var(--text);border:1px solid var(--border);font-size:.78rem;appearance:none">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="frozen">Frozen</option>
                        <option value="pending">Pending</option>
                    </select>
                    <button class="btn primary" onclick="openMemberCreateModal()"><i class="ti ti-plus"></i> Add Member</button>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="members-tbl">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Joined</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="members-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- ─── BRANCHES ──────────────────────────── -->
        <div class="page" id="page-branches">
            <div class="sec-head">
                <div class="sec-title">Branches <small><?= number_format($dashboard['active_branches']) ?> active</small></div>
            </div>
            <div class="grid g-3" id="branches-grid"></div>
        </div>

        <!-- ─── SCHEDULES ─────────────────────────── -->
        <div class="page" id="page-schedules">
            <div class="sec-head sched-sec-head">
                <div class="sec-title">Schedules</div>
                <div class="sched-actions">
                    <button class="btn primary" onclick="openClassModal()"><i class="ti ti-plus"></i> Class</button>
                    <button class="btn primary" onclick="openScheduleModal()"><i class="ti ti-calendar-plus"></i> Schedule</button>
                    <button class="btn primary" onclick="openAnnouncementModal()"><i class="ti ti-speakerphone"></i> Announcement</button>
                </div>
            </div>
            <div class="grid g-2 sched-grid" style="margin-bottom:1.25rem">
                <div class="tbl-wrap">
                    <div class="card-head" style="border-bottom:1px solid var(--border)">
                        <div class="card-title">Upcoming class schedules</div>
                    </div>
                    <table class="sched-tbl">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th class="sched-hide-sm">Branch</th>
                                <th>Date</th>
                                <th class="sched-hide-sm">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($classSchedules): ?>
                                <?php foreach ($classSchedules as $schedule): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600"><?= htmlspecialchars((string) $schedule['title']) ?></div>
                                            <div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars((string) ($schedule['trainer_name'] ?? '')) ?></div>
                                            <div class="sched-show-sm"><span class="badge <?= htmlspecialchars((string) $schedule['status']) ?>" style="margin-top:.25rem"><?= htmlspecialchars(ucfirst((string) $schedule['status'])) ?></span></div>
                                        </td>
                                        <td class="sched-hide-sm"><?= htmlspecialchars((string) $schedule['branch_name']) ?></td>
                                        <td style="font-size:.8rem;color:var(--text-2);white-space:nowrap"><?= htmlspecialchars(date('M j, Y', strtotime((string) $schedule['scheduled_date'])) . ' ' . substr((string) $schedule['start_time'], 0, 5)) ?></td>
                                        <td class="sched-hide-sm"><span class="badge <?= htmlspecialchars((string) $schedule['status']) ?>"><?= htmlspecialchars(ucfirst((string) $schedule['status'])) ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <button class="tbtn" title="Edit" onclick="openScheduleModal(<?= (int) $schedule['id'] ?>)"><i class="ti ti-pencil"></i></button>
                                                <button class="tbtn" title="Complete" onclick="scheduleStatusAction(<?= (int) $schedule['id'] ?>,'completed')"><i class="ti ti-check"></i></button>
                                                <button class="tbtn" title="Cancel" onclick="scheduleStatusAction(<?= (int) $schedule['id'] ?>,'cancelled')"><i class="ti ti-ban"></i></button>
                                                <button class="tbtn danger" title="Delete" onclick="deleteScheduleAction(<?= (int) $schedule['id'] ?>)"><i class="ti ti-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr><td colspan="5"><div class="empty"><i class="ti ti-calendar-event"></i>No class schedules found</div></td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
                <div class="tbl-wrap">
                    <div class="card-head" style="border-bottom:1px solid var(--border)">
                        <div class="card-title">Classes</div>
                    </div>
                    <table class="sched-tbl">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th class="sched-hide-sm">Branch</th>
                                <th class="sched-hide-sm">Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($classes): ?>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600"><?= htmlspecialchars((string) $class['title']) ?></div>
                                            <div class="sched-show-sm" style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars((string) ($class['branch_name'] ?? 'Unassigned')) ?></div>
                                        </td>
                                        <td class="sched-hide-sm"><?= htmlspecialchars((string) ($class['branch_name'] ?? 'Unassigned')) ?></td>
                                        <td class="sched-hide-sm"><?= $class['capacity'] !== null ? number_format((int) $class['capacity']) : 'Open' ?></td>
                                        <td><span class="badge <?= (int) $class['is_active'] === 1 ? 'active' : 'expired' ?>"><?= (int) $class['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <button class="tbtn" title="Edit" onclick="openClassModal(<?= (int) $class['id'] ?>)"><i class="ti ti-pencil"></i></button>
                                                <button class="tbtn" title="<?= (int) $class['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>" onclick="classActiveAction(<?= (int) $class['id'] ?>,<?= (int) $class['is_active'] === 1 ? 0 : 1 ?>)"><i class="ti <?= (int) $class['is_active'] === 1 ? 'ti-eye-off' : 'ti-eye' ?>"></i></button>
                                                <button class="tbtn danger" title="Delete" onclick="deleteClassAction(<?= (int) $class['id'] ?>)"><i class="ti ti-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr><td colspan="5"><div class="empty"><i class="ti ti-barbell"></i>No classes found</div></td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="grid g-2 sched-grid">
                <div class="tbl-wrap">
                    <div class="card-head" style="border-bottom:1px solid var(--border)">
                        <div class="card-title">Announcements</div>
                    </div>
                    <table class="sched-tbl">
                        <thead><tr><th>Title</th><th class="sched-hide-sm">Branch</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if ($announcements): ?>
                                <?php foreach ($announcements as $notice): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600"><?= htmlspecialchars((string) $notice['title']) ?></div>
                                            <div class="sched-show-sm" style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($notice['branch_name'] !== null ? (string) $notice['branch_name'] : 'All Branches') ?></div>
                                        </td>
                                        <td class="sched-hide-sm"><?= htmlspecialchars($notice['branch_name'] !== null ? (string) $notice['branch_name'] : 'All Branches') ?></td>
                                        <td><span class="badge <?= (int) $notice['is_active'] === 1 ? 'active' : 'expired' ?>"><?= (int) $notice['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <button class="tbtn" title="Edit" onclick="openAnnouncementModal(<?= (int) $notice['id'] ?>)"><i class="ti ti-pencil"></i></button>
                                                <button class="tbtn" title="<?= (int) $notice['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>" onclick="announcementActiveAction(<?= (int) $notice['id'] ?>,<?= (int) $notice['is_active'] === 1 ? 0 : 1 ?>)"><i class="ti <?= (int) $notice['is_active'] === 1 ? 'ti-eye-off' : 'ti-eye' ?>"></i></button>
                                                <button class="tbtn danger" title="Delete" onclick="deleteAnnouncementAction(<?= (int) $notice['id'] ?>)"><i class="ti ti-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr><td colspan="4"><div class="empty"><i class="ti ti-speakerphone"></i>No announcements found</div></td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
                <div class="tbl-wrap">
                    <div class="card-head" style="border-bottom:1px solid var(--border)">
                        <div class="card-title">Operating hours</div>
                        <button class="btn sm" onclick="openOperatingHourModal()"><i class="ti ti-plus"></i> Edit Hours</button>
                    </div>
                    <table class="sched-tbl">
                        <thead><tr><th>Branch</th><th>Day</th><th>Hours</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>
                            <?php if ($operatingHours): ?>
                                <?php foreach ($operatingHours as $hour): ?>
                                    <tr>
                                        <td style="font-weight:600"><?= htmlspecialchars((string) $hour['branch_name']) ?></td>
                                        <td><?= htmlspecialchars($dayNames[(int) $hour['day_of_week']] ?? (string) $hour['day_of_week']) ?></td>
                                        <td style="font-size:.8rem;color:var(--text-2);white-space:nowrap"><?= (int) $hour['is_closed'] === 1 ? 'Closed' : htmlspecialchars(substr((string) $hour['open_time'], 0, 5) . ' – ' . substr((string) $hour['close_time'], 0, 5)) ?></td>
                                        <td><button class="tbtn" title="Edit" onclick="openOperatingHourModal(<?= (int) $hour['branch_id'] ?>,<?= (int) $hour['day_of_week'] ?>)"><i class="ti ti-pencil"></i></button></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr><td colspan="4"><div class="empty"><i class="ti ti-clock"></i>No operating hours configured</div></td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ─── FEEDBACKS ─────────────────────────── -->
        <div class="page" id="page-feedbacks">
            <div class="sec-head">
                <div class="sec-title">Feedbacks &amp; Reviews</div>
            </div>
            <?php
                $allRatings = array_merge(
                    array_column($feedbacks, 'rating'),
                    array_column($orderReviews, 'rating')
                );
                $totalReviews = count($allRatings);
                $avgRating    = $totalReviews > 0 ? array_sum($allRatings) / $totalReviews : 0;
                $ratingCounts = array_count_values($allRatings);
            ?>
            <div class="grid g-2-1">
                <div>
                    <div class="dash-tabs" style="margin-bottom:1rem">
                        <button class="dash-tab active" onclick="switchFbTab('gym',this)">Gym Feedback <span class="badge" style="margin-left:.35rem"><?= number_format(count($feedbacks)) ?></span></button>
                        <button class="dash-tab" onclick="switchFbTab('orders',this)">Product Reviews <span class="badge" style="margin-left:.35rem"><?= number_format(count($orderReviews)) ?></span></button>
                    </div>
                    <div id="fb-star-filter" style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:1rem">
                        <button class="btn sm active" id="fsf-0" onclick="setFbStarFilter(0,this)">All</button>
                        <button class="btn sm" id="fsf-5" onclick="setFbStarFilter(5,this)">5 ★</button>
                        <button class="btn sm" id="fsf-4" onclick="setFbStarFilter(4,this)">4 ★</button>
                        <button class="btn sm" id="fsf-3" onclick="setFbStarFilter(3,this)">3 ★</button>
                        <button class="btn sm" id="fsf-2" onclick="setFbStarFilter(2,this)">2 ★</button>
                        <button class="btn sm" id="fsf-1" onclick="setFbStarFilter(1,this)">1 ★</button>
                    </div>
                    <div id="fb-list"></div>
                    <div id="or-list" style="display:none"></div>
                </div>
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">Rating breakdown</div>
                    </div>
                    <div class="card-body" style="text-align:center;margin-bottom:1rem">
                        <div style="font-size:3.5rem;font-weight:900;line-height:1"><?= number_format($avgRating, 1) ?></div>
                        <div style="color:var(--red);font-size:.9rem;letter-spacing:2px;margin:.3rem 0">★★★★★</div>
                        <div style="font-size:.72rem;color:var(--text-3)">Based on <?= number_format($totalReviews) ?> reviews (all sources)</div>
                    </div>
                    <div class="card-body" style="padding-top:0">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                        <?php $cnt = $ratingCounts[$star] ?? 0; $pct = $totalReviews > 0 ? round($cnt / $totalReviews * 100) : 0; ?>
                        <div class="rb-row"><span class="rb-lbl"><?= $star ?>★</span>
                            <div class="rb-track"><div class="rb-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="rb-pct"><?= $pct ?>%</span>
                        </div>
                        <?php endfor ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── REPORTS ───────────────────────────── -->
        <div class="page" id="page-reports">
            <?php $overview = $reports['overview']; $membershipReport = $reports['memberships']; $revenueReport = $reports['revenue']; $attendanceReport = $reports['attendance']; $classReport = $reports['classes']; ?>
            <div class="sec-head">
                <div>
                    <div class="sec-title">Reports</div>
                    <div style="font-size:.8rem;color:var(--text-3);margin-top:.25rem">Live gym analytics for memberships, revenue, attendance, and classes.</div>
                </div>
            </div>
            <div class="dash-tabs">
                <?php foreach (['overview' => 'Overview', 'memberships' => 'Memberships', 'revenue' => 'Revenue', 'attendance' => 'Attendance', 'classes' => 'Classes'] as $tabKey => $tabLabel): ?>
                    <a class="dash-tab <?= $activeReportTab === $tabKey ? 'active' : '' ?>" href="<?= htmlspecialchars(adminReportUrl($tabKey)) ?>" data-rtab="<?= htmlspecialchars($tabKey) ?>" onclick="switchReportTab('<?= htmlspecialchars($tabKey) ?>',event)" style="text-decoration:none"><?= htmlspecialchars($tabLabel) ?></a>
                <?php endforeach ?>
            </div>
            <form id="rpt-filter-form" method="get" action="admin.php#reports" class="card" style="margin-bottom:1.25rem<?= $activeReportTab === 'overview' ? ';display:none' : '' ?>">
                <input type="hidden" name="report_tab" value="<?= htmlspecialchars($activeReportTab) ?>">
                <div class="card-body report-filters-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:.75rem;align-items:end">
                    <label style="font-size:.72rem;color:var(--text-3);font-weight:700">Start<input class="btn" type="date" name="start" value="<?= htmlspecialchars($reportFilters['range']['start']) ?>" style="width:100%;margin-top:.35rem"></label>
                    <label style="font-size:.72rem;color:var(--text-3);font-weight:700">End<input class="btn" type="date" name="end" value="<?= htmlspecialchars($reportFilters['range']['end']) ?>" style="width:100%;margin-top:.35rem"></label>
                    <label id="rf-branch" style="font-size:.72rem;color:var(--text-3);font-weight:700">Branch<select class="btn" name="branch_id" style="width:100%;margin-top:.35rem"><option value="">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= $reportFilters['branch_id'] === (int) $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option><?php endforeach ?></select></label>
                    <label id="rf-plan" style="font-size:.72rem;color:var(--text-3);font-weight:700;<?= in_array($activeReportTab, ['memberships','revenue']) ? '' : 'display:none' ?>">Plan<select class="btn" name="plan_id" style="width:100%;margin-top:.35rem"><option value="">All plans</option><?php foreach ($membershipPlans as $plan): ?><option value="<?= (int) $plan['id'] ?>" <?= $reportFilters['plan_id'] === (int) $plan['id'] ? 'selected' : '' ?>><?= htmlspecialchars($plan['label']) ?></option><?php endforeach ?></select></label>
                    <label id="rf-class" style="font-size:.72rem;color:var(--text-3);font-weight:700;<?= $activeReportTab === 'classes' ? '' : 'display:none' ?>">Class<select class="btn" name="class_id" style="width:100%;margin-top:.35rem"><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $reportFilters['class_id'] === (int) $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['title']) ?></option><?php endforeach ?></select></label>
                    <label id="rf-status" style="font-size:.72rem;color:var(--text-3);font-weight:700;<?= $activeReportTab === 'memberships' ? '' : 'display:none' ?>">Status<select class="btn" name="status" style="width:100%;margin-top:.35rem"><option value="">All statuses</option><?php foreach (['active', 'expired', 'frozen', 'cancelled', 'pending'] as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $reportFilters['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach ?></select></label>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap"><button class="btn primary" type="submit"><i class="ti ti-filter"></i> Apply</button><a class="btn" href="admin.php?report_tab=<?= htmlspecialchars($activeReportTab) ?>#reports" id="rpt-reset-link"><i class="ti ti-rotate"></i> Reset</a></div>
                </div>
            </form>
            <div class="dash-panel <?= $activeReportTab === 'overview' ? 'active' : '' ?>" id="rt-overview">
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <?php foreach ([['ti-users', number_format($overview['metrics']['total_members']), 'Total Members'], ['ti-user-check', number_format($overview['metrics']['active_members']), 'Active Members'], ['ti-user-x', number_format($overview['metrics']['expired_members']), 'Expired Members'], ['ti-clock-dollar', number_format($overview['metrics']['pending_renewals']), 'Pending Renewals'], ['ti-cash', reportMoney($overview['metrics']['revenue_month']), 'Revenue This Month'], ['ti-report-money', reportMoney($overview['metrics']['revenue_year']), 'Revenue This Year'], ['ti-login-2', number_format($overview['metrics']['attendance_month']), 'Attendance This Month'], ['ti-calendar-event', number_format($overview['metrics']['upcoming_classes']), 'Upcoming Classes']] as $metric): ?>
                        <div class="stat"><div class="stat-icon"><i class="ti <?= htmlspecialchars($metric[0]) ?>"></i></div><div class="stat-val"><?= htmlspecialchars($metric[1]) ?></div><div class="stat-lbl"><?= htmlspecialchars($metric[2]) ?></div></div>
                    <?php endforeach ?>
                </div>
                <div class="grid g-3">
                    <div class="card"><div class="card-head"><div class="card-title">Membership Status Breakdown</div></div><div class="card-body"><?php $statusMax = max(1, ...array_map('intval', array_column($overview['membership_status'], 'total') ?: [1])); ?><?php foreach ($overview['membership_status'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(ucfirst($row['status'])) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['total'] / $statusMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['total']) ?></span></div><?php endforeach ?></div></div>
                    <div class="card"><div class="card-head"><div class="card-title">Revenue Trend Summary</div></div><div class="card-body"><?php $revMax = max(1, ...array_map('floatval', array_column($overview['revenue_trend'], 'revenue') ?: [1])); ?><?php foreach ($overview['revenue_trend'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(date('M Y', strtotime($row['month_key'] . '-01'))) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((float) $row['revenue'] / $revMax) * 100) ?>%"></div></div><span class="rev-val"><?= reportMoney($row['revenue']) ?></span></div><?php endforeach ?></div></div>
                    <div class="card"><div class="card-head"><div class="card-title">Attendance Summary</div></div><div class="card-body"><?php $attMax = max(1, ...array_map('intval', array_column($overview['attendance_summary'], 'visits') ?: [1])); ?><?php foreach ($overview['attendance_summary'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(date('M j', strtotime($row['attendance_date']))) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['visits'] / $attMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['visits']) ?></span></div><?php endforeach ?></div></div>
                </div>
            </div>
            <div class="dash-panel <?= $activeReportTab === 'memberships' ? 'active' : '' ?>" id="rt-memberships">
                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-bottom:1rem;flex-wrap:wrap"><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('memberships', 'csv')) ?>"><i class="ti ti-file-type-csv"></i> CSV</a><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('memberships', 'excel')) ?>"><i class="ti ti-file-spreadsheet"></i> Excel</a></div>
                <div class="grid g-4" style="margin-bottom:1.25rem"><?php foreach ([['ti-user-check', 'active', 'Active Members'], ['ti-user-x', 'expired', 'Expired Members'], ['ti-player-pause', 'frozen', 'Frozen Members'], ['ti-ban', 'cancelled', 'Cancelled Members'], ['ti-user-plus', 'new_this_period', 'New Members'], ['ti-calendar-time', 'expiring_soon', 'Expiring Soon']] as $metric): ?><div class="stat"><div class="stat-icon"><i class="ti <?= $metric[0] ?>"></i></div><div class="stat-val"><?= number_format((int) $membershipReport['metrics'][$metric[1]]) ?></div><div class="stat-lbl"><?= $metric[2] ?></div></div><?php endforeach ?></div>
                <div class="grid g-3">
                    <div class="tbl-wrap"><div class="card-head"><div class="card-title">Recently Joined Members</div></div><table><thead><tr><th>Member</th><th>Plan</th><th>Branch</th><th>Joined</th></tr></thead><tbody><?php foreach ($membershipReport['recent_members'] as $row): ?><tr><td><strong><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['email']) ?></div></td><td><?= htmlspecialchars($row['plan'] ?? 'No plan') ?></td><td><?= htmlspecialchars($row['branch'] ?? 'Unassigned') ?></td><td><?= htmlspecialchars(date('M j, Y', strtotime($row['created_at']))) ?></td></tr><?php endforeach ?><?php if (!$membershipReport['recent_members']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-users"></i>No members in this range</div></td></tr><?php endif ?></tbody></table></div>
                    <div class="tbl-wrap"><div class="card-head"><div class="card-title">Memberships Expiring Soon</div></div><table><thead><tr><th>Member</th><th>Plan</th><th>Branch</th><th>Expires</th></tr></thead><tbody><?php foreach ($membershipReport['expiring_soon'] as $row): ?><tr><td><strong><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['email']) ?></div></td><td><?= htmlspecialchars($row['plan']) ?></td><td><?= htmlspecialchars($row['branch']) ?></td><td><?= htmlspecialchars(date('M j, Y', strtotime($row['ends_at']))) ?></td></tr><?php endforeach ?><?php if (!$membershipReport['expiring_soon']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-check"></i>No upcoming expirations</div></td></tr><?php endif ?></tbody></table></div>
                    <div class="tbl-wrap"><div class="card-head"><div class="card-title">Recent Renewals</div></div><table><thead><tr><th>Member</th><th>Plan</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach ($membershipReport['recent_renewals'] as $row): ?><tr><td><strong><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['branch']) ?></div></td><td><?= htmlspecialchars($row['plan']) ?></td><td><?= reportMoney($row['amount_paid']) ?></td><td><span class="badge <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['payment_status'])) ?></span></td></tr><?php endforeach ?><?php if (!$membershipReport['recent_renewals']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-receipt"></i>No renewals in this range</div></td></tr><?php endif ?></tbody></table></div>
                </div>
            </div>
            <div class="dash-panel <?= $activeReportTab === 'revenue' ? 'active' : '' ?>" id="rt-revenue">
                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-bottom:1rem;flex-wrap:wrap"><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('revenue', 'csv')) ?>"><i class="ti ti-file-type-csv"></i> CSV</a><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('revenue', 'excel')) ?>"><i class="ti ti-file-spreadsheet"></i> Excel</a><button class="btn sm" onclick="window.print()"><i class="ti ti-printer"></i> Print</button></div>
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <div class="stat" style="border:1px solid var(--accent);background:color-mix(in srgb,var(--accent) 8%,transparent)">
                        <div class="stat-icon"><i class="ti ti-filter-dollar"></i></div>
                        <div class="stat-val"><?= reportMoney($revenueReport['metrics']['period_total']) ?></div>
                        <div class="stat-lbl">Selected Period</div>
                        <div style="font-size:.65rem;color:var(--text-3);margin-top:.2rem"><?= htmlspecialchars($reportFilters['range']['start']) ?> → <?= htmlspecialchars($reportFilters['range']['end']) ?></div>
                    </div>
                    <?php foreach ([['ti-calendar', 'today', 'Revenue Today'], ['ti-calendar-week', 'week', 'Revenue This Week'], ['ti-cash', 'month', 'Revenue This Month'], ['ti-report-money', 'year', 'Revenue This Year']] as $metric): ?>
                    <div class="stat"><div class="stat-icon"><i class="ti <?= $metric[0] ?>"></i></div><div class="stat-val"><?= reportMoney($revenueReport['metrics'][$metric[1]]) ?></div><div class="stat-lbl"><?= $metric[2] ?></div></div>
                    <?php endforeach ?>
                </div>
                <?php
                    $revTotal = $revenueReport['metrics']['period_total'];
                    $memPct   = $revTotal > 0 ? round($revenueReport['metrics']['membership_total'] / $revTotal * 100, 1) : 0;
                    $shopPct  = $revTotal > 0 ? round($revenueReport['metrics']['shop_total'] / $revTotal * 100, 1) : 0;
                ?>
                <div class="card" style="margin-bottom:1.25rem">
                    <div class="card-head"><div class="card-title">Revenue by Source <span style="font-size:.72rem;font-weight:400;color:var(--text-3)">— selected period</span></div></div>
                    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">
                        <div>
                            <div style="font-size:.72rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem"><i class="ti ti-id-badge-2"></i> Membership Fees</div>
                            <div style="font-size:1.5rem;font-weight:700"><?= reportMoney($revenueReport['metrics']['membership_total']) ?></div>
                            <div style="margin-top:.5rem"><div style="height:6px;border-radius:4px;background:var(--border);overflow:hidden"><div style="height:100%;width:<?= $memPct ?>%;background:#e53e3e;border-radius:4px"></div></div>
                            <div style="font-size:.72rem;color:var(--text-3);margin-top:.25rem"><?= $memPct ?>% of total revenue</div></div>
                        </div>
                        <div>
                            <div style="font-size:.72rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem"><i class="ti ti-shopping-cart"></i> Shop Sales</div>
                            <div style="font-size:1.5rem;font-weight:700"><?= reportMoney($revenueReport['metrics']['shop_total']) ?></div>
                            <div style="margin-top:.5rem"><div style="height:6px;border-radius:4px;background:var(--border);overflow:hidden"><div style="height:100%;width:<?= $shopPct ?>%;background:#7c3aed;border-radius:4px"></div></div>
                            <div style="font-size:.72rem;color:var(--text-3);margin-top:.25rem"><?= $shopPct ?>% of total revenue</div></div>
                        </div>
                    </div>
                </div>
                <div class="grid g-3" style="margin-bottom:1.25rem"><div class="card"><div class="card-head"><div class="card-title">Membership Revenue Trend</div></div><div class="card-body"><?php $monthMax = max(1, ...array_map('floatval', array_column($revenueReport['by_month'], 'revenue') ?: [1])); ?><?php foreach ($revenueReport['by_month'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(date('M Y', strtotime($row['month_key'] . '-01'))) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((float) $row['revenue'] / $monthMax) * 100) ?>%"></div></div><span class="rev-val"><?= reportMoney($row['revenue']) ?></span></div><?php endforeach ?></div></div><div class="card"><div class="card-head"><div class="card-title">Shop Revenue Trend</div></div><div class="card-body"><?php $shopMonthMax = max(1, ...array_map('floatval', array_column($revenueReport['shop_by_month'], 'revenue') ?: [1])); ?><?php foreach ($revenueReport['shop_by_month'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(date('M Y', strtotime($row['month_key'] . '-01'))) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((float) $row['revenue'] / $shopMonthMax) * 100) ?>;background:#7c3aed"></div></div><span class="rev-val"><?= reportMoney($row['revenue']) ?></span></div><?php endforeach ?><?php if (!$revenueReport['shop_by_month']): ?><div class="empty"><i class="ti ti-shopping-cart-off"></i>No shop revenue in this range</div><?php endif ?></div></div><div class="card"><div class="card-head"><div class="card-title">Revenue By Membership Plan</div></div><div class="card-body"><?php $planMax = max(1, ...array_map('floatval', array_column($revenueReport['by_plan'], 'revenue') ?: [1])); ?><?php foreach ($revenueReport['by_plan'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars($row['label']) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((float) $row['revenue'] / $planMax) * 100) ?>%"></div></div><span class="rev-val"><?= reportMoney($row['revenue']) ?></span></div><?php endforeach ?></div></div></div>
                <div class="grid g-2" style="margin-bottom:1.25rem"><div class="tbl-wrap"><div class="card-head"><div class="card-title">Membership Payments</div></div><table><thead><tr><th>Member</th><th>Plan</th><th>Branch</th><th>Amount</th></tr></thead><tbody><?php foreach ($revenueReport['recent_payments'] as $row): ?><tr><td><?= htmlspecialchars($row['member_name']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['email']) ?></div></td><td><?= htmlspecialchars($row['plan']) ?></td><td><?= htmlspecialchars($row['branch']) ?></td><td><?= reportMoney($row['amount_paid']) ?></td></tr><?php endforeach ?><?php if (!$revenueReport['recent_payments']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-cash-off"></i>No membership payments in this range</div></td></tr><?php endif ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Shop Orders Revenue</div></div><table><thead><tr><th>Member</th><th>Order</th><th>Amount</th><th>Method</th></tr></thead><tbody><?php foreach ($revenueReport['shop_recent'] as $row): ?><tr><td><?= htmlspecialchars($row['member_name']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['email']) ?></div></td><td><span class="badge <?= $row['status'] === 'completed' ? 'active' : htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span><div style="font-size:.7rem;color:var(--text-3)">#<?= (int) $row['order_id'] ?> &middot; <?= htmlspecialchars(date('M j, Y', strtotime($row['paid_at']))) ?></div></td><td><?= reportMoney($row['total_amount']) ?></td><td style="font-size:.75rem"><?= htmlspecialchars(str_replace('_',' ',ucfirst($row['payment_method']))) ?></td></tr><?php endforeach ?><?php if (!$revenueReport['shop_recent']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-shopping-cart-off"></i>No shop revenue in this range</div></td></tr><?php endif ?></tbody></table></div></div>
                <div class="grid g-2"><div class="tbl-wrap"><div class="card-head"><div class="card-title">Recent Renewals</div></div><table><thead><tr><th>Member</th><th>Plan</th><th>Amount</th><th>Date</th></tr></thead><tbody><?php foreach ($revenueReport['recent_renewals'] as $row): ?><tr><td><?= htmlspecialchars($row['member_name']) ?></td><td><?= htmlspecialchars($row['plan']) ?></td><td><?= reportMoney($row['amount_paid']) ?></td><td><?= htmlspecialchars(date('M j, Y', strtotime($row['paid_at']))) ?></td></tr><?php endforeach ?><?php if (!$revenueReport['recent_renewals']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-repeat-off"></i>No renewal revenue in this range</div></td></tr><?php endif ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Membership Revenue Summary</div></div><table><thead><tr><th>Plan</th><th>Branch</th><th>Payments</th><th>Revenue</th></tr></thead><tbody><?php foreach ($revenueReport['summary'] as $row): ?><tr><td><?= htmlspecialchars($row['plan']) ?></td><td><?= htmlspecialchars($row['branch']) ?></td><td><?= number_format((int) $row['payments']) ?></td><td><?= reportMoney($row['revenue']) ?></td></tr><?php endforeach ?><?php if (!$revenueReport['summary']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-chart-bar-off"></i>No revenue in this range</div></td></tr><?php endif ?></tbody></table></div></div>
            </div>
            <div class="dash-panel <?= $activeReportTab === 'attendance' ? 'active' : '' ?>" id="rt-attendance">
                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-bottom:1rem;flex-wrap:wrap"><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('attendance', 'csv')) ?>"><i class="ti ti-file-type-csv"></i> CSV</a><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('attendance', 'excel')) ?>"><i class="ti ti-file-spreadsheet"></i> Excel</a></div>
                <div class="grid g-4" style="margin-bottom:1.25rem"><div class="stat"><div class="stat-icon"><i class="ti ti-login-2"></i></div><div class="stat-val"><?= number_format((int) $attendanceReport['metrics']['total_checkins']) ?></div><div class="stat-lbl">Total Check-ins</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-users"></i></div><div class="stat-val"><?= number_format((int) $attendanceReport['metrics']['unique_visitors']) ?></div><div class="stat-lbl">Unique Visitors</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-chart-line"></i></div><div class="stat-val"><?= number_format((float) $attendanceReport['metrics']['average_daily'], 1) ?></div><div class="stat-lbl">Average Daily Attendance</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-calendar-star"></i></div><div class="stat-val"><?= $attendanceReport['metrics']['peak_day'] ? htmlspecialchars(date('M j', strtotime($attendanceReport['metrics']['peak_day']['day_key']))) : 'None' ?></div><div class="stat-lbl">Peak Attendance Day</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-clock-star"></i></div><div class="stat-val"><?= $attendanceReport['metrics']['peak_hour'] ? htmlspecialchars(date('g A', strtotime((int) $attendanceReport['metrics']['peak_hour']['hour_key'] . ':00'))) : 'None' ?></div><div class="stat-lbl">Peak Attendance Hour</div></div></div>
                <div class="grid g-2" style="margin-bottom:1.25rem"><div class="card"><div class="card-head"><div class="card-title">Attendance Trend</div></div><div class="card-body"><?php $trendMax = max(1, ...array_map('intval', array_column($attendanceReport['trend'], 'visits') ?: [1])); ?><?php foreach ($attendanceReport['trend'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars(date('M j', strtotime($row['attendance_date']))) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['visits'] / $trendMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['visits']) ?></span></div><?php endforeach ?></div></div><div class="card"><div class="card-head"><div class="card-title">Attendance By Day Of Week</div></div><div class="card-body"><?php $dowMax = max(1, ...array_map('intval', array_column($attendanceReport['by_day_of_week'], 'visits') ?: [1])); ?><?php foreach ($attendanceReport['by_day_of_week'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars($row['day_name']) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['visits'] / $dowMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['visits']) ?></span></div><?php endforeach ?></div></div></div>
                <div class="grid g-3"><div class="tbl-wrap"><div class="card-head"><div class="card-title">Most Active Members</div></div><table><thead><tr><th>Member</th><th>Visits</th><th>Last Visit</th></tr></thead><tbody><?php foreach ($attendanceReport['most_active_members'] as $row): ?><tr><td><?= htmlspecialchars($row['member_name']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['email']) ?></div></td><td><?= number_format((int) $row['visits']) ?></td><td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($row['last_visit']))) ?></td></tr><?php endforeach ?><?php if (!$attendanceReport['most_active_members']): ?><tr><td colspan="3"><div class="empty"><i class="ti ti-run"></i>No attendance in this range</div></td></tr><?php endif ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Attendance By Branch</div></div><table><thead><tr><th>Branch</th><th>Visitors</th><th>Visits</th></tr></thead><tbody><?php foreach ($attendanceReport['by_branch'] as $row): ?><tr><td><?= htmlspecialchars($row['name']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['city']) ?></div></td><td><?= number_format((int) $row['visitors']) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr><?php endforeach ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Attendance By Date</div></div><table><thead><tr><th>Date</th><th>Visitors</th><th>Visits</th></tr></thead><tbody><?php foreach ($attendanceReport['by_date'] as $row): ?><tr><td><?= htmlspecialchars(date('M j, Y', strtotime($row['attendance_date']))) ?></td><td><?= number_format((int) $row['visitors']) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr><?php endforeach ?><?php if (!$attendanceReport['by_date']): ?><tr><td colspan="3"><div class="empty"><i class="ti ti-calendar-off"></i>No attendance dates in this range</div></td></tr><?php endif ?></tbody></table></div></div>
            </div>
            <div class="dash-panel <?= $activeReportTab === 'classes' ? 'active' : '' ?>" id="rt-classes">
                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-bottom:1rem;flex-wrap:wrap"><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('classes', 'csv')) ?>"><i class="ti ti-file-type-csv"></i> CSV</a><a class="btn sm" href="<?= htmlspecialchars(adminReportExportUrl('classes', 'excel')) ?>"><i class="ti ti-file-spreadsheet"></i> Excel</a></div>
                <div class="grid g-4" style="margin-bottom:1.25rem"><div class="stat"><div class="stat-icon"><i class="ti ti-calendar-plus"></i></div><div class="stat-val"><?= number_format((int) $classReport['metrics']['total_bookings']) ?></div><div class="stat-lbl">Total Bookings</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-user-check"></i></div><div class="stat-val"><?= number_format((int) $classReport['metrics']['total_attendance']) ?></div><div class="stat-lbl">Total Attendance</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-chart-dots"></i></div><div class="stat-val"><?= number_format((float) $classReport['metrics']['average_attendance'], 1) ?></div><div class="stat-lbl">Average Class Attendance</div></div><div class="stat"><div class="stat-icon"><i class="ti ti-circle-check"></i></div><div class="stat-val"><?= reportPercent($classReport['metrics']['completion_rate']) ?></div><div class="stat-lbl">Completion Rate</div></div></div>
                <div class="grid g-2" style="margin-bottom:1.25rem"><div class="card"><div class="card-head"><div class="card-title">Bookings Per Class</div></div><div class="card-body"><?php $bookMax = max(1, ...array_map('intval', array_column($classReport['bookings_per_class'], 'bookings') ?: [1])); ?><?php foreach ($classReport['bookings_per_class'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars($row['title']) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['bookings'] / $bookMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['bookings']) ?></span></div><?php endforeach ?></div></div><div class="card"><div class="card-head"><div class="card-title">Attendance Per Class</div></div><div class="card-body"><?php $classAttMax = max(1, ...array_map('intval', array_column($classReport['attendance_per_class'], 'attendance') ?: [1])); ?><?php foreach ($classReport['attendance_per_class'] as $row): ?><div class="rev-row"><span class="rev-label"><?= htmlspecialchars($row['title']) ?></span><div class="rev-track"><div class="rev-fill" style="width:<?= min(100, ((int) $row['attendance'] / $classAttMax) * 100) ?>%"></div></div><span class="rev-val"><?= number_format((int) $row['attendance']) ?></span></div><?php endforeach ?></div></div></div>
                <div class="grid g-3"><div class="tbl-wrap"><div class="card-head"><div class="card-title">Most Popular Classes</div></div><table><thead><tr><th>Class</th><th>Bookings</th><th>Attendance</th><th>Utilization</th></tr></thead><tbody><?php foreach ($classReport['popular_classes'] as $row): ?><tr><td><?= htmlspecialchars($row['title']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['branch']) ?> · Capacity <?= number_format((int) ($row['capacity'] ?? 0)) ?></div></td><td><?= number_format((int) $row['bookings']) ?></td><td><?= number_format((int) $row['attendance']) ?></td><td><?= reportPercent($row['utilization']) ?></td></tr><?php endforeach ?><?php if (!$classReport['popular_classes']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-calendar-off"></i>No class bookings in this range</div></td></tr><?php endif ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Upcoming Classes</div></div><table><thead><tr><th>Class</th><th>Date</th><th>Capacity</th><th>Bookings</th></tr></thead><tbody><?php foreach ($classReport['upcoming_classes'] as $row): ?><tr><td><?= htmlspecialchars($row['title']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['branch']) ?></div></td><td><?= htmlspecialchars(date('M j, Y', strtotime($row['scheduled_date'])) . ' ' . date('g:i A', strtotime($row['start_time']))) ?></td><td><?= $row['capacity'] !== null ? number_format((int) $row['capacity']) : 'Open' ?></td><td><?= number_format((int) $row['bookings']) ?></td></tr><?php endforeach ?><?php if (!$classReport['upcoming_classes']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-calendar-off"></i>No upcoming classes</div></td></tr><?php endif ?></tbody></table></div><div class="tbl-wrap"><div class="card-head"><div class="card-title">Class Performance Ranking</div></div><table><thead><tr><th>Class</th><th>Bookings</th><th>Attendance</th><th>Utilization</th></tr></thead><tbody><?php foreach ($classReport['ranking'] as $row): ?><tr><td><?= htmlspecialchars($row['title']) ?><div style="font-size:.7rem;color:var(--text-3)"><?= htmlspecialchars($row['branch']) ?> · Capacity <?= number_format((int) ($row['capacity'] ?? 0)) ?></div></td><td><?= number_format((int) $row['bookings']) ?></td><td><?= number_format((int) $row['attendance']) ?></td><td><?= reportPercent($row['utilization']) ?></td></tr><?php endforeach ?><?php if (!$classReport['ranking']): ?><tr><td colspan="4"><div class="empty"><i class="ti ti-chart-bar-off"></i>No class performance data</div></td></tr><?php endif ?></tbody></table></div></div>
            </div>
        </div>

        <!-- ─── SETTINGS ──────────────────────────── -->
        <div class="page" id="page-settings">
            <div class="sec-head">
                <div class="sec-title">Settings</div>
            </div>
            <div class="grid g-2">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">Administrator</div>
                    </div>
                    <div class="card-body">
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Name</div>
                                <div class="settings-sub"><?= htmlspecialchars(trim(($adminUser['first_name'] ?? '') . ' ' . ($adminUser['last_name'] ?? ''))) ?></div>
                            </div>
                            <button class="btn sm" onclick="toast('info','Edit profile','Admin profile editing is managed through the users table and has no visible admin handler action yet.')">Edit</button>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Email</div>
                                <div class="settings-sub"><?= htmlspecialchars((string) ($adminUser['email'] ?? '')) ?></div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Password</div>
                                <div class="settings-sub">Use account recovery or direct user management</div>
                            </div>
                            <button class="btn sm" onclick="toast('info','Change password','No admin password action exists in admin_handler.php.')">Change</button>
                        </div>
                        <div class="settings-row" style="border-bottom:none">
                            <div>
                                <div class="settings-lbl">Role</div>
                                <div class="settings-sub">Administrator</div>
                            </div>
                            <span class="badge active">Admin</span>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">System</div>
                    </div>
                    <div class="card-body">
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Active Plans</div>
                                <div class="settings-sub">1 Month, 3 Months, 6 Months, 12 Months</div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Branches</div>
                                <div class="settings-sub"><?= number_format($dashboard['active_branches']) ?> active branches</div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Total Memberships</div>
                                <div class="settings-sub"><?= number_format((int) adminScalar($pdo, 'SELECT COUNT(*) FROM memberships')) ?> total / <?= number_format($dashboard['active_memberships']) ?> active</div>
                            </div>
                        </div>
                        <div class="settings-row" style="border-bottom:none">
                            <div>
                                <div class="settings-lbl">Feedbacks</div>
                                <div class="settings-sub"><?= number_format($dashboard['visible_feedbacks']) ?> visible reviews</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── INVENTORY ─────────────────────────── -->
        <div class="page" id="page-inventory">
            <div class="sec-head">
                <div class="sec-title">Inventory</div>
                <button class="btn primary" onclick="openAddProductModal()"><i class="ti ti-plus"></i> Add Product</button>
            </div>

            <div class="grid g-4" style="margin-bottom:1.25rem">
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-box"></i></div>
                    <div class="stat-val"><?= number_format($shopStats['total_products']) ?></div>
                    <div class="stat-lbl">Active Products</div>
                </div>
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-stack"></i></div>
                    <div class="stat-val"><?= number_format($shopStats['total_inventory']) ?></div>
                    <div class="stat-lbl">Total Stock Units</div>
                </div>
                <div class="stat <?= $shopStats['out_of_stock'] > 0 ? 'urgent' : '' ?>">
                    <div class="stat-icon"><i class="ti ti-alert-circle"></i></div>
                    <div class="stat-val"><?= number_format($shopStats['out_of_stock']) ?></div>
                    <div class="stat-lbl">Out of Stock</div>
                    <div class="stat-sub down"><i class="ti ti-trending-down"></i> Needs restocking</div>
                </div>
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-cash"></i></div>
                    <div class="stat-val">&#8369;<?= number_format($shopStats['shop_revenue']) ?></div>
                    <div class="stat-lbl">Shop Revenue</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head inv-card-head">
                    <div class="card-title">Products</div>
                    <div class="inv-filters">
                        <input type="text" id="invSearch" placeholder="Search products…" oninput="filterInventory()"
                            style="padding:.35rem .75rem;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.8rem;width:180px;outline:none;min-width:0">
                        <select id="invCatFilter" onchange="filterInventory()"
                            style="padding:.35rem .75rem;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.8rem;outline:none">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                        <thead>
                            <tr style="background:var(--th-bg)">
                                <th style="padding:.65rem 1rem;text-align:left;font-weight:600;color:var(--text-2)">Product</th>
                                <th style="padding:.65rem .75rem;text-align:left;font-weight:600;color:var(--text-2)">Category</th>
                                <th style="padding:.65rem .75rem;text-align:right;font-weight:600;color:var(--text-2)">Price</th>
                                <th style="padding:.65rem .75rem;text-align:center;font-weight:600;color:var(--text-2)">Stock</th>
                                <th style="padding:.65rem .75rem;text-align:center;font-weight:600;color:var(--text-2)">Status</th>
                                <th style="padding:.65rem .75rem;text-align:right;font-weight:600;color:var(--text-2)">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ─── SHOP ORDERS ────────────────────────── -->
        <div class="page" id="page-shop-orders">
            <div class="sec-head" style="margin-bottom:1.1rem">
                <div class="sec-title">Shop Orders</div>
                <button class="btn sm" onclick="loadShopOrders()" style="display:inline-flex;align-items:center;gap:.35rem">
                    <i class="ti ti-refresh"></i> Refresh
                </button>
            </div>

            <!-- Stats row -->
            <div class="so-stats-row">
                <div class="so-stat-card">
                    <div class="so-stat-icon" style="background:rgba(74,158,255,.12);color:#4a9eff"><i class="ti ti-shopping-bag"></i></div>
                    <div>
                        <div class="so-stat-val"><?= number_format($shopStats['total_orders']) ?></div>
                        <div class="so-stat-lbl">Total Orders</div>
                    </div>
                </div>
                <div class="so-stat-card <?= $shopStats['pending_orders'] > 0 ? 'so-urgent' : '' ?>">
                    <div class="so-stat-icon" style="background:rgba(255,193,7,.12);color:#ffc107"><i class="ti ti-clock"></i></div>
                    <div>
                        <div class="so-stat-val" style="color:<?= $shopStats['pending_orders'] > 0 ? '#ffc107' : 'inherit' ?>"><?= number_format($shopStats['pending_orders']) ?></div>
                        <div class="so-stat-lbl">Pending</div>
                    </div>
                </div>
                <div class="so-stat-card">
                    <div class="so-stat-icon" style="background:rgba(46,204,113,.12);color:#2ecc71"><i class="ti ti-circle-check"></i></div>
                    <div>
                        <div class="so-stat-val" style="color:#2ecc71"><?= number_format(adminScalar($pdo, 'SELECT COUNT(*) FROM orders WHERE status="completed"')) ?></div>
                        <div class="so-stat-lbl">Completed</div>
                    </div>
                </div>
                <div class="so-stat-card">
                    <div class="so-stat-icon" style="background:rgba(204,26,26,.12);color:var(--red)"><i class="ti ti-currency-peso"></i></div>
                    <div>
                        <div class="so-stat-val">&#8369;<?= number_format($shopStats['shop_revenue']) ?></div>
                        <div class="so-stat-lbl">Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Filter pill tabs + search -->
            <div class="so-toolbar">
                <div class="so-filter-tabs" id="soFilterTabs">
                    <button class="so-tab active" data-val="" onclick="soSetFilter(this)">All</button>
                    <button class="so-tab" data-val="pending" onclick="soSetFilter(this)">Pending</button>
                    <button class="so-tab" data-val="processing" onclick="soSetFilter(this)">Processing</button>
                    <button class="so-tab" data-val="completed" onclick="soSetFilter(this)">Completed</button>
                    <button class="so-tab" data-val="cancelled" onclick="soSetFilter(this)">Cancelled / Rejected</button>
                </div>
                <div class="so-search-wrap">
                    <i class="ti ti-search"></i>
                    <input id="soSearch" type="text" placeholder="Search order # or customer…" oninput="filterShopOrders()">
                </div>
            </div>

            <!-- Orders table -->
            <div class="so-table-wrap">
                <table class="so-table">
                    <thead>
                        <tr>
                            <th style="width:52px">#</th>
                            <th>Customer</th>
                            <th style="text-align:center">Fulfillment</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:center">Order Status</th>
                            <th style="text-align:center">Payment</th>
                            <th style="text-align:center">Proof</th>
                            <th style="text-align:center">Date</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="shopOrdersBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <div class="modal-backdrop" id="order-detail-modal" style="display:none" onclick="if(event.target===this)closeOrderDetailModal()">
            <div class="modal-box" style="max-width:600px;width:95%">
                <div class="modal-head">
                    <h2 id="order-detail-title">Order Details</h2>
                    <button class="modal-close" onclick="closeOrderDetailModal()"><i class="ti ti-x"></i></button>
                </div>
                <div class="modal-body" id="order-detail-body" style="max-height:65vh;overflow-y:auto"></div>
                <div class="modal-foot" style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.85rem 1.25rem;border-top:1px solid var(--border)">
                    <div id="order-detail-verify-btns" style="display:flex;gap:.5rem"></div>
                    <div style="display:flex;gap:.5rem">
                        <button class="btn sm" id="order-detail-print-btn" onclick="printAdminOrderReceipt()"><i class="ti ti-printer"></i> Receipt</button>
                        <button class="btn" onclick="closeOrderDetailModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Proof Image Modal -->
        <div class="modal-backdrop" id="proof-img-modal" style="display:none" onclick="if(event.target===this)document.getElementById('proof-img-modal').style.display='none'">
            <div class="modal-box" style="max-width:560px;width:95%;background:transparent;border:none;box-shadow:none">
                <img id="proof-img-el" src="" style="width:100%;border-radius:14px;border:2px solid var(--border)" alt="Proof of Payment">
                <div style="text-align:center;margin-top:.75rem">
                    <button class="btn" onclick="document.getElementById('proof-img-modal').style.display='none'">Close</button>
                </div>
            </div>
        </div>

        <div class="modal-backdrop" id="product-modal-backdrop" style="display:none" onclick="if(event.target===this)closeProductModal()">
            <div class="modal-box" style="max-width:620px;width:95%">
                <div class="modal-head">
                    <h2 id="product-modal-title">Add Product</h2>
                    <button class="modal-close" onclick="closeProductModal()"><i class="ti ti-x"></i></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="admin_save_product">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="product_id" id="pf-id" value="0">
                        <input type="hidden" name="existing_image" id="pf-existing-image" value="">
                        <div class="grid g-2" style="gap:.85rem">
                            <div style="grid-column:1/-1">
                                <label style="font-size:.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.3rem">Product Name *</label>
                                <input type="text" name="name" id="pf-name" required placeholder="e.g. Whey Protein"
                                    style="width:100%;padding:.55rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.875rem;outline:none">
                            </div>
                            <div>
                                <label style="font-size:.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.3rem">Category *</label>
                                <input type="text" name="category" id="pf-category" required placeholder="Supplement"
                                    list="pf-cat-list"
                                    style="width:100%;padding:.55rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.875rem;outline:none">
                                <datalist id="pf-cat-list">
                                    <option value="Supplement">
                                    <option value="Equipment">
                                    <option value="Apparel">
                                    <option value="Accessories">
                                </datalist>
                            </div>
                            <div style="display:flex;gap:.75rem">
                                <div style="flex:1">
                                    <label style="font-size:.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.3rem">Price (&#8369;) *</label>
                                    <input type="number" name="price" id="pf-price" required min="1" step="0.01"
                                        style="width:100%;padding:.55rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.875rem;outline:none">
                                </div>
                            </div>
                            <div style="grid-column: 1/-1;">
                                <label style="font-size:.82rem;font-weight:700;color:var(--text-1);display:block;margin-bottom:.4rem">Branch Inventory Stocks</label>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.75rem;" id="pf-branch-stocks-container">
                                    <?php foreach ($branches as $br): ?>
                                        <div style="background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;padding:.5rem .75rem">
                                            <label style="font-size:.72rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($br['name']) ?>">
                                                <?= htmlspecialchars($br['name']) ?>
                                            </label>
                                            <input type="number" name="stocks[<?= $br['id'] ?>]" id="pf-stock-<?= $br['id'] ?>" required min="0" value="0"
                                                style="width:100%;padding:.35rem .6rem;background:var(--surface);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:.8rem;outline:none">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="grid-column:1/-1">
                                <label style="font-size:.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.3rem">Description</label>
                                <textarea name="description" id="pf-desc" rows="3" placeholder="Product description…"
                                    style="width:100%;padding:.55rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.875rem;outline:none;resize:vertical"></textarea>
                            </div>
                            <div style="grid-column:1/-1">
                                <label style="font-size:.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.3rem">Product Image</label>
                                <input type="file" name="image" id="pf-image" accept="image/*"
                                    style="width:100%;padding:.45rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.82rem">
                                <div id="pf-img-preview" style="margin-top:.5rem"></div>
                            </div>
                            <div style="grid-column:1/-1;display:flex;align-items:center;gap:.5rem">
                                <input type="checkbox" name="is_active" id="pf-active" checked style="width:16px;height:16px">
                                <label for="pf-active" style="font-size:.82rem;font-weight:600">Active (visible in shop)</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-foot">
                    <button class="btn" onclick="closeProductModal()">Cancel</button>
                    <button class="btn primary" onclick="submitProductForm()" id="saveProductBtn">
                        <i class="ti ti-check"></i> Save Product
                    </button>
                </div>
            </div>
        </div>

        <!-- Order Details Modal (removed duplicate) -->

        <style>
        /* ─── SHOP ADMIN STYLES ── */
        .inv-img { width:44px;height:44px;border-radius:9px;object-fit:cover; }
        .inv-img-ph { width:44px;height:44px;border-radius:9px;background:var(--input-bg);display:flex;align-items:center;justify-content:center;color:var(--text-3);font-size:1.2rem; }

        /* Order status badges */
        .ord-s { display:inline-flex;align-items:center;gap:.3rem;font-size:.68rem;font-weight:700;padding:.22rem .6rem;border-radius:20px;text-transform:capitalize;letter-spacing:.2px;white-space:nowrap; }
        .ord-pending         { background:rgba(255,193,7,.15);color:#ffc107;border:1px solid rgba(255,193,7,.3); }
        .ord-processing      { background:rgba(74,158,255,.15);color:#4a9eff;border:1px solid rgba(74,158,255,.3); }
        .ord-shipped         { background:rgba(111,66,193,.15);color:#a066f5;border:1px solid rgba(111,66,193,.3); }
        .ord-delivered       { background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid rgba(46,204,113,.3); }
        .ord-cancelled       { background:rgba(255,107,107,.15);color:#ff6b6b;border:1px solid rgba(255,107,107,.3); }
        .ord-completed       { background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid rgba(46,204,113,.4); }
        .ord-ready_for_pickup{ background:rgba(255,193,7,.15);color:#ffc107;border:1px solid rgba(255,193,7,.3); }
        .ord-out_for_delivery{ background:rgba(111,66,193,.15);color:#a066f5;border:1px solid rgba(111,66,193,.3); }
        .ord-picked_up       { background:rgba(88,214,141,.12);color:#58d68d;border:1px solid rgba(88,214,141,.3); }

        /* Stats row */
        .so-stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem; }
        @media(max-width:700px){.so-stats-row{grid-template-columns:repeat(2,1fr);}}
        .so-stat-card { display:flex;align-items:center;gap:.85rem;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:.9rem 1.1rem;transition:border-color .2s; }
        .so-stat-card:hover { border-color:var(--red); }
        .so-stat-card.so-urgent { border-color:rgba(255,193,7,.45);background:rgba(255,193,7,.04); }
        .so-stat-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0; }
        .so-stat-val { font-size:1.35rem;font-weight:800;line-height:1.1; }
        .so-stat-lbl { font-size:.72rem;color:var(--text-3);margin-top:.1rem;font-weight:500; }

        /* Toolbar */
        .so-toolbar { display:flex;align-items:center;gap:.85rem;flex-wrap:wrap;margin-bottom:.85rem; }
        .so-filter-tabs { display:flex;gap:.35rem;flex-wrap:wrap; }
        .so-tab { padding:.35rem .85rem;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--text-2);font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s; }
        .so-tab:hover { border-color:var(--red);color:var(--text); }
        .so-tab.active { background:var(--red);border-color:var(--red);color:#fff; }
        .so-search-wrap { display:flex;align-items:center;gap:.5rem;background:var(--input-bg);border:1px solid var(--border);border-radius:9px;padding:.35rem .8rem;flex:1;min-width:200px;max-width:280px; }
        .so-search-wrap i { color:var(--text-3);font-size:.9rem;flex-shrink:0; }
        .so-search-wrap input { border:none;background:transparent;outline:none;color:var(--text);font-size:.82rem;width:100%; }

        /* Orders table */
        .so-table-wrap { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
        .so-table { width:100%;border-collapse:collapse;font-size:.82rem; }
        .so-table thead tr { background:var(--th-bg); }
        .so-table th { padding:.65rem 1rem;text-align:left;font-weight:600;color:var(--text-3);font-size:.74rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap; }
        .so-table td { padding:.75rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle; }
        .so-table tbody tr:last-child td { border-bottom:none; }
        .so-table tbody tr { transition:background .13s; }
        .so-table tbody tr:hover { background:var(--row-hover); }
        .so-proof-thumb { width:36px;height:36px;border-radius:7px;object-fit:cover;cursor:pointer;border:1px solid var(--border);transition:transform .15s; }
        .so-proof-thumb:hover { transform:scale(1.1);border-color:var(--red); }
        .so-pay-badge { font-size:.68rem;font-weight:700;padding:.2rem .55rem;border-radius:20px; }
        .so-pay-pending  { background:rgba(255,193,7,.15);color:#ffc107;border:1px solid rgba(255,193,7,.3); }
        .so-pay-paid     { background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid rgba(46,204,113,.3); }
        .so-pay-rejected { background:rgba(255,107,107,.15);color:#ff6b6b;border:1px solid rgba(255,107,107,.3); }
        .so-action-btn { display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.3rem .6rem;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text-2);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap; }
        .so-action-btn:hover { border-color:var(--red);color:var(--red); }
        .so-action-btn.approve { border-color:rgba(46,204,113,.5);color:#2ecc71; }
        .so-action-btn.approve:hover { background:rgba(46,204,113,.1); }
        .so-action-btn.reject { border-color:rgba(255,107,107,.5);color:#ff6b6b; }
        .so-action-btn.reject:hover { background:rgba(255,107,107,.1); }
        
        @media(max-width:991px) {
            .so-table thead { display: none; }
            .so-table tbody td { 
                display: flex; justify-content: space-between; align-items: center; 
                text-align: right !important; padding: .65rem 1rem; border-bottom: 1px dashed var(--border);
            }
            .so-table tbody td::before { 
                content: attr(data-label); font-weight: 600; color: var(--text-3); 
                font-size: .75rem; text-transform: uppercase; margin-right: 1rem;
            }
            .so-table tbody td > div, .so-table tbody td > span { text-align: right; }
            .so-table tbody td[data-label="Customer"] > div { text-align: right; }
            .so-table tbody tr { display: block; border-bottom: 3px solid var(--border); padding: .5rem 0; }
            .so-table tbody tr:last-child { border-bottom: none; }
            .so-table tbody td[data-label="Actions"] > div { justify-content: flex-end; width: 100%; }
        }
        </style>

    </main><!-- /main -->


    <!-- ══ TOAST CONTAINER ════════════════════════ -->
    <div id="toast-container"></div>

    <!-- ══ MODAL BACKDROP ════════════════════════ -->
    <div class="modal-backdrop" id="modal-backdrop" style="display:none" onclick="e=>e.target===this&&closeModal()">
        <div class="modal-box" id="modal-box">
            <div class="modal-head">
                <h2 id="modal-title">Member Details</h2>
                <button class="modal-close" onclick="closeModal()"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body" id="modal-body"></div>
            <div class="modal-foot" id="modal-foot"></div>
        </div>
    </div>

    <!-- ══ CONFIRM BACKDROP ═══════════════════════ -->
    <div class="confirm-backdrop" id="confirm-backdrop" style="display:none">
        <div class="confirm-box">
            <div class="confirm-icon warn"><i class="ti ti-alert-triangle"></i></div>
            <div class="confirm-title" id="confirm-title">Are you sure?</div>
            <div class="confirm-msg" id="confirm-msg">This action cannot be undone.</div>
            <div class="confirm-actions">
                <button class="btn" onclick="closeConfirm()">Cancel</button>
                <button class="btn primary" id="confirm-ok">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const ADMIN_DATA = <?= json_encode($adminData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CSRF_TOKEN = ADMIN_DATA.csrf;
        const adminMembers = ADMIN_DATA.members;
        const adminBranches = ADMIN_DATA.branches;
        const adminFeedbacks    = ADMIN_DATA.feedbacks;
        const adminOrderReviews = ADMIN_DATA.orderReviews || [];

        const signupData = ADMIN_DATA.signupData;
        const revenueData = ADMIN_DATA.revenueData;
        const months = ADMIN_DATA.months;
        const adminPlans = ADMIN_DATA.membershipPlans || [];
        const adminClasses = ADMIN_DATA.classes || [];
        const adminSchedules = ADMIN_DATA.classSchedules || [];
        const adminAnnouncements = ADMIN_DATA.announcements || [];
        const adminOperatingHours = ADMIN_DATA.operatingHours || [];
        const adminNotes = ADMIN_DATA.memberNotes || [];
        /* ── RENDER ───────────────────────────────── */
        function init() {
            buildSparkline();
            buildRevBars();
            renderRevenueByPlan();
            renderRecentMembers();
            renderPendingList();
            renderMembers();
            renderAttendanceTables();
            renderBranches();
            renderFeedbacks();
            renderOrderReviews();

            restoreAdminPage();
        }

        function fmtDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function initials(f, l) {
            return ((f[0] || '')).toUpperCase() + ((l[0] || '')).toUpperCase();
        }

        function cap(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—';
        }

        async function adminPost(payload) {
            const res = await fetch('handlers/admin_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...payload, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
            if (!res.ok && data.success !== true) data.success = false;
            return data;
        }

        async function runAdminAction(payload, successTitle = 'Updated') {
            try {
                const data = await adminPost(payload);
                toast(data.success ? 'success' : 'error', data.success ? successTitle : 'Action failed', data.message || 'No response message.');
                if (data.success && data.reload) {
                    const targetHash = currentAdminPageHash();
                    setTimeout(() => {
                        const nextUrl = location.pathname + location.search + targetHash;
                        location.href = nextUrl;
                        location.reload();
                    }, 700);
                }
                return data;
            } catch (e) {
                toast('error', 'Action failed', e.message || 'Unable to contact the server.');
                return { success: false };
            }
        }

        async function runAdminActionNoReload(payload, successTitle = 'Updated') {
            try {
                const data = await adminPost(payload);
                toast(data.success ? 'success' : 'error', data.success ? successTitle : 'Action failed', data.message || 'No response message.');
                if (data.success) closeModal();
                return data;
            } catch (e) {
                toast('error', 'Action failed', e.message || 'Unable to contact the server.');
                return { success: false };
            }
        }

        async function logoutAdmin() {
            try {
                const res = await fetch('handlers/auth_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout', csrf_token: CSRF_TOKEN })
                });
                const data = await res.json();
                if (data.redirect) location.href = data.redirect;
            } catch (e) {
                location.href = 'index.php';
            }
        }

        function memberName(member) {
            return member ? `${member.fname || ''} ${member.lname || ''}`.trim() : 'this member';
        }

        function paymentAction(action, membershipId) {
            if (!membershipId) {
                toast('error', 'Missing membership', 'This row has no membership record to update.');
                return;
            }
            const member = adminMembers.find(m => Number(m.membership_id) === Number(membershipId));
            const name = memberName(member);
            const approving = action === 'approve_payment';
            confirmAction(
                approving ? 'Approve payment?' : 'Reject payment?',
                approving ? `Activate ${name}'s membership.` : 'This will cancel the pending membership request.',
                () => runAdminAction({ action, membership_id: membershipId }, approving ? 'Payment approved' : 'Payment rejected')
            );
        }

        function membershipStatusAction(membershipId, status) {
            if (!membershipId) {
                toast('error', 'Missing membership', 'This member has no membership record to update.');
                return;
            }
            const member = adminMembers.find(m => Number(m.membership_id) === Number(membershipId));
            const name = memberName(member);
            confirmAction(
                `${cap(status)} membership?`,
                `${name}'s membership status will be set to ${status}.`,
                () => runAdminAction({ action: 'set_membership_status', membership_id: membershipId, status }, 'Membership updated')
            );
        }

        function accountAction(action, memberId) {
            const member = adminMembers.find(m => Number(m.id) === Number(memberId));
            const name = memberName(member);
            const labels = {
                approve_account: ['Approve account?', `${name} will be marked approved.`],
                reject_account: ['Reject account?', `${name}'s account will be deactivated and pending memberships cancelled.`],
                delete_account: ['Delete account?', 'This will permanently delete the member account and related admin records.']
            };
            confirmAction(labels[action][0], labels[action][1], () => runAdminAction({ action, member_id: memberId }, 'Account updated'));
        }

        function deleteFeedbackAction(feedbackId) {
            confirmAction('Delete feedback?', 'This review will be hidden from admin and member views.', () =>
                runAdminAction({ action: 'delete_feedback', feedback_id: feedbackId }, 'Feedback deleted')
            );
        }

        const fieldStyle = 'width:100%;padding:.55rem .7rem;border-radius:9px;background:var(--input-bg);color:var(--text);border:1px solid var(--border);font:inherit';
        const rowStyle = 'display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-bottom:.7rem';
        const fullRowStyle = 'margin-bottom:.7rem';

        function h(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
        }

        function optionList(rows, selected, labelKey = 'label') {
            return rows.map(row => `<option value="${Number(row.id)}" ${Number(row.id) === Number(selected) ? 'selected' : ''}>${h(row[labelKey] || row.name || row.title)}</option>`).join('');
        }

        function formVal(id) {
            return document.getElementById(id)?.value || '';
        }

        function checkedVal(id) {
            return document.getElementById(id)?.checked ? 1 : 0;
        }

        function openActionModal(title, body, footer) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').innerHTML = body;
            document.getElementById('modal-foot').innerHTML = footer;
            document.getElementById('modal-backdrop').style.display = 'flex';
        }

        function openMemberCreateModal() {
            openActionModal('Add Member', `
                <div style="${rowStyle}">
                    <input id="cm-first" style="${fieldStyle}" placeholder="First name" />
                    <input id="cm-last" style="${fieldStyle}" placeholder="Last name" />
                </div>
                <div style="${rowStyle}">
                    <input id="cm-email" style="${fieldStyle}" type="email" placeholder="Email" />
                    <select id="cm-gender" style="${fieldStyle}">
                        <option value="male">Male</option><option value="female">Female</option><option value="nonbinary">Nonbinary</option><option value="other">Other</option>
                    </select>
                </div>
                <div style="${rowStyle}">
                    <input id="cm-birthdate" style="${fieldStyle}" type="date" />
                    <select id="cm-plan" style="${fieldStyle}">${optionList(adminPlans)}</select>
                </div>
                <div style="${rowStyle}">
                    <input id="cm-password" style="${fieldStyle}" type="password" placeholder="Password" />
                    <input id="cm-confirm" style="${fieldStyle}" type="password" placeholder="Confirm password" />
                </div>
                <div style="${fullRowStyle}">
                    <select id="cm-payment" style="${fieldStyle}">
                        <option value="cash">Cash / Walk-in</option><option value="gcash">GCash</option><option value="maya">Maya</option><option value="bank_transfer">Bank Transfer</option><option value="credit_card">Credit Card</option><option value="debit_card">Debit Card</option>
                    </select>
                </div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitCreateMember()">Create</button>`);
        }

        function submitCreateMember() {
            runAdminAction({
                action: 'create_member',
                first_name: formVal('cm-first'),
                last_name: formVal('cm-last'),
                email: formVal('cm-email'),
                gender: formVal('cm-gender'),
                birthdate: formVal('cm-birthdate'),
                plan_id: formVal('cm-plan'),
                payment_method: formVal('cm-payment'),
                password: formVal('cm-password'),
                confirm_password: formVal('cm-confirm')
            }, 'Member created');
        }

        function openMemberPlanModal(memberId) {
            const m = adminMembers.find(x => Number(x.id) === Number(memberId));
            if (!m) return;
            openActionModal('Change Plan', `
                <div style="${fullRowStyle}"><select id="mp-plan" style="${fieldStyle}">${optionList(adminPlans, m.plan_id)}</select></div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitMemberPlan(${m.id},${m.membership_id || 0})">Save</button>`);
        }

        function submitMemberPlan(memberId, membershipId) {
            runAdminAction({ action: 'change_member_plan', member_id: memberId, membership_id: membershipId, plan_id: formVal('mp-plan') }, 'Plan updated');
        }

        function openMemberBranchModal(memberId) {
            const m = adminMembers.find(x => Number(x.id) === Number(memberId));
            if (!m) return;
            openActionModal('Change Branch', `
                <div style="${fullRowStyle}"><select id="mb-branch" style="${fieldStyle}">${optionList(adminBranches.filter(b => Number(b.is_active) === 1), m.branch_id, 'name')}</select></div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitMemberBranch(${m.id})">Save</button>`);
        }

        function submitMemberBranch(memberId) {
            runAdminAction({ action: 'change_member_branch', member_id: memberId, branch_id: formVal('mb-branch') }, 'Branch updated');
        }

        function openExtendMembershipModal(memberId) {
            const m = adminMembers.find(x => Number(x.id) === Number(memberId));
            if (!m) return;
            openActionModal('Extend Membership', `
                <div style="${fullRowStyle}"><input id="me-days" style="${fieldStyle}" type="number" min="1" max="730" value="30" placeholder="Days to add" /></div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitExtendMembership(${m.membership_id || 0})">Extend</button>`);
        }

        function submitExtendMembership(membershipId) {
            runAdminAction({ action: 'extend_membership', membership_id: membershipId, days: formVal('me-days') }, 'Membership extended');
        }

        function openMemberNoteModal(memberId) {
            const m = adminMembers.find(x => Number(x.id) === Number(memberId));
            if (!m) return;
            openActionModal('Add Member Note', `
                <div style="${fullRowStyle}"><textarea id="mn-body" style="${fieldStyle};min-height:120px" placeholder="Note"></textarea></div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitMemberNote(${m.id})">Save Note</button>`);
        }

        function submitMemberNote(memberId) {
            runAdminAction({ action: 'add_member_note', member_id: memberId, note_body: formVal('mn-body') }, 'Note added');
        }

        function classById(id) {
            return adminClasses.find(row => Number(row.id) === Number(id)) || {};
        }

        function openClassModal(id = 0) {
            const c = id ? classById(id) : {};
            openActionModal(id ? 'Edit Class' : 'Create Class', `
                <div style="${rowStyle}">
                    <input id="cl-title" style="${fieldStyle}" placeholder="Class title" value="${h(c.title)}" />
                    <input id="cl-trainer" style="${fieldStyle}" placeholder="Trainer" value="${h(c.trainer_name)}" />
                </div>
                <div style="${rowStyle}">
                    <select id="cl-branch" style="${fieldStyle}">${optionList(adminBranches.filter(b => Number(b.is_active) === 1), c.branch_id, 'name')}</select>
                    <input id="cl-duration" style="${fieldStyle}" type="number" min="15" max="360" value="${h(c.duration_minutes || 60)}" placeholder="Duration minutes" />
                </div>
                <div style="${rowStyle}">
                    <input id="cl-capacity" style="${fieldStyle}" type="number" min="0" max="500" value="${h(c.capacity || '')}" placeholder="Capacity" />
                    <label style="display:flex;align-items:center;gap:.5rem;color:var(--text-2);font-size:.82rem"><input id="cl-active" type="checkbox" ${Number(c.is_active ?? 1) === 1 ? 'checked' : ''}/> Active</label>
                </div>
                <div style="${fullRowStyle}"><textarea id="cl-description" style="${fieldStyle};min-height:90px" placeholder="Description">${h(c.description)}</textarea></div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitClass(${Number(id)})">Save</button>`);
        }

        function submitClass(id = 0) {
            runAdminAction({
                action: 'save_class',
                class_id: id,
                title: formVal('cl-title'),
                trainer_name: formVal('cl-trainer'),
                branch_id: formVal('cl-branch'),
                duration_minutes: formVal('cl-duration'),
                capacity: formVal('cl-capacity'),
                is_active: checkedVal('cl-active'),
                description: formVal('cl-description')
            }, 'Class saved');
        }

        function classActiveAction(classId, isActive) {
            confirmAction(isActive ? 'Activate class?' : 'Deactivate class?', 'This updates class availability.', () =>
                runAdminAction({ action: 'set_class_active', class_id: classId, is_active: isActive }, 'Class updated')
            );
        }

        function deleteClassAction(classId) {
            confirmAction('Delete class?', 'This cannot be undone.', () => runAdminAction({ action: 'delete_class', class_id: classId }, 'Class deleted'));
        }

        function scheduleById(id) {
            return adminSchedules.find(row => Number(row.id) === Number(id)) || {};
        }

        function openScheduleModal(id = 0) {
            const s = id ? scheduleById(id) : {};
            openActionModal(id ? 'Edit Schedule' : 'Create Schedule', `
                <div style="${rowStyle}">
                    <select id="sc-class" style="${fieldStyle}">${optionList(adminClasses, s.class_id, 'title')}</select>
                    <select id="sc-branch" style="${fieldStyle}">${optionList(adminBranches.filter(b => Number(b.is_active) === 1), s.branch_id, 'name')}</select>
                </div>
                <div style="${rowStyle}">
                    <input id="sc-date" style="${fieldStyle}" type="date" value="${h(s.scheduled_date)}" />
                    <select id="sc-status" style="${fieldStyle}">
                        <option value="scheduled" ${s.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                        <option value="cancelled" ${s.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        <option value="completed" ${s.status === 'completed' ? 'selected' : ''}>Completed</option>
                    </select>
                </div>
                <div style="${rowStyle}">
                    <input id="sc-start" style="${fieldStyle}" type="time" value="${h(String(s.start_time || '').slice(0,5))}" />
                    <input id="sc-end" style="${fieldStyle}" type="time" value="${h(String(s.end_time || '').slice(0,5))}" />
                </div>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitSchedule(${Number(id)})">Save</button>`);
        }

        function submitSchedule(id = 0) {
            runAdminAction({
                action: 'save_class_schedule',
                schedule_id: id,
                class_id: formVal('sc-class'),
                branch_id: formVal('sc-branch'),
                scheduled_date: formVal('sc-date'),
                start_time: formVal('sc-start'),
                end_time: formVal('sc-end'),
                status: formVal('sc-status')
            }, 'Schedule saved');
        }

        function scheduleStatusAction(scheduleId, status) {
            confirmAction(`${cap(status)} schedule?`, 'This updates the schedule status.', () =>
                runAdminAction({ action: 'set_class_schedule_status', schedule_id: scheduleId, status }, 'Schedule updated')
            );
        }

        function deleteScheduleAction(scheduleId) {
            confirmAction('Delete schedule?', 'This cannot be undone.', () => runAdminAction({ action: 'delete_class_schedule', schedule_id: scheduleId }, 'Schedule deleted'));
        }

        function announcementById(id) {
            return adminAnnouncements.find(row => Number(row.id) === Number(id)) || {};
        }

        function dtLocal(value) {
            return value ? String(value).replace(' ', 'T').slice(0, 16) : '';
        }

        function openAnnouncementModal(id = 0) {
            const a = id ? announcementById(id) : {};
            openActionModal(id ? 'Edit Announcement' : 'Create Announcement', `
                <div style="${rowStyle}">
                    <input id="an-title" style="${fieldStyle}" placeholder="Title" value="${h(a.title)}" />
                    <select id="an-branch" style="${fieldStyle}">
                        <option value="0" ${!a.branch_id || Number(a.branch_id) === 0 ? 'selected' : ''}>🌐 All Branches</option>
                        ${adminBranches.filter(b => Number(b.is_active) === 1).map(b =>
                            `<option value="${Number(b.id)}" ${Number(a.branch_id) === Number(b.id) ? 'selected' : ''}>${h(b.name)}</option>`
                        ).join('')}
                    </select>
                </div>
                <div style="${rowStyle}">
                    <input id="an-start" style="${fieldStyle}" type="datetime-local" value="${h(dtLocal(a.starts_at))}" />
                    <input id="an-end" style="${fieldStyle}" type="datetime-local" value="${h(dtLocal(a.ends_at))}" />
                </div>
                <div style="${fullRowStyle}"><textarea id="an-body" style="${fieldStyle};min-height:120px" placeholder="Announcement body">${h(a.body)}</textarea></div>
                <label style="display:flex;align-items:center;gap:.5rem;color:var(--text-2);font-size:.82rem"><input id="an-active" type="checkbox" ${Number(a.is_active ?? 1) === 1 ? 'checked' : ''}/> Active</label>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitAnnouncement(${Number(id)})">Save</button>`);
        }

        function submitAnnouncement(id = 0) {
            runAdminAction({
                action: 'save_announcement',
                announcement_id: id,
                branch_id: formVal('an-branch'),
                title: formVal('an-title'),
                body: formVal('an-body'),
                starts_at: formVal('an-start'),
                ends_at: formVal('an-end'),
                is_active: checkedVal('an-active')
            }, 'Announcement saved');
        }

        function announcementActiveAction(announcementId, isActive) {
            confirmAction(isActive ? 'Activate announcement?' : 'Deactivate announcement?', 'This updates announcement visibility.', () =>
                runAdminAction({ action: 'set_announcement_active', announcement_id: announcementId, is_active: isActive }, 'Announcement updated')
            );
        }

        function deleteAnnouncementAction(announcementId) {
            confirmAction('Delete announcement?', 'This cannot be undone.', () => runAdminAction({ action: 'delete_announcement', announcement_id: announcementId }, 'Announcement deleted'));
        }

        function openOperatingHourModal(branchId = 0, day = 1) {
            const activeBranches = adminBranches.filter(b => Number(b.is_active) === 1);
            const selectedBranch = branchId || Number(activeBranches[0]?.id || 0);
            const hour = adminOperatingHours.find(row => Number(row.branch_id) === Number(selectedBranch) && Number(row.day_of_week) === Number(day)) || {};
            openActionModal('Edit Operating Hours', `
                <div style="${rowStyle}">
                    <select id="oh-branch" style="${fieldStyle}">${optionList(activeBranches, selectedBranch, 'name')}</select>
                    <select id="oh-day" style="${fieldStyle}">
                        ${[1,2,3,4,5,6,7].map(d => `<option value="${d}" ${Number(day) === d ? 'selected' : ''}>${['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][d]}</option>`).join('')}
                    </select>
                </div>
                <div style="${rowStyle}">
                    <input id="oh-open" style="${fieldStyle}" type="time" value="${h(String(hour.open_time || '').slice(0,5))}" />
                    <input id="oh-close" style="${fieldStyle}" type="time" value="${h(String(hour.close_time || '').slice(0,5))}" />
                </div>
                <label style="display:flex;align-items:center;gap:.5rem;color:var(--text-2);font-size:.82rem"><input id="oh-closed" type="checkbox" ${Number(hour.is_closed || 0) === 1 ? 'checked' : ''}/> Closed</label>
            `, `<button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" onclick="submitOperatingHour()">Save</button>`);
        }

        function submitOperatingHour() {
            runAdminActionNoReload({
                action: 'save_operating_hour',
                branch_id: formVal('oh-branch'),
                day_of_week: formVal('oh-day'),
                open_time: formVal('oh-open'),
                close_time: formVal('oh-close'),
                is_closed: checkedVal('oh-closed')
            }, 'Operating hours saved');
        }

        function buildSparkline() {
            const max = Math.max(...signupData, 1);
            const el = document.getElementById('spark');
            if (!el) return;
            el.innerHTML = signupData.map((v, i) =>
                `<div class="spark-bar${i===signupData.length-1?' hi':''}" style="height:${Math.round(v/max*100)}%" title="${v} sign-ups"></div>`
            ).join('');
            document.getElementById('spark-labels').innerHTML = months.map(m => `<span>${m}</span>`).join('');
        }

        function buildRevBars() {
            const el = document.getElementById('rev-bars');
            if (!el) return;
            const max = Math.max(...revenueData, 1);
            el.innerHTML = revenueData.map((v, i) => `
    <div class="rev-row">
      <span class="rev-label">${months[i]}</span>
      <div class="rev-track"><div class="rev-fill" style="width:${Math.round(v/max*100)}%"></div></div>
      <span class="rev-val">₱${(v/1000).toFixed(0)}k</span>
    </div>
  `).join('');
        }

        function renderRevenueByPlan() {
            const el = document.getElementById('revenue-by-plan');
            if (!el) return;
            const rows = ADMIN_DATA.revenueByPlan || [];
            const max = Math.max(...rows.map(r => Number(r.revenue || 0)), 1);
            el.innerHTML = rows.length ? rows.map(r => `
                <div class="rev-row"><span class="rev-label">${r.label}</span>
                    <div class="rev-track">
                        <div class="rev-fill" style="width:${Math.round(Number(r.revenue || 0) / max * 100)}%"></div>
                    </div><span class="rev-val">₱${Number(r.revenue || 0).toLocaleString()}</span>
                </div>
            `).join('') : '<div class="empty"><i class="ti ti-cash"></i>No paid revenue yet</div>';
        }

        function renderRecentMembers() {
            const el = document.getElementById('recent-tbody');
            if (!el) return;
            el.innerHTML = adminMembers.slice(0, 5).map(m => `
    <tr>
      <td><div style="display:flex;align-items:center;gap:.5rem">
        <div class="mem-av">${initials(m.fname,m.lname)}</div>
        <span style="font-weight:600">${m.fname} ${m.lname}</span>
      </div></td>
      <td><span class="plan-badge ${m.planCls}">${m.plan}</span></td>
      <td><span class="badge ${m.status}">${cap(m.status)}</span></td>
    </tr>
  `).join('');
        }

        function viewReceipt(path, date, name) {
            if (!path) return;
            const isPdf = path.toLowerCase().endsWith('.pdf');
            if (isPdf) {
                window.open(path, '_blank');
                return;
            }
            const modalHtml = `
            <div id="receipt-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;padding:2rem;" onclick="this.remove()">
                <div style="background:var(--surface);padding:1.5rem;border-radius:12px;max-width:800px;width:100%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);" onclick="event.stopPropagation()">
                    <h3 style="margin-bottom:0.5rem">Payment Receipt for ${name}</h3>
                    <p style="color:var(--text-3);margin-bottom:1rem;font-size:0.85rem;">Uploaded: ${date}</p>
                    <img src="${path}" style="max-width:100%;max-height:65vh;border-radius:8px;object-fit:contain;background:#000;" />
                    <div style="margin-top:1.5rem">
                        <button class="btn" onclick="document.getElementById('receipt-modal').remove()">Close</button>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function renderPendingList() {
            const el = document.getElementById('pending-list');
            if (!el) return;
            const pending = adminMembers.filter(m => m.payment === 'pending');
            if (!pending.length) {
                el.innerHTML = '<div class="empty"><i class="ti ti-check"></i>All payments approved</div>';
                return;
            }
            el.innerHTML = pending.map(m => `
    <div class="pending-row">
      <div class="mem-av">${initials(m.fname,m.lname)}</div>
      <div style="flex:1">
        <div style="font-weight:600;font-size:.84rem">${m.fname} ${m.lname} <span class="pulse-dot"></span></div>
        <div style="font-size:.7rem;color:var(--text-3)">${m.plan}</div>
      </div>
      <div style="display:flex;gap:.3rem">
        ${m.proof_file ? `<button class="tbtn" title="View Receipt" onclick="viewReceipt('${m.proof_file}', '${m.proof_date}', '${m.fname} ${m.lname}')"><i class="ti ti-file-invoice"></i></button>` : ''}
        <button class="tbtn success" title="Approve" onclick="paymentAction('approve_payment',${m.membership_id || 0})"><i class="ti ti-check"></i></button>
        <button class="tbtn danger" title="Reject" onclick="paymentAction('reject_payment',${m.membership_id || 0})"><i class="ti ti-x"></i></button>
      </div>
    </div>
  `).join('');
        }

        function renderMembers() {
            const q = (document.getElementById('search-input')?.value || '').toLowerCase();
            const sf = (document.getElementById('status-filter')?.value || '');
            const el = document.getElementById('members-tbody');
            if (!el) return;
            const data = adminMembers.filter(m => {
                const txt = (m.fname + ' ' + m.lname + ' ' + m.email).toLowerCase();
                return (!q || txt.includes(q)) && (!sf || m.status === sf);
            });
            const lbl = document.getElementById('member-count');
            if (lbl) lbl.textContent = `${data.length} of ${adminMembers.length}`;
            if (!data.length) {
                el.innerHTML = '<tr><td colspan="7"><div class="empty"><i class="ti ti-search"></i>No members found</div></td></tr>';
                return;
            }
            el.innerHTML = data.map(m => `
    <tr>
      <td><div style="display:flex;align-items:center;gap:.5rem">
        <div class="mem-av">${initials(m.fname,m.lname)}</div>
        <div>
          <div style="font-weight:600">${m.fname} ${m.lname}${m.payment==='pending'?'<span class="pulse-dot"></span>':''}</div>
          <div style="font-size:.68rem;color:var(--text-3)">#${String(m.id).padStart(5,'0')}</div>
        </div>
      </div></td>
      <td style="font-size:.8rem;color:var(--text-2)">${m.email}</td>
      <td><span class="plan-badge ${m.planCls}">${m.plan}</span></td>
      <td style="font-size:.8rem;color:var(--text-2)">${fmtDate(m.joined)}</td>
      <td style="font-size:.8rem;color:var(--text-2)">${fmtDate(m.expiry)}</td>
      <td><span class="dot ${m.status}"></span>${cap(m.status)}</td>
      <td><div class="actions">
        <button class="tbtn" title="View" onclick="showMemberModal(${m.id})"><i class="ti ti-eye"></i></button>
        <button class="tbtn" title="Freeze" onclick="membershipStatusAction(${m.membership_id || 0},'frozen')"><i class="ti ti-player-pause"></i></button>
        <button class="tbtn danger" title="Cancel" onclick="membershipStatusAction(${m.membership_id || 0},'cancelled')"><i class="ti ti-ban"></i></button>
      </div></td>
    </tr>
  `).join('');
        }

        function renderAttendanceTables() {
            const at = document.getElementById('att-tbody');
            if (at) at.innerHTML = ADMIN_DATA.recentAttendance.length
                ? ADMIN_DATA.recentAttendance.map(r => `<tr><td style="font-weight:600">${r.name}</td><td>${r.branch}</td><td style="color:var(--text-2)">${new Date(r.check_in_at).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit'})}</td></tr>`).join('')
                : '<tr><td colspan="3"><div class="empty"><i class="ti ti-login-2"></i>No check-ins recorded</div></td></tr>';

            const bat = document.getElementById('branch-att-tbody');
            if (bat) bat.innerHTML = adminBranches.map(b => `<tr><td style="font-weight:600">${b.name}</td><td>${Number(b.today_visits || 0)}</td><td>${Number(b.total_visits || 0)}</td><td>${Number(b.members || 0)}</td></tr>`).join('');

            const act = document.getElementById('active-tbody');
            if (act) act.innerHTML = ADMIN_DATA.activeMembers.length
                ? ADMIN_DATA.activeMembers.map(m => `<tr><td style="font-weight:600">${m.fname} ${m.lname}</td><td>${Number(m.visits || 0)}</td><td style="color:var(--text-2)">${fmtDate(m.last_visit)}</td></tr>`).join('')
                : '<tr><td colspan="3"><div class="empty"><i class="ti ti-run"></i>No active attendance yet</div></td></tr>';

            const ict = document.getElementById('inactive-tbody');
            if (ict) ict.innerHTML = ADMIN_DATA.inactiveMembers.length
                ? ADMIN_DATA.inactiveMembers.map(m => `<tr><td style="font-weight:600">${m.fname} ${m.lname}</td><td style="color:#e05656">${m.last_visit ? fmtDate(m.last_visit) : 'No visits'}</td></tr>`).join('')
                : '<tr><td colspan="2"><div class="empty"><i class="ti ti-check"></i>No inactive members</div></td></tr>';
        }

        function renderBranches() {
            const el = document.getElementById('branches-grid');
            if (!el) return;
            el.innerHTML = adminBranches.map(b => `
    <div class="branch-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
        <div>
          <div class="branch-name">${b.name}</div>
          <div class="branch-city">${b.city}</div>
        </div>
        <span class="badge ${Number(b.is_active)===1?'active':'expired'}">${Number(b.is_active)===1?'Active':'Inactive'}</span>
      </div>
      <div class="branch-addr"><i class="ti ti-map-pin" style="font-size:.8rem;vertical-align:-1px"></i> ${b.address}</div>
    </div>
  `).join('');
        }

        let _fbStarFilter = 0;

        function setFbStarFilter(star, btn) {
            _fbStarFilter = star;
            document.querySelectorAll('#fb-star-filter .btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            renderFeedbacks();
            renderOrderReviews();
        }

        function renderFeedbacks() {
            const el = document.getElementById('fb-list');
            if (!el) return;
            const data = _fbStarFilter ? adminFeedbacks.filter(f => Number(f.rating) === _fbStarFilter) : adminFeedbacks;
            if (!data.length) {
                el.innerHTML = adminFeedbacks.length
                    ? '<div class="empty"><i class="ti ti-filter-off"></i>No ' + _fbStarFilter + '★ gym feedback</div>'
                    : '<div class="empty"><i class="ti ti-message-off"></i>No gym feedback yet</div>';
                return;
            }
            el.innerHTML = data.map(f => `
    <div class="fb-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:.88rem">${f.name}</div>
          <div class="fb-stars">${'★'.repeat(f.rating)}${'☆'.repeat(5-f.rating)}</div>
        </div>
        <button class="tbtn danger" onclick="deleteFeedbackAction(${f.id})"><i class="ti ti-trash"></i></button>
      </div>
      <div class="fb-text">"${f.text}"</div>
      <div class="fb-meta"><i class="ti ti-map-pin" style="font-size:.75rem"></i> ${f.branch} · ${fmtDate(f.date)}</div>
    </div>
  `).join('');
        }

        function renderOrderReviews() {
            const el = document.getElementById('or-list');
            if (!el) return;
            const data = _fbStarFilter ? adminOrderReviews.filter(r => Number(r.rating) === _fbStarFilter) : adminOrderReviews;
            if (!data.length) {
                el.innerHTML = adminOrderReviews.length
                    ? '<div class="empty"><i class="ti ti-filter-off"></i>No ' + _fbStarFilter + '★ product reviews</div>'
                    : '<div class="empty"><i class="ti ti-star-off"></i>No product reviews yet</div>';
                return;
            }
            el.innerHTML = data.map(r => `
    <div class="fb-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:.88rem">${h(r.name)}</div>
          <div style="font-size:.72rem;color:var(--text-3);margin-bottom:.2rem">${h(r.email)} &middot; Order #${r.order_id}</div>
          <div class="fb-stars">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</div>
        </div>
        <button class="tbtn danger" onclick="deleteOrderReviewAction(${r.id})"><i class="ti ti-trash"></i></button>
      </div>
      <div class="fb-text">"${h(r.text)}"</div>
      <div class="fb-meta"><i class="ti ti-shopping-cart" style="font-size:.75rem"></i> ${fmtDate(r.date)}</div>
    </div>
  `).join('');
        }

        function switchFbTab(tab, btn) {
            document.querySelectorAll('#page-feedbacks .dash-tab').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            // Reset star filter when switching source tab
            _fbStarFilter = 0;
            document.querySelectorAll('#fb-star-filter .btn').forEach(b => b.classList.remove('active'));
            const allBtn = document.getElementById('fsf-0');
            if (allBtn) allBtn.classList.add('active');
            document.getElementById('fb-list').style.display = tab === 'gym'    ? '' : 'none';
            document.getElementById('or-list').style.display = tab === 'orders' ? '' : 'none';
            renderFeedbacks();
            renderOrderReviews();
        }

        function deleteOrderReviewAction(reviewId) {
            confirmAction('Delete review?', 'This product review will be hidden from all views.', () =>
                runAdminAction({ action: 'delete_order_review', review_id: reviewId }, 'Review deleted')
            );
        }

        /* ── MEMBER DETAIL MODAL ─────────────────── */
        function showMemberModal(id) {
            const m = adminMembers.find(x => x.id === id);
            if (!m) return;
            document.getElementById('modal-title').textContent = `${m.fname} ${m.lname}`;
            const amountStr = m.amount > 0 ? `₱${Number(m.amount).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}` : '—';
            const paymentMethodLabel = m.payment_method
                ? m.payment_method.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
                : '—';
            document.getElementById('modal-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:.9rem;margin-bottom:1.1rem">
      <div class="mem-av" style="width:44px;height:44px;border-radius:12px;font-size:1rem">${initials(m.fname,m.lname)}</div>
      <div>
        <div style="font-weight:700;font-size:1rem">${m.fname} ${m.lname}</div>
        <div style="font-size:.78rem;color:var(--text-2)">${m.email}</div>
      </div>
      <span class="badge ${m.status}" style="margin-left:auto">${cap(m.status)}</span>
    </div>
    <div class="detail-grid">
      <div class="detail-cell"><div class="detail-label">Member ID</div><div class="detail-val">#${String(m.id).padStart(5,'0')}</div></div>
      <div class="detail-cell"><div class="detail-label">Branch</div><div class="detail-val">${m.branch || '—'}</div></div>
      <div class="detail-cell"><div class="detail-label">Plan</div><div class="detail-val"><span class="plan-badge ${m.planCls}">${m.plan}</span></div></div>
      <div class="detail-cell"><div class="detail-label">Amount Paid</div><div class="detail-val">${amountStr}</div></div>
      <div class="detail-cell"><div class="detail-label">Payment Method</div><div class="detail-val">${paymentMethodLabel}</div></div>
      <div class="detail-cell"><div class="detail-label">Payment Status</div><div class="detail-val"><span class="badge ${m.payment==='paid'?'paid':'pending'}">${cap(m.payment)}</span></div></div>
      <div class="detail-cell"><div class="detail-label">Joined</div><div class="detail-val">${fmtDate(m.joined)}</div></div>
      <div class="detail-cell"><div class="detail-label">Expires</div><div class="detail-val">${fmtDate(m.expiry)}</div></div>
    </div>
    <div style="margin-top:1rem">
      <div class="detail-label" style="margin-bottom:.35rem">Recent Notes</div>
      ${(adminNotes.filter(n => Number(n.member_id) === Number(m.id)).slice(0,3).map(n => `
        <div class="detail-cell" style="margin-bottom:.4rem">
          <div class="detail-val">${h(n.note_body)}</div>
          <div class="detail-label">${h(n.admin_name || 'Admin')} · ${fmtDate(n.created_at)}</div>
        </div>
      `).join('') || '<div class="detail-cell"><div class="detail-val">No notes yet</div></div>')}
    </div>
  `;
            const foot = document.getElementById('modal-foot');
            const actions = [];
            
            const isPendingAccount = !m.approved;
            const isPendingPayment = m.payment === 'pending';

            if (isPendingAccount || isPendingPayment) {
                if (isPendingAccount) {
                    actions.push(`<button class="btn success-btn sm" onclick="closeModal();accountAction('approve_account',${m.id})"><i class="ti ti-user-check"></i> Approve</button>`);
                    actions.push(`<button class="btn sm" style="border-color:rgba(220,53,69,.4);color:#e05656" onclick="closeModal();accountAction('reject_account',${m.id})"><i class="ti ti-user-x"></i> Reject</button>`);
                } else {
                    actions.push(`<button class="btn success-btn sm" onclick="closeModal();paymentAction('approve_payment',${m.membership_id || 0})"><i class="ti ti-check"></i> Approve</button>`);
                    actions.push(`<button class="btn sm" style="border-color:rgba(220,53,69,.4);color:#e05656" onclick="closeModal();paymentAction('reject_payment',${m.membership_id || 0})"><i class="ti ti-x"></i> Reject</button>`);
                }

                if (m.proof_file) {
                    actions.push(`<button class="btn sm" onclick="viewReceipt('${m.proof_file}', '${m.proof_date}', '${m.fname} ${m.lname}')"><i class="ti ti-file-invoice"></i> View Receipt</button>`);
                }
                
                actions.push(`<div style="flex-basis:100%;height:0;margin:0;"></div>`);
            }

            actions.push(`<button class="btn sm" onclick="openMemberBranchModal(${m.id})"><i class="ti ti-building-store"></i> Branch</button>`);
            actions.push(`<button class="btn sm" onclick="openMemberPlanModal(${m.id})"><i class="ti ti-id-badge-2"></i> Plan</button>`);
            actions.push(`<button class="btn sm" onclick="openExtendMembershipModal(${m.id})"><i class="ti ti-calendar-plus"></i> Extend</button>`);
            actions.push(`<button class="btn sm" onclick="openMemberNoteModal(${m.id})"><i class="ti ti-note"></i> Note</button>`);
            if (m.status === 'active') actions.push(`<button class="btn sm" onclick="closeModal();membershipStatusAction(${m.membership_id || 0},'frozen')"><i class="ti ti-player-pause"></i> Freeze</button>`);
            actions.push(`<button class="btn danger sm" onclick="closeModal();accountAction('delete_account',${m.id})"><i class="ti ti-trash"></i> Delete</button>`);

            foot.style.flexWrap = 'wrap';
            foot.style.justifyContent = 'flex-start';
            foot.innerHTML = actions.join('');
            document.getElementById('modal-backdrop').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modal-backdrop').style.display = 'none';
        }

        /* ── CONFIRM DIALOG ──────────────────────── */
        let confirmCallback = null;

        function confirmAction(title, msg, cb) {
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-msg').textContent = msg;
            confirmCallback = cb;
            document.getElementById('confirm-backdrop').style.display = 'flex';
        }

        function closeConfirm() {
            document.getElementById('confirm-backdrop').style.display = 'none';
            confirmCallback = null;
        }
        document.getElementById('confirm-ok').addEventListener('click', () => {
            const cb = confirmCallback;
            closeConfirm();
            if (cb) cb();
        });

        /* ── TOAST ───────────────────────────────── */
        function toast(type, title, msg) {
            const icons = {
                success: 'ti-circle-check',
                error: 'ti-circle-x',
                info: 'ti-info-circle'
            };
            const el = document.createElement('div');
            el.className = `toast toast-${type}`;
            el.innerHTML = `
    <i class="ti ${icons[type]||icons.info} toast-icon"></i>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${msg?`<div class="toast-msg">${msg}</div>`:''}
    </div>
    <button class="toast-close" onclick="this.closest('.toast').remove()"><i class="ti ti-x"></i></button>
  `;
            document.getElementById('toast-container').appendChild(el);
            setTimeout(() => {
                el.classList.add('out');
                setTimeout(() => el.remove(), 300);
            }, 3800);
        }

        /* ── NAVIGATION ──────────────────────────── */
        const pageTitles = {
            dashboard: 'Dashboard',
            members: 'Members',
            branches: 'Branches',
            schedules: 'Schedules',
            feedbacks: 'Feedbacks',
            reports: 'Reports',
            settings: 'Settings',
            inventory: 'Inventory',
            'shop-orders': 'Shop Orders'
        };
        const pageCrumbs = {
            dashboard: 'Overview',
            members: 'Member Management',
            branches: 'Branch Overview',
            schedules: 'Class Management',
            feedbacks: 'Review Feedbacks',
            reports: 'Analytics',
            settings: 'System Settings',
            inventory: 'Product Management',
            'shop-orders': 'Order Management'
        };

        function showPage(id, btn) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.sb-link').forEach(l => l.classList.remove('active'));
            const pg = document.getElementById('page-' + id);
            if (pg) pg.classList.add('active');
            if (btn) btn.classList.add('active');
            else document.querySelector(`.sb-link[onclick*="'${id}'"]`)?.classList.add('active');
            document.getElementById('tb-title').textContent = pageTitles[id] || id;
            document.getElementById('tb-crumb').textContent = pageCrumbs[id] || id;
            if (location.hash !== '#' + id) history.replaceState(null, '', '#' + id);
            closeSidebar();
            // Lazy-load shop pages
            if (id === 'inventory')   loadInventory();
            if (id === 'shop-orders') loadShopOrders();
        }

        /* ══════════════════════════════════════════════════
           SHOP ADMIN MODULE
        ══════════════════════════════════════════════════ */
        let adminProducts  = [];
        let adminOrders    = [];
        let adminOrdDetail = {};

        // ── Inventory ──────────────────────────────────────
        async function loadInventory() {
            try {
                const res  = await fetch('handlers/shop_handler.php?action=admin_get_products');
                const data = await res.json();
                if (!data.success) return;
                adminProducts = data.products;
                populateInvCatFilter(data.categories);
                renderInvTable(data.products);
            } catch(e) { console.error('Inv load', e); }
        }

        function populateInvCatFilter(cats) {
            const sel = document.getElementById('invCatFilter');
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = '<option value="">All Categories</option>';
            cats.forEach(c => {
                const o = document.createElement('option');
                o.value = c; o.textContent = c;
                if (c === cur) o.selected = true;
                sel.appendChild(o);
            });
        }

        function filterInventory() {
            const q   = (document.getElementById('invSearch')?.value || '').toLowerCase();
            const cat = document.getElementById('invCatFilter')?.value || '';
            renderInvTable(adminProducts.filter(p =>
                (!q || p.name.toLowerCase().includes(q) || p.description.toLowerCase().includes(q))
                && (!cat || p.category === cat)
            ));
        }

        function renderInvTable(products) {
            const tbody = document.getElementById('invTableBody');
            if (!tbody) return;
            if (!products.length) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-3)">No products found.</td></tr>`;
                return;
            }
            const sc = s => parseInt(s) === 0 ? '#ff6b6b' : parseInt(s) <= 5 ? '#ffc107' : '#2ecc71';
            tbody.innerHTML = products.map(p => `
            <tr style="border-bottom:1px solid var(--border);transition:background .12s"
                onmouseover="this.style.background='var(--row-hover)'"
                onmouseout="this.style.background=''">
                <td style="padding:.65rem 1rem">
                    <div style="display:flex;align-items:center;gap:.75rem">
                        ${ p.image && p.image !== ''
                            ? `<img src="${p.image}" class="inv-img" alt="">`
                            : `<div class="inv-img-ph"><i class="ti ti-photo"></i></div>`
                        }
                        <div>
                            <div style="font-weight:600">${adEsc(p.name)}</div>
                            <div style="font-size:.72rem;color:var(--text-3);max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${adEsc(p.description)}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:.65rem .75rem;color:var(--text-2)">${adEsc(p.category)}</td>
                <td style="padding:.65rem .75rem;text-align:right;font-weight:700">&#8369;${parseFloat(p.price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                <td style="padding:.65rem .75rem;text-align:center;font-weight:700;color:${sc(p.stock)}">${p.stock}</td>
                <td style="padding:.65rem .75rem;text-align:center">
                    <span style="font-size:.68rem;font-weight:700;padding:.18rem .55rem;border-radius:6px;
                        background:${parseInt(p.is_active)?'rgba(46,204,113,.12)':'rgba(220,53,69,.12)'};
                        color:${parseInt(p.is_active)?'#2ecc71':'#ff6b6b'}">
                        ${parseInt(p.is_active)?'Active':'Hidden'}
                    </span>
                </td>
                <td style="padding:.65rem .75rem;text-align:right">
                    <div style="display:flex;justify-content:flex-end;gap:.4rem">
                        <button class="btn sm" onclick="openEditProductModal(${p.id})"><i class="ti ti-edit"></i> Edit</button>
                        <button class="btn sm" style="color:var(--red)" onclick="deleteProduct(${p.id},'${adEscAttr(p.name)}')"><i class="ti ti-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
        }

        function openAddProductModal() {
            document.getElementById('product-modal-title').textContent = 'Add Product';
            document.getElementById('pf-id').value        = '0';
            document.getElementById('pf-name').value      = '';
            document.getElementById('pf-category').value  = '';
            document.getElementById('pf-price').value     = '';
            
            // Reset branch stocks
            const branches = <?php echo json_encode(array_map(fn($b) => (int)$b['id'], $branches)); ?>;
            branches.forEach(bid => {
                const input = document.getElementById('pf-stock-' + bid);
                if (input) input.value = 0;
            });
            
            document.getElementById('pf-desc').value      = '';
            document.getElementById('pf-active').checked  = true;
            document.getElementById('pf-existing-image').value = '';
            document.getElementById('pf-img-preview').innerHTML = '';
            document.getElementById('pf-image').value     = '';
            document.getElementById('product-modal-backdrop').style.display = 'flex';
        }

        function openEditProductModal(id) {
            const p = adminProducts.find(x => parseInt(x.id) === id);
            if (!p) return;
            document.getElementById('product-modal-title').textContent = 'Edit Product';
            document.getElementById('pf-id').value        = p.id;
            document.getElementById('pf-name').value      = p.name;
            document.getElementById('pf-category').value  = p.category;
            document.getElementById('pf-price').value     = p.price;
            
            // Populate branch stocks
            const branches = <?php echo json_encode(array_map(fn($b) => (int)$b['id'], $branches)); ?>;
            branches.forEach(bid => {
                const input = document.getElementById('pf-stock-' + bid);
                if (input) {
                    input.value = p.stocks && p.stocks[bid] !== undefined ? p.stocks[bid] : 0;
                }
            });
            
            document.getElementById('pf-desc').value      = p.description;
            document.getElementById('pf-active').checked  = parseInt(p.is_active) === 1;
            document.getElementById('pf-existing-image').value = p.image || '';
            document.getElementById('pf-image').value     = '';
            document.getElementById('pf-img-preview').innerHTML = p.image
                ? `<img src="${p.image}" style="width:72px;height:72px;border-radius:9px;object-fit:cover;margin-top:.4rem" alt="current">`
                : '';
            document.getElementById('product-modal-backdrop').style.display = 'flex';
        }

        function closeProductModal() {
            document.getElementById('product-modal-backdrop').style.display = 'none';
        }

        async function submitProductForm() {
            const btn = document.getElementById('saveProductBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin 1s linear infinite"></i> Saving…';
            try {
                const form = new FormData(document.getElementById('productForm'));
                const res  = await fetch('handlers/shop_handler.php', { method: 'POST', body: form });
                const data = await res.json();
                if (data.success) {
                    toast('success', data.message);
                    closeProductModal();
                    loadInventory();
                } else {
                    toast('error', data.message || 'Save failed.');
                }
            } catch { toast('error', 'Network error.'); }
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-check"></i> Save Product';
        }

        async function deleteProduct(id, name) {
            confirmAction('Confirm', `Delete "${name}" from the shop?`, async () => {
                const f = new FormData();
                f.append('action',     'admin_delete_product');
                f.append('csrf_token', ADMIN_DATA.csrf);
                f.append('product_id', id);
                const data = await (await fetch('handlers/shop_handler.php', { method:'POST', body:f })).json();
                if (data.success) { toast('success', data.message); loadInventory(); }
                else toast('error', data.message || 'Delete failed.');
            });
        }

        // ── Shop Orders ────────────────────────────────────
        async function loadShopOrders() {
            try {
                const res  = await fetch('handlers/shop_handler.php?action=admin_get_orders');
                const data = await res.json();
                if (!data.success) return;
                adminOrders    = data.orders;
                adminOrdDetail = data.details || {};
                filterShopOrders();
            } catch(e) { console.error('Orders load', e); }
        }

        let _soActiveFilter = '';

        function soSetFilter(btn) {
            document.querySelectorAll('.so-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
            _soActiveFilter = btn.dataset.val;
            filterShopOrders();
        }

        function filterShopOrders() {
            const q = (document.getElementById('soSearch')?.value || '').toLowerCase();
            let list = adminOrders;
            if (_soActiveFilter) list = list.filter(o => {
                if (_soActiveFilter === 'cancelled') return o.status === 'cancelled';
                return o.status === _soActiveFilter;
            });
            if (q) list = list.filter(o =>
                String(o.id).includes(q) ||
                (o.customer_name || '').toLowerCase().includes(q) ||
                (o.customer_email || '').toLowerCase().includes(q)
            );
            renderShopOrdersTable(list);
        }

        function renderShopOrdersTable(orders) {
            const tbody = document.getElementById('shopOrdersBody');
            if (!tbody) return;
            if (!orders.length) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:3rem 1rem;color:var(--text-3)">
                    <i class="ti ti-package-off" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    No orders found.
                </td></tr>`;
                return;
            }
            const statusIcons = {
                pending:'ti-clock', processing:'ti-loader-2', out_for_delivery:'ti-truck',
                delivered:'ti-circle-check', ready_for_pickup:'ti-building-store',
                picked_up:'ti-checks', cancelled:'ti-ban', completed:'ti-circle-check'
            };
            const payBadgeCls = {pending:'so-pay-pending', paid:'so-pay-paid', rejected:'so-pay-rejected'};
            tbody.innerHTML = orders.map(o => {
                const d = new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
                const fIcon  = o.fulfillment_method === 'pickup' ? 'ti-building-store' : 'ti-truck';
                const fLabel = o.fulfillment_method === 'pickup' ? 'Pick-Up' : 'Delivery';
                const fColor = o.fulfillment_method === 'pickup' ? '#a066f5' : '#4a9eff';
                const courierLine = (o.fulfillment_method !== 'pickup' && o.shipping_provider)
                    ? `<div style="font-size:.65rem;color:var(--text-3);margin-top:.15rem">${adShippingLabel(o.shipping_provider)}</div>` : '';
                const proofHtml = o.proof_of_payment
                    ? `<img src="${o.proof_of_payment}" class="so-proof-thumb" onclick="viewProofImage('${adEscAttr(o.proof_of_payment)}')" alt="proof">`
                    : `<span style="color:var(--text-3);font-size:.8rem">—</span>`;
                const isComplete = o.status === 'completed';
                const isCancelled= o.status === 'cancelled';
                return `<tr>
                    <td data-label="#" style="font-weight:700;color:var(--text-2);font-size:.8rem">#${o.id}</td>
                    <td data-label="Customer">
                        <div style="font-weight:700;font-size:.85rem">${adEsc(o.customer_name)}</div>
                        <div style="font-size:.72rem;color:var(--text-3);margin-top:.1rem">${adEsc(o.customer_email)}</div>
                    </td>
                    <td data-label="Fulfillment" style="text-align:center">
                        <div style="display:inline-flex;flex-direction:column;align-items:center">
                            <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.22rem .6rem;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:${fColor}">
                                <i class="ti ${fIcon}"></i>${fLabel}
                            </span>
                            ${courierLine}
                        </div>
                    </td>
                    <td data-label="Total" style="text-align:right;font-weight:800;font-size:.88rem">&#8369;${parseFloat(o.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                    <td data-label="Order Status" style="text-align:center">
                        <span class="ord-s ord-${o.status}">
                            <i class="ti ${statusIcons[o.status]||'ti-circle'}"></i>
                            ${o.status.replace(/_/g,' ')}
                        </span>
                    </td>
                    <td data-label="Payment" style="text-align:center">
                        <div style="font-weight:700;font-size:.8rem;text-transform:capitalize;margin-bottom:.3rem;color:var(--text-2)">${o.payment_method.replace(/_/g,' ')}</div>
                        <span class="so-pay-badge ${payBadgeCls[o.payment_status]||''}"
                            style="display:inline-block;${!payBadgeCls[o.payment_status]?'background:rgba(255,255,255,.07);color:var(--text-2)':''}">
                            ${o.payment_status}
                        </span>
                    </td>
                    <td data-label="Proof" style="text-align:center">${proofHtml}</td>
                    <td data-label="Date" style="text-align:center;color:var(--text-3);font-size:.78rem;white-space:nowrap">${d}</td>
                    <td data-label="Actions" style="text-align:right">
                        <div style="display:flex;justify-content:flex-end;align-items:center;gap:.35rem;flex-wrap:nowrap">
                            <button class="so-action-btn" title="View Details" onclick="viewOrderDetail(${o.id})">
                                <i class="ti ti-eye"></i>
                            </button>
                            ${(!isComplete && !isCancelled && o.status !== 'pending') ? `<button class="so-action-btn" title="Update Status" onclick="openUpdateStatus(${o.id},'${o.status}','${o.fulfillment_method}')">
                                <i class="ti ti-edit"></i>
                            </button>` : ''}
                            ${o.status === 'pending' ? `
                            <button class="so-action-btn approve" title="Accept Order" onclick="adminVerifyPayment(${o.id},'approve')">
                                <i class="ti ti-check"></i>
                            </button>
                            <button class="so-action-btn reject" title="Reject Order" onclick="adminVerifyPayment(${o.id},'reject')">
                                <i class="ti ti-x"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function viewProofImage(path) {
            document.getElementById('proof-img-el').src = path;
            document.getElementById('proof-img-modal').style.display = 'flex';
        }

        async function adminVerifyPayment(id, action) {
            const label = action === 'approve' ? 'Accept' : 'Reject';
            confirmAction('Confirm', `${label} Order #${id}?`, async () => {
                const f = new FormData();
                f.append('action',        'admin_verify_payment');
                f.append('csrf_token',    ADMIN_DATA.csrf);
                f.append('order_id',      id);
                f.append('verify_action', action);
                const data = await (await fetch('handlers/shop_handler.php', {method:'POST',body:f})).json();
                if (data.success) { toast('success', data.message); loadShopOrders(); }
                else toast('error', data.message || 'Action failed.');
            });
        }

        let _currentOrderId = null;
        function viewOrderDetail(id) {
            _currentOrderId = id;
            const items = adminOrdDetail[id] || [];
            const o     = adminOrders.find(x => parseInt(x.id) === id);
            document.getElementById('order-detail-title').textContent = `Order #${id}`;
            // Show accept/reject in footer for pending orders
            const verifyBtns = document.getElementById('order-detail-verify-btns');
            if (verifyBtns) {
                if (o?.status === 'pending') {
                    verifyBtns.innerHTML = `
                        <button class="btn sm primary" onclick="adminVerifyPayment(${id},'approve');closeOrderDetailModal();" style="color:#2ecc71;border-color:#2ecc71">
                            <i class="ti ti-check"></i> Accept Order
                        </button>
                        <button class="btn sm" onclick="adminVerifyPayment(${id},'reject');closeOrderDetailModal();" style="color:#ff6b6b;border-color:#ff6b6b">
                            <i class="ti ti-x"></i> Reject Order
                        </button>`;
                } else {
                    verifyBtns.innerHTML = '';
                }
            }
            let addrHtml = '';
            if (o?.fulfillment_method === 'pickup') {
                addrHtml = `<div style="margin-bottom:.65rem;padding:.65rem .85rem;background:rgba(255,255,255,.04);border-radius:9px;font-size:.82rem">
                    <div style="font-weight:700;margin-bottom:.35rem"><i class="ti ti-building-store"></i> Branch Pick-Up</div>
                    <div><span style="color:var(--text-3)">Branch:</span> <strong>${adEsc(o?.branch_name||'')}</strong> — ${adEsc(o?.branch_address||'')}</div>
                    <div><span style="color:var(--text-3)">Date:</span> ${adEsc(o?.pickup_date||'')} at ${adEsc(o?.pickup_time||'')}</div>
                </div>`;
            } else if (o?.delivery_address) {
                let addr = {}; try { addr = JSON.parse(o.delivery_address); } catch(e){}
                const courierBadge = o?.shipping_provider
                    ? `<div style="margin-top:.35rem;display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;font-weight:600;padding:.18rem .55rem;border-radius:20px;background:rgba(74,158,255,.1);border:1px solid rgba(74,158,255,.25);color:#4a9eff"><i class="ti ti-truck" style="font-size:.8rem"></i>${adShippingLabel(o.shipping_provider)}</div>` : '';
                addrHtml = `<div style="margin-bottom:.65rem;padding:.65rem .85rem;background:rgba(255,255,255,.04);border-radius:9px;font-size:.82rem">
                    <div style="font-weight:700;margin-bottom:.35rem"><i class="ti ti-truck"></i> Delivery</div>
                    <div><strong>${adEsc(o?.recipient_name||'')}</strong> · ${adEsc(o?.recipient_contact||'')}</div>
                    <div style="color:var(--text-3);margin-top:.2rem">${adEsc(addr.street||'')+', '+adEsc(addr.barangay||'')+', '+adEsc(addr.city||'')+', '+adEsc(addr.region||'')+' '+adEsc(addr.zip||'')}</div>
                    ${addr.landmark?`<div style="color:var(--text-3)">📍 ${adEsc(addr.landmark)}</div>`:''}
                    ${courierBadge}
                </div>`;
            }
            const statusIcons = {
                pending:'ti-clock', processing:'ti-loader-2', out_for_delivery:'ti-truck',
                delivered:'ti-circle-check', ready_for_pickup:'ti-building-store',
                picked_up:'ti-checks', cancelled:'ti-ban', completed:'ti-circle-check'
            };
            const payColors = {pending:'#ffc107',paid:'#2ecc71',rejected:'#ff6b6b'};
            document.getElementById('order-detail-body').innerHTML = `
                <div style="padding:.85rem 1.1rem">
                    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:.75rem">
                        <span class="ord-s ord-${o?.status}">
                            <i class="ti ${statusIcons[o?.status]||'ti-circle'}"></i>
                            ${(o?.status||'').replace(/_/g,' ')}
                        </span>
                        <span style="font-size:.7rem;font-weight:700;padding:.2rem .55rem;border-radius:7px;background:rgba(0,0,0,.15);color:${payColors[o?.payment_status]||'#aaa'}">
                            ${o?.payment_method?.replace(/_/g,' ')} · ${o?.payment_status}
                        </span>
                        <span style="font-size:.82rem;font-weight:700;margin-left:auto;color:var(--red)">
                            &#8369;${parseFloat(o?.total_amount||0).toLocaleString('en-PH',{minimumFractionDigits:2})}
                        </span>
                    </div>
                    ${addrHtml}
                    ${o?.proof_of_payment?`<div style="margin-bottom:.65rem"><div style="font-size:.75rem;color:var(--text-3);margin-bottom:.35rem;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Proof of Payment</div><img src="${o.proof_of_payment}" onclick="viewProofImage('${adEscAttr(o.proof_of_payment)}')" style="max-height:160px;max-width:100%;border-radius:10px;cursor:pointer;border:1px solid var(--border);display:block" alt="proof"><div style="font-size:.72rem;color:var(--text-3);margin-top:.25rem">Click to enlarge</div></div>`:''}
                    <div style="font-size:.78rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.45rem">Items</div>
                    ${ items.map(it => `<div style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border)">
                        ${ it.image
                            ? `<img src="${it.image}" style="width:48px;height:48px;border-radius:9px;object-fit:cover" alt="">`
                            : `<div style="width:48px;height:48px;border-radius:9px;background:var(--input-bg);display:flex;align-items:center;justify-content:center;color:var(--text-3)"><i class="ti ti-package" style="font-size:1.3rem"></i></div>`
                        }
                        <div style="flex:1">
                            <div style="font-size:.88rem;font-weight:600">${adEsc(it.name)}</div>
                            <div style="font-size:.75rem;color:var(--text-3)">&times;${it.quantity} at &#8369;${parseFloat(it.price).toLocaleString('en-PH',{minimumFractionDigits:2})}/ea</div>
                        </div>
                        <div style="font-weight:700">&#8369;${(parseFloat(it.price)*parseInt(it.quantity)).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                    </div>`).join('') }
                    ${o?.delivery_fee>0?`<div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.82rem"><span style="color:var(--text-3)">Delivery Fee</span><span>&#8369;${parseFloat(o.delivery_fee).toLocaleString('en-PH',{minimumFractionDigits:2})}</span></div>`:''}
                    ${o?.status === 'cancelled' && o?.cancel_reason ? `<div style="margin-top:.85rem;padding:.7rem .9rem;background:rgba(255,107,107,.08);border:1px solid rgba(255,107,107,.2);border-radius:10px">
                        <div style="font-size:.72rem;font-weight:700;color:#ff6b6b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:.3rem;display:flex;align-items:center;gap:.35rem">
                            <i class="ti ti-message-x" style="font-size:.85rem"></i> Cancellation Reason
                        </div>
                        <div style="font-size:.84rem;color:var(--text-2);line-height:1.55">${adEsc(o.cancel_reason)}</div>
                    </div>` : ''}
                </div>`;
            document.getElementById('order-detail-modal').style.display = 'flex';
        }
        function closeOrderDetailModal() {
            document.getElementById('order-detail-modal').style.display = 'none';
        }

        function printAdminOrderReceipt() {
            const id = _currentOrderId;
            if (!id) return;
            const o = adminOrders.find(x => parseInt(x.id) === id);
            const items = adminOrdDetail[id] || [];
            if (!o) return;
            const d = new Date(o.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'});
            let addrBlock = '';
            if (o.fulfillment_method === 'pickup') {
                addrBlock = `<p><strong>Pickup Branch:</strong> ${adEsc(o.branch_name||'')}<br>${adEsc(o.branch_address||'')}</p>
                             <p><strong>Pickup Date/Time:</strong> ${adEsc(o.pickup_date||'')} ${adEsc(o.pickup_time||'')}</p>`;
            } else if (o.delivery_address) {
                let addr = {}; try { addr = JSON.parse(o.delivery_address); } catch(e) {}
                addrBlock = `<p><strong>Deliver to:</strong> ${adEsc(o.recipient_name||'')} &middot; ${adEsc(o.recipient_contact||'')}<br>
                              ${[addr.street,addr.barangay,addr.city,addr.region,addr.zip].filter(Boolean).map(adEsc).join(', ')}</p>`;
            }
            const rows = items.map(it =>
                `<tr><td>${adEsc(it.name)}</td><td style="text-align:center">${it.quantity}</td><td style="text-align:right">&#8369;${parseFloat(it.price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td><td style="text-align:right">&#8369;${(parseFloat(it.price)*parseInt(it.quantity)).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>`
            ).join('');
            const deliveryRow = parseFloat(o.delivery_fee) > 0
                ? `<tr><td colspan="3" style="text-align:right">Delivery Fee</td><td style="text-align:right">&#8369;${parseFloat(o.delivery_fee).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>` : '';
            const win = window.open('', '_blank', 'width=700,height=900');
            win.document.write(`<!DOCTYPE html><html><head><title>Receipt — Order #${id}</title>
            <style>
                body{font-family:Arial,sans-serif;font-size:13px;color:#111;margin:0;padding:2rem}
                h1{font-size:1.3rem;margin:0 0 .25rem}
                h2{font-size:1rem;margin:0 0 1.5rem;color:#555}
                table{width:100%;border-collapse:collapse;margin:1rem 0}
                th{background:#f2f2f2;padding:.45rem .6rem;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px}
                td{padding:.4rem .6rem;border-bottom:1px solid #eee}
                .total-row td{font-weight:700;font-size:1rem;border-top:2px solid #111;border-bottom:none}
                .meta{display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.85rem}
                .meta span{color:#555}
                .footer{margin-top:2rem;font-size:.8rem;color:#888;text-align:center}
                @media print{body{padding:.5rem}button{display:none}}
            </style></head><body>
            <h1>FitSync — Order Receipt</h1>
            <h2>Order #${id} &nbsp;&middot;&nbsp; ${d}</h2>
            <div class="meta">
                <div><span>Customer:</span><br><strong>${adEsc(o.customer_name)}</strong></div>
                <div><span>Email:</span><br><strong>${adEsc(o.customer_email)}</strong></div>
                <div><span>Payment:</span><br><strong>${o.payment_method.replace(/_/g,' ')} &middot; ${o.payment_status}</strong></div>
                <div><span>Status:</span><br><strong>${o.status.replace(/_/g,' ')}</strong></div>
            </div>
            ${addrBlock}
            <table>
                <thead><tr><th>Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Subtotal</th></tr></thead>
                <tbody>${rows}${deliveryRow}</tbody>
                <tfoot><tr class="total-row"><td colspan="3" style="text-align:right">TOTAL</td><td style="text-align:right">&#8369;${parseFloat(o.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr></tfoot>
            </table>
            <div class="footer">Thank you for your purchase! &mdash; FitSync</div>
            <br><button onclick="window.print()" style="padding:.5rem 1.5rem;font-size:.85rem;cursor:pointer">🖨 Print</button>
            </body></html>`);
            win.document.close();
        }

        function openUpdateStatus(id, currentStatus, fulfillmentMethod) {
            const delivery  = ['pending','processing','out_for_delivery','delivered','completed','cancelled'];
            const pickup    = ['pending','processing','ready_for_pickup','picked_up','completed','cancelled'];
            const statuses  = fulfillmentMethod === 'pickup' ? pickup : delivery;
            const opts = statuses.map(s =>
                `<option value="${s}"${s===currentStatus?' selected':''}>${s.replace(/_/g,' ')}</option>`
            ).join('');

            openModal(`Update Order #${id}`,
                `<div style="padding:1.25rem">
                    <label style="font-size:.82rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:.4rem">New Status</label>
                    <select id="new-order-status" style="width:100%;padding:.55rem .8rem;background:var(--input-bg);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.875rem">${opts}</select>
                </div>`,
                [
                    { label: 'Cancel',  cls: '',        fn: 'closeModal()' },
                    { label: 'Update',  cls: 'primary', fn: `saveOrderStatus(${id})` }
                ]
            );
        }

        async function saveOrderStatus(id) {
            const status = document.getElementById('new-order-status')?.value;
            if (!status) return;
            closeModal();
            const f = new FormData();
            f.append('action',     'admin_update_order');
            f.append('csrf_token', ADMIN_DATA.csrf);
            f.append('order_id',   id);
            f.append('status',     status);
            const data = await (await fetch('handlers/shop_handler.php', { method:'POST', body:f })).json();
            if (data.success) { toast('success', data.message); loadShopOrders(); }
            else toast('error', data.message || 'Update failed.');
        }

        // ── Helpers ────────────────────────────────────────
        function adEsc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function adEscAttr(s) { return String(s).replace(/'/g,"&#39;").replace(/"/g,'&quot;'); }
        function adShippingLabel(p) {
            if (!p) return '';
            return {jnt:'J&T Express',flash:'Flash Express',lalamove:'Lalamove',grab:'Grab Express',standard:'FitSync Standard Delivery'}[p] || p;
        }

        function openModal(title, bodyHtml, buttons) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').innerHTML    = bodyHtml;
            document.getElementById('modal-foot').innerHTML    = buttons.map(b =>
                `<button class="btn ${b.cls||''}" onclick="${b.fn}">${b.label}</button>`
            ).join('');
            document.getElementById('modal-backdrop').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('modal-backdrop').style.display = 'none';
        }

        function currentAdminPageHash() {
            const active = document.querySelector('.page.active');
            const id = active?.id?.replace(/^page-/, '') || normalizeAdminPageHash(location.hash) || 'dashboard';
            return '#' + id;
        }

        function normalizeAdminPageHash(hash) {
            const id = String(hash || '').replace(/^#/, '');
            return document.getElementById('page-' + id) ? id : '';
        }

        function restoreAdminPage() {
            const id = normalizeAdminPageHash(location.hash);
            if (id && id !== 'dashboard') showPage(id, null);
        }

        function switchDashTab(id, btn) {
            document.querySelectorAll('.dash-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.dash-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('dt-' + id)?.classList.add('active');
        }

        function filterMembers() {
            renderMembers();
        }

        /* ── SIDEBAR ─────────────────────────────── */
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('overlay').classList.add('open');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('open');
        }

        /* ── THEME ───────────────────────────────── */
        function toggleTheme() {
            const h = document.documentElement;
            const isDark = h.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            h.setAttribute('data-theme', newTheme);
            localStorage.setItem('fs-theme', newTheme);
            const logo = document.getElementById('sidebarLogo');
            if (logo) logo.src = logo.dataset['logo' + (newTheme.charAt(0).toUpperCase() + newTheme.slice(1))];
        }
        (() => {
            const s = localStorage.getItem('fs-theme');
            if (s) {
                document.documentElement.setAttribute('data-theme', s);
                const logo = document.getElementById('sidebarLogo');
                if (logo) logo.src = logo.dataset['logo' + (s.charAt(0).toUpperCase() + s.slice(1))];
            }
        })();

        /* ── BOOT ────────────────────────────────── */
        init();
    </script>

    <!-- ══ QR SCANNER FAB + MODAL ══ -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    <!-- FAB -->
    <button id="qr-fab" onclick="openQR()" title="QR Scanner">
        <i class="ti ti-qrcode"></i>
    </button>

    <!-- Scanner overlay (camera only) -->
    <div id="qr-overlay">
        <div id="qr-overlay-inner">
            <div id="qr-overlay-header">
                <div>
                    <div id="qr-overlay-title"><i class="ti ti-scan" style="color:var(--red);margin-right:.4rem"></i>QR Scanner</div>
                    <div id="qr-overlay-sub" class="qr-sub-label">Camera inactive — press Start to begin</div>
                </div>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <span class="qr-dot qr-dot-offline" id="qr-status-dot"></span>
                    <span class="qr-status-txt" id="qr-status-txt">Offline</span>
                    <button class="qr-close-btn" onclick="closeQR()" title="Close"><i class="ti ti-x"></i></button>
                </div>
            </div>

            <div id="qr-cam-wrap">
                <video id="qr-video" autoplay playsinline muted></video>
                <canvas id="qr-canvas" style="display:none"></canvas>
                <div class="qr-idle" id="qr-idle">
                    <i class="ti ti-camera-off"></i>
                    <p>Camera is off. Press <strong>Start Camera</strong> below.</p>
                </div>
                <div class="qr-scan-overlay" id="qr-scan-overlay" style="display:none">
                    <div class="qr-frame">
                        <div class="qr-cb"></div><div class="qr-cbr"></div>
                        <div class="qr-laser"></div>
                    </div>
                </div>
                <div class="qr-flash" id="qr-flash"></div>
            </div>

            <div class="qr-controls">
                <button class="qr-btn qr-btn-primary" id="qr-btn-start" onclick="qrStartCamera()"><i class="ti ti-player-play"></i> Start Camera</button>
                <button class="qr-btn" id="qr-btn-stop"  onclick="qrStopCamera()"  disabled><i class="ti ti-player-stop"></i> Stop</button>
                <button class="qr-btn" id="qr-btn-flip"  onclick="qrFlipCamera()"  disabled><i class="ti ti-camera-rotate"></i> Flip</button>
            </div>
        </div>
    </div>

    <!-- Confirm check-in modal -->
    <div id="qr-modal-backdrop">
        <div id="qr-modal-box">
            <button class="qr-modal-close" onclick="qrDismissModal()" title="Dismiss"><i class="ti ti-x"></i></button>

            <!-- Avatar + name -->
            <div class="qr-modal-avatar" id="qrm-initials">--</div>
            <div class="qr-modal-name"   id="qrm-name">—</div>
            <div class="qr-modal-id"     id="qrm-id">—</div>

            <!-- Status badge -->
            <span class="badge" id="qrm-badge" style="margin-bottom:.9rem">—</span>

            <!-- Expiry warning -->
            <div class="qr-modal-warn" id="qrm-warn" style="display:none">
                <i class="ti ti-alert-triangle"></i>
                <span id="qrm-warn-txt"></span>
            </div>

            <!-- Detail rows -->
            <div class="qr-modal-details">
                <div class="qr-modal-row"><span class="qr-modal-lbl"><i class="ti ti-id-badge"></i> Plan</span><span class="qr-modal-val" id="qrm-plan">—</span></div>
                <div class="qr-modal-row"><span class="qr-modal-lbl"><i class="ti ti-calendar-event"></i> Expires</span><span class="qr-modal-val" id="qrm-expiry">—</span></div>
                <div class="qr-modal-row"><span class="qr-modal-lbl"><i class="ti ti-building"></i> Branch</span><span class="qr-modal-val" id="qrm-branch">—</span></div>
            </div>

            <!-- Action buttons -->
            <div class="qr-modal-actions">
                <button class="qr-modal-reject" onclick="qrReject()"><i class="ti ti-x"></i> Reject</button>
                <button class="qr-modal-accept" id="qrm-accept-btn" onclick="qrAccept()"><i class="ti ti-check"></i> Accept</button>
            </div>
        </div>
    </div>

    <style>
        /* ── FAB ── */
        #qr-fab {
            position: fixed; bottom: 1.6rem; right: 1.6rem; z-index: 500;
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--red); border: none; color: #fff;
            font-size: 1.35rem; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px var(--red-glow); cursor: pointer;
            transition: background .15s, transform .15s, box-shadow .15s;
        }
        #qr-fab:hover { background: #a01212; transform: translateY(-2px); box-shadow: 0 10px 28px var(--red-glow); }

        /* ── SCANNER OVERLAY ── */
        #qr-overlay {
            display: none; position: fixed; inset: 0; z-index: 600;
            background: var(--bg); overflow-y: auto;
        }
        #qr-overlay.qr-open { display: block; }

        #qr-overlay-inner {
            display: flex; flex-direction: column;
            max-width: 560px; margin: 0 auto;
            padding: 1.25rem 1.5rem 2rem;
            min-height: 100vh;
        }

        /* header */
        #qr-overlay-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.25rem; padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        #qr-overlay-title { font-size: 1rem; font-weight: 700; }
        .qr-sub-label    { font-size: .72rem; color: var(--text-3); margin-top: .1rem; }
        .qr-status-txt   { font-size: .72rem; color: var(--text-3); }
        .qr-close-btn {
            background: none; border: 1px solid var(--border2); color: var(--text-2);
            border-radius: 9px; width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; cursor: pointer; transition: all .15s;
        }
        .qr-close-btn:hover { background: var(--red-soft); color: var(--text); border-color: rgba(204,26,26,.3); }

        /* camera */
        #qr-cam-wrap {
            position: relative; width: 100%; aspect-ratio: 4/3;
            background: #000; border-radius: 12px; overflow: hidden;
        }
        #qr-video { width: 100%; height: 100%; object-fit: cover; display: block; }

        .qr-idle {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: .75rem;
            background: var(--surface2);
        }
        .qr-idle i { font-size: 2.8rem; color: var(--text-3); }
        .qr-idle p { font-size: .8rem; color: var(--text-2); }

        .qr-scan-overlay {
            position: absolute; inset: 0; display: flex;
            align-items: center; justify-content: center; pointer-events: none;
        }
        .qr-frame { width: 190px; height: 190px; position: relative; }
        .qr-frame::before, .qr-frame::after, .qr-cb, .qr-cbr {
            content: ''; position: absolute; width: 28px; height: 28px;
            border-color: var(--red); border-style: solid;
        }
        .qr-frame::before { top:0; left:0;   border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
        .qr-frame::after  { top:0; right:0;  border-width:3px 3px 0 0; border-radius:0 4px 0 0; }
        .qr-cb            { bottom:0; left:0;  border-width:0 0 3px 3px; border-radius:0 0 0 4px; }
        .qr-cbr           { bottom:0; right:0; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
        .qr-laser {
            position: absolute; left: 4px; right: 4px; height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            border-radius: 99px; box-shadow: 0 0 8px var(--red);
            animation: qrLaser 2s ease-in-out infinite;
        }
        @keyframes qrLaser {
            0%   { top: 8px; opacity: 1; }
            45%  { top: calc(100% - 10px); opacity: 1; }
            50%  { top: calc(100% - 10px); opacity: 0; }
            55%  { top: 8px; opacity: 0; }
            60%  { top: 8px; opacity: 1; }
            100% { top: 8px; opacity: 1; }
        }
        .qr-flash {
            position: absolute; inset: 0; display: none;
            background: rgba(76,175,135,.18);
            border: 2px solid #4caf87; border-radius: 10px;
            animation: qrFlashIn .35s ease forwards;
        }
        @keyframes qrFlashIn { 0%{opacity:0} 30%{opacity:1} 100%{opacity:1} }

        /* controls */
        .qr-controls { display: flex; gap: .6rem; margin-top: .9rem; flex-wrap: wrap; }
        .qr-btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .4rem .9rem; border-radius: 99px;
            font-size: .78rem; font-weight: 600; font-family: inherit;
            border: 1px solid var(--border2); background: transparent;
            color: var(--text-2); cursor: pointer; transition: all .15s;
        }
        .qr-btn:hover { background: var(--red-soft); border-color: rgba(204,26,26,.3); color: var(--text); }
        .qr-btn-primary { background: var(--red); border-color: var(--red); color: #fff; }
        .qr-btn-primary:hover { background: #a01212; border-color: #a01212; }
        .qr-btn:disabled { opacity: .4; pointer-events: none; }

        /* status dot */
        .qr-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .qr-dot-online  { background: #4caf87; box-shadow: 0 0 5px rgba(76,175,135,.5); }
        .qr-dot-offline { background: #555; }

        /* ── CONFIRM MODAL ── */
        #qr-modal-backdrop {
            display: none; position: fixed; inset: 0; z-index: 700;
            background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        #qr-modal-backdrop.qrm-open { display: flex; }

        #qr-modal-box {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 18px; padding: 1.75rem 1.5rem 1.5rem;
            width: min(360px, calc(100vw - 2rem));
            display: flex; flex-direction: column; align-items: center;
            gap: .35rem; position: relative;
            animation: qrmSlide .22s ease;
        }
        @keyframes qrmSlide { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:none; } }

        .qr-modal-close {
            position: absolute; top: .85rem; right: .85rem;
            background: none; border: 1px solid var(--border2); color: var(--text-2);
            border-radius: 8px; width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem; cursor: pointer; transition: all .15s;
        }
        .qr-modal-close:hover { background: var(--red-soft); color: var(--text); }

        .qr-modal-avatar {
            width: 64px; height: 64px; border-radius: 16px;
            background: linear-gradient(135deg, var(--red), #7a0f0f);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; font-weight: 800; color: #fff;
            margin-bottom: .4rem;
        }
        .qr-modal-name { font-size: 1.1rem; font-weight: 700; text-align: center; }
        .qr-modal-id   { font-size: .72rem; color: var(--text-3); margin-bottom: .4rem; }

        .qr-modal-warn {
            display: flex; align-items: center; gap: .5rem;
            background: rgba(204,26,26,.08); border: 1px solid rgba(204,26,26,.2);
            border-radius: 9px; padding: .55rem .8rem;
            font-size: .74rem; color: rgba(255,120,120,.9);
            width: 100%; margin-bottom: .2rem;
        }
        .qr-modal-warn i { color: var(--red); flex-shrink: 0; }

        .qr-modal-details {
            width: 100%; display: flex; flex-direction: column;
            gap: .45rem; margin: .5rem 0 .9rem;
        }
        .qr-modal-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: .48rem .7rem; background: var(--surface2);
            border-radius: 9px; border: 1px solid var(--border);
        }
        .qr-modal-lbl {
            font-size: .67rem; font-weight: 700; color: var(--text-3);
            text-transform: uppercase; letter-spacing: .5px;
            display: flex; align-items: center; gap: .3rem;
        }
        .qr-modal-val { font-size: .82rem; font-weight: 600; }

        .qr-modal-actions { display: flex; gap: .7rem; width: 100%; margin-top: .2rem; }
        .qr-modal-reject, .qr-modal-accept {
            flex: 1; padding: .65rem 1rem; border-radius: 10px;
            font-size: .85rem; font-weight: 700; font-family: inherit;
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .45rem;
            transition: background .15s;
        }
        .qr-modal-reject {
            background: var(--surface2); border: 1px solid var(--border2); color: var(--text-2);
        }
        .qr-modal-reject:hover { background: var(--input-bg); color: var(--text); }
        .qr-modal-accept { background: var(--red); color: #fff; }
        .qr-modal-accept:hover { background: #a01212; }
        .qr-modal-accept:disabled { opacity: .4; pointer-events: none; }
    </style>

    <script>
    /* ── member lookup map ── */
    const qrMembers = Object.fromEntries(adminMembers.map(m => [
        `MBR-${String(m.id).padStart(5,'0')}`,
        {
            id:            `MBR-${String(m.id).padStart(5,'0')}`,
            member_id:     m.id,
            membership_id: m.membership_id,
            fname:         m.fname,
            lname:         m.lname,
            plan:          m.plan,
            status:        m.status,
            expiry:        m.expiry,
            branch:        m.branch,
            branch_id:     m.branch_id,
        }
    ]));

    let qrStream      = null;
    let qrScanning    = false;
    let qrFacingMode  = 'environment';
    let qrRafId       = null;
    let qrCurrentMember = null;

    /* ── open / close overlay ── */
    function openQR() {
        document.getElementById('qr-overlay').classList.add('qr-open');
        document.body.style.overflow = 'hidden';
    }
    function closeQR() {
        qrStopCamera();
        document.getElementById('qr-overlay').classList.remove('qr-open');
        document.body.style.overflow = '';
        qrDismissModal();
    }

    /* ── camera ── */
    async function qrStartCamera() {
        try {
            qrStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: qrFacingMode, width:{ideal:1280}, height:{ideal:960} }
            });
            const v = document.getElementById('qr-video');
            v.srcObject = qrStream;
            await v.play();
            document.getElementById('qr-idle').style.display = 'none';
            document.getElementById('qr-scan-overlay').style.display = 'flex';
            document.getElementById('qr-btn-start').disabled = true;
            document.getElementById('qr-btn-stop').disabled  = false;
            document.getElementById('qr-btn-flip').disabled  = false;
            document.getElementById('qr-status-dot').className = 'qr-dot qr-dot-online';
            document.getElementById('qr-status-txt').textContent = 'Live';
            document.getElementById('qr-overlay-sub').textContent = 'Scanning for QR codes\u2026';
            qrScanning = true;
            requestAnimationFrame(qrScanFrame);
        } catch(e) {
            toast('error', 'Camera error', e.message || 'Could not access camera.');
        }
    }

    function qrStopCamera() {
        qrScanning = false;
        if (qrRafId) cancelAnimationFrame(qrRafId);
        if (qrStream) { qrStream.getTracks().forEach(t => t.stop()); qrStream = null; }
        const v = document.getElementById('qr-video');
        if (v) v.srcObject = null;
        document.getElementById('qr-idle').style.display    = 'flex';
        document.getElementById('qr-scan-overlay').style.display = 'none';
        document.getElementById('qr-btn-start').disabled = false;
        document.getElementById('qr-btn-stop').disabled  = true;
        document.getElementById('qr-btn-flip').disabled  = true;
        document.getElementById('qr-status-dot').className = 'qr-dot qr-dot-offline';
        document.getElementById('qr-status-txt').textContent = 'Offline';
        document.getElementById('qr-overlay-sub').textContent = 'Camera inactive \u2014 press Start to begin';
    }

    async function qrFlipCamera() {
        qrFacingMode = qrFacingMode === 'environment' ? 'user' : 'environment';
        qrStopCamera();
        await qrStartCamera();
    }

    /* ── scan loop ── */
    function qrScanFrame() {
        if (!qrScanning) return;
        const v = document.getElementById('qr-video');
        const c = document.getElementById('qr-canvas');
        if (v.readyState === v.HAVE_ENOUGH_DATA) {
            // Downscale the image to a max dimension of ~600px.
            // Mobile cameras output huge resolutions (e.g. 4K) which choke jsQR
            // and reduce its ability to find finder patterns.
            const maxDim = 600;
            const scale = Math.min(maxDim / Math.max(v.videoWidth, v.videoHeight), 1);
            
            c.width = Math.floor(v.videoWidth * scale);
            c.height = Math.floor(v.videoHeight * scale);
            
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(v, 0, 0, c.width, c.height);
            const img = ctx.getImageData(0, 0, c.width, c.height);
            
            const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
            if (code) {
                /* pause scanning while modal is open */
                qrScanning = false;
                qrHandleScan(code.data);
                return;
            }
        }
        qrRafId = requestAnimationFrame(qrScanFrame);
    }

    /* ── lookup & show modal ── */
    function qrHandleScan(raw) {
        const id = raw.trim().toUpperCase();
        const flash = document.getElementById('qr-flash');
        flash.style.display = 'block';
        setTimeout(() => flash.style.display = 'none', 700);

        const m = qrMembers[id] || null;
        if (!m) {
            toast('error', 'Not found', `No member with ID "${id}".`);
            /* resume scanning */
            qrScanning = true;
            requestAnimationFrame(qrScanFrame);
            return;
        }
        qrCurrentMember = m;
        qrShowModal(m);
    }

    function qrShowModal(m) {
        /* populate */
        document.getElementById('qrm-initials').textContent = (m.fname[0]+m.lname[0]).toUpperCase();
        document.getElementById('qrm-name').textContent     = `${m.fname} ${m.lname}`;
        document.getElementById('qrm-id').textContent       = `ID: ${m.id}`;
        document.getElementById('qrm-plan').textContent     = m.plan;
        document.getElementById('qrm-expiry').textContent   = qrFmtDate(m.expiry);
        document.getElementById('qrm-branch').textContent   = m.branch;

        const badge = document.getElementById('qrm-badge');
        badge.textContent = qrCap(m.status);
        badge.className   = `badge ${m.status}`;

        /* expiry warning */
        const warn     = document.getElementById('qrm-warn');
        const warnTxt  = document.getElementById('qrm-warn-txt');
        const daysLeft = Math.ceil((new Date(m.expiry) - new Date()) / 86400000);
        if (m.status === 'expired') {
            warn.style.display = 'flex';
            warnTxt.textContent = `Membership expired on ${qrFmtDate(m.expiry)}.`;
        } else if (m.status === 'active' && daysLeft <= 30 && daysLeft >= 0) {
            warn.style.display = 'flex';
            warnTxt.textContent = `Expires in ${daysLeft} day${daysLeft===1?'':'s'}.`;
        } else {
            warn.style.display = 'none';
        }

        /* disable Accept if not active */
        document.getElementById('qrm-accept-btn').disabled = m.status !== 'active';

        document.getElementById('qr-modal-backdrop').classList.add('qrm-open');
    }

    function qrDismissModal() {
        document.getElementById('qr-modal-backdrop').classList.remove('qrm-open');
        qrCurrentMember = null;
        /* resume scanning */
        if (qrStream) {
            qrScanning = true;
            requestAnimationFrame(qrScanFrame);
        }
    }

    /* ── reject ── */
    function qrReject() {
        toast('info', 'Check-in rejected', qrCurrentMember
            ? `${qrCurrentMember.fname} ${qrCurrentMember.lname} was not admitted.`
            : 'Entry rejected.');
        qrDismissModal();
    }

    /* ── accept (write to DB) ── */
    async function qrAccept() {
        if (!qrCurrentMember) return;
        const m   = qrCurrentMember;
        const btn = document.getElementById('qrm-accept-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin 1s linear infinite"></i> Logging\u2026';

        try {
            const res  = await fetch('handlers/checkin_handler.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    action:     'checkin',
                    user_id:    m.member_id,
                    branch_id:  m.branch_id,
                    csrf_token: CSRF_TOKEN,
                }),
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));

            if (data.success) {
                toast('success', 'Checked in!', `${m.fname} ${m.lname} has been admitted.`);
                qrDismissModal();
            } else {
                toast('error', 'Check-in failed', data.message || 'Could not log visit.');
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-check"></i> Accept';
            }
        } catch(e) {
            toast('error', 'Network error', e.message || 'Could not reach server.');
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-check"></i> Accept';
        }
    }

    /* ── utils ── */
    function qrFmtDate(d) {
        if (!d) return '\u2014';
        return new Date(d+'T00:00:00').toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
    }
    function qrCap(s) { return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; }

    /* close on Escape */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (document.getElementById('qr-modal-backdrop').classList.contains('qrm-open')) {
                qrDismissModal();
            } else {
                closeQR();
            }
        }
    });

    /* close modal when clicking backdrop */
    document.getElementById('qr-modal-backdrop').addEventListener('click', function(e) {
        if (e.target === this) qrDismissModal();
    });
        // ── Report tab client-side switching (no page reload) ──────────
        function switchReportTab(tab, event) {
            if (event) event.preventDefault();
            // Switch tab active state
            document.querySelectorAll('#page-reports [data-rtab]').forEach(function(a) {
                a.classList.toggle('active', a.dataset.rtab === tab);
            });
            // Switch panel visibility
            ['overview','memberships','revenue','attendance','classes'].forEach(function(t) {
                var p = document.getElementById('rt-' + t);
                if (p) p.classList.toggle('active', t === tab);
            });
            // Show/hide filter form
            var form = document.getElementById('rpt-filter-form');
            if (form) {
                form.style.display = tab === 'overview' ? 'none' : '';
                var ti = form.querySelector('input[name="report_tab"]');
                if (ti) ti.value = tab;
                // Reset link
                var rl = document.getElementById('rpt-reset-link');
                if (rl) rl.href = 'admin.php?report_tab=' + encodeURIComponent(tab) + '#reports';
                // Show/hide contextual filter fields
                var rfPlan   = document.getElementById('rf-plan');
                var rfClass  = document.getElementById('rf-class');
                var rfStatus = document.getElementById('rf-status');
                if (rfPlan)   rfPlan.style.display   = (tab === 'memberships' || tab === 'revenue') ? '' : 'none';
                if (rfClass)  rfClass.style.display  = tab === 'classes'      ? '' : 'none';
                if (rfStatus) rfStatus.style.display = tab === 'memberships'  ? '' : 'none';
            }
            // Update URL silently
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('report_tab', tab);
                history.replaceState({}, '', u.toString());
            } catch(e) {}
        }

        /* ════════════════════════════════════════════
           ADMIN NOTIFICATION PANEL
        ════════════════════════════════════════════ */
        function buildAdminNotifs() {
            const d   = ADMIN_DATA.dashboard || {};
            const ss  = ADMIN_DATA.shopStats  || {};
            const items = [];

            // Pending account approvals
            const pendingApprovals = d.pending_approvals || 0;
            if (pendingApprovals > 0) {
                items.push({
                    icon: 'ti-user-plus', type: 'warn',
                    title: `${pendingApprovals} Pending Account${pendingApprovals > 1 ? 's' : ''}`,
                    sub: 'New member registration(s) awaiting your approval.',
                    page: 'members'
                });
            }

            // Pending payments
            const pendingPayments = d.pending_payments || 0;
            if (pendingPayments > 0) {
                items.push({
                    icon: 'ti-clock-dollar', type: 'warn',
                    title: `${pendingPayments} Pending Payment${pendingPayments > 1 ? 's' : ''}`,
                    sub: 'Membership renewal payments waiting for verification.',
                    page: 'members'
                });
            }

            // Out-of-stock products
            const outOfStock = ss.out_of_stock || 0;
            if (outOfStock > 0) {
                items.push({
                    icon: 'ti-alert-circle', type: 'danger',
                    title: `${outOfStock} Product${outOfStock > 1 ? 's' : ''} Out of Stock`,
                    sub: 'Restock required — these items cannot be purchased.',
                    page: 'inventory'
                });
            }

            // Low-stock products
            const lowStock = ss.low_stock || 0;
            if (lowStock > 0) {
                items.push({
                    icon: 'ti-package', type: 'warn',
                    title: `${lowStock} Product${lowStock > 1 ? 's' : ''} Low on Stock`,
                    sub: 'Running low — consider restocking soon.',
                    page: 'inventory'
                });
            }

            // Pending shop orders
            const pendingOrders = ss.pending_orders || 0;
            if (pendingOrders > 0) {
                items.push({
                    icon: 'ti-shopping-bag', type: 'info',
                    title: `${pendingOrders} Shop Order${pendingOrders > 1 ? 's' : ''} Pending`,
                    sub: 'New shop orders need to be processed.',
                    page: 'shop-orders'
                });
            }

            const list = document.getElementById('adminNotifList');
            if (!list) return;

            if (!items.length) {
                list.innerHTML = `<div class="admin-notif-empty">
                    <i class="ti ti-checks"></i>
                    <div style="font-size:.88rem;font-weight:600;margin-bottom:.35rem">All clear!</div>
                    <div style="font-size:.78rem">No pending actions right now.</div>
                </div>`;
                const dot = document.getElementById('adminNotifDot');
                if (dot) dot.style.display = 'none';
            } else {
                list.innerHTML = items.map(item => `
                    <div class="admin-notif-item" data-page="${item.page}" role="button" tabindex="0">
                        <div class="admin-notif-item-icon ${item.type}">
                            <i class="ti ${item.icon}"></i>
                        </div>
                        <div class="admin-notif-item-body">
                            <div class="admin-notif-item-title">${item.title}</div>
                            <div class="admin-notif-item-sub">${item.sub}</div>
                        </div>
                        <i class="ti ti-chevron-right" style="font-size:.8rem;color:var(--text-3);flex-shrink:0;align-self:center"></i>
                    </div>`).join('');

                // Attach click handlers after rendering
                list.querySelectorAll('.admin-notif-item[data-page]').forEach(el => {
                    el.addEventListener('click', function() {
                        const page = this.dataset.page;
                        closeAdminNotifPanel();
                        showPage(page, null);
                    });
                    el.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') this.click();
                    });
                });
            }
        }

        function toggleAdminNotifPanel() {
            const panel   = document.getElementById('adminNotifPanel');
            const overlay = document.getElementById('adminNotifOverlay');
            if (!panel) return;
            if (panel.classList.contains('open')) {
                closeAdminNotifPanel();
            } else {
                buildAdminNotifs();
                panel.classList.add('open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAdminNotifPanel() {
            const panel   = document.getElementById('adminNotifPanel');
            const overlay = document.getElementById('adminNotifOverlay');
            if (!panel) return;
            panel.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Initialise dot badge on page load
        (function initAdminNotifDot() {
            const d  = ADMIN_DATA.dashboard || {};
            const ss = ADMIN_DATA.shopStats  || {};
            const total = (d.pending_approvals || 0)
                        + (d.pending_payments  || 0)
                        + (ss.out_of_stock     || 0)
                        + (ss.low_stock        || 0)
                        + (ss.pending_orders   || 0);
            const dot = document.getElementById('adminNotifDot');
            if (dot) dot.style.display = total > 0 ? 'block' : 'none';
        })();
    </script>

</body>

</html>