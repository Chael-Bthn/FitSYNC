<?php
require_once __DIR__ . '/config/auth_guard.php';
requireRole('admin');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/membership_helpers.php';
require_once __DIR__ . '/includes/report_helpers.php';
require_once __DIR__ . '/includes/schedule_helpers.php';

$pdo = db();
expireOldMemberships($pdo);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$reportRange = reportDateRange($_GET);

$adminUser = currentUser();
$adminInitial = strtoupper(substr(trim($adminUser['name'] ?: $adminUser['email'] ?: 'A'), 0, 1));

$totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$totalBranches = (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE is_active = 1")->fetchColumn();
$totalFeedbacks = (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE is_visible = 1")->fetchColumn();
$monthlyRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships
     WHERE payment_status = 'paid'
       AND (
           updated_at BETWEEN CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00') AND CONCAT(CURDATE(), ' 23:59:59')
           OR created_at BETWEEN CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00') AND CONCAT(CURDATE(), ' 23:59:59')
       )"
)->fetchColumn();
$averageRating = $pdo->query("SELECT AVG(rating) FROM feedback WHERE is_visible = 1")->fetchColumn();
$averageRating = $averageRating ? round((float) $averageRating, 1) : 0.0;

$todayCheckIns = (int) $pdo->query(
    "SELECT COUNT(*) FROM attendance_logs WHERE DATE(check_in_at) = CURDATE()"
)->fetchColumn();

$recentAttendance = $pdo->query(
    "SELECT al.id, al.check_in_at, al.notes,
            u.first_name AS fname, u.last_name AS lname, u.email,
            b.name AS branch
     FROM attendance_logs al
     INNER JOIN users u ON u.id = al.user_id
     INNER JOIN branches b ON b.id = al.branch_id
     ORDER BY al.check_in_at DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$mostActiveMembers = $pdo->query(
    "SELECT u.id, u.first_name AS fname, u.last_name AS lname, u.email,
            COUNT(al.id) AS visits,
            MAX(al.check_in_at) AS last_check_in
     FROM attendance_logs al
     INNER JOIN users u ON u.id = al.user_id
     WHERE al.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY u.id, u.first_name, u.last_name, u.email
     ORDER BY visits DESC, last_check_in DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$inactiveMembers = $pdo->query(
    "SELECT u.id, u.first_name AS fname, u.last_name AS lname, u.email,
            MAX(al.check_in_at) AS last_check_in
     FROM users u
     INNER JOIN memberships m ON m.user_id = u.id
        AND m.status = 'active'
        AND m.payment_status = 'paid'
        AND m.starts_at <= CURDATE()
        AND m.ends_at >= CURDATE()
     LEFT JOIN attendance_logs al ON al.user_id = u.id
     WHERE u.role = 'member' AND u.is_active = 1
     GROUP BY u.id, u.first_name, u.last_name, u.email
     HAVING MAX(al.check_in_at) IS NULL OR MAX(al.check_in_at) < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY MAX(al.check_in_at) IS NULL DESC, MAX(al.check_in_at) ASC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$attendanceByBranch = $pdo->query(
    "SELECT b.id, b.name, b.city,
            COUNT(al.id) AS total_visits,
            SUM(CASE WHEN DATE(al.check_in_at) = CURDATE() THEN 1 ELSE 0 END) AS today_visits,
            SUM(CASE WHEN al.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS visits_30d
     FROM branches b
     LEFT JOIN attendance_logs al ON al.branch_id = b.id
     WHERE b.is_active = 1
     GROUP BY b.id, b.name, b.city
     ORDER BY visits_30d DESC, b.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$totalVisitsLast30Days = array_sum(array_map('intval', array_column($attendanceByBranch, 'visits_30d')));

$memberRows = $pdo->query("SELECT u.id, m.id AS membership_id, u.first_name AS fname, u.last_name AS lname, u.email, u.is_active,
           COALESCE(m.starts_at, u.created_at) AS joined,
           COALESCE(m.ends_at, u.created_at) AS expiry,
           p.id AS plan_id,
           COALESCE(p.label, 'No Plan') AS plan,
           COALESCE(m.status, 'expired') AS status,
           COALESCE(m.payment_status, 'pending') AS payment_status,
           EXISTS(
               SELECT 1
               FROM memberships m3
               WHERE m3.user_id = u.id
                 AND m3.payment_status = 'pending'
                 AND m3.status = 'pending'
           ) AS has_pending_payment
    FROM users u
    LEFT JOIN memberships m ON m.id = (
        SELECT m2.id
        FROM memberships m2
        WHERE m2.user_id = u.id
        ORDER BY
            CASE
                WHEN m2.status = 'active'
                 AND m2.payment_status = 'paid'
                 AND m2.starts_at <= CURDATE()
                 AND m2.ends_at >= CURDATE()
                THEN 1
                WHEN m2.payment_status = 'pending'
                THEN 2
                WHEN m2.status = 'frozen'
                THEN 3
                ELSE 4
            END,
            m2.ends_at DESC,
            m2.id DESC
        LIMIT 1
    )
    LEFT JOIN membership_plans p ON p.id = m.plan_id
    WHERE u.role = 'member'
    ORDER BY COALESCE(m.starts_at, u.created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);

$memberships = $pdo->query("SELECT m.id, u.id AS user_id, u.first_name AS fname, u.last_name AS lname,
           p.label AS plan, b.name AS branch,
           m.starts_at, m.ends_at, m.amount_paid, m.payment_method, m.payment_status, m.status
    FROM memberships m
    INNER JOIN users u ON u.id = m.user_id
    INNER JOIN membership_plans p ON p.id = m.plan_id
    INNER JOIN branches b ON b.id = m.branch_id
    ORDER BY m.starts_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pendingPayments = $pdo->query("SELECT m.id, u.first_name AS fname, u.last_name AS lname, u.email,
           p.label AS plan, b.name AS branch, m.starts_at, m.ends_at, m.amount_paid, m.payment_method, m.created_at
    FROM memberships m
    INNER JOIN users u ON u.id = m.user_id
    INNER JOIN membership_plans p ON p.id = m.plan_id
    INNER JOIN branches b ON b.id = m.branch_id
    WHERE m.payment_status = 'pending' AND m.status = 'pending'
    ORDER BY m.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
$pendingPaymentCount = count($pendingPayments);

$expiringMemberships = $pdo->query("SELECT m.id, u.first_name AS fname, u.last_name AS lname, u.email,
           p.label AS plan, m.ends_at
    FROM memberships m
    INNER JOIN users u ON u.id = m.user_id
    INNER JOIN membership_plans p ON p.id = m.plan_id
    WHERE m.status = 'active' AND m.payment_status = 'paid'
      AND m.ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY m.ends_at ASC")->fetchAll(PDO::FETCH_ASSOC);

$expiredMemberships = $pdo->query("SELECT m.id, u.first_name AS fname, u.last_name AS lname, u.email,
           p.label AS plan, m.ends_at
    FROM memberships m
    INNER JOIN users u ON u.id = m.user_id
    INNER JOIN membership_plans p ON p.id = m.plan_id
    WHERE m.status = 'expired'
    ORDER BY m.ends_at DESC
    LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

$branches = $pdo->query("SELECT id, name, city, address, is_active FROM branches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$plans = $pdo->query("SELECT id, label FROM membership_plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$scheduleBranches = scheduleBranches($pdo);
$classes = scheduleClasses($pdo);
$activeClasses = scheduleClasses($pdo, true);
$classSchedules = scheduleClassSchedules($pdo, 80);
$operatingHours = scheduleOperatingHours($pdo);
$announcements = scheduleAnnouncements($pdo);

$feedbacks = $pdo->query("SELECT f.id, COALESCE(u.first_name, 'Anonymous') AS fname, COALESCE(u.last_name, '') AS lname,
           f.rating, f.body AS text, COALESCE(b.name, 'Unknown') AS branch, f.created_at AS date
    FROM feedback f
    LEFT JOIN users u ON u.id = f.user_id
    LEFT JOIN branches b ON b.id = f.branch_id
    WHERE f.is_visible = 1
    ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

try {
    $contactMessages = $pdo->query(
        "SELECT id, name, email, phone, subject, message, status, created_at
         FROM contact_messages
         ORDER BY created_at DESC
         LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $contactMessages = [];
}

$totalMemberships = (int) $pdo->query("SELECT COUNT(*) FROM memberships")->fetchColumn();
$activeMemberships = (int) $pdo->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND payment_status = 'paid' AND starts_at <= CURDATE() AND ends_at >= CURDATE()")->fetchColumn();
$pendingPaymentCount = count($pendingPayments);
$pendingRegistrationCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND is_active = 0")->fetchColumn();
$memberNotifications = $pendingPaymentCount + $pendingRegistrationCount;
$expiredMembershipCount = (int) $pdo->query("SELECT COUNT(*) FROM memberships WHERE status = 'expired'")->fetchColumn();
$activePlans = count($plans);
$memberReport = memberAnalytics($pdo, $reportRange);
$revenueReport = revenueAnalytics($pdo, $reportRange);
$attendanceReport = attendanceAnalytics($pdo, $reportRange);

$monthlyLabels = [];
$signupData = [];
$revenueData = [];
for ($i = 11; $i >= 0; $i--) {
    $month = new DateTime("first day of -{$i} month");
    $monthlyLabels[] = $month->format('M');
    $start = $month->format('Y-m-01');
    $end = $month->format('Y-m-t');

    $signupStmt = $pdo->prepare("SELECT COUNT(*) FROM memberships WHERE starts_at BETWEEN ? AND ?");
    $signupStmt->execute([$start, $end]);
    $signupData[] = (int) $signupStmt->fetchColumn();

    $revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM memberships
        WHERE payment_status = 'paid'
          AND (
              updated_at BETWEEN ? AND ? OR created_at BETWEEN ? AND ?
          )");
    $revenueStmt->execute([$start . ' 00:00:00', $end . ' 23:59:59', $start . ' 00:00:00', $end . ' 23:59:59']);
    $revenueData[] = (float) $revenueStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin — FitSync</title>
    <link rel="icon" href="assets/FitSYNC Emblem Light.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <script>
        /* Apply saved theme & fix logos before first paint */
        (function () {
            var saved = localStorage.getItem('fs-theme');
            if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
            document.addEventListener('DOMContentLoaded', function () {
                var isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
                document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(function (logo) {
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
            --sidebar-w: 256px;
            --sidebar-bg: #0a0a0a;
            --topbar-h: 60px;
            --card-bg: #111;
            --card-border: rgba(255, 255, 255, .07);
            --text-primary: #fff;
            --text-muted: rgba(255, 255, 255, .4);
            --text-dimmed: rgba(255, 255, 255, .25);
            --link-muted: rgba(255, 255, 255, .5);
            --topbar-bg: rgba(13, 13, 13, .9);
            --body-bg: #0d0d0d;
            --input-bg: rgba(255, 255, 255, .05);
            --input-border: rgba(255, 255, 255, .08);
            --input-color: #fff;
            --input-ph: rgba(255, 255, 255, .3);
            --search-icon: rgba(255, 255, 255, .3);
            --row-hover: rgba(255, 255, 255, .025);
            --th-bg: rgba(255, 255, 255, .03);
            --td-border: rgba(255, 255, 255, .04);
            --stat-before: var(--fs-red-soft);
        }

        [data-bs-theme="light"] {
            --sidebar-bg: #fff;
            --card-bg: #fff;
            --card-border: rgba(0, 0, 0, .08);
            --text-primary: #111;
            --text-muted: rgba(0, 0, 0, .45);
            --text-dimmed: rgba(0, 0, 0, .25);
            --link-muted: rgba(0, 0, 0, .55);
            --topbar-bg: rgba(255, 255, 255, .92);
            --body-bg: #f4f4f5;
            --input-bg: rgba(0, 0, 0, .04);
            --input-border: rgba(0, 0, 0, .1);
            --input-color: #111;
            --input-ph: rgba(0, 0, 0, .3);
            --search-icon: rgba(0, 0, 0, .35);
            --row-hover: rgba(0, 0, 0, .02);
            --th-bg: rgba(0, 0, 0, .03);
            --td-border: rgba(0, 0, 0, .05);
            --stat-before: rgba(204, 26, 26, .06);
        }

        * {
            font-family: 'Outfit', system-ui, sans-serif;
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--body-bg);
            overflow-x: hidden;
            transition: background .3s;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--card-border);
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: transform .3s cubic-bezier(.4, 0, .2, 1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: 1.15rem 1.25rem 1rem;
            border-bottom: 1px solid var(--card-border);
            text-decoration: none;
        }

        .brand-text .fit {
            font-size: 1.1rem;
            font-weight: 900;
            letter-spacing: 1px;
            color: var(--text-primary)
        }

        .brand-text .sync {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--fs-red);
            letter-spacing: 1px
        }

        .sidebar-admin-badge {
            margin: .9rem 1.25rem .4rem;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--text-dimmed);
        }

        .nav-section {
            padding: .2rem 0
        }

        .nav-section-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            color: var(--text-dimmed);
            padding: .9rem 1.35rem .35rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .62rem 1.25rem;
            margin: .1rem .7rem;
            border-radius: 10px;
            color: var(--link-muted);
            text-decoration: none;
            font-size: .87rem;
            font-weight: 500;
            letter-spacing: .2px;
            transition: background .18s, color .18s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: calc(100% - 1.4rem);
            text-align: left;
        }

        .sidebar-link i {
            font-size: 1.1rem;
            flex-shrink: 0
        }

        .sidebar-link:hover {
            background: rgba(128, 128, 128, .1);
            color: var(--text-primary)
        }

        .sidebar-link.active {
            background: var(--fs-red-soft);
            color: var(--text-primary)
        }

        .sidebar-link.active i {
            color: var(--fs-red)
        }

        .sidebar-link .nav-pill {
            margin-left: auto;
            background: var(--fs-red);
            color: #fff;
            font-size: .62rem;
            font-weight: 700;
            padding: .1rem .45rem;
            border-radius: 50px;
            line-height: 1.5;
        }

        /* ── SIDEBAR FOOTER ── */
        .sidebar-footer {
            margin-top: auto;
            padding: 1rem .7rem;
            border-top: 1px solid var(--card-border);
        }

        .sb-theme-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .5rem .85rem;
            margin-bottom: .35rem;
        }

        .sb-theme-label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* ── THEME PILL (matches profile.php) ── */
        .theme-pill {
            width: 44px;
            height: 24px;
            border-radius: 50px;
            border: 1px solid var(--card-border);
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
            transform: translateX(20px)
        }

        .sidebar-link.logout {
            color: rgba(255, 80, 80, .65)
        }

        .sidebar-link.logout:hover {
            background: rgba(204, 26, 26, .12);
            color: #ff6b6b
        }

        /* ── TOPBAR ── */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: var(--topbar-bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            padding: 0 1.75rem;
            gap: 1rem;
            z-index: 1030;
            transition: background .3s;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: .1px;
        }

        .topbar-breadcrumb {
            font-size: .75rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        .topbar-search {
            margin-left: auto;
            position: relative;
        }

        .topbar-search input {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-color);
            border-radius: 10px;
            padding: .4rem .9rem .4rem 2.2rem;
            font-size: .82rem;
            font-family: 'Outfit', sans-serif;
            width: 220px;
            transition: border-color .2s, background .2s;
        }

        .topbar-search input::placeholder {
            color: var(--input-ph)
        }

        .topbar-search input:focus {
            outline: none;
            border-color: rgba(204, 26, 26, .5)
        }

        .topbar-search i {
            position: absolute;
            left: .7rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--search-icon);
            font-size: .95rem;
            pointer-events: none;
        }

        .topbar-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--fs-red), #7a0f0f);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .topbar-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.3rem;
            cursor: pointer;
            padding: .2rem;
        }

        /* ── MAIN CONTENT ── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh
        }

        .main-content {
            padding: 1.75rem
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.4rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
        }

        .stat-card.urgent {
            border-color: var(--fs-red);
            box-shadow: 0 8px 24px var(--fs-red-glow);
        }

        .stat-card .urgent-badge {
            position: absolute;
            top: 10px;
            right: 12px;
            background: var(--fs-red);
            color: #fff;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: .78rem;
        }

        .stat-card:hover {
            border-color: rgba(204, 26, 26, .3);
            transform: translateY(-3px)
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--stat-before);
            pointer-events: none;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--fs-red-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--fs-red);
            margin-bottom: .9rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -1px
        }

        .stat-label {
            font-size: .75rem;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-top: .3rem
        }

        .stat-delta {
            font-size: .75rem;
            font-weight: 600;
            margin-top: .5rem
        }

        .stat-delta.up {
            color: #4caf87
        }

        .stat-delta.down {
            color: #e05656
        }

        /* ── SECTION HEADER ── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.1rem
        }

        .section-h {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.2px
        }

        .section-h small {
            font-size: .72rem;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: .5rem
        }

        /* ── TABLE ── */
        .admin-table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .admin-table {
            margin: 0
        }

        .admin-table thead th {
            background: var(--th-bg);
            border-bottom: 1px solid var(--card-border);
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: .8rem 1.1rem;
            white-space: nowrap;
        }

        .admin-table tbody td {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid var(--td-border);
            color: var(--text-primary);
            font-size: .85rem;
            vertical-align: middle;
        }

        .admin-table tbody tr:last-child td {
            border-bottom: none
        }

        .admin-table tbody tr:hover td {
            background: var(--row-hover)
        }

        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--fs-red), #7a0f0f);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .plan-badge {
            font-size: .65rem;
            font-weight: 700;
            padding: .2rem .6rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: .4px
        }

        .plan-badge.mo1 {
            background: var(--input-bg);
            color: var(--text-muted)
        }

        .plan-badge.mo3 {
            background: rgba(76, 175, 135, .12);
            color: #4caf87
        }

        .plan-badge.mo6 {
            background: rgba(204, 26, 26, .15);
            color: var(--fs-red);
            border: 1px solid rgba(204, 26, 26, .25)
        }

        .plan-badge.yr {
            background: rgba(255, 193, 7, .1);
            color: #ffc107
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: .4rem
        }

        .status-dot.active {
            background: #4caf87;
            box-shadow: 0 0 6px rgba(76, 175, 135, .5)
        }

        .status-dot.inactive {
            background: #888
        }

        .status-dot.pending {
            background: #ffc107
        }

        /* ── TABLE ACTION BUTTONS ── */
        .tbl-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            background: transparent;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            cursor: pointer;
            transition: all .18s;
        }

        .tbl-btn:hover {
            background: var(--fs-red-soft);
            border-color: rgba(204, 26, 26, .3);
            color: var(--fs-red)
        }

        .tbl-btn.danger:hover {
            background: rgba(220, 53, 69, .15);
            border-color: rgba(220, 53, 69, .3);
            color: #e05656
        }

        /* ── FEEDBACK CARDS ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .24rem .58rem;
            border-radius: 999px;
            font-size: .65rem;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-badge.active,
        .status-badge.paid,
        .status-badge.scheduled {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, .14);
            color: #d6a100;
        }

        .status-badge.expired,
        .status-badge.completed,
        .status-badge.frozen {
            background: rgba(150, 150, 150, .14);
            color: #999;
        }

        .status-badge.cancelled,
        .status-badge.failed,
        .status-badge.inactive {
            background: rgba(220, 53, 69, .14);
            color: #e05656;
        }

        .feedback-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 1.15rem 1.3rem;
            transition: border-color .2s, transform .2s;
        }

        .feedback-card:hover {
            border-color: rgba(204, 26, 26, .25);
            transform: translateY(-2px)
        }

        .feedback-stars {
            color: var(--fs-red);
            font-size: .85rem;
            letter-spacing: 1px
        }

        .feedback-text {
            font-size: .85rem;
            color: var(--text-muted);
            line-height: 1.7;
            margin: .5rem 0 0
        }

        .feedback-meta {
            font-size: .72rem;
            color: var(--text-dimmed);
            margin-top: .6rem
        }

        .rating-bar-label {
            font-size: .75rem;
            color: var(--text-muted);
            width: 40px
        }

        .rating-bar-track {
            flex: 1;
            background: var(--input-bg);
            border-radius: 50px;
            height: 7px;
            overflow: hidden
        }

        .rating-bar-fill {
            height: 100%;
            border-radius: 50px;
            background: var(--fs-red)
        }

        /* ── SPARKLINE ── */
        .sparkline {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            height: 40px
        }

        .spark-bar {
            flex: 1;
            border-radius: 3px 3px 0 0;
            background: var(--fs-red-soft);
            transition: background .2s
        }

        .spark-bar.hi {
            background: var(--fs-red)
        }

        .spark-bar:hover {
            background: var(--fs-red)
        }

        /* ── QUICK ACTIONS ── */
        .quick-action {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .85rem;
            cursor: pointer;
            text-decoration: none;
            transition: border-color .2s, transform .2s, background .2s;
        }

        .quick-action:hover {
            border-color: rgba(204, 26, 26, .35);
            background: rgba(204, 26, 26, .04);
            transform: translateY(-2px)
        }

        .quick-action-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: var(--fs-red-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--fs-red);
            flex-shrink: 0;
        }

        .quick-action-label {
            font-size: .83rem;
            font-weight: 600;
            color: var(--text-primary)
        }

        .quick-action-sub {
            font-size: .7rem;
            color: var(--text-muted);
            margin-top: .1rem
        }

        /* ── PAGES ── */
        .page-section {
            display: none
        }

        .page-section.active {
            display: block
        }

        /* ── MODAL ── */
        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px
        }

        .modal-header {
            border-bottom: 1px solid var(--card-border)
        }

        .modal-footer {
            border-top: 1px solid var(--card-border)
        }

        [data-bs-theme="dark"] .btn-close {
            filter: invert(1) grayscale(1)
        }

        /* ── FORM ELEMENTS ── */
        .fs-select {
            width: auto;
            background: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--card-border) !important;
            font-size: .8rem;
            appearance: none;
            padding-right: 2.4rem !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238f8f8f' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .85rem center;
            background-size: 14px 14px;
        }

        select.fs-input {
            appearance: none;
            padding-right: 2.4rem !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238f8f8f' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .85rem center;
            background-size: 14px 14px;
        }

        .fs-select option {
            background: var(--card-bg);
            color: var(--text-primary)
        }

        .fs-input {
            background: var(--input-bg) !important;
            border-color: var(--card-border) !important;
            color: var(--text-primary) !important
        }

        .fs-input::placeholder {
            color: var(--input-ph) !important
        }

        .fs-input:focus {
            border-color: rgba(204, 26, 26, .5) !important;
            box-shadow: none !important
        }

        select.fs-input {
            appearance: none;
            padding-right: 2.4rem !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238f8f8f' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right .85rem center !important;
            background-size: 14px 14px !important;
        }

        .fs-input option {
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        [data-bs-theme="dark"] .fs-input option {
            background: #1a1a1a;
            color: #fff;
        }
        
        [data-bs-theme="light"] .fs-input option {
            background: #fff;
            color: #000;
        }

        .fs-label {
            font-size: .75rem;
            color: var(--text-muted)
        }

        /* ── SIDEBAR OVERLAY ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 1039
        }

        /* ── MOBILE SEARCH BAR ── */
        .mobile-search-bar {
            display: none;
            position: fixed;
            top: var(--topbar-h);
            left: 0;
            right: 0;
            background: var(--topbar-bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--card-border);
            padding: .6rem 1rem;
            align-items: center;
            gap: .5rem;
            z-index: 1029;
        }

        .mobile-search-bar.open {
            display: flex
        }

        .mobile-search-bar i {
            color: var(--search-icon);
            font-size: 1rem;
            flex-shrink: 0
        }

        .mobile-search-bar input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--input-color);
            font-family: 'Outfit', sans-serif;
            font-size: .88rem
        }

        .mobile-search-bar input::placeholder {
            color: var(--input-ph)
        }

        .mobile-search-bar button {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            padding: .1rem
        }

        .mobile-search-open .main-wrap {
            padding-top: calc(var(--topbar-h) + 44px)
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar {
            width: 5px
        }

        ::-webkit-scrollbar-track {
            background: transparent
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .1);
            border-radius: 10px
        }

        /* ══════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════ */

        /* Tablet ≤ 991px — sidebar becomes a drawer */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                width: min(280px, 80vw);
                max-width: 280px;
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .sidebar-overlay.open {
                display: block
            }

            .main-wrap {
                margin-left: 0
            }

            .topbar {
                left: 0
            }

            .topbar-toggle {
                display: flex
            }

            .main-content {
                padding: 1.25rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .topbar-search input {
                width: 140px
            }

            .admin-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .admin-table {
                min-width: 960px;
                width: max-content;
            }

            .admin-table thead th,
            .admin-table tbody td {
                padding: .7rem .9rem;
            }

            .admin-table thead th {
                white-space: normal;
            }

            .tbl-btn {
                width: 28px;
                height: 28px;
                font-size: .85rem;
            }
        }

        /* Mobile ≤ 767px */
        @media (max-width: 767px) {
            .main-content {
                padding: 1rem
            }

            .topbar {
                padding: 0 1rem;
                gap: .6rem;
                flex-wrap: nowrap
            }

            .topbar-search {
                display: none
            }

            .topbar-breadcrumb {
                display: none
            }

            .topbar-title {
                font-size: .9rem
            }

            .stat-value {
                font-size: 1.6rem
            }

            .stat-card {
                padding: 1rem 1.1rem
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
                margin-bottom: .6rem
            }

            .section-header {
                flex-wrap: wrap;
                gap: .6rem
            }

            #page-members .section-header>div.d-flex {
                flex-wrap: wrap;
                width: 100%;
                gap: .5rem
            }

            #page-members .section-header>div.d-flex .fs-select {
                flex: 1
            }

            #page-members .section-header>div.d-flex .btn {
                flex: 1;
                justify-content: center
            }

            .modal-dialog {
                margin: .5rem
            }

            .modal-content {
                border-radius: 14px
            }
        }

        /* Small phones ≤ 479px */
        @media (max-width: 479px) {
            .main-content {
                padding: .75rem
            }

            .stat-card {
                padding: .85rem 1rem
            }

            .stat-value {
                font-size: 1.45rem;
                letter-spacing: -.5px
            }

            .stat-label {
                font-size: .68rem
            }

            .stat-delta {
                font-size: .7rem
            }

            .topbar {
                padding: 0 .75rem;
                gap: .4rem
            }

            .topbar-avatar {
                width: 30px;
                height: 30px;
                font-size: .75rem
            }

            .admin-table .col-hide-xs {
                display: none
            }

            .quick-action {
                padding: .75rem .9rem
            }

            .quick-action-icon {
                width: 32px;
                height: 32px;
                font-size: .95rem
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ════════════════ SIDEBAR ════════════════ -->
    <aside class="sidebar" id="sidebar">

        <a class="sidebar-brand" href="index.php">
            <img class="theme-logo" src="assets/FitSYNC%20Emblem%20Light.svg" data-logo-dark="assets/FitSYNC%20Emblem%20Light.svg" data-logo-light="assets/FitSYNC%20Emblem.svg" alt="FitSync" width="32" height="32" />
            <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
        </a>

        <div class="sidebar-admin-badge">Admin Panel</div>

        <nav class="nav-section flex-grow-1" style="overflow-y:auto">
            <div class="nav-section-label">Overview</div>
            <a class="sidebar-link active" onclick="showPage('dashboard',this)">
                <i class="ti ti-layout-dashboard"></i> Dashboard
            </a>

            <div class="nav-section-label">Management</div>
            <a class="sidebar-link" onclick="showPage('members',this)">
                <i class="ti ti-users"></i> Members
                <?php if (!empty($memberNotifications)): ?>
                    <span class="nav-pill" id="pill-members"><?= number_format($memberNotifications) ?></span>
                <?php endif ?>
            </a>
            <a class="sidebar-link" onclick="showPage('branches',this)">
                <i class="ti ti-building-store"></i> Branches
                <span class="nav-pill" id="pill-branches"><?= number_format($totalBranches) ?></span>
            </a>
            <a class="sidebar-link" onclick="showPage('schedules',this)">
                <i class="ti ti-calendar-event"></i> Schedules
            </a>
            <a class="sidebar-link" onclick="showPage('announcements',this)">
                <i class="ti ti-speakerphone"></i> Announcements
            </a>
            <a class="sidebar-link" onclick="showPage('feedbacks',this)">
                <i class="ti ti-message-star"></i> Feedbacks
                <span class="nav-pill" id="pill-feedbacks"><?= number_format($totalFeedbacks) ?></span>
            </a>
            <a class="sidebar-link" onclick="showPage('reports',this)">
                <i class="ti ti-chart-pie"></i> Reports
            </a>
            <a class="sidebar-link" onclick="showPage('settings',this)">
                <i class="ti ti-settings"></i> Settings
            </a>
        </nav>

        <div class="sidebar-footer">
            <!-- Theme toggle — matches profile.php -->
            <div class="sb-theme-row">
                <span class="sb-theme-label">
                    <i class="ti ti-moon"></i> Dark Mode
                </span>
                <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle theme">
                    <div class="theme-pill-knob"></div>
                </button>
            </div>

            <a class="sidebar-link logout" href="logout.php">
                <i class="ti ti-logout"></i> Logout
            </a>
        </div>

    </aside>

    <!-- ════════════════ TOPBAR ════════════════ -->
    <div class="topbar">
        <button class="topbar-toggle" onclick="openSidebar()"><i class="ti ti-menu-2"></i></button>
        <div class="me-auto">
            <div class="topbar-title" id="topbar-title">Dashboard</div>
            <div class="topbar-breadcrumb">FitSync Admin &rsaquo; <span id="topbar-crumb">Overview</span></div>
        </div>
        <div class="topbar-search" id="topbarSearch">
            <i class="ti ti-search"></i>
            <input type="text" placeholder="Search members…" id="memberSearch" oninput="filterMembers()" autocomplete="off" />
        </div>
        <button class="topbar-toggle d-md-none" onclick="toggleMobileSearch()" aria-label="Search">
            <i class="ti ti-search"></i>
        </button>
        <div class="topbar-avatar" title="Administrator"><?= htmlspecialchars($adminInitial) ?></div>
    </div>

    <!-- Mobile search bar -->
    <div class="mobile-search-bar" id="mobileSearchBar">
        <i class="ti ti-search"></i>
        <input type="text" placeholder="Search members…" id="memberSearchMobile" oninput="syncMobileSearch(this)" autocomplete="off" />
        <button onclick="toggleMobileSearch()"><i class="ti ti-x"></i></button>
    </div>

    <!-- ════════════════ MAIN ════════════════ -->
    <div class="main-wrap">
        <div class="main-content">

            <!-- ══ DASHBOARD ══ -->
            <div class="page-section active" id="page-dashboard">

                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-users"></i></div>
                            <div class="stat-value"><?= number_format($totalMembers) ?></div>
                            <div class="stat-label">Total Members</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> Active & Live</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-cash"></i></div>
                            <div class="stat-value">₱<?= number_format($monthlyRevenue, 0) ?></div>
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> This month</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                            <div class="stat-value"><?= number_format($totalBranches) ?></div>
                            <div class="stat-label">Active Branches</div>
                            <div class="stat-delta up"><i class="ti ti-point"></i> All operational</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-star"></i></div>
                            <div class="stat-value"><?= $averageRating ?></div>
                            <div class="stat-label">Avg. Rating</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> Based on feedback</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">New Sign-ups <small>Last 12 months</small></div>
                            </div>
                            <div class="sparkline" id="sparkline-chart" style="height:80px;gap:5px"></div>
                            <div class="d-flex justify-content-between mt-1" style="font-size:.65rem;color:var(--text-dimmed)">
                                <span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span>
                                <span>Nov</span><span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span>
                                <span>Apr</span><span>May</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="admin-table-wrap p-3 h-100">
                            <div class="section-header mb-3">
                                <div class="section-h">Quick Actions</div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <a class="quick-action" onclick="showPage('members',document.querySelector('.sidebar-link:nth-child(3)'))">
                                    <div class="quick-action-icon"><i class="ti ti-user-plus"></i></div>
                                    <div>
                                        <div class="quick-action-label">Add New Member</div>
                                        <div class="quick-action-sub">Register a walk-in member</div>
                                    </div>
                                </a>
                                <a class="quick-action" onclick="showPage('feedbacks',null)">
                                    <div class="quick-action-icon"><i class="ti ti-message-star"></i></div>
                                    <div>
                                        <div class="quick-action-label">Review Feedbacks</div>
                                        <div class="quick-action-sub"><?= number_format($totalFeedbacks) ?> new reviews</div>
                                    </div>
                                </a>
                                <a class="quick-action" href="index.php" target="_blank">
                                    <div class="quick-action-icon"><i class="ti ti-external-link"></i></div>
                                    <div>
                                        <div class="quick-action-label">View Public Site</div>
                                        <div class="quick-action-sub">Open index.php in new tab</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-header">
                    <div class="section-h">Attendance Analytics <small>Live check-ins</small></div>
                    <span class="btn btn-sm btn-outline-secondary rounded-pill disabled" style="font-size:.75rem">
                        <?= number_format($todayCheckIns) ?> today
                    </span>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-login-2"></i></div>
                            <div class="stat-value"><?= number_format($todayCheckIns) ?></div>
                            <div class="stat-label">Today's Check-ins</div>
                            <div class="stat-delta up"><i class="ti ti-calendar-check"></i> <?= date('M j, Y') ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-run"></i></div>
                            <div class="stat-value"><?= number_format((int) ($mostActiveMembers[0]['visits'] ?? 0)) ?></div>
                            <div class="stat-label">Top 30-Day Visits</div>
                            <div class="stat-delta up"><i class="ti ti-flame"></i> Most active member</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-user-pause"></i></div>
                            <div class="stat-value"><?= number_format(count($inactiveMembers)) ?></div>
                            <div class="stat-label">Inactive Members</div>
                            <div class="stat-delta down"><i class="ti ti-clock"></i> 30+ days no visit</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                            <div class="stat-value"><?= number_format($totalVisitsLast30Days) ?></div>
                            <div class="stat-label">Visits Last 30 Days</div>
                            <div class="stat-delta up"><i class="ti ti-map-pin"></i> All branches</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-6">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Recent Attendance <small>Latest scans</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Branch</th>
                                        <th>Checked In</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentAttendance): ?>
                                        <?php foreach ($recentAttendance as $log): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600"><?= htmlspecialchars($log['fname'] . ' ' . $log['lname']) ?></div>
                                                    <div style="font-size:.7rem;color:var(--text-dimmed)"><?= htmlspecialchars($log['email']) ?></div>
                                                </td>
                                                <td class="col-hide-xs"><?= htmlspecialchars($log['branch']) ?></td>
                                                <td><?= date('M j, g:i A', strtotime($log['check_in_at'])) ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-secondary py-4">No attendance logged yet</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Branch Attendance <small>Today / 30 days</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Today</th>
                                        <th>30 Days</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceByBranch as $branchLog): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600"><?= htmlspecialchars($branchLog['name']) ?></div>
                                                <div style="font-size:.7rem;color:var(--text-dimmed)"><?= htmlspecialchars($branchLog['city']) ?></div>
                                            </td>
                                            <td><?= number_format((int) $branchLog['today_visits']) ?></td>
                                            <td><?= number_format((int) $branchLog['visits_30d']) ?></td>
                                            <td><?= number_format((int) $branchLog['total_visits']) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-6">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Most Active Members <small>Last 30 days</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Visits</th>
                                        <th>Last Visit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($mostActiveMembers): ?>
                                        <?php foreach ($mostActiveMembers as $activeMember): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($activeMember['fname'] . ' ' . $activeMember['lname']) ?></td>
                                                <td><?= number_format((int) $activeMember['visits']) ?></td>
                                                <td><?= date('M j', strtotime($activeMember['last_check_in'])) ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-secondary py-4">No visits in the last 30 days</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Inactive Members <small>30+ days</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Last Visit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($inactiveMembers): ?>
                                        <?php foreach ($inactiveMembers as $inactiveMember): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($inactiveMember['fname'] . ' ' . $inactiveMember['lname']) ?></td>
                                                <td class="col-hide-xs"><?= htmlspecialchars($inactiveMember['email']) ?></td>
                                                <td><?= $inactiveMember['last_check_in'] ? date('M j, Y', strtotime($inactiveMember['last_check_in'])) : 'Never' ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-secondary py-4">No inactive active-membership members</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="section-header">
                    <div class="section-h">Recent Registrations <small>Latest 5</small></div>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="showPage('members',null)" style="font-size:.75rem">
                        View All <i class="ti ti-chevron-right"></i>
                    </button>
                </div>
                <div class="admin-table-wrap">
                    <table class="table admin-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th class="col-hide-xs">Branch</th>
                                <th class="col-hide-xs">Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recent-members-tbody"></tbody>
                    </table>
                </div>

                <div class="section-header mt-4">
                    <div class="section-h">Membership Operations <small>Payments and lifecycle</small></div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-id-badge-2"></i></div>
                            <div class="stat-value"><?= number_format($activeMemberships) ?></div>
                            <div class="stat-label">Active Memberships</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-cash-banknote"></i></div>
                            <div class="stat-value"><?= number_format($pendingPaymentCount) ?></div>
                            <div class="stat-label">Pending Payments</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-calendar-time"></i></div>
                            <div class="stat-value"><?= number_format(count($expiringMemberships)) ?></div>
                            <div class="stat-label">Expiring in 7 Days</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-id-badge-off"></i></div>
                            <div class="stat-value"><?= number_format($expiredMembershipCount) ?></div>
                            <div class="stat-label">Expired Memberships</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-7">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Pending Payment Approvals</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Member</th><th>Plan</th><th>Amount</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if ($pendingPayments): ?>
                                        <?php foreach ($pendingPayments as $payment): ?>
                                            <tr class="pending-payment-row">
                                                <td>
                                                    <div style="font-weight:600"><?= htmlspecialchars($payment['fname'] . ' ' . $payment['lname']) ?> <span class="pending-dot" title="Pending payment approval"></span></div>
                                                    <div style="font-size:.7rem;color:var(--text-dimmed)"><?= htmlspecialchars($payment['email']) ?></div>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($payment['plan']) ?>
                                                    <div style="font-size:.7rem;color:var(--text-dimmed)"><?= date('M j, Y', strtotime($payment['starts_at'])) ?> - <?= date('M j, Y', strtotime($payment['ends_at'])) ?></div>
                                                </td>
                                                <td>₱<?= number_format((float) $payment['amount_paid'], 2) ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="tbl-btn" data-membership="<?= (int) $payment['id'] ?>" title="Approve" onclick="membershipAction('approve_payment', <?= (int) $payment['id'] ?>)"><i class="ti ti-check"></i></button>
                                                        <button class="tbl-btn danger" data-membership="<?= (int) $payment['id'] ?>" title="Reject" onclick="membershipAction('reject_payment', <?= (int) $payment['id'] ?>)"><i class="ti ti-x"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-secondary py-4">No pending payment approvals</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Upcoming Expirations <small>Next 7 days</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Member</th><th>Plan</th><th>Expires</th></tr></thead>
                                <tbody>
                                    <?php if ($expiringMemberships): ?>
                                        <?php foreach ($expiringMemberships as $expiring): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($expiring['fname'] . ' ' . $expiring['lname']) ?></td>
                                                <td><?= htmlspecialchars($expiring['plan']) ?></td>
                                                <td><?= date('M j, Y', strtotime($expiring['ends_at'])) ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-secondary py-4">No memberships expiring soon</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-12">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Expired Memberships <small>Latest expired</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Member</th><th>Plan</th><th>Expired</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php if ($expiredMemberships): ?>
                                        <?php foreach ($expiredMemberships as $expired): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600"><?= htmlspecialchars($expired['fname'] . ' ' . $expired['lname']) ?></div>
                                                    <div style="font-size:.7rem;color:var(--text-dimmed)"><?= htmlspecialchars($expired['email']) ?></div>
                                                </td>
                                                <td><?= htmlspecialchars($expired['plan']) ?></td>
                                                <td><?= date('M j, Y', strtotime($expired['ends_at'])) ?></td>
                                                <td>
                                                    <button class="tbl-btn" title="Reactivate manually" onclick="membershipAction('set_membership_status', <?= (int) $expired['id'] ?>, 'active')">
                                                        <i class="ti ti-player-play"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-secondary py-4">No expired memberships</td></tr>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /dashboard -->

            <!-- ══ MEMBERS ══ -->
            <div class="page-section" id="page-members">
                <div class="section-header mb-3">
                    <div class="section-h">All Members <small id="member-count-label"></small></div>
                    <div class="d-flex gap-2 flex-wrap">
                        <select class="form-select form-select-sm fs-select" id="memberStatusFilter" onchange="filterMembers()">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="frozen">Frozen</option>
                            <option value="pending">Pending Payments</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select class="form-select form-select-sm fs-select" id="planFilter" onchange="filterMembers()">
                            <option value="">All Plans</option>
                            <?php foreach ($plans as $plan) : ?>
                                <option value="<?= htmlspecialchars($plan['label']) ?>"><?= htmlspecialchars($plan['label']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <select class="form-select form-select-sm fs-select" id="branchFilter" onchange="filterMembers()">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch) : ?>
                                <option value="<?= htmlspecialchars($branch['name']) ?>"><?= htmlspecialchars($branch['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <button class="btn btn-sm btn-fs rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="ti ti-plus"></i> Add Member
                        </button>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="table admin-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th class="col-hide-xs">Email</th>
                                <th>Plan</th>
                                <th class="col-hide-xs">Joined</th>
                                <th class="col-hide-xs">Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="members-tbody"></tbody>
                    </table>
                </div>
            </div><!-- /members -->

            <!-- ══ BRANCHES ══ -->
            <div class="page-section" id="page-branches">
                <div class="section-header mb-3">
                    <div class="section-h">Branches <small><?= number_format($totalBranches) ?> active</small></div>
                </div>
                <div class="mb-3" style="font-size:.85rem;color:var(--text-muted)">Once a user registers, they may access any active branch across the network.</div>
                <div class="row g-3" id="branches-list"></div>
            </div><!-- /branches -->

            <!-- ══ REPORTS ══ -->
            <!-- SCHEDULES -->
            <div class="page-section" id="page-schedules">
                <div class="section-header mb-3">
                    <div class="section-h">Schedules <small>Classes and operating hours</small></div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-xl-4">
                        <div class="admin-table-wrap p-3 h-100">
                            <div class="section-h mb-3">Create / Edit Class</div>
                            <input type="hidden" id="classId">
                            <div class="mb-2">
                                <label class="form-label fs-label">Title</label>
                                <input class="form-control fs-input" id="classTitle" maxlength="120">
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-label">Description</label>
                                <textarea class="form-control fs-input" id="classDescription" rows="3" maxlength="500"></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Trainer</label>
                                    <input class="form-control fs-input" id="trainerName" maxlength="120">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Branch</label>
                                    <select class="form-select fs-select" id="classBranch">
                                        <?php foreach ($scheduleBranches as $branch): ?>
                                            <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Duration</label>
                                    <input class="form-control fs-input" id="durationMinutes" type="number" min="15" max="360" value="60">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Capacity</label>
                                    <input class="form-control fs-input" id="classCapacity" type="number" min="0" max="500">
                                </div>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="classActive" checked>
                                <label class="form-check-label" for="classActive">Active</label>
                            </div>
                            <button class="btn btn-fs rounded-pill mt-3" onclick="saveClass()">Save Class</button>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Classes</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Class</th><th>Trainer</th><th>Branch</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($class['title']) ?></strong><div style="color:var(--text-muted);font-size:.75rem"><?= htmlspecialchars($class['description'] ?? '') ?></div></td>
                                            <td><?= htmlspecialchars($class['trainer_name'] ?? 'TBA') ?></td>
                                            <td><?= htmlspecialchars($class['branch_name']) ?></td>
                                            <td><?= (int) $class['duration_minutes'] ?> min</td>
                                            <td><span class="status-badge <?= (int) $class['is_active'] ? 'active' : 'inactive' ?>"><?= (int) $class['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="tbl-btn" title="Edit" onclick='editClass(<?= json_encode($class, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="ti ti-pencil"></i></button>
                                                    <button class="tbl-btn" title="Toggle visibility" onclick="adminAction({action:'set_class_active',class_id:<?= (int) $class['id'] ?>,is_active:<?= (int) $class['is_active'] ? 0 : 1 ?>})"><i class="ti <?= (int) $class['is_active'] ? 'ti-eye-off' : 'ti-eye' ?>"></i></button>
                                                    <button class="tbl-btn danger" title="Delete" onclick="deleteClass(<?= (int) $class['id'] ?>)"><i class="ti ti-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (!$classes): ?><tr><td colspan="6" class="text-center text-secondary py-4">No classes created yet</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-4">
                        <div class="admin-table-wrap p-3 h-100">
                            <div class="section-h mb-3">Add Class Schedule</div>
                            <input type="hidden" id="scheduleId">
                            <div class="mb-2">
                                <label class="form-label fs-label">Class</label>
                                <select class="form-select fs-select" id="scheduleClass">
                                    <?php foreach ($activeClasses as $class): ?>
                                        <option value="<?= (int) $class['id'] ?>" data-branch="<?= (int) $class['branch_id'] ?>"><?= htmlspecialchars($class['title']) ?> - <?= htmlspecialchars($class['branch_name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-label">Branch</label>
                                <select class="form-select fs-select" id="scheduleBranch">
                                    <?php foreach ($scheduleBranches as $branch): ?>
                                        <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label fs-label">Date</label>
                                    <input class="form-control fs-input" id="scheduledDate" type="date">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-label">Start</label>
                                    <input class="form-control fs-input" id="startTime" type="time">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-label">End</label>
                                    <input class="form-control fs-input" id="endTime" type="time">
                                </div>
                            </div>
                            <select class="form-select fs-select mt-2" id="scheduleStatus">
                                <option value="scheduled">Scheduled</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                            <button class="btn btn-fs rounded-pill mt-3" onclick="saveSchedule()">Save Schedule</button>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Recent Schedules</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Class</th><th>Date</th><th>Time</th><th>Branch</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($classSchedules as $schedule): ?>
                                        <?php
                                            $capacity = $schedule['capacity'] !== null ? (int) $schedule['capacity'] : null;
                                            $bookedCount = (int) $schedule['booked_count'];
                                            $remaining = scheduleRemainingCapacity($capacity, $bookedCount);
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($schedule['title']) ?></strong><div style="color:var(--text-muted);font-size:.75rem"><?= htmlspecialchars($schedule['trainer_name'] ?? 'TBA') ?></div></td>
                                            <td><?= date('M j, Y', strtotime($schedule['scheduled_date'])) ?></td>
                                            <td><?= htmlspecialchars(scheduleTime($schedule['start_time']) . ' - ' . scheduleTime($schedule['end_time'])) ?></td>
                                            <td><?= htmlspecialchars($schedule['branch_name']) ?></td>
                                            <td>
                                                <strong><?= number_format($bookedCount) ?><?= $capacity ? ' / ' . number_format($capacity) : '' ?></strong>
                                                <?php if ($remaining !== null): ?>
                                                    <div style="color:var(--text-muted);font-size:.75rem"><?= number_format($remaining) ?> remaining</div>
                                                <?php endif ?>
                                            </td>
                                            <td><span class="status-badge <?= htmlspecialchars($schedule['status']) ?>"><?= htmlspecialchars($schedule['status']) ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="tbl-btn" title="Edit" onclick='editSchedule(<?= json_encode($schedule, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="ti ti-pencil"></i></button>
                                                    <button class="tbl-btn" title="Cancel" onclick="adminAction({action:'set_class_schedule_status',schedule_id:<?= (int) $schedule['id'] ?>,status:'cancelled'})"><i class="ti ti-calendar-x"></i></button>
                                                    <button class="tbl-btn danger" title="Delete" onclick="deleteSchedule(<?= (int) $schedule['id'] ?>)"><i class="ti ti-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (!$classSchedules): ?><tr><td colspan="7" class="text-center text-secondary py-4">No schedules created yet</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="admin-table-wrap p-3">
                    <div class="section-h mb-3">Branch Operating Hours</div>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <select class="form-select fs-select" id="hoursBranch">
                                <?php foreach ($scheduleBranches as $branch): ?>
                                    <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select fs-select" id="hoursDay">
                                <?php for ($day = 1; $day <= 7; $day++): ?>
                                    <option value="<?= $day ?>"><?= scheduleDayName($day) ?></option>
                                <?php endfor ?>
                            </select>
                        </div>
                        <div class="col-md-2"><input class="form-control fs-input" id="openTime" type="time" value="06:00"></div>
                        <div class="col-md-2"><input class="form-control fs-input" id="closeTime" type="time" value="22:00"></div>
                        <div class="col-md-1 d-flex align-items-center"><input class="form-check-input me-2" type="checkbox" id="isClosed"><label for="isClosed">Closed</label></div>
                        <div class="col-md-2"><button class="btn btn-fs w-100 rounded-pill" onclick="saveHours()">Save Hours</button></div>
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <?php foreach ($operatingHours as $hour): ?>
                            <span class="status-badge <?= (int) $hour['is_closed'] ? 'cancelled' : 'active' ?>">
                                <?= htmlspecialchars($hour['branch_name']) ?> <?= scheduleDayName((int) $hour['day_of_week']) ?>:
                                <?= (int) $hour['is_closed'] ? 'Closed' : htmlspecialchars(scheduleTime($hour['open_time']) . '-' . scheduleTime($hour['close_time'])) ?>
                            </span>
                        <?php endforeach ?>
                    </div>
                </div>
            </div><!-- /schedules -->

            <!-- ANNOUNCEMENTS -->
            <div class="page-section" id="page-announcements">
                <div class="section-header mb-3">
                    <div class="section-h">Announcements <small>Branch notices for members</small></div>
                </div>
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="admin-table-wrap p-3 h-100">
                            <div class="section-h mb-3">Create / Edit Announcement</div>
                            <input type="hidden" id="announcementId">
                            <div class="mb-2">
                                <label class="form-label fs-label">Branch</label>
                                <select class="form-select fs-select" id="announcementBranch">
                                    <?php foreach ($scheduleBranches as $branch): ?>
                                        <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-label">Title</label>
                                <input class="form-control fs-input" id="announcementTitle" maxlength="140">
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-label">Body</label>
                                <textarea class="form-control fs-input" id="announcementBody" rows="5"></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Starts</label>
                                    <input class="form-control fs-input" id="startsAt" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs-label">Ends</label>
                                    <input class="form-control fs-input" id="endsAt" type="datetime-local">
                                </div>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="announcementActive" checked>
                                <label class="form-check-label" for="announcementActive">Active</label>
                            </div>
                            <button class="btn btn-fs rounded-pill mt-3" onclick="saveAnnouncement()">Save Announcement</button>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="admin-table-wrap">
                            <div class="section-header p-3 pb-0">
                                <div class="section-h">Branch Announcements</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Notice</th><th>Branch</th><th>Visibility</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($announcements as $notice): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($notice['title']) ?></strong>
                                                <div style="color:var(--text-muted);font-size:.78rem;max-width:420px"><?= htmlspecialchars($notice['body']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($notice['branch_name']) ?></td>
                                            <td>
                                                <?= date('M j, Y g:i A', strtotime($notice['starts_at'])) ?>
                                                <div style="color:var(--text-muted);font-size:.75rem">to <?= $notice['ends_at'] ? date('M j, Y g:i A', strtotime($notice['ends_at'])) : 'further notice' ?></div>
                                            </td>
                                            <td><span class="status-badge <?= (int) $notice['is_active'] ? 'active' : 'inactive' ?>"><?= (int) $notice['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="tbl-btn" title="Edit" onclick='editAnnouncement(<?= json_encode($notice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="ti ti-pencil"></i></button>
                                                    <button class="tbl-btn" title="Toggle visibility" onclick="adminAction({action:'set_announcement_active',announcement_id:<?= (int) $notice['id'] ?>,is_active:<?= (int) $notice['is_active'] ? 0 : 1 ?>})"><i class="ti <?= (int) $notice['is_active'] ? 'ti-eye-off' : 'ti-eye' ?>"></i></button>
                                                    <button class="tbl-btn danger" title="Delete" onclick="deleteAnnouncement(<?= (int) $notice['id'] ?>)"><i class="ti ti-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php if (!$announcements): ?><tr><td colspan="5" class="text-center text-secondary py-4">No announcements created yet</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- /announcements -->

            <div class="page-section" id="page-reports">
                <form class="admin-table-wrap p-3 mb-4" method="get">
                    <input type="hidden" name="page" value="reports">
                    <div class="section-header mb-3">
                        <div class="section-h">Report Filters <small><?= htmlspecialchars($reportRange['start']) ?> to <?= htmlspecialchars($reportRange['end']) ?></small></div>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fs-label">Range</label>
                            <select class="form-select fs-select" name="range">
                                <?php foreach (['today' => 'Today', 'last_7' => 'Last 7 Days', 'last_30' => 'Last 30 Days', 'current_month' => 'Current Month', 'custom' => 'Custom'] as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $reportRange['preset'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-label">Start</label>
                            <input type="date" class="form-control fs-input" name="start" value="<?= htmlspecialchars($reportRange['start']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-label">End</label>
                            <input type="date" class="form-control fs-input" name="end" value="<?= htmlspecialchars($reportRange['end']) ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button class="btn btn-fs rounded-pill px-3" type="submit"><i class="ti ti-filter"></i> Apply</button>
                            <a class="btn btn-outline-secondary rounded-pill px-3" href="admin.php?page=reports"><i class="ti ti-refresh"></i></a>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="handlers/report_export.php?type=attendance&range=<?= urlencode($reportRange['preset']) ?>&start=<?= urlencode($reportRange['start']) ?>&end=<?= urlencode($reportRange['end']) ?>">Export Attendance CSV</a>
                        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="handlers/report_export.php?type=memberships&range=<?= urlencode($reportRange['preset']) ?>&start=<?= urlencode($reportRange['start']) ?>&end=<?= urlencode($reportRange['end']) ?>">Export Memberships CSV</a>
                        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="handlers/report_export.php?type=revenue&range=<?= urlencode($reportRange['preset']) ?>&start=<?= urlencode($reportRange['start']) ?>&end=<?= urlencode($reportRange['end']) ?>">Export Revenue CSV</a>
                    </div>
                </form>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-users"></i></div>
                            <div class="stat-value"><?= number_format($totalMembers) ?></div>
                            <div class="stat-label">Total Members</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-cash"></i></div>
                            <div class="stat-value">₱<?= number_format($monthlyRevenue, 2) ?></div>
                            <div class="stat-label">Revenue This Month</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                            <div class="stat-value"><?= number_format($totalBranches) ?></div>
                            <div class="stat-label">Branches</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-star"></i></div>
                            <div class="stat-value"><?= $averageRating ?></div>
                            <div class="stat-label">Avg. Rating</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-id-badge-2"></i></div>
                            <div class="stat-value"><?= number_format($memberReport['active_members']) ?></div>
                            <div class="stat-label">Active Members</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-user-plus"></i></div>
                            <div class="stat-value"><?= number_format($memberReport['membership_growth']) ?></div>
                            <div class="stat-label">Member Growth</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-calendar-stats"></i></div>
                            <div class="stat-value"><?= number_format($attendanceReport['attendance_count']) ?></div>
                            <div class="stat-label">Attendance</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-repeat"></i></div>
                            <div class="stat-value"><?= number_format($memberReport['average_attendance_frequency'], 1) ?></div>
                            <div class="stat-label">Avg Visits / Member</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-id-badge-off"></i></div>
                            <div class="stat-value"><?= number_format($memberReport['expired_memberships']) ?></div>
                            <div class="stat-label">Expired Memberships</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-users-group"></i></div>
                            <div class="stat-value"><?= number_format($memberReport['active_inactive_ratio'], 2) ?></div>
                            <div class="stat-label">Active / Inactive Ratio</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-cash"></i></div>
                            <div class="stat-value">₱<?= number_format($revenueReport['pending_revenue'], 2) ?></div>
                            <div class="stat-label">Pending Revenue</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-report-money"></i></div>
                            <div class="stat-value">₱<?= number_format($revenueReport['projected_revenue'], 2) ?></div>
                            <div class="stat-label">Projected Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Monthly Signups</div>
                            </div>
                            <div class="sparkline" id="report-signup-chart" style="height:90px;gap:5px"></div>
                            <div class="d-flex justify-content-between mt-1" id="report-signup-labels" style="font-size:.65rem;color:var(--text-dimmed)"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Revenue Trend</div>
                            </div>
                            <div id="revenue-bars"></div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Revenue by Plan</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Plan</th><th>Payments</th><th>Revenue</th></tr></thead>
                                <tbody>
                                    <?php foreach ($revenueReport['by_plan'] as $row): ?>
                                        <tr><td><?= htmlspecialchars($row['label']) ?></td><td><?= number_format((int) $row['count']) ?></td><td>₱<?= number_format((float) $row['revenue'], 2) ?></td></tr>
                                    <?php endforeach ?>
                                    <?php if (!$revenueReport['by_plan']): ?><tr><td colspan="3" class="text-center text-secondary py-4">No paid revenue in this range</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Revenue by Payment Method</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Method</th><th>Payments</th><th>Revenue</th></tr></thead>
                                <tbody>
                                    <?php foreach ($revenueReport['by_payment_method'] as $row): ?>
                                        <tr><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $row['payment_method']))) ?></td><td><?= number_format((int) $row['count']) ?></td><td>₱<?= number_format((float) $row['revenue'], 2) ?></td></tr>
                                    <?php endforeach ?>
                                    <?php if (!$revenueReport['by_payment_method']): ?><tr><td colspan="3" class="text-center text-secondary py-4">No paid revenue in this range</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Busiest Attendance Days</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Date</th><th>Visits</th></tr></thead>
                                <tbody>
                                    <?php foreach ($attendanceReport['busiest_days'] as $row): ?>
                                        <tr><td><?= date('M j, Y', strtotime($row['attendance_date'])) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr>
                                    <?php endforeach ?>
                                    <?php if (!$attendanceReport['busiest_days']): ?><tr><td colspan="2" class="text-center text-secondary py-4">No attendance in this range</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Branch Activity Comparison</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Branch</th><th>City</th><th>Visits</th></tr></thead>
                                <tbody>
                                    <?php foreach ($attendanceReport['branch_comparison'] as $row): ?>
                                        <tr><td><?= htmlspecialchars($row['name']) ?></td><td><?= htmlspecialchars($row['city']) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Most Active Members</div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Member</th><th>Email</th><th>Visits</th></tr></thead>
                                <tbody>
                                    <?php foreach ($attendanceReport['most_active_members'] as $row): ?>
                                        <tr><td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td><td class="col-hide-xs"><?= htmlspecialchars($row['email']) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr>
                                    <?php endforeach ?>
                                    <?php if (!$attendanceReport['most_active_members']): ?><tr><td colspan="3" class="text-center text-secondary py-4">No attendance in this range</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Attendance Trends <small>Weekly</small></div>
                            </div>
                            <table class="table admin-table">
                                <thead><tr><th>Week Start</th><th>Visits</th></tr></thead>
                                <tbody>
                                    <?php foreach ($attendanceReport['weekly_trends'] as $row): ?>
                                        <tr><td><?= date('M j, Y', strtotime($row['week_start'])) ?></td><td><?= number_format((int) $row['visits']) ?></td></tr>
                                    <?php endforeach ?>
                                    <?php if (!$attendanceReport['weekly_trends']): ?><tr><td colspan="2" class="text-center text-secondary py-4">No weekly trend data</td></tr><?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- /reports -->

            <!-- ══ SETTINGS ══ -->
            <div class="page-section" id="page-settings">
                <div class="section-header mb-3">
                    <div class="section-h">Settings</div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">Administrator</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <strong>Name</strong>
                                    <div class="text-muted"><?= htmlspecialchars($adminUser['name'] ?: 'Administrator') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Email</strong>
                                    <div class="text-muted"><?= htmlspecialchars($adminUser['email'] ?: 'admin@fitsync.com') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Role</strong>
                                    <div class="text-muted">Admin</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="admin-table-wrap p-3">
                            <div class="section-header mb-3">
                                <div class="section-h">System Summary</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6"><strong>Plans</strong><div class="text-muted"><?= number_format($activePlans) ?></div></div>
                                <div class="col-6"><strong>Branches</strong><div class="text-muted"><?= number_format($totalBranches) ?></div></div>
                                <div class="col-6"><strong>Memberships</strong><div class="text-muted"><?= number_format($totalMemberships) ?></div></div>
                                <div class="col-6"><strong>Feedbacks</strong><div class="text-muted"><?= number_format($totalFeedbacks) ?></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="admin-table-wrap p-3">
                    <div class="section-header mb-3">
                        <div class="section-h">Active Plans</div>
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        <?php foreach ($plans as $plan) : ?>
                            <div class="col">
                                <div class="stat-card" style="padding:1rem">
                                    <div class="stat-value" style="font-size:1rem; margin-bottom:.4rem"><?= htmlspecialchars($plan['label']) ?></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div><!-- /settings -->

            <!-- ══ FEEDBACKS ══ -->
            <div class="page-section" id="page-feedbacks">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="admin-table-wrap p-3 text-center">
                            <div style="font-size:3.5rem;font-weight:900;color:var(--text-primary);line-height:1">4.8</div>
                            <div class="feedback-stars">★★★★★</div>
                            <div style="font-size:.75rem;color:var(--text-dimmed);margin-top:.3rem">Overall Rating</div>
                            <hr style="border-color:var(--card-border)">
                            <div class="d-flex flex-column gap-2 text-start">
                                <?php
                                $ratings = [5 => 72, 4 => 18, 3 => 6, 2 => 3, 1 => 1];
                                foreach ($ratings as $star => $pct) { ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rating-bar-label"><?= $star ?>★</span>
                                        <div class="rating-bar-track">
                                            <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <span style="font-size:.7rem;color:var(--text-dimmed);width:32px;text-align:right"><?= $pct ?>%</span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="d-flex flex-column gap-2" id="feedback-list"></div>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <div class="section-header p-3 pb-0">
                        <div class="section-h">Contact Messages <small>Latest public inquiries</small></div>
                    </div>
                    <table class="table admin-table">
                        <thead><tr><th>Sender</th><th>Subject</th><th>Message</th><th>Received</th></tr></thead>
                        <tbody>
                            <?php foreach ($contactMessages as $message): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700"><?= htmlspecialchars($message['name']) ?></div>
                                        <div style="font-size:.72rem;color:var(--text-dimmed)"><?= htmlspecialchars($message['email']) ?></div>
                                        <?php if ($message['phone']): ?><div style="font-size:.72rem;color:var(--text-dimmed)"><?= htmlspecialchars($message['phone']) ?></div><?php endif ?>
                                    </td>
                                    <td><?= htmlspecialchars($message['subject']) ?></td>
                                    <td style="max-width:420px;color:var(--text-muted)"><?= nl2br(htmlspecialchars($message['message'])) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></td>
                                </tr>
                            <?php endforeach ?>
                            <?php if (!$contactMessages): ?><tr><td colspan="4" class="text-center text-secondary py-4">No contact messages yet</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /feedbacks -->

        </div>
    </div><!-- /main-wrap -->

    <!-- ════════════════ ADD MEMBER MODAL ════════════════ -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-size:.95rem">Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div class="row g-3">
                        <!-- Name Row -->
                        <div class="col-6">
                            <label class="form-label fs-label">First Name</label>
                            <input type="text" class="form-control fs-input" placeholder="Juan" id="new-fname">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-label">Last Name</label>
                            <input type="text" class="form-control fs-input" placeholder="Dela Cruz" id="new-lname">
                        </div>
                        
                        <!-- Gender -->
                        <div class="col-12">
                            <label class="form-label fs-label">Gender</label>
                            <select class="form-select fs-input" id="new-gender">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="nonbinary">Non-binary</option>
                                <option value="other">Prefer not to say</option>
                            </select>
                        </div>
                        
                        <!-- Birthdate -->
                        <div class="col-12">
                            <label class="form-label fs-label">Birthdate</label>
                            <input type="date" class="form-control fs-input" id="new-birthdate" max="<?= date('Y-m-d', strtotime('-16 years')) ?>">
                        </div>
                        
                        <!-- Email -->
                        <div class="col-12">
                            <label class="form-label fs-label">Email</label>
                            <input type="email" class="form-control fs-input" placeholder="juan@email.com" id="new-email">
                        </div>
                        
                        <!-- Password -->
                        <div class="col-12">
                            <label class="form-label fs-label">Password</label>
                            <input type="password" class="form-control fs-input" placeholder="Min. 8 characters" id="new-password">
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="col-12">
                            <label class="form-label fs-label">Confirm Password</label>
                            <input type="password" class="form-control fs-input" placeholder="Repeat password" id="new-confirm-password">
                        </div>
                        
                        <!-- Plan -->
                        <div class="col-12">
                            <label class="form-label fs-label">Plan</label>
                            <select class="form-select fs-input" id="new-plan">
                                <?php foreach ($plans as $plan) : ?>
                                    <option value="<?= (int) $plan['id'] ?>"><?= htmlspecialchars($plan['label']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="col-12">
                            <label class="form-label fs-label">Payment Method</label>
                            <select class="form-select fs-input" id="new-payment">
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash" selected>Cash / Walk-in</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-fs btn-sm rounded-pill px-4" onclick="addMember()">
                        <i class="ti ti-user-plus me-1"></i>Add Member
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* ── DATA ── */
        let members = <?= json_encode($memberRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const memberships = <?= json_encode($memberships, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const branches = <?= json_encode($branches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const feedbacks = <?= json_encode($feedbacks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const plans = <?= json_encode($plans, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const monthlyLabels = <?= json_encode($monthlyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const signupData = <?= json_encode($signupData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const revenueData = <?= json_encode($revenueData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const ADMIN_CSRF = <?= json_encode($csrf) ?>;
        const planClass = {
            '1 Month': 'mo1',
            '3 Months': 'mo3',
            '6 Months': 'mo6',
            '12 Months': 'yr'
        };
        const starsStr = n => '★'.repeat(n) + '☆'.repeat(5 - n);

        /* ── INIT ── */
        function init() {
            var pf = document.getElementById('planFilter');
            if (pf) pf.selectedIndex = 0;
            var ms = document.getElementById('memberSearch');
            if (ms) ms.value = '';
            var msm = document.getElementById('memberSearchMobile');
            if (msm) msm.value = '';
            var sf = document.getElementById('memberStatusFilter');
            if (sf) sf.selectedIndex = 0;
            var bf = document.getElementById('branchFilter');
            if (bf) bf.selectedIndex = 0;

            buildSparkline();
            buildRevenueBars();
            renderRecentMembers();
            renderMembers();
            renderFeedbacks();
            renderBranches();
        }

        function buildSparkline() {
            const max = Math.max(...signupData, 1);
            const html = signupData.map((v, i) => {
                const h = Math.round((v / max) * 100);
                const hi = i === signupData.length - 1;
                return `<div class="spark-bar${hi ? ' hi' : ''}" style="height:${h}%" title="${v} signups"></div>`;
            }).join('');
            document.getElementById('sparkline-chart').innerHTML = html;
            document.getElementById('report-signup-chart').innerHTML = html;
            document.getElementById('report-signup-labels').innerHTML = monthlyLabels.map(label => `<span>${label}</span>`).join('');
        }

        function buildRevenueBars() {
            const max = Math.max(...revenueData, 1);
            document.getElementById('revenue-bars').innerHTML = revenueData.map((value, i) => `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span style="width: 38px; font-size:.75rem; color: var(--text-muted)">${monthlyLabels[i]}</span>
                    <div class="flex-grow-1" style="height:10px; background: var(--input-bg); border-radius:999px; overflow:hidden;">
                        <div style="width:${Math.round((value / max) * 100)}%; height:100%; background: var(--fs-red);"></div>
                    </div>
                    <span style="width:90px; text-align:right; font-size:.75rem; color: var(--text-muted)">₱${value.toLocaleString('en-PH', {maximumFractionDigits:2,minimumFractionDigits:2})}</span>
                </div>
            `).join('');
        }

        function renderRecentMembers() {
            document.getElementById('recent-members-tbody').innerHTML =
                members.slice(0, 5).map(m => memberRow(m, true)).join('');
        }

        function renderMembers() {
            const q    = (document.getElementById('memberSearch')?.value  || '').trim().toLowerCase();
            const plan = (document.getElementById('planFilter')?.value    || '').trim();
            const status = (document.getElementById('memberStatusFilter')?.value || '').trim();
            const branch = (document.getElementById('branchFilter')?.value || '').trim();

            const data = members.filter(function(m) {
                var txt = ((m.fname||'') + ' ' + (m.lname||'') + ' ' + (m.email||'')).toLowerCase();
                var planOk = !plan || (m.plan||'') === plan;
                var branchOk = !branch || (m.branch||'') === branch;
                var statusOk = !status
                    || (status === 'pending' && (m.payment_status || '') === 'pending')
                    || (status === 'inactive' && !(m.last_check_in || ''))
                    || (m.status || '') === status;
                var txtOk  = !q    || txt.includes(q);
                return planOk && branchOk && statusOk && txtOk;
            });

            var tbody = document.getElementById('members-tbody');
            if (!tbody) return;

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">No members found</td></tr>';
                document.getElementById('member-count-label').textContent = '0 of ' + members.length;
                return;
            }

            var html = '';
            for (var i = 0; i < data.length; i++) {
                try { html += memberRow(data[i], false); }
                catch(e) { console.error('memberRow error', data[i], e); }
            }
            tbody.innerHTML = html || '<tr><td colspan="7" class="text-center text-secondary py-4">No members found</td></tr>';
            document.getElementById('member-count-label').textContent = data.length + ' of ' + members.length;
        }

        function renderMemberships() {
            document.getElementById('memberships-tbody').innerHTML =
                memberships.map(m => membershipRow(m)).join('') ||
                '<tr><td colspan="7" class="text-center text-secondary py-4">No memberships found</td></tr>';
        }

        function renderBranches() {
            document.getElementById('branches-list').innerHTML = branches.map(b => `
                <div class="col-md-6 col-xl-4">
                    <div class="feedback-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 style="margin:0;font-size:1rem;color:var(--text-primary)">${b.name}</h5>
                                <div style="font-size:.82rem;color:var(--text-muted)">${b.city}</div>
                            </div>
                            <span class="status-dot ${b.is_active ? 'active' : 'inactive'}"></span>
                        </div>
                        <div style="font-size:.82rem;color:var(--text-muted)">${b.address || 'Address not set'}</div>
                    </div>
                </div>
            `).join('');
        }

        function membershipRow(m) {
            const starts = formatDate(m.starts_at);
            const ends = formatDate(m.ends_at);
            var urgentCls = '';
            var pendingDot = '';
            if ((m.payment_status || '') === 'pending' && (m.status || '') === 'pending') {
                urgentCls = ' class="row-urgent"';
                pendingDot = '<span class="pending-dot" title="Pending payment"></span> ';
            }
            return `<tr${urgentCls}>
                <td>${pendingDot}${m.fname} ${m.lname}</td>
                <td class="col-hide-xs">${m.plan}</td>
                <td class="col-hide-xs">${m.branch}</td>
                <td>${starts}</td>
                <td class="col-hide-xs">${ends}</td>
                <td>₱${Number(m.amount_paid).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td class="col-hide-xs">${capitalize(m.payment_status)} / ${capitalize(m.status)}</td>
            </tr>`;
        }

        function formatDate(value) {
            if (!value) return '—';
            return new Date(value).toLocaleDateString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function capitalize(value) {
            return value ? value.charAt(0).toUpperCase() + value.slice(1) : '—';
        }

        // small UI helpers for urgency
        var style = document.createElement('style');
        style.innerHTML = `
            .pending-dot { display:inline-block;width:10px;height:10px;background:var(--fs-red);border-radius:50%;margin-left:.5rem;box-shadow:0 0 6px rgba(122,15,15,.85);vertical-align:middle }
            .row-urgent { background: linear-gradient(90deg, rgba(255,241,241,.8), rgba(255,249,249,.6)); border-left: 4px solid var(--fs-red); }
        `;
        document.head.appendChild(style);

        function memberRow(m, compact) {
            var fname    = m.fname   || '';
            var lname    = m.lname   || '';
            var email    = m.email   || '';
            var plan     = m.plan    || 'No Plan';
            var status   = m.status  || 'active';
            var initials = ((fname[0] || '?') + (lname[0] || '?')).toUpperCase();
            var date     = formatDate(m.joined);
            var expiry   = formatDate(m.expiry);
            var id       = m.id || 0;
            var membershipId = m.membership_id || 0;
            var idStr    = String(id).padStart(5, '0');
            var cls      = planClass[plan] || '';

            var avatar   = '<div class="member-avatar">' + initials + '</div>';
            var statusHtml = '<span class="status-dot ' + status + '"></span>' + capitalize(status);
            var planBadge  = '<span class="plan-badge ' + cls + '">' + plan + '</span>';

            var planOptions = '';
            plans.forEach(function(p) {
                planOptions += '<option value="' + p.id + '"' + (Number(p.id) === Number(m.plan_id || 0) ? ' selected' : '') + '>' + p.label + '</option>';
            });
            var planSelect = '<select class="form-select fs-select" style="font-size:.85rem;padding:.4rem" onchange="changeMemberPlan(' + id + ', this)">' + planOptions + '</select>';

            if (compact) {
                return '<tr>'
                    + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<span>' + fname + ' ' + lname + '</span></div></td>'
                    + '<td>' + planBadge + '</td>'
                    + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + date + '</span></td>'
                    + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + expiry + '</span></td>'
                    + '<td>' + statusHtml + '</td>'
                    + '</tr>';
            }

            var rowAttr = 'data-id="' + id + '"';
            var pendingMark = '';
            var urgentRow = '';
            if ((m.has_pending_payment || false) || (m.payment_status || '') === 'pending') {
                pendingMark = '<span class="pending-dot" title="Pending payment approval"></span>';
                urgentRow = ' class="row-urgent"';
            }

            return '<tr ' + rowAttr + urgentRow + '>'
                + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div>'
                +   '<div style="font-weight:600">' + fname + ' ' + lname + ' ' + pendingMark + '</div>'
                +   '<div style="font-size:.7rem;color:var(--text-dimmed)">#' + idStr + '</div>'
                + '</div></div></td>'
                + '<td class="col-hide-xs"><span style="font-size:.82rem;color:var(--text-muted)">' + email + '</span></td>'
                + '<td>' + planSelect + '</td>'
                + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + date + '</span></td>'
                + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + expiry + '</span></td>'
                + '<td>' + statusHtml + '</td>'
                + '<td><div class="d-flex gap-1">'
                +   '<a class="tbl-btn d-inline-flex align-items-center justify-content-center text-decoration-none" title="View Profile" href="admin/member_view.php?id=' + id + '" target="_blank" rel="noopener"><i class="ti ti-eye"></i></a>'
                +   (membershipId ? '<button class="tbl-btn" data-membership="' + membershipId + '" title="Activate" onclick="membershipAction(\'set_membership_status\',' + membershipId + ',\'active\')"><i class="ti ti-player-play"></i></button>' : '')
                +   (membershipId ? '<button class="tbl-btn" data-membership="' + membershipId + '" title="Freeze" onclick="membershipAction(\'set_membership_status\',' + membershipId + ',\'frozen\')"><i class="ti ti-player-pause"></i></button>' : '')
                +   (membershipId ? '<button class="tbl-btn danger" data-membership="' + membershipId + '" title="Deactivate" onclick="membershipAction(\'set_membership_status\',' + membershipId + ',\'cancelled\')"><i class="ti ti-ban"></i></button>' : '')
                + '</div></td>'
                + '</tr>';
        }

        function renderFeedbacks() {
            document.getElementById('feedback-list').innerHTML = feedbacks.map(f => `
                <div class="feedback-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div style="font-weight:700;font-size:.88rem;color:var(--text-primary)">${f.fname} ${f.lname}</div>
                            <div class="feedback-stars">${starsStr(f.rating)}</div>
                        </div>
                        <button class="tbl-btn danger" title="Delete feedback" onclick="deleteFeedback(${f.id})"><i class="ti ti-trash"></i></button>
                    </div>
                    <div class="feedback-text">"${f.text}"</div>
                    <div class="feedback-meta"><i class="ti ti-map-pin" style="font-size:.8rem"></i> ${f.branch} &nbsp;·&nbsp; ${formatDate(f.date)}</div>
                </div>`).join('');
        }

        /* ── NAVIGATION ── */
        async function adminAction(payload) {
            try {
                const data = await adminPost(payload);
                alert(data.message || (data.success ? 'Action completed.' : 'Action failed.'));
                if (data.reload) location.reload();
            } catch {
                alert('Connection error. Please try again.');
            }
        }

        function editClass(row) {
            document.getElementById('classId').value = row.id || '';
            document.getElementById('classTitle').value = row.title || '';
            document.getElementById('classDescription').value = row.description || '';
            document.getElementById('trainerName').value = row.trainer_name || '';
            document.getElementById('classBranch').value = row.branch_id || '';
            document.getElementById('durationMinutes').value = row.duration_minutes || 60;
            document.getElementById('classCapacity').value = row.capacity || '';
            document.getElementById('classActive').checked = Number(row.is_active) === 1;
        }

        function saveClass() {
            adminAction({
                action: 'save_class',
                class_id: document.getElementById('classId').value,
                title: document.getElementById('classTitle').value.trim(),
                description: document.getElementById('classDescription').value.trim(),
                trainer_name: document.getElementById('trainerName').value.trim(),
                branch_id: document.getElementById('classBranch').value,
                duration_minutes: document.getElementById('durationMinutes').value,
                capacity: document.getElementById('classCapacity').value,
                is_active: document.getElementById('classActive').checked ? 1 : 0
            });
        }

        function deleteClass(classId) {
            if (!confirm('Delete this class and all related schedules/bookings?')) return;
            adminAction({ action: 'delete_class', class_id: classId });
        }

        function editSchedule(row) {
            document.getElementById('scheduleId').value = row.id || '';
            document.getElementById('scheduleClass').value = row.class_id || '';
            document.getElementById('scheduleBranch').value = row.branch_id || '';
            document.getElementById('scheduledDate').value = row.scheduled_date || '';
            document.getElementById('startTime').value = (row.start_time || '').slice(0, 5);
            document.getElementById('endTime').value = (row.end_time || '').slice(0, 5);
            document.getElementById('scheduleStatus').value = row.status || 'scheduled';
        }

        function saveSchedule() {
            adminAction({
                action: 'save_class_schedule',
                schedule_id: document.getElementById('scheduleId').value,
                class_id: document.getElementById('scheduleClass').value,
                branch_id: document.getElementById('scheduleBranch').value,
                scheduled_date: document.getElementById('scheduledDate').value,
                start_time: document.getElementById('startTime').value,
                end_time: document.getElementById('endTime').value,
                status: document.getElementById('scheduleStatus').value
            });
        }

        function deleteSchedule(scheduleId) {
            if (!confirm('Delete this schedule and all related bookings?')) return;
            adminAction({ action: 'delete_class_schedule', schedule_id: scheduleId });
        }

        function saveHours() {
            adminAction({
                action: 'save_operating_hour',
                branch_id: document.getElementById('hoursBranch').value,
                day_of_week: document.getElementById('hoursDay').value,
                open_time: document.getElementById('openTime').value,
                close_time: document.getElementById('closeTime').value,
                is_closed: document.getElementById('isClosed').checked ? 1 : 0
            });
        }

        function toLocalInput(value) {
            if (!value) return '';
            return value.replace(' ', 'T').slice(0, 16);
        }

        function editAnnouncement(row) {
            document.getElementById('announcementId').value = row.id || '';
            document.getElementById('announcementBranch').value = row.branch_id || '';
            document.getElementById('announcementTitle').value = row.title || '';
            document.getElementById('announcementBody').value = row.body || '';
            document.getElementById('startsAt').value = toLocalInput(row.starts_at);
            document.getElementById('endsAt').value = toLocalInput(row.ends_at);
            document.getElementById('announcementActive').checked = Number(row.is_active) === 1;
        }

        function saveAnnouncement() {
            adminAction({
                action: 'save_announcement',
                announcement_id: document.getElementById('announcementId').value,
                branch_id: document.getElementById('announcementBranch').value,
                title: document.getElementById('announcementTitle').value.trim(),
                body: document.getElementById('announcementBody').value.trim(),
                starts_at: document.getElementById('startsAt').value,
                ends_at: document.getElementById('endsAt').value,
                is_active: document.getElementById('announcementActive').checked ? 1 : 0
            });
        }

        function deleteAnnouncement(announcementId) {
            if (!confirm('Delete this announcement?')) return;
            adminAction({ action: 'delete_announcement', announcement_id: announcementId });
        }

        function showPage(id, btn) {
            const page = document.getElementById('page-' + id);
            if (!page) return;

            document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
            page.classList.add('active');

            document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
            if (btn) {
                btn.classList.add('active');
            } else {
                document.querySelectorAll('.sidebar-link').forEach(l => {
                    if (l.textContent.trim().toLowerCase().includes(id)) l.classList.add('active');
                });
            }

            const titles = {
                dashboard: 'Dashboard',
                members: 'Members',
                memberships: 'Memberships',
                branches: 'Branches',
                schedules: 'Schedules',
                announcements: 'Announcements',
                feedbacks: 'Feedbacks',
                reports: 'Reports',
                settings: 'Settings'
            };
            const crumbs = {
                dashboard: 'Overview',
                members: 'Member Management',
                memberships: 'Membership Management',
                branches: 'Branch Overview',
                schedules: 'Class Management',
                announcements: 'Branch Notices',
                feedbacks: 'Review Feedbacks',
                reports: 'Revenue + Signups',
                settings: 'System Settings'
            };
            document.getElementById('topbar-title').textContent = titles[id] || id;
            document.getElementById('topbar-crumb').textContent = crumbs[id] || id;

            const url = new URL(window.location.href);
            if (id === 'dashboard') {
                url.searchParams.delete('page');
            } else {
                url.searchParams.set('page', id);
            }
            history.replaceState(null, '', url);

            if (id === 'members') {
                var pf = document.getElementById('planFilter');
                if (pf) pf.selectedIndex = 0;
                var ms = document.getElementById('memberSearch');
                if (ms) ms.value = '';
                var msm = document.getElementById('memberSearchMobile');
                if (msm) msm.value = '';
                renderMembers();
            }
            if (id === 'branches') renderBranches();
            if (id === 'feedbacks') renderFeedbacks();
            if (id === 'reports') {
                buildSparkline();
                buildRevenueBars();
            }
            closeSidebar();
        }

        function filterMembers() {
            renderMembers()
        }

        /* ── ADD / EDIT / DELETE MEMBER ── */
        async function addMember() {
            const fname = document.getElementById('new-fname').value.trim();
            const lname = document.getElementById('new-lname').value.trim();
            const gender = document.getElementById('new-gender').value;
            const birthdate = document.getElementById('new-birthdate').value;
            const email = document.getElementById('new-email').value.trim();
            const password = document.getElementById('new-password').value;
            const confirm = document.getElementById('new-confirm-password').value;
            const plan = document.getElementById('new-plan').value;
            const payment = document.getElementById('new-payment').value;

            // Validation
            if (!fname || !lname) {
                alert('Please enter your full name.');
                return;
            }
            if (!gender) {
                alert('Please select your gender.');
                return;
            }
            if (!birthdate) {
                alert('Please enter your birthdate.');
                return;
            }
            if (!email) {
                alert('Please enter your email.');
                return;
            }
            if (!password || password.length < 8) {
                alert('Password must be at least 8 characters.');
                return;
            }
            if (password !== confirm) {
                alert('Passwords do not match.');
                return;
            }

            try {
                const data = await adminPost({
                    action: 'create_member',
                    first_name: fname,
                    last_name: lname,
                    gender,
                    birthdate,
                    email,
                    password,
                    confirm_password: confirm,
                    plan_id: plan,
                    payment_method: payment
                });
                alert(data.message || (data.success ? 'Member added successfully.' : 'Unable to add member.'));
                if (data.success && data.reload) location.reload();
            } catch {
                alert('Connection error. Please try again.');
            }
        }

        async function changeMemberPlan(id, select) {
            const m = members.find(m => m.id === id);
            if (!m) return;
            const oldValue = String(m.plan_id || '');
            try {
                const data = await adminPost({
                    action: 'change_member_plan',
                    member_id: id,
                    membership_id: m.membership_id || 0,
                    plan_id: select.value
                });
                alert(data.message || (data.success ? 'Plan updated.' : 'Unable to update plan.'));
                if (data.success && data.reload) location.reload();
                else select.value = oldValue;
            } catch {
                select.value = oldValue;
                alert('Connection error. Please try again.');
            }
        }

        async function deleteFeedback(id) {
            if (!confirm('Delete this feedback?')) return;
            try {
                const data = await adminPost({ action: 'delete_feedback', feedback_id: id });
                alert(data.message || (data.success ? 'Feedback deleted.' : 'Unable to delete feedback.'));
                if (data.success && data.reload) location.reload();
            } catch {
                alert('Connection error. Please try again.');
            }
        }

        function deleteMember(id) {
            if (!confirm('Remove this member?')) return;
            members = members.filter(m => m.id !== id);
            renderMembers();
            renderRecentMembers();
        }

        async function adminPost(payload) {
            const res = await fetch('handlers/admin_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...payload, csrf_token: ADMIN_CSRF })
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
            return data;
        }

        async function membershipAction(action, membershipId, status) {
            if (!membershipId) return;
            const key = 'membership_inflight_' + membershipId;
            if (window[key]) return; // prevent duplicate in-flight requests for same membership
            window[key] = true;
            const buttons = Array.from(document.querySelectorAll('[data-membership="' + membershipId + '"]'));
            buttons.forEach(b => b.disabled = true);
            try {
                const payload = { action, membership_id: membershipId };
                if (status) payload.status = status;
                const data = await adminPost(payload);
                alert(data.message || (data.success ? 'Membership updated.' : 'Action failed.'));
                if (data.reload) {
                    location.reload();
                    return;
                }
                // fallback: re-render lists to reflect local state (prevents disappearing)
                try { renderMembers(); } catch (e) { /* ignore */ }
                try { renderMemberships(); } catch (e) { /* ignore */ }
                try { renderRecentMembers(); } catch (e) { /* ignore */ }
            } catch (e) {
                console.error('membershipAction error', e);
                alert('Connection error. Please try again.');
            } finally {
                buttons.forEach(b => b.disabled = false);
                window[key] = false;
            }
        }

        function editMember(id) {
            const m = members.find(m => m.id === id);
            if (!m) return;
            const newName = prompt('Edit name:', `${m.fname} ${m.lname}`);
            if (newName) {
                const parts = newName.trim().split(' ');
                m.fname = parts[0] || m.fname;
                m.lname = parts.slice(1).join(' ') || m.lname;
                renderMembers();
            }
        }

        /* ── SIDEBAR (mobile) ── */
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('open');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }

        /* ── MOBILE SEARCH ── */
        function toggleMobileSearch() {
            const bar = document.getElementById('mobileSearchBar');
            const isOpen = bar.classList.toggle('open');
            document.body.classList.toggle('mobile-search-open', isOpen);
            if (isOpen) document.getElementById('memberSearchMobile').focus();
            else {
                document.getElementById('memberSearchMobile').value = '';
                filterMembers()
            }
        }

        function syncMobileSearch(el) {
            document.getElementById('memberSearch').value = el.value;
            filterMembers();
        }

        /* ── THEME ── */
        function updateThemeLogos() {
            const isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
            document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
                logo.setAttribute('src', isLight ? logo.dataset.logoLight : logo.dataset.logoDark);
            });
        }

        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
            updateThemeLogos();
        }

        /* Restore saved theme on load */
        (function() {
            const saved = localStorage.getItem('fs-theme');
            if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
            updateThemeLogos();
        })();

        /* ── BOOT ── */
        init();
        const initialPage = new URLSearchParams(window.location.search).get('page') || location.hash.replace('#', '');
        if (['dashboard', 'reports', 'schedules', 'announcements', 'members', 'branches', 'feedbacks', 'settings'].includes(initialPage)) {
            showPage(initialPage, null);
        }

        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (performance.getEntriesByType && performance.getEntriesByType('navigation').some(e => e.type === 'back_forward'))) {
                const page = new URLSearchParams(window.location.search).get('page') || location.hash.replace('#', '');
                init();
                if (['dashboard', 'reports', 'schedules', 'announcements', 'members', 'branches', 'feedbacks', 'settings'].includes(page)) {
                    showPage(page, null);
                }
            }
        });
    </script>
</body>

</html>
