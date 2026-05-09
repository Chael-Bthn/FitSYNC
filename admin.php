<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin — FitSync</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
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

        .nav-section {
            padding: .2rem 0;
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

        .sidebar-link.active i {
            color: var(--fs-red);
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

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem .7rem;
            border-top: 1px solid var(--card-border);
        }

        .sidebar-link.logout {
            color: rgba(255, 80, 80, .65);
        }

        .sidebar-link.logout:hover {
            background: rgba(204, 26, 26, .12);
            color: #ff6b6b;
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
            color: var(--input-ph);
        }

        .topbar-search input:focus {
            outline: none;
            border-color: rgba(204, 26, 26, .5);
            background: var(--input-bg);
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

        /* ── THEME TOGGLE ── */
        .theme-toggle {
            width: 52px;
            height: 28px;
            border-radius: 50px;
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            position: relative;
            cursor: pointer;
            transition: background .3s;
            padding: 0;
            flex-shrink: 0;
        }

        .theme-toggle .tog-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--fs-red);
            transition: transform .3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .65rem;
            color: #fff;
        }

        [data-bs-theme="light"] .theme-toggle .tog-knob {
            transform: translateX(24px);
        }

        .theme-toggle .tog-icon-dark,
        .theme-toggle .tog-icon-light {
            position: absolute;
            top: 50%;
            font-size: .75rem;
            transform: translateY(-50%);
            transition: opacity .3s;
        }

        .theme-toggle .tog-icon-dark {
            left: 7px;
            opacity: 1;
            color: rgba(255, 255, 255, .7);
        }

        .theme-toggle .tog-icon-light {
            right: 7px;
            opacity: .35;
            color: rgba(0, 0, 0, .5);
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-dark {
            opacity: .35;
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-light {
            opacity: 1;
        }

        /* ── MAIN CONTENT ── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
        }

        .main-content {
            padding: 1.75rem;
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
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-top: .3rem;
        }

        .stat-delta {
            font-size: .75rem;
            font-weight: 600;
            margin-top: .5rem;
        }

        .stat-delta.up {
            color: #4caf87;
        }

        .stat-delta.down {
            color: #e05656;
        }

        /* ── SECTION TITLE ── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.1rem;
        }

        .section-h {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.2px;
        }

        .section-h small {
            font-size: .72rem;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: .5rem;
        }

        /* ── TABLE ── */
        .admin-table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow: hidden;
        }

        .admin-table {
            margin: 0;
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
            border-bottom: none;
        }

        .admin-table tbody tr:hover td {
            background: var(--row-hover);
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
            letter-spacing: .4px;
        }

        .plan-badge.mo1 {
            background: var(--input-bg);
            color: var(--text-muted);
        }

        .plan-badge.mo3 {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
        }

        .plan-badge.mo6 {
            background: rgba(204, 26, 26, .15);
            color: var(--fs-red);
            border: 1px solid rgba(204, 26, 26, .25);
        }

        .plan-badge.yr {
            background: rgba(255, 193, 7, .1);
            color: #ffc107;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: .4rem;
        }

        .status-dot.active {
            background: #4caf87;
            box-shadow: 0 0 6px rgba(76, 175, 135, .5);
        }

        .status-dot.inactive {
            background: #888;
        }

        .status-dot.pending {
            background: #ffc107;
        }

        /* ── ACTION BUTTONS ── */
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
            color: var(--fs-red);
        }

        .tbl-btn.danger:hover {
            background: rgba(220, 53, 69, .15);
            border-color: rgba(220, 53, 69, .3);
            color: #e05656;
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
            transform: translateY(-2px);
        }

        .feedback-stars {
            color: var(--fs-red);
            font-size: .85rem;
            letter-spacing: 1px;
        }

        .feedback-text {
            font-size: .85rem;
            color: var(--text-muted);
            line-height: 1.7;
            margin: .5rem 0 0;
        }

        .feedback-meta {
            font-size: .72rem;
            color: var(--text-dimmed);
            margin-top: .6rem;
        }

        .rating-bar-label {
            font-size: .75rem;
            color: var(--text-muted);
            width: 40px;
        }

        .rating-bar-track {
            flex: 1;
            background: var(--input-bg);
            border-radius: 50px;
            height: 7px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            border-radius: 50px;
            background: var(--fs-red);
        }

        /* ── MINI CHART (pure CSS sparkline) ── */
        .sparkline {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            height: 40px;
        }

        .spark-bar {
            flex: 1;
            border-radius: 3px 3px 0 0;
            background: var(--fs-red-soft);
            transition: background .2s;
        }

        .spark-bar.hi {
            background: var(--fs-red);
        }

        .spark-bar:hover {
            background: var(--fs-red);
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
            transform: translateY(-2px);
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
            color: var(--text-primary);
        }

        .quick-action-sub {
            font-size: .7rem;
            color: var(--text-muted);
            margin-top: .1rem;
        }

        /* ── EMPTY / MODAL ── */
        .page-section {
            display: none;
        }

        .page-section.active {
            display: block;
        }

        /* Modal */
        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
        }

        .modal-header {
            border-bottom: 1px solid var(--card-border);
        }

        .modal-footer {
            border-top: 1px solid var(--card-border);
        }

        /* ── RESPONSIVE ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 1039;
        }

        /* Table scroll wrapper for all screen sizes */
        .admin-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* ── TABLET (≤ 991px) — sidebar becomes a drawer ── */
        @media(max-width:991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.open {
                display: block;
            }

            .main-wrap {
                margin-left: 0;
            }

            .topbar {
                left: 0;
            }

            .topbar-toggle {
                display: flex;
            }

            .main-content {
                padding: 1.25rem;
            }

            /* Shrink search on tablet */
            .topbar-search input {
                width: 160px;
            }
        }

        /* ── MOBILE (≤ 767px) ── */
        @media(max-width:767px) {
            .main-content {
                padding: 1rem;
            }

            /* Topbar: hide search bar, keep it accessible via a toggle state */
            .topbar {
                padding: 0 1rem;
                gap: .6rem;
                flex-wrap: nowrap;
            }

            .topbar-search {
                display: none;
            }

            .topbar-breadcrumb {
                display: none;
            }

            .topbar-title {
                font-size: .9rem;
            }

            /* Stat cards: shrink font on mobile */
            .stat-value {
                font-size: 1.6rem;
            }

            .stat-card {
                padding: 1rem 1.1rem;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
                margin-bottom: .6rem;
            }

            /* Section header: stack on small screens */
            .section-header {
                flex-wrap: wrap;
                gap: .6rem;
            }

            /* Members toolbar: stack filter + button */
            #page-members .section-header>div.d-flex {
                flex-wrap: wrap;
                width: 100%;
                gap: .5rem;
            }

            #page-members .section-header>div.d-flex .fs-select {
                flex: 1;
            }

            #page-members .section-header>div.d-flex .btn {
                flex: 1;
                justify-content: center;
            }

            /* Feedback rating: stack vertically */
            #page-feedbacks .row {
                --bs-gutter-x: .75rem;
            }

            /* Sparkline month labels: fewer chars */
            .spark-month {
                display: none;
            }

            .spark-month.show {
                display: inline;
            }

            /* Modal: full-screen feel */
            .modal-dialog {
                margin: .5rem;
            }

            .modal-content {
                border-radius: 14px;
            }
        }

        /* ── SMALL PHONES (≤ 479px) ── */
        @media(max-width:479px) {
            .main-content {
                padding: .75rem;
            }

            /* Stat cards: full width on very small screens */
            .stat-card {
                padding: .85rem 1rem;
            }

            .stat-value {
                font-size: 1.45rem;
                letter-spacing: -.5px;
            }

            .stat-label {
                font-size: .68rem;
            }

            .stat-delta {
                font-size: .7rem;
            }

            /* Topbar */
            .topbar {
                padding: 0 .75rem;
                gap: .4rem;
            }

            .topbar-avatar {
                width: 30px;
                height: 30px;
                font-size: .75rem;
            }

            .theme-toggle {
                width: 44px;
                height: 24px;
            }

            .theme-toggle .tog-knob {
                width: 16px;
                height: 16px;
                top: 3px;
                left: 3px;
            }

            [data-bs-theme="light"] .theme-toggle .tog-knob {
                transform: translateX(20px);
            }

            /* Admin table: hide lower-priority columns */
            .admin-table .col-hide-xs {
                display: none;
            }

            /* Quick actions: compact */
            .quick-action {
                padding: .75rem .9rem;
            }

            .quick-action-icon {
                width: 32px;
                height: 32px;
                font-size: .95rem;
            }
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
            display: flex;
        }

        .mobile-search-bar i {
            color: var(--search-icon);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .mobile-search-bar input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--input-color);
            font-family: 'Outfit', sans-serif;
            font-size: .88rem;
        }

        .mobile-search-bar input::placeholder {
            color: var(--input-ph);
        }

        .mobile-search-bar button {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            padding: .1rem;
        }

        /* When mobile search is open, push content down */
        .mobile-search-open .main-wrap {
            padding-top: calc(var(--topbar-h) + 44px);
        }

        /* ── MODAL CLOSE BTN ── */
        [data-bs-theme="dark"] .btn-close {
            filter: invert(1) grayscale(1);
        }

        /* ── FS SELECT ── */
        .fs-select {
            width: auto;
            background: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--card-border) !important;
            font-size: .8rem;
        }

        .fs-select option {
            background: var(--card-bg);
            color: var(--text-primary);
        }

        /* ── MODAL FORM INPUTS ── */
        .fs-input {
            background: var(--input-bg) !important;
            border-color: var(--card-border) !important;
            color: var(--text-primary) !important;
        }

        .fs-input::placeholder {
            color: var(--input-ph) !important;
        }

        .fs-input:focus {
            border-color: rgba(204, 26, 26, .5) !important;
            box-shadow: none !important;
        }

        /* ── MODAL LABEL ── */
        .fs-label {
            font-size: .75rem;
            color: var(--text-muted);
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .1);
            border-radius: 10px;
        }
    </style>
</head>

<body>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ════════════════ SIDEBAR ════════════════ -->
    <aside class="sidebar" id="sidebar">

        <a class="sidebar-brand" href="index.php">
            <img src="FitSYNC Emblem.svg" alt="FitSync" width="32" height="32" />
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
                <span class="nav-pill" id="pill-members">12K</span>
            </a>
            <a class="sidebar-link" onclick="showPage('feedbacks',this)">
                <i class="ti ti-message-star"></i> Feedbacks
                <span class="nav-pill" id="pill-feedbacks">6</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a class="sidebar-link logout" href="index.php">
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
            <input type="text" placeholder="Search members…" id="memberSearch" oninput="filterMembers()" />
        </div>
        <button class="topbar-toggle d-md-none" id="searchToggleBtn" onclick="toggleMobileSearch()" aria-label="Search"><i class="ti ti-search"></i></button>
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme">
            <i class="ti ti-moon tog-icon-dark"></i>
            <i class="ti ti-sun tog-icon-light"></i>
            <span class="tog-knob" id="tog-knob"><i class="ti ti-moon" id="knob-icon"></i></span>
        </button>
        <div class="topbar-avatar" title="Administrator">A</div>
    </div>

    <!-- Mobile search bar (drops below topbar on small screens) -->
    <div class="mobile-search-bar" id="mobileSearchBar">
        <i class="ti ti-search"></i>
        <input type="text" placeholder="Search members…" id="memberSearchMobile" oninput="syncMobileSearch(this)" />
        <button onclick="toggleMobileSearch()"><i class="ti ti-x"></i></button>
    </div>

    <!-- ════════════════ MAIN ════════════════ -->
    <div class="main-wrap">
        <div class="main-content">

            <!-- ══ DASHBOARD PAGE ══ -->
            <div class="page-section active" id="page-dashboard">

                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-users"></i></div>
                            <div class="stat-value">12,483</div>
                            <div class="stat-label">Total Members</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> +342 this month</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-cash"></i></div>
                            <div class="stat-value">₱1.2M</div>
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> +18% vs last month</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                            <div class="stat-value">8</div>
                            <div class="stat-label">Active Branches</div>
                            <div class="stat-delta up"><i class="ti ti-point"></i> All operational</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="ti ti-star"></i></div>
                            <div class="stat-value">4.8</div>
                            <div class="stat-label">Avg. Rating</div>
                            <div class="stat-delta up"><i class="ti ti-trending-up"></i> +0.2 this quarter</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <!-- New Signups Chart -->
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

                    <!-- Quick Actions -->
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
                                        <div class="quick-action-sub">6 new unread reviews</div>
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

                <!-- Recent Members -->
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


            <!-- ══ MEMBERS PAGE ══ -->
            <div class="page-section" id="page-members">
                <div class="section-header mb-3">
                    <div class="section-h">All Members <small id="member-count-label"></small></div>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm fs-select" id="planFilter" onchange="filterMembers()">
                            <option value="">All Plans</option>
                            <option value="1 Month">1 Month</option>
                            <option value="3 Months">3 Months</option>
                            <option value="6 Months">6 Months</option>
                            <option value="12 Months">12 Months</option>
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
                                <th class="col-hide-xs">Branch</th>
                                <th class="col-hide-xs">Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="members-tbody"></tbody>
                    </table>
                </div>
            </div><!-- /members -->


            <!-- ══ FEEDBACKS PAGE ══ -->
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
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-label">First Name</label>
                            <input type="text" class="form-control fs-input" placeholder="Juan" id="new-fname">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-label">Last Name</label>
                            <input type="text" class="form-control fs-input" placeholder="dela Cruz" id="new-lname">
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-label">Email</label>
                            <input type="email" class="form-control fs-input" placeholder="juan@email.com" id="new-email">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-label">Plan</label>
                            <select class="form-select fs-input" id="new-plan">
                                <option>1 Month</option>
                                <option>3 Months</option>
                                <option selected>6 Months</option>
                                <option>12 Months</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-label">Branch</label>
                            <select class="form-select fs-input" id="new-branch">
                                <option>Quezon City</option>
                                <option>Makati</option>
                                <option>BGC</option>
                                <option>Ortigas</option>
                                <option>Eastwood</option>
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
        let members = [{
                id: 1,
                fname: 'Maria',
                lname: 'Santos',
                email: 'maria@example.com',
                plan: '6 Months',
                branch: 'BGC',
                joined: '2025-01-15',
                status: 'active'
            },
            {
                id: 2,
                fname: 'Jose',
                lname: 'Reyes',
                email: 'jose@example.com',
                plan: '12 Months',
                branch: 'Makati',
                joined: '2025-02-03',
                status: 'active'
            },
            {
                id: 3,
                fname: 'Ana',
                lname: 'Cruz',
                email: 'ana@example.com',
                plan: '3 Months',
                branch: 'Quezon City',
                joined: '2025-03-22',
                status: 'active'
            },
            {
                id: 4,
                fname: 'Carlos',
                lname: 'Garcia',
                email: 'carlos@example.com',
                plan: '1 Month',
                branch: 'Ortigas',
                joined: '2025-04-08',
                status: 'inactive'
            },
            {
                id: 5,
                fname: 'Liza',
                lname: 'Navarro',
                email: 'liza@example.com',
                plan: '6 Months',
                branch: 'Eastwood',
                joined: '2025-04-18',
                status: 'active'
            },
            {
                id: 6,
                fname: 'Ramon',
                lname: 'Torres',
                email: 'ramon@example.com',
                plan: '12 Months',
                branch: 'BGC',
                joined: '2025-04-25',
                status: 'active'
            },
            {
                id: 7,
                fname: 'Patricia',
                lname: 'Mendoza',
                email: 'pat@example.com',
                plan: '3 Months',
                branch: 'Makati',
                joined: '2025-05-01',
                status: 'pending'
            },
            {
                id: 8,
                fname: 'Dennis',
                lname: 'Villanueva',
                email: 'dennis@example.com',
                plan: '1 Month',
                branch: 'Quezon City',
                joined: '2025-05-05',
                status: 'active'
            },
            {
                id: 9,
                fname: 'Rosa',
                lname: 'Aquino',
                email: 'rosa@example.com',
                plan: '6 Months',
                branch: 'Ortigas',
                joined: '2025-05-07',
                status: 'active'
            },
            {
                id: 10,
                fname: 'Miguel',
                lname: 'Dela Cruz',
                email: 'miguel@example.com',
                plan: '12 Months',
                branch: 'Eastwood',
                joined: '2025-05-08',
                status: 'inactive'
            },
        ];

        const feedbacks = [{
                name: 'Maria Santos',
                rating: 5,
                text: 'Best gym I\'ve ever been to! The equipment is top-notch and the coaches are incredibly supportive. Completely transformed my fitness journey.',
                date: 'May 7, 2025',
                branch: 'BGC'
            },
            {
                name: 'Jose Reyes',
                rating: 5,
                text: 'Signed up for the annual plan and it was worth every peso. The multi-branch access is super convenient for my work schedule.',
                date: 'May 5, 2025',
                branch: 'Makati'
            },
            {
                name: 'Anonymous',
                rating: 4,
                text: 'Great facilities overall. Would love to see more yoga class slots added — they fill up really fast.',
                date: 'May 3, 2025',
                branch: 'Quezon City'
            },
            {
                name: 'Liza Navarro',
                rating: 5,
                text: 'The staff here makes all the difference. Always welcoming and the atmosphere pushes you to go harder every session.',
                date: 'Apr 30, 2025',
                branch: 'Eastwood'
            },
            {
                name: 'Anonymous',
                rating: 3,
                text: 'Equipment is great but the locker rooms get crowded during peak hours. Still a solid gym overall.',
                date: 'Apr 28, 2025',
                branch: 'Ortigas'
            },
            {
                name: 'Ramon Torres',
                rating: 5,
                text: 'Personal training sessions are worth it. My PT is knowledgeable and keeps me accountable. Renewed for another year!',
                date: 'Apr 25, 2025',
                branch: 'BGC'
            },
        ];

        const sparkData = [120, 185, 210, 178, 230, 195, 260, 300, 278, 320, 290, 342];
        const planClass = {
            '1 Month': 'mo1',
            '3 Months': 'mo3',
            '6 Months': 'mo6',
            '12 Months': 'yr'
        };
        const starsStr = n => '★'.repeat(n) + '☆'.repeat(5 - n);

        /* ── INIT ── */
        function init() {
            buildSparkline();
            renderRecentMembers();
            renderMembers();
            renderFeedbacks();
        }

        function buildSparkline() {
            const max = Math.max(...sparkData);
            const el = document.getElementById('sparkline-chart');
            el.innerHTML = sparkData.map((v, i) => {
                const h = Math.round((v / max) * 100);
                const hi = i === sparkData.length - 1;
                return `<div class="spark-bar${hi?' hi':''}" style="height:${h}%" title="${v} signups"></div>`;
            }).join('');
        }

        function renderRecentMembers() {
            const el = document.getElementById('recent-members-tbody');
            el.innerHTML = members.slice(-5).reverse().map(m => memberRow(m, true)).join('');
        }

        function renderMembers() {
            const q = (document.getElementById('memberSearch').value || '').toLowerCase();
            const plan = (document.getElementById('planFilter')?.value || '');
            let data = members.filter(m => {
                const txt = `${m.fname} ${m.lname} ${m.email} ${m.branch}`.toLowerCase();
                return (!q || txt.includes(q)) && (!plan || m.plan === plan);
            });
            const el = document.getElementById('members-tbody');
            el.innerHTML = data.map(m => memberRow(m, false)).join('') || '<tr><td colspan="7" class="text-center text-secondary py-4">No members found</td></tr>';
            document.getElementById('member-count-label').textContent = `${data.length} of ${members.length}`;
        }

        function memberRow(m, compact) {
            const initials = (m.fname[0] || '') + (m.lname[0] || '');
            const status = `<span class="status-dot ${m.status}"></span>${m.fname[0]==='M'&&m.status==='active'?'Active':m.status.charAt(0).toUpperCase()+m.status.slice(1)}`;
            const plan = `<span class="plan-badge ${planClass[m.plan]||''}">${m.plan}</span>`;
            const date = new Date(m.joined).toLocaleDateString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            const avatar = `<div class="member-avatar">${initials}</div>`;
            if (compact) {
                return `<tr>
            <td><div class="d-flex align-items-center gap-2">${avatar}<span>${m.fname} ${m.lname}</span></div></td>
            <td>${plan}</td>
            <td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">${m.branch}</span></td>
            <td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">${date}</span></td>
            <td>${status}</td>
        </tr>`;
            }
            return `<tr>
        <td><div class="d-flex align-items-center gap-2">${avatar}<div><div style="font-weight:600">${m.fname} ${m.lname}</div><div style="font-size:.7rem;color:var(--text-dimmed)">#${String(m.id).padStart(5,'0')}</div></div></div></td>
        <td class="col-hide-xs"><span style="font-size:.82rem;color:var(--text-muted)">${m.email}</span></td>
        <td>${plan}</td>
        <td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">${m.branch}</span></td>
        <td class="col-hide-xs"><span style="font-size:.8rem;color:var(--text-muted)">${date}</span></td>
        <td>${status}</td>
        <td>
            <div class="d-flex gap-1">
                <button class="tbl-btn" title="Edit" onclick="editMember(${m.id})"><i class="ti ti-edit"></i></button>
                <button class="tbl-btn danger" title="Delete" onclick="deleteMember(${m.id})"><i class="ti ti-trash"></i></button>
            </div>
        </td>
    </tr>`;
        }

        function renderFeedbacks() {
            document.getElementById('feedback-list').innerHTML = feedbacks.map(f => `
        <div class="feedback-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-weight:700;font-size:.88rem;color:var(--text-primary)">${f.name}</div>
                    <div class="feedback-stars">${starsStr(f.rating)}</div>
                </div>
                <button class="tbl-btn danger" title="Delete feedback"><i class="ti ti-trash"></i></button>
            </div>
            <div class="feedback-text">"${f.text}"</div>
            <div class="feedback-meta"><i class="ti ti-map-pin" style="font-size:.8rem"></i> ${f.branch} &nbsp;·&nbsp; ${f.date}</div>
        </div>
    `).join('');
        }

        /* ── NAVIGATION ── */
        function showPage(id, btn) {
            document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
            document.getElementById('page-' + id).classList.add('active');

            document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
            if (btn) btn.classList.add('active');
            else {
                document.querySelectorAll('.sidebar-link').forEach(l => {
                    if (l.textContent.trim().toLowerCase().includes(id)) l.classList.add('active');
                });
            }

            const titles = {
                dashboard: 'Dashboard',
                members: 'Members',
                feedbacks: 'Feedbacks'
            };
            const crumbs = {
                dashboard: 'Overview',
                members: 'Member Management',
                feedbacks: 'Review Feedbacks'
            };
            document.getElementById('topbar-title').textContent = titles[id] || id;
            document.getElementById('topbar-crumb').textContent = crumbs[id] || id;

            if (id === 'members') renderMembers();
            closeSidebar();
        }

        function filterMembers() {
            renderMembers();
        }

        /* ── ADD MEMBER ── */
        function addMember() {
            const fname = document.getElementById('new-fname').value.trim();
            const lname = document.getElementById('new-lname').value.trim();
            const email = document.getElementById('new-email').value.trim();
            const plan = document.getElementById('new-plan').value;
            const branch = document.getElementById('new-branch').value;
            if (!fname || !lname || !email) {
                alert('Please fill in all fields.');
                return;
            }
            const newId = Math.max(...members.map(m => m.id)) + 1;
            members.push({
                id: newId,
                fname,
                lname,
                email,
                plan,
                branch,
                joined: new Date().toISOString().split('T')[0],
                status: 'active'
            });
            document.getElementById('pill-members').textContent = members.length >= 1000 ?
                Math.round(members.length / 1000 * 10) / 10 + 'K' : members.length;
            bootstrap.Modal.getInstance(document.getElementById('addMemberModal')).hide();
            ['new-fname', 'new-lname', 'new-email'].forEach(id => document.getElementById(id).value = '');
            renderMembers();
            renderRecentMembers();
            showPage('members', null);
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
                filterMembers();
            }
        }

        function syncMobileSearch(el) {
            document.getElementById('memberSearch').value = el.value;
            filterMembers();
        }

        /* ── THEME ── */
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            document.getElementById('knob-icon').className = isDark ? 'ti ti-sun' : 'ti ti-moon';
            localStorage.setItem('fs-admin-theme', isDark ? 'light' : 'dark');
        }
        // Restore saved theme
        (function() {
            const saved = localStorage.getItem('fs-admin-theme');
            if (saved) {
                document.documentElement.setAttribute('data-bs-theme', saved);
                const knob = document.getElementById('knob-icon');
                if (knob) knob.className = saved === 'light' ? 'ti ti-sun' : 'ti ti-moon';
            }
        })();

        /* ── BOOT ── */
        init();
    </script>
</body>

</html>