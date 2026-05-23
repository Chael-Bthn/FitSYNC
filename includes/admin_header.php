<?php
if (!isset($pageTitle)) {
    $pageTitle = 'FitSync Admin';
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$adminUser = currentUser();
$adminInitial = strtoupper(substr(trim($adminUser['name'] ?: $adminUser['email'] ?: 'A'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - FitSync</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        (function () {
            var saved = localStorage.getItem('fs-theme');
            if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
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
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: background .3s;
        }

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
            color: var(--text-primary);
        }

        .brand-text .sync {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--fs-red);
            letter-spacing: 1px;
        }

        .sidebar-admin-badge {
            margin: .9rem 1.25rem .4rem;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--text-dimmed);
        }

        .nav-section { padding: .2rem 0 }

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
            flex-shrink: 0;
        }

        .sidebar-link:hover {
            background: rgba(128, 128, 128, .1);
            color: var(--text-primary);
        }

        .sidebar-link.active {
            background: var(--fs-red-soft);
            color: var(--text-primary);
        }

        .sidebar-link.active i { color: var(--fs-red) }

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

        [data-bs-theme="light"] .theme-pill-knob { transform: translateX(20px) }
        .sidebar-link.logout { color: rgba(255, 80, 80, .65) }
        .sidebar-link.logout:hover {
            background: rgba(204, 26, 26, .12);
            color: #ff6b6b;
        }

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

        .main-wrap {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
        }

        .main-content { padding: 1.75rem }

        .stat-card,
        .admin-table-wrap,
        .fs-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
        }

        .stat-card {
            padding: 1.4rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
        }

        .stat-card:hover {
            border-color: rgba(204, 26, 26, .3);
            transform: translateY(-3px);
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
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: .75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
        }

        .admin-table { margin: 0; color: var(--text-primary) }
        .admin-table th {
            font-size: .7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 800;
            background: var(--th-bg);
            border-color: var(--card-border);
        }
        .admin-table td {
            border-color: var(--td-border);
            vertical-align: middle;
            color: var(--text-primary);
            font-size: .86rem;
        }

        .fs-input,
        .fs-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-color);
            border-radius: 12px;
        }
        select.fs-input,
        select.fs-select {
            appearance: none;
            padding-right: 2.4rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238f8f8f' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .85rem center;
            background-size: 14px 14px;
        }
        .fs-select option {
            background: var(--body-bg);
            color: var(--input-color);
        }
        .fs-input:focus,
        .fs-select:focus {
            background: var(--input-bg);
            color: var(--input-color);
            border-color: var(--fs-red);
            box-shadow: 0 0 0 .2rem rgba(204, 26, 26, .12);
        }
        .btn-fs { background: var(--fs-red); border: none; color: #fff; font-weight: 800 }
        .btn-fs:hover { background: var(--fs-red-hover); color: #fff }
        .status-badge { font-size: .65rem; font-weight: 800; padding: .22rem .6rem; border-radius: 999px; text-transform: uppercase }
        .status-badge.active,
        .status-badge.paid { background: rgba(76, 175, 135, .12); color: #4caf87 }
        .status-badge.pending { background: rgba(255, 193, 7, .14); color: #d6a100 }
        .status-badge.expired,
        .status-badge.frozen { background: rgba(150, 150, 150, .14); color: #999 }
        .status-badge.cancelled,
        .status-badge.failed { background: rgba(220, 53, 69, .14); color: #e05656 }
        .tbl-btn {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .tbl-btn:hover { color: var(--fs-red); border-color: var(--fs-red) }
        .fs-label { font-size: .75rem; color: var(--text-muted) }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 1039;
        }

        ::-webkit-scrollbar { width: 5px }
        ::-webkit-scrollbar-track { background: transparent }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .1); border-radius: 10px }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                width: min(280px, 80vw);
                max-width: 280px;
            }
            .sidebar.open { transform: translateX(0) }
            .sidebar-overlay.open { display: block }
            .main-wrap { margin-left: 0 }
            .topbar { left: 0 }
            .topbar-toggle { display: flex }
            .main-content {
                padding: 1.25rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        @media (max-width: 575px) {
            .main-content { padding: 1rem }
            .topbar { padding: 0 1rem }
            .stat-card { padding: 1rem 1.15rem }
            .stat-value { font-size: 1.55rem }
        }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
