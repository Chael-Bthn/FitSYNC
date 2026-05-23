<?php
$sidebarMemberCount = $sidebarMemberCount ?? null;
$sidebarBranchCount = $sidebarBranchCount ?? null;
$sidebarFeedbackCount = $sidebarFeedbackCount ?? null;
$adminPathPrefix = $adminPathPrefix ?? (
    str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/')
        ? '../'
        : ''
);
$adminHomeUrl = $adminPathPrefix . 'admin.php';
$adminIndexUrl = $adminPathPrefix . 'index.php';
$adminLogoutUrl = $adminPathPrefix . 'logout.php';
$adminLogoDark = $adminPathPrefix . 'assets/FitSYNC%20Emblem%20Light.svg';
$adminLogoLight = $adminPathPrefix . 'assets/FitSYNC%20Emblem.svg';
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sidebarMemberCount ??= (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
        $sidebarBranchCount ??= (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE is_active = 1")->fetchColumn();
        $sidebarFeedbackCount ??= (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE is_visible = 1")->fetchColumn();
    } catch (Throwable $e) {
        $sidebarMemberCount ??= 0;
        $sidebarBranchCount ??= 0;
        $sidebarFeedbackCount ??= 0;
    }
}
?>
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= htmlspecialchars($adminIndexUrl) ?>">
        <img class="theme-logo" src="<?= htmlspecialchars($adminLogoDark) ?>" data-logo-dark="<?= htmlspecialchars($adminLogoDark) ?>" data-logo-light="<?= htmlspecialchars($adminLogoLight) ?>" alt="FitSync" width="32" height="32">
        <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
    </a>

    <div class="sidebar-admin-badge">Admin Panel</div>

    <nav class="nav-section flex-grow-1" style="overflow-y:auto">
        <div class="nav-section-label">Overview</div>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>">
            <i class="ti ti-layout-dashboard"></i> Dashboard
        </a>

        <div class="nav-section-label">Management</div>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'members' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>#members">
            <i class="ti ti-users"></i> Members
            <span class="nav-pill" id="pill-members"><?= number_format((int) $sidebarMemberCount) ?></span>
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'branches' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>#branches">
            <i class="ti ti-building-store"></i> Branches
            <span class="nav-pill" id="pill-branches"><?= number_format((int) $sidebarBranchCount) ?></span>
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'schedules' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>?page=schedules">
            <i class="ti ti-calendar-event"></i> Schedules
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'announcements' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>?page=announcements">
            <i class="ti ti-speakerphone"></i> Announcements
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'feedbacks' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>#feedbacks">
            <i class="ti ti-message-star"></i> Feedbacks
            <span class="nav-pill" id="pill-feedbacks"><?= number_format((int) $sidebarFeedbackCount) ?></span>
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'reports' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>?page=reports">
            <i class="ti ti-chart-pie"></i> Reports
        </a>
        <a class="sidebar-link <?= ($activeAdminPage ?? '') === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminHomeUrl) ?>#settings">
            <i class="ti ti-settings"></i> Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sb-theme-row">
            <span class="sb-theme-label">
                <i class="ti ti-moon"></i> Dark Mode
            </span>
            <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle theme">
                <div class="theme-pill-knob"></div>
            </button>
        </div>

        <a class="sidebar-link logout" href="<?= htmlspecialchars($adminLogoutUrl) ?>">
            <i class="ti ti-logout"></i> Logout
        </a>
    </div>
</aside>

<div class="topbar">
    <button class="topbar-toggle" onclick="openSidebar()" aria-label="Open sidebar"><i class="ti ti-menu-2"></i></button>
    <div class="me-auto">
        <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Admin') ?></div>
        <div class="topbar-breadcrumb">FitSync Admin &rsaquo; <?= htmlspecialchars($topbarCrumb ?? 'Operations') ?></div>
    </div>
    <div class="topbar-avatar" title="Administrator"><?= htmlspecialchars($adminInitial) ?></div>
</div>

<div class="main-wrap">
    <main class="main-content">
