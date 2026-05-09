profile.php
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — My Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204,26,26,.28);
            --sidebar-w: 270px;
            --sidebar-bg: #0d0d0d;
            --sidebar-border: rgba(255,255,255,.07);
            --card-bg: #111111;
            --card-border: rgba(255,255,255,.07);
            --page-bg: #0a0a0a;
        }

        [data-bs-theme="light"] {
            --sidebar-bg: #ffffff;
            --sidebar-border: rgba(0,0,0,.08);
            --card-bg: #ffffff;
            --card-border: rgba(0,0,0,.07);
            --page-bg: #f4f2ef;
        }

        * { font-family: 'Outfit', system-ui, sans-serif; box-sizing: border-box }

        body {
            background: var(--page-bg);
            overflow-x: hidden;
            transition: background .25s;
        }

        /* ══════════════════ SIDEBAR ══════════════════ */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            z-index: 200;
            transition: transform .3s cubic-bezier(.25,.46,.45,.94), background .25s;
            overflow-y: auto;
        }

        /* Sidebar header */
        .sb-header {
            padding: 1.5rem 1.35rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }

        .sb-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
            margin-bottom: 1.35rem;
        }

        .brand-text .fit  { font-size:1.1rem; font-weight:900; letter-spacing:1px; color: var(--bs-body-color) }
        .brand-text .sync { font-size:1.1rem; font-weight:900; color:var(--fs-red); letter-spacing:1px }

        /* Avatar card */
        .sb-avatar-card {
            background: linear-gradient(135deg, var(--fs-red) 0%, #8a1010 100%);
            border-radius: 16px;
            padding: 1.1rem;
            position: relative;
            overflow: hidden;
        }

        .sb-avatar-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 80px; height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
        }

        .sb-avatar-card::after {
            content: '';
            position: absolute;
            bottom: -15px; left: 10px;
            width: 60px; height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
        }

        .sb-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            border: 2px solid rgba(255,255,255,.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 800; color: #fff;
            margin-bottom: .65rem;
            position: relative; z-index: 1;
        }

        .sb-member-name {
            font-size: .95rem; font-weight: 800; color: #fff;
            line-height: 1.1; position: relative; z-index: 1;
        }

        .sb-member-plan {
            font-size: .65rem; font-weight: 700; letter-spacing: .8px;
            text-transform: uppercase; color: rgba(255,255,255,.7);
            margin-top: .2rem; position: relative; z-index: 1;
        }

        .sb-member-badge {
            position: absolute; top: .75rem; right: .75rem;
            background: rgba(255,255,255,.18);
            color: #fff; font-size: .58rem; font-weight: 700;
            padding: .18rem .55rem; border-radius: 50px;
            letter-spacing: .5px; text-transform: uppercase;
            z-index: 1;
        }

        /* Nav */
        .sb-nav {
            padding: .75rem .75rem;
            flex: 1;
        }

        .sb-nav-label {
            font-size: .6rem; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: var(--bs-secondary-color);
            padding: .5rem .65rem .35rem;
            margin-top: .25rem;
        }

        .sb-nav-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .6rem .85rem;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: var(--bs-secondary-color);
            font-size: .85rem; font-weight: 600;
            transition: background .18s, color .18s;
            margin-bottom: 2px;
            border: none; background: none; width: 100%; text-align: left;
        }

        .sb-nav-item i { font-size: 1.05rem; flex-shrink: 0 }

        .sb-nav-item:hover {
            background: rgba(204,26,26,.1);
            color: var(--fs-red);
        }

        .sb-nav-item.active {
            background: rgba(204,26,26,.12);
            color: var(--fs-red);
        }

        .sb-nav-item .nav-badge {
            margin-left: auto;
            background: var(--fs-red);
            color: #fff;
            font-size: .6rem; font-weight: 700;
            padding: .15rem .45rem; border-radius: 50px;
        }

        /* Sidebar footer */
        .sb-footer {
            padding: .85rem .75rem 1.25rem;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }

        .sb-logout {
            display: flex; align-items: center; gap: .75rem;
            padding: .6rem .85rem;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: var(--bs-secondary-color);
            font-size: .85rem; font-weight: 600;
            transition: background .18s, color .18s;
            border: none; background: none; width: 100%;
        }

        .sb-logout:hover {
            background: rgba(204,26,26,.1);
            color: var(--fs-red);
        }

        /* Theme toggle in sidebar */
        .sb-theme-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: .5rem .85rem;
            margin-bottom: .35rem;
        }

        .sb-theme-label {
            font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color);
            display: flex; align-items: center; gap: .5rem;
        }

        .theme-pill {
            width: 44px; height: 24px;
            border-radius: 50px;
            border: 1px solid var(--sidebar-border);
            background: var(--bs-secondary-bg);
            position: relative; cursor: pointer;
            transition: background .3s;
            padding: 0;
        }

        .theme-pill-knob {
            position: absolute; top: 3px; left: 3px;
            width: 16px; height: 16px; border-radius: 50%;
            background: var(--fs-red);
            transition: transform .3s;
        }

        [data-bs-theme="light"] .theme-pill-knob { transform: translateX(20px) }

        /* ══════════════════ MAIN CONTENT ══════════════════ */
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 2rem 2rem 3rem;
            transition: margin .3s;
        }

        /* Topbar */
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .topbar-left h1 {
            font-size: 1.6rem; font-weight: 800; letter-spacing: -.5px;
            margin: 0; line-height: 1.1;
        }

        .topbar-left p {
            font-size: .82rem; color: var(--bs-secondary-color); margin: .2rem 0 0;
        }

        .topbar-right { display: flex; align-items: center; gap: .65rem }

        .topbar-icon-btn {
            width: 38px; height: 38px; border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--bs-secondary-color);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1rem;
            transition: all .2s; position: relative;
        }

        .topbar-icon-btn:hover { border-color: rgba(204,26,26,.35); color: var(--fs-red) }

        .notif-dot {
            position: absolute; top: 7px; right: 7px;
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--fs-red);
            border: 1.5px solid var(--page-bg);
        }

        /* Hamburger (mobile) */
        .hamburger {
            display: none;
            width: 38px; height: 38px; border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--bs-body-color);
            align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.1rem;
        }

        /* ── STAT CARDS ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem 1.35rem;
            position: relative;
            overflow: hidden;
            transition: transform .2s, border-color .2s;
        }

        .stat-card:hover { transform: translateY(-3px); border-color: rgba(204,26,26,.3) }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -18px; right: -18px;
            width: 64px; height: 64px;
            border-radius: 50%;
            background: rgba(204,26,26,.06);
        }

        .stat-card-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(204,26,26,.12);
            display: flex; align-items: center; justify-content: center;
            color: var(--fs-red); font-size: 1.1rem;
            margin-bottom: .9rem;
        }

        .stat-card-value {
            font-size: 1.75rem; font-weight: 800; letter-spacing: -1px;
            line-height: 1; margin-bottom: .2rem;
        }

        .stat-card-label {
            font-size: .7rem; font-weight: 700; letter-spacing: .5px;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }

        .stat-card-sub {
            font-size: .72rem; color: var(--bs-secondary-color); margin-top: .3rem;
        }

        .stat-trend {
            display: inline-flex; align-items: center; gap: .2rem;
            font-size: .7rem; font-weight: 700; padding: .15rem .45rem;
            border-radius: 50px; margin-top: .35rem;
        }

        .stat-trend.up { background: rgba(46,204,113,.12); color: #2ecc71 }
        .stat-trend.warn { background: rgba(230,126,34,.12); color: #e67e22 }

        /* ── MEMBERSHIP CARD ── */
        .membership-card {
            background: linear-gradient(135deg, #1a0505 0%, #0d0d0d 50%, #1a0808 100%);
            border: 1px solid rgba(204,26,26,.25);
            border-radius: 20px;
            padding: 1.75rem;
            position: relative;
            overflow: hidden;
        }

        [data-bs-theme="light"] .membership-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 50%, #fff0f0 100%);
        }

        .membership-card::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(204,26,26,.07);
        }

        .membership-card::after {
            content: '';
            position: absolute;
            bottom: -25px; left: 30px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(204,26,26,.04);
        }

        .membership-tag {
            font-size: .68rem; font-weight: 700; letter-spacing: .8px;
            text-transform: uppercase; color: var(--fs-red);
            margin-bottom: .5rem;
            display: flex; align-items: center; gap: .4rem;
        }

        .membership-tag span {
            width: 6px; height: 6px; border-radius: 50%; background: var(--fs-red); display: inline-block;
        }

        .membership-plan-name {
            font-size: 1.6rem; font-weight: 900; letter-spacing: -.5px;
            margin-bottom: .25rem;
        }

        .membership-dates {
            font-size: .78rem; color: var(--bs-secondary-color); margin-bottom: 1.25rem;
        }

        .membership-progress-wrap {
            margin-bottom: 1rem;
        }

        .membership-progress-label {
            display: flex; justify-content: space-between; align-items: center;
            font-size: .72rem; margin-bottom: .45rem;
        }

        .membership-progress-label span:first-child { color: var(--bs-secondary-color) }
        .membership-progress-label span:last-child { font-weight: 700; color: var(--fs-red) }

        .progress-bar-track {
            height: 5px; border-radius: 3px;
            background: rgba(204,26,26,.12);
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%; border-radius: 3px;
            background: var(--fs-red);
            transition: width 1s cubic-bezier(.25,.46,.45,.94);
        }

        .membership-features {
            display: flex; flex-wrap: wrap; gap: .5rem;
        }

        .mem-feat-pill {
            display: flex; align-items: center; gap: .3rem;
            background: rgba(204,26,26,.1);
            border: 1px solid rgba(204,26,26,.2);
            color: var(--bs-body-color); font-size: .72rem; font-weight: 600;
            padding: .28rem .75rem; border-radius: 50px;
        }

        .mem-feat-pill i { color: var(--fs-red); font-size: .85rem }

        /* ── ACTIVITY FEED ── */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1rem;
        }

        .section-header h2 {
            font-size: 1rem; font-weight: 800; letter-spacing: -.3px; margin: 0;
        }

        .section-header a {
            font-size: .75rem; font-weight: 600; color: var(--fs-red); text-decoration: none;
        }

        .activity-list {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow: hidden;
        }

        .activity-item {
            display: flex; align-items: center; gap: .9rem;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid var(--card-border);
            transition: background .15s;
        }

        .activity-item:last-child { border-bottom: none }
        .activity-item:hover { background: rgba(204,26,26,.04) }

        .activity-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .activity-icon.gym    { background: rgba(204,26,26,.12); color: var(--fs-red) }
        .activity-icon.class  { background: rgba(52,152,219,.12); color: #3498db }
        .activity-icon.pt     { background: rgba(155,89,182,.12); color: #9b59b6 }
        .activity-icon.badge  { background: rgba(46,204,113,.12); color: #2ecc71 }

        .activity-info { flex: 1; min-width: 0 }

        .activity-title {
            font-size: .85rem; font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .activity-sub {
            font-size: .72rem; color: var(--bs-secondary-color); margin-top: .1rem;
        }

        .activity-time {
            font-size: .68rem; color: var(--bs-secondary-color); flex-shrink: 0;
        }

        /* ── QUICK ACTIONS ── */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: .75rem;
        }

        .quick-action {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.1rem;
            text-decoration: none;
            color: var(--bs-body-color);
            transition: transform .2s, border-color .2s;
            display: flex; flex-direction: column; gap: .65rem;
            cursor: pointer;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            border-color: rgba(204,26,26,.3);
            color: var(--bs-body-color);
        }

        .quick-action-icon {
            width: 38px; height: 38px; border-radius: 11px;
            background: rgba(204,26,26,.1);
            display: flex; align-items: center; justify-content: center;
            color: var(--fs-red); font-size: 1.1rem;
        }

        .quick-action-title {
            font-size: .82rem; font-weight: 700; line-height: 1.2;
        }

        .quick-action-sub {
            font-size: .68rem; color: var(--bs-secondary-color); line-height: 1.4;
        }

        /* ── UPCOMING CLASSES ── */
        .class-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: .95rem 1.1rem;
            display: flex; align-items: center; gap: .9rem;
            margin-bottom: .6rem;
            transition: border-color .2s;
        }

        .class-card:hover { border-color: rgba(204,26,26,.3) }

        .class-day {
            text-align: center; flex-shrink: 0;
            width: 42px;
        }

        .class-day-num { font-size: 1.3rem; font-weight: 900; line-height: 1; color: var(--fs-red) }
        .class-day-name { font-size: .6rem; text-transform: uppercase; letter-spacing: .6px; color: var(--bs-secondary-color); font-weight: 700 }

        .class-divider {
            width: 1px; height: 40px; background: var(--card-border); flex-shrink: 0;
        }

        .class-info { flex: 1; min-width: 0 }
        .class-name { font-size: .88rem; font-weight: 700 }
        .class-meta { font-size: .7rem; color: var(--bs-secondary-color); margin-top: .15rem }

        .class-badge {
            font-size: .6rem; font-weight: 700; padding: .2rem .55rem;
            border-radius: 50px; flex-shrink: 0;
        }

        .class-badge.confirmed { background: rgba(46,204,113,.12); color: #2ecc71 }
        .class-badge.waitlist  { background: rgba(230,126,34,.12);  color: #e67e22 }

        /* ── GOAL RING ── */
        .goal-ring-wrap {
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem 0;
        }

        .ring-svg { transform: rotate(-90deg) }

        .ring-label {
            text-align: center; margin-top: .75rem;
        }

        .ring-label .val { font-size: 1.4rem; font-weight: 900; color: var(--fs-red) }
        .ring-label .lbl { font-size: .7rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .6px; font-weight: 700 }

        /* ── COACH CARD ── */
        .coach-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.1rem;
            display: flex; align-items: center; gap: .9rem;
        }

        .coach-avatar {
            width: 48px; height: 48px; border-radius: 14px;
            background: rgba(204,26,26,.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; font-weight: 800; color: var(--fs-red);
            flex-shrink: 0;
        }

        .coach-name { font-size: .9rem; font-weight: 800 }
        .coach-role { font-size: .72rem; color: var(--bs-secondary-color) }

        .coach-next {
            margin-left: auto; text-align: right; flex-shrink: 0;
        }

        .coach-next-time { font-size: .78rem; font-weight: 700 }
        .coach-next-label { font-size: .62rem; color: var(--bs-secondary-color) }

        /* ── SIDEBAR OVERLAY (mobile) ── */
        .sb-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 199;
        }

        /* ══════════════════ RESPONSIVE ══════════════════ */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-w))) }
            .sidebar.open { transform: translateX(0) }
            .main-content { margin-left: 0 }
            .sb-overlay.active { display: block }
            .hamburger { display: flex }
        }

        @media (max-width: 575.98px) {
            .main-content { padding: 1.25rem 1rem 2rem }
            .stat-grid { grid-template-columns: 1fr 1fr }
            .stat-card-value { font-size: 1.4rem }
            .quick-grid { grid-template-columns: 1fr 1fr }
            .topbar-left h1 { font-size: 1.2rem }
        }
    </style>
</head>

<body>

<!-- Sidebar overlay (mobile) -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="sidebar" id="sidebar">

    <div class="sb-header">
        <!-- Brand -->
        <a class="sb-brand" href="index.php">
            <img src="FitSYNC Emblem.svg" alt="FitSync" width="30" height="30" />
            <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
        </a>

        <!-- Member avatar card -->
        <div class="sb-avatar-card">
            <span class="sb-member-badge">Active</span>
            <div class="sb-avatar">JD</div>
            <div class="sb-member-name">Juan Dela Cruz</div>
            <div class="sb-member-plan">6-Month Plan · Member</div>
        </div>
    </div>

    <nav class="sb-nav">

        <div class="sb-nav-label">Main</div>
        <a href="#" class="sb-nav-item active" onclick="setActive(this);return false">
            <i class="ti ti-layout-dashboard"></i> Dashboard
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-user-circle"></i> My Profile
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-calendar-event"></i> Schedule
            <span class="nav-badge">3</span>
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-barbell"></i> Workouts
        </a>

        <div class="sb-nav-label">Membership</div>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-id-badge"></i> My Plan
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-receipt"></i> Billing
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-trophy"></i> Achievements
            <span class="nav-badge">2</span>
        </a>

        <div class="sb-nav-label">Connect</div>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-users"></i> Community
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-message-2"></i> Messages
        </a>
        <a href="#" class="sb-nav-item" onclick="setActive(this);return false">
            <i class="ti ti-settings"></i> Settings
        </a>

    </nav>

    <div class="sb-footer">
        <!-- Theme toggle -->
        <div class="sb-theme-row">
            <span class="sb-theme-label">
                <i class="ti ti-moon"></i> Dark Mode
            </span>
            <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle theme">
                <div class="theme-pill-knob" id="themePillKnob"></div>
            </button>
        </div>

        <!-- Logout -->
        <a href="index.php" class="sb-logout">
            <i class="ti ti-logout"></i> Log Out
        </a>
    </div>

</aside>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<main class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <div style="display:flex;align-items:center;gap:.65rem">
                <button class="hamburger" id="hamburger" onclick="openSidebar()">
                    <i class="ti ti-menu-2"></i>
                </button>
                <div>
                    <h1>Good morning, Juan 👋</h1>
                    <p>Saturday, May 9 · You have 2 classes today</p>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-icon-btn" title="Notifications">
                <i class="ti ti-bell"></i>
                <span class="notif-dot"></span>
            </div>
            <div class="topbar-icon-btn" title="Search">
                <i class="ti ti-search"></i>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="stat-grid mb-4">

        <div class="stat-card">
            <div class="stat-card-icon"><i class="ti ti-flame"></i></div>
            <div class="stat-card-value">24</div>
            <div class="stat-card-label">Sessions This Month</div>
            <div class="stat-trend up"><i class="ti ti-trending-up" style="font-size:.75rem"></i> +6 vs last month</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon"><i class="ti ti-clock-hour-4"></i></div>
            <div class="stat-card-value">38<span style="font-size:1rem;font-weight:500">h</span></div>
            <div class="stat-card-label">Total Hours Trained</div>
            <div class="stat-trend up"><i class="ti ti-trending-up" style="font-size:.75rem"></i> Personal best</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon"><i class="ti ti-bolt"></i></div>
            <div class="stat-card-value">6</div>
            <div class="stat-card-label">Day Streak</div>
            <div class="stat-trend warn"><i class="ti ti-alert-triangle" style="font-size:.75rem"></i> Rest day today</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon"><i class="ti ti-calendar-check"></i></div>
            <div class="stat-card-value">142</div>
            <div class="stat-card-label">Days Remaining</div>
            <div class="stat-card-sub">6-Month plan ends Nov 2025</div>
        </div>

    </div>

    <!-- ── ROW: MEMBERSHIP + QUICK ACTIONS ── -->
    <div class="row g-3 mb-4">

        <div class="col-lg-8">
            <div class="membership-card">
                <div class="membership-tag"><span></span> Active Membership</div>
                <div class="membership-plan-name">6-Month Elite</div>
                <div class="membership-dates">
                    <i class="ti ti-calendar" style="font-size:.85rem"></i>
                    &nbsp;May 9, 2025 — Nov 9, 2025
                </div>

                <div class="membership-progress-wrap">
                    <div class="membership-progress-label">
                        <span>Plan progress</span>
                        <span id="progressPct">0%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
                    </div>
                </div>

                <div class="membership-features">
                    <span class="mem-feat-pill"><i class="ti ti-check"></i> Full Gym Access</span>
                    <span class="mem-feat-pill"><i class="ti ti-check"></i> Unlimited Classes</span>
                    <span class="mem-feat-pill"><i class="ti ti-check"></i> 2 PT Sessions/mo</span>
                    <span class="mem-feat-pill"><i class="ti ti-check"></i> Multi-Branch</span>
                    <span class="mem-feat-pill"><i class="ti ti-check"></i> Locker &amp; Showers</span>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:20px;padding:1.25rem;height:100%">
                <div class="section-header mb-3">
                    <h2>Monthly Goal</h2>
                    <span style="font-size:.75rem;font-weight:700;color:var(--fs-red)">24 / 28 sessions</span>
                </div>
                <div class="goal-ring-wrap py-0 mb-2">
                    <div>
                        <svg class="ring-svg" width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(204,26,26,.12)" stroke-width="10"/>
                            <circle id="goalRing" cx="60" cy="60" r="50" fill="none" stroke="#cc1a1a" stroke-width="10"
                                stroke-linecap="round"
                                stroke-dasharray="314"
                                stroke-dashoffset="314"/>
                        </svg>
                        <div class="ring-label">
                            <div class="val">86%</div>
                            <div class="lbl">Complete</div>
                        </div>
                    </div>
                </div>
                <p style="font-size:.72rem;color:var(--bs-secondary-color);text-align:center;margin:0">
                    4 more sessions to hit your monthly goal 💪
                </p>
            </div>
        </div>

    </div>

    <!-- ── ROW: ACTIVITY + SCHEDULE + COACH ── -->
    <div class="row g-3 mb-4">

        <!-- Recent Activity -->
        <div class="col-lg-5">
            <div class="section-header">
                <h2>Recent Activity</h2>
                <a href="#">View all</a>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon gym"><i class="ti ti-barbell"></i></div>
                    <div class="activity-info">
                        <div class="activity-title">Strength Training</div>
                        <div class="activity-sub">Main Branch · 1h 15m</div>
                    </div>
                    <div class="activity-time">Today</div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon class"><i class="ti ti-yoga"></i></div>
                    <div class="activity-info">
                        <div class="activity-title">Yoga Flow</div>
                        <div class="activity-sub">BGC Branch · 45m</div>
                    </div>
                    <div class="activity-time">Yesterday</div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon pt"><i class="ti ti-user-star"></i></div>
                    <div class="activity-info">
                        <div class="activity-title">PT Session — Coach Marco</div>
                        <div class="activity-sub">Main Branch · 1h</div>
                    </div>
                    <div class="activity-time">May 7</div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon gym"><i class="ti ti-run"></i></div>
                    <div class="activity-info">
                        <div class="activity-title">Cardio &amp; HIIT</div>
                        <div class="activity-sub">Makati Branch · 50m</div>
                    </div>
                    <div class="activity-time">May 6</div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon badge"><i class="ti ti-trophy"></i></div>
                    <div class="activity-info">
                        <div class="activity-title">Badge Earned: Iron Week</div>
                        <div class="activity-sub">7 consecutive gym days</div>
                    </div>
                    <div class="activity-time">May 5</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Classes -->
        <div class="col-lg-4">
            <div class="section-header">
                <h2>Upcoming Classes</h2>
                <a href="#">Book more</a>
            </div>
            <div class="class-card">
                <div class="class-day">
                    <div class="class-day-num">10</div>
                    <div class="class-day-name">Sat</div>
                </div>
                <div class="class-divider"></div>
                <div class="class-info">
                    <div class="class-name">Boxing Fundamentals</div>
                    <div class="class-meta"><i class="ti ti-clock" style="font-size:.75rem"></i> 7:00 AM · Makati Branch</div>
                </div>
                <span class="class-badge confirmed">Confirmed</span>
            </div>
            <div class="class-card">
                <div class="class-day">
                    <div class="class-day-num">11</div>
                    <div class="class-day-name">Sun</div>
                </div>
                <div class="class-divider"></div>
                <div class="class-info">
                    <div class="class-name">Yoga Flow</div>
                    <div class="class-meta"><i class="ti ti-clock" style="font-size:.75rem"></i> 9:00 AM · BGC Branch</div>
                </div>
                <span class="class-badge confirmed">Confirmed</span>
            </div>
            <div class="class-card">
                <div class="class-day">
                    <div class="class-day-num">13</div>
                    <div class="class-day-name">Tue</div>
                </div>
                <div class="class-divider"></div>
                <div class="class-info">
                    <div class="class-name">HIIT Circuit</div>
                    <div class="class-meta"><i class="ti ti-clock" style="font-size:.75rem"></i> 6:00 PM · Main Branch</div>
                </div>
                <span class="class-badge waitlist">Waitlist</span>
            </div>
        </div>

        <!-- My Coach -->
        <div class="col-lg-3">
            <div class="section-header">
                <h2>My Coach</h2>
                <a href="#">Message</a>
            </div>
            <div class="coach-card mb-3">
                <div class="coach-avatar">MR</div>
                <div>
                    <div class="coach-name">Marco Reyes</div>
                    <div class="coach-role">Strength &amp; Conditioning</div>
                    <div style="display:flex;align-items:center;gap:.25rem;margin-top:.35rem">
                        <i class="ti ti-star-filled" style="color:#f1c40f;font-size:.75rem"></i>
                        <i class="ti ti-star-filled" style="color:#f1c40f;font-size:.75rem"></i>
                        <i class="ti ti-star-filled" style="color:#f1c40f;font-size:.75rem"></i>
                        <i class="ti ti-star-filled" style="color:#f1c40f;font-size:.75rem"></i>
                        <i class="ti ti-star-filled" style="color:#f1c40f;font-size:.75rem"></i>
                        <span style="font-size:.65rem;color:var(--bs-secondary-color);margin-left:.2rem">5.0</span>
                    </div>
                </div>
                <div class="coach-next">
                    <div class="coach-next-time" style="color:var(--fs-red)">May 16</div>
                    <div class="coach-next-label">Next session</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="quick-grid">
                <a href="#" class="quick-action">
                    <div class="quick-action-icon"><i class="ti ti-calendar-plus"></i></div>
                    <div>
                        <div class="quick-action-title">Book Class</div>
                        <div class="quick-action-sub">Browse schedule</div>
                    </div>
                </a>
                <a href="#" class="quick-action">
                    <div class="quick-action-icon"><i class="ti ti-qrcode"></i></div>
                    <div>
                        <div class="quick-action-title">My QR Code</div>
                        <div class="quick-action-sub">Gym check-in</div>
                    </div>
                </a>
                <a href="#" class="quick-action">
                    <div class="quick-action-icon"><i class="ti ti-refresh"></i></div>
                    <div>
                        <div class="quick-action-title">Renew Plan</div>
                        <div class="quick-action-sub">142 days left</div>
                    </div>
                </a>
                <a href="#" class="quick-action">
                    <div class="quick-action-icon"><i class="ti ti-help-circle"></i></div>
                    <div>
                        <div class="quick-action-title">Support</div>
                        <div class="quick-action-sub">Help center</div>
                    </div>
                </a>
            </div>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

/* ── THEME ── */
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
}

/* ── SIDEBAR (MOBILE) ── */
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

/* ── ACTIVE NAV ── */
function setActive(el) {
    document.querySelectorAll('.sb-nav-item').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    if (window.innerWidth < 992) closeSidebar();
}

/* ── ANIMATED PROGRESS BARS ── */
window.addEventListener('load', () => {
    /* Membership progress — 142/180 days used (38/180 = 21%) */
    const progress = Math.round((38 / 180) * 100);
    setTimeout(() => {
        document.getElementById('progressFill').style.width = progress + '%';
        document.getElementById('progressPct').textContent = progress + '%';
    }, 300);

    /* Goal ring — 24/28 = 85.7% */
    const pct = 24 / 28;
    const circumference = 314;
    const offset = circumference - (pct * circumference);
    setTimeout(() => {
        document.getElementById('goalRing').style.strokeDashoffset = offset;
        document.getElementById('goalRing').style.transition = 'stroke-dashoffset 1.2s cubic-bezier(.25,.46,.45,.94)';
    }, 400);
});

</script>
</body>
</html>