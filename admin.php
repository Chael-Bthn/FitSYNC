<?php
require_once __DIR__ . '/config/auth_guard.php';
requireRole('admin');
require_once __DIR__ . '/config/db.php';

$pdo = db();

$adminUser = currentUser();
$adminInitial = strtoupper(substr(trim($adminUser['name'] ?: $adminUser['email'] ?: 'A'), 0, 1));

$totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$totalBranches = (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE is_active = 1")->fetchColumn();
$totalFeedbacks = (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE is_visible = 1")->fetchColumn();
$monthlyRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships
     WHERE MONTH(starts_at) = MONTH(CURDATE()) AND YEAR(starts_at) = YEAR(CURDATE())"
)->fetchColumn();
$averageRating = $pdo->query("SELECT AVG(rating) FROM feedback WHERE is_visible = 1")->fetchColumn();
$averageRating = $averageRating ? round((float) $averageRating, 1) : 0.0;

$memberRows = $pdo->query("SELECT u.id, u.first_name AS fname, u.last_name AS lname, u.email, u.is_active,
           COALESCE(m.starts_at, u.created_at) AS joined,
           COALESCE(m.ends_at, u.created_at) AS expiry,
           COALESCE(p.label, 'No Plan') AS plan,
           COALESCE(m.status, 'active') AS status
    FROM users u
    LEFT JOIN memberships m ON m.user_id = u.id
    LEFT JOIN membership_plans p ON p.id = m.plan_id
    WHERE u.role = 'member'
    ORDER BY COALESCE(m.starts_at, u.created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);

$memberships = $pdo->query("SELECT m.id, u.first_name AS fname, u.last_name AS lname,
           p.label AS plan, b.name AS branch,
           m.starts_at, m.ends_at, m.amount_paid, m.payment_method, m.status
    FROM memberships m
    INNER JOIN users u ON u.id = m.user_id
    INNER JOIN membership_plans p ON p.id = m.plan_id
    INNER JOIN branches b ON b.id = m.branch_id
    ORDER BY m.starts_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$branches = $pdo->query("SELECT id, name, city, address, is_active FROM branches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$plans = $pdo->query("SELECT label FROM membership_plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

$feedbacks = $pdo->query("SELECT f.id, COALESCE(u.first_name, 'Anonymous') AS fname, COALESCE(u.last_name, '') AS lname,
           f.rating, f.body AS text, COALESCE(b.name, 'Unknown') AS branch, f.created_at AS date
    FROM feedback f
    LEFT JOIN users u ON u.id = f.user_id
    LEFT JOIN branches b ON b.id = f.branch_id
    WHERE f.is_visible = 1
    ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$totalMemberships = (int) $pdo->query("SELECT COUNT(*) FROM memberships")->fetchColumn();
$activePlans = count($plans);

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

    $revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM memberships WHERE starts_at BETWEEN ? AND ?");
    $revenueStmt->execute([$start, $end]);
    $revenueData[] = (float) $revenueStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin — FitSync</title>
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
            font-size: .8rem
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
                <span class="nav-pill" id="pill-members"><?= number_format($totalMembers) ?></span>
            </a>
            <a class="sidebar-link" onclick="showPage('branches',this)">
                <i class="ti ti-building-store"></i> Branches
                <span class="nav-pill" id="pill-branches"><?= number_format($totalBranches) ?></span>
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

            </div><!-- /dashboard -->

            <!-- ══ MEMBERS ══ -->
            <div class="page-section" id="page-members">
                <div class="section-header mb-3">
                    <div class="section-h">All Members <small id="member-count-label"></small></div>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm fs-select" id="planFilter" onchange="filterMembers()">
                            <option value="">All Plans</option>
                            <?php foreach ($plans as $plan) : ?>
                                <option value="<?= htmlspecialchars($plan['label']) ?>"><?= htmlspecialchars($plan['label']) ?></option>
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
            <div class="page-section" id="page-reports">
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
                                    <option><?= htmlspecialchars($plan['label']) ?></option>
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
        const monthlyLabels = <?= json_encode($monthlyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const signupData = <?= json_encode($signupData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const revenueData = <?= json_encode($revenueData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
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

            const data = members.filter(function(m) {
                var txt = ((m.fname||'') + ' ' + (m.lname||'') + ' ' + (m.email||'')).toLowerCase();
                var planOk = !plan || (m.plan||'') === plan;
                var txtOk  = !q    || txt.includes(q);
                return planOk && txtOk;
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
            return `<tr>
                <td>${m.fname} ${m.lname}</td>
                <td class="col-hide-xs">${m.plan}</td>
                <td class="col-hide-xs">${m.branch}</td>
                <td>${starts}</td>
                <td class="col-hide-xs">${ends}</td>
                <td>₱${Number(m.amount_paid).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td class="col-hide-xs">${capitalize(m.status)}</td>
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
            var idStr    = String(id).padStart(5, '0');
            var cls      = planClass[plan] || '';

            var avatar   = '<div class="member-avatar">' + initials + '</div>';
            var statusHtml = '<span class="status-dot ' + status + '"></span>' + capitalize(status);
            var planBadge  = '<span class="plan-badge ' + cls + '">' + plan + '</span>';

            var planOptions = '';
            Object.keys(planClass).forEach(function(p) {
                planOptions += '<option value="' + p + '"' + (p === plan ? ' selected' : '') + '>' + p + '</option>';
            });
            var planSelect = '<select class="form-select" style="font-size:.85rem;padding:.4rem" onchange="changeMemberPlan(' + id + ', this)">' + planOptions + '</select>';

            if (compact) {
                return '<tr>'
                    + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<span>' + fname + ' ' + lname + '</span></div></td>'
                    + '<td>' + planBadge + '</td>'
                    + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + date + '</span></td>'
                    + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + expiry + '</span></td>'
                    + '<td>' + statusHtml + '</td>'
                    + '</tr>';
            }

            return '<tr>'
                + '<td><div class="d-flex align-items-center gap-2">' + avatar + '<div>'
                +   '<div style="font-weight:600">' + fname + ' ' + lname + '</div>'
                +   '<div style="font-size:.7rem;color:var(--text-dimmed)">#' + idStr + '</div>'
                + '</div></div></td>'
                + '<td class="col-hide-xs"><span style="font-size:.82rem;color:var(--text-muted)">' + email + '</span></td>'
                + '<td>' + planSelect + '</td>'
                + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + date + '</span></td>'
                + '<td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">' + expiry + '</span></td>'
                + '<td>' + statusHtml + '</td>'
                + '<td><div class="d-flex gap-1">'
                +   '<button class="tbl-btn danger" title="Delete" onclick="deleteMember(' + id + ')"><i class="ti ti-trash"></i></button>'
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
                        <button class="tbl-btn danger" title="Delete feedback"><i class="ti ti-trash"></i></button>
                    </div>
                    <div class="feedback-text">"${f.text}"</div>
                    <div class="feedback-meta"><i class="ti ti-map-pin" style="font-size:.8rem"></i> ${f.branch} &nbsp;·&nbsp; ${formatDate(f.date)}</div>
                </div>`).join('');
        }

        /* ── NAVIGATION ── */
        function showPage(id, btn) {
            document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
            document.getElementById('page-' + id).classList.add('active');

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
                feedbacks: 'Feedbacks',
                reports: 'Reports',
                settings: 'Settings'
            };
            const crumbs = {
                dashboard: 'Overview',
                members: 'Member Management',
                memberships: 'Membership Management',
                branches: 'Branch Overview',
                feedbacks: 'Review Feedbacks',
                reports: 'Revenue + Signups',
                settings: 'System Settings'
            };
            document.getElementById('topbar-title').textContent = titles[id] || id;
            document.getElementById('topbar-crumb').textContent = crumbs[id] || id;

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
        function addMember() {
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

            const newId = Math.max(...members.map(m => m.id), 0) + 1;
            members.push({
                id: newId,
                fname,
                lname,
                email,
                plan,
                joined: new Date().toISOString().split('T')[0],
                expiry: new Date().toISOString().split('T')[0],
                status: 'active'
            });

            document.getElementById('pill-members').textContent =
                members.length >= 1000 ? Math.round(members.length / 1000 * 10) / 10 + 'K' : members.length;

            bootstrap.Modal.getInstance(document.getElementById('addMemberModal')).hide();
            ['new-fname', 'new-lname', 'new-gender', 'new-birthdate', 'new-email', 'new-password', 'new-confirm-password'].forEach(id => document.getElementById(id).value = '');
            renderMembers();
            renderRecentMembers();
            showPage('members', null);
            alert('Member added successfully!');
        }

        function changeMemberPlan(id, select) {
            const m = members.find(m => m.id === id);
            if (!m) return;
            const newPlan = select.value;
            m.plan = newPlan;
            renderMembers();
            renderRecentMembers();
        }

        function deleteMember(id) {
            if (!confirm('Remove this member?')) return;
            members = members.filter(m => m.id !== id);
            renderMembers();
            renderRecentMembers();
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
    </script>
</body>

</html>