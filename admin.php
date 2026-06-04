<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync Admin — Improved</title>
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
            gap: .5rem;
            justify-content: flex-end;
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
            .g-3,
            .g-2 {
                grid-template-columns: 1fr 1fr;
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
                    <span class="sb-pill">7</span>
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
        </nav>

        <div class="sb-footer">
            <div class="sb-theme-row">
                <span class="sb-theme-label"><i class="ti ti-moon" style="font-size:.9rem"></i> Dark mode</span>
                <button class="pill-toggle" onclick="toggleTheme()">
                    <div class="pill-knob"></div>
                </button>
            </div>
            <button class="sb-link logout"><i class="ti ti-logout"></i> Logout</button>
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
        <button class="notif-btn" onclick="toast('info','3 pending payments awaiting approval')">
            <i class="ti ti-bell"></i>
            <div class="notif-dot"></div>
        </button>
        <div class="avatar">A</div>
    </div>

    <!-- ══ MAIN ══════════════════════════════════ -->
    <main class="main">

        <!-- ─── DASHBOARD ──────────────────────────── -->
        <div class="page active" id="page-dashboard">

            <!-- Urgent alert (only when there are pending items) -->
            <div class="alert-banner">
                <i class="ti ti-alert-triangle"></i>
                <div>
                    <strong>3 pending payments</strong> and <strong>4 new registrations</strong> require your attention.
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
                        <div class="stat-val">284</div>
                        <div class="stat-lbl">Total Members</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> +12 this month</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-cash"></i></div>
                        <div class="stat-val">₱148k</div>
                        <div class="stat-lbl">Monthly Revenue</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> +8% vs last month</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                        <div class="stat-val">5</div>
                        <div class="stat-lbl">Active Branches</div>
                        <div class="stat-sub"><i class="ti ti-point"></i> All operational</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-star"></i></div>
                        <div class="stat-val">4.8</div>
                        <div class="stat-lbl">Avg. Rating</div>
                        <div class="stat-sub up"><i class="ti ti-trending-up"></i> 142 reviews</div>
                    </div>
                </div>

                <div class="grid g-2-1" style="margin-bottom:1.25rem">
                    <div class="card">
                        <div class="card-head">
                            <div>
                                <div class="card-title">New sign-ups</div>
                                <div class="card-sub">Last 12 months</div>
                            </div>
                            <span class="badge active">+12 this month</span>
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
                                    <div class="qa-sub">142 total reviews</div>
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
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">Pending approvals</div>
                            <span class="badge pending">3 pending</span>
                        </div>
                        <div class="card-body" id="pending-list"></div>
                    </div>
                </div>
            </div><!-- /overview tab -->

            <!-- ── ATTENDANCE TAB ── -->
            <div class="dash-panel" id="dt-attendance">
                <div class="grid g-4" style="margin-bottom:1.25rem">
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-login-2"></i></div>
                        <div class="stat-val">47</div>
                        <div class="stat-lbl">Today's Check-ins</div>
                        <div class="stat-sub"><i class="ti ti-calendar-check"></i> Jun 4, 2026</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-flame"></i></div>
                        <div class="stat-val">28</div>
                        <div class="stat-lbl">Top 30-Day Visits</div>
                        <div class="stat-sub up"><i class="ti ti-run"></i> Most active member</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-user-pause"></i></div>
                        <div class="stat-val">19</div>
                        <div class="stat-lbl">Inactive Members</div>
                        <div class="stat-sub down"><i class="ti ti-clock"></i> 30+ days no visit</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-building-store"></i></div>
                        <div class="stat-val">1,240</div>
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
                        <div class="stat-val">241</div>
                        <div class="stat-lbl">Active Memberships</div>
                    </div>
                    <div class="stat urgent">
                        <div class="stat-icon"><i class="ti ti-cash-banknote"></i></div>
                        <div class="stat-val">3</div>
                        <div class="stat-lbl">Pending Payments</div>
                        <div class="stat-sub down"><i class="ti ti-alert-circle"></i> Needs review</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-calendar-time"></i></div>
                        <div class="stat-val">8</div>
                        <div class="stat-lbl">Expiring in 7 Days</div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon"><i class="ti ti-id-badge-off"></i></div>
                        <div class="stat-val">43</div>
                        <div class="stat-lbl">Expired</div>
                    </div>
                </div>

                <div class="grid g-2" style="margin-bottom:1.25rem">
                    <div class="tbl-wrap">
                        <div class="card-head" style="border-bottom:1px solid var(--border)">
                            <div class="card-title">Pending payment approvals</div>
                            <span class="badge pending">3</span>
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
                                <tr>
                                    <td>
                                        <div style="font-weight:600">Maria Santos <span class="pulse-dot"></span></div>
                                        <div style="font-size:.7rem;color:var(--text-3)">maria@email.com</div>
                                    </td>
                                    <td><span class="plan-badge mo6">6 Months</span></td>
                                    <td>₱3,500</td>
                                    <td>
                                        <div class="actions">
                                            <button class="tbtn success" title="Approve" onclick="confirmAction('Approve this payment?','Activate Maria Santos\'s membership.',()=>toast('success','Payment approved','Membership is now active.'))"><i class="ti ti-check"></i></button>
                                            <button class="tbtn danger" title="Reject" onclick="confirmAction('Reject payment?','This will cancel the membership request.',()=>toast('error','Payment rejected'))"><i class="ti ti-x"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:600">Jose Reyes <span class="pulse-dot"></span></div>
                                        <div style="font-size:.7rem;color:var(--text-3)">jose@email.com</div>
                                    </td>
                                    <td><span class="plan-badge yr">12 Months</span></td>
                                    <td>₱6,000</td>
                                    <td>
                                        <div class="actions">
                                            <button class="tbtn success" onclick="confirmAction('Approve this payment?','Activate Jose Reyes\'s membership.',()=>toast('success','Payment approved','Membership is now active.'))"><i class="ti ti-check"></i></button>
                                            <button class="tbtn danger" onclick="confirmAction('Reject payment?','This will cancel the membership request.',()=>toast('error','Payment rejected'))"><i class="ti ti-x"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:600">Ana Cruz <span class="pulse-dot"></span></div>
                                        <div style="font-size:.7rem;color:var(--text-3)">ana@email.com</div>
                                    </td>
                                    <td><span class="plan-badge mo3">3 Months</span></td>
                                    <td>₱2,000</td>
                                    <td>
                                        <div class="actions">
                                            <button class="tbtn success" onclick="confirmAction('Approve this payment?','Activate Ana Cruz\'s membership.',()=>toast('success','Payment approved','Membership is now active.'))"><i class="ti ti-check"></i></button>
                                            <button class="tbtn danger" onclick="confirmAction('Reject payment?','This will cancel the membership request.',()=>toast('error','Payment rejected'))"><i class="ti ti-x"></i></button>
                                        </div>
                                    </td>
                                </tr>
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
            <div class="sec-head">
                <div class="sec-title">All Members <small id="member-count"></small></div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <select class="btn" id="status-filter" onchange="filterMembers()" style="padding:.38rem .8rem;border-radius:9px;background:var(--surface);color:var(--text);border:1px solid var(--border);font-size:.78rem;appearance:none">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="frozen">Frozen</option>
                        <option value="pending">Pending</option>
                    </select>
                    <button class="btn primary" onclick="toast('info','Add Member','Open registration form')"><i class="ti ti-plus"></i> Add Member</button>
                </div>
            </div>
            <div class="tbl-wrap">
                <table>
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
                <div class="sec-title">Branches <small>5 active</small></div>
            </div>
            <div class="grid g-3" id="branches-grid"></div>
        </div>

        <!-- ─── SCHEDULES ─────────────────────────── -->
        <div class="page" id="page-schedules">
            <div class="sec-head">
                <div class="sec-title">Schedules</div>
            </div>
            <div class="card" style="padding:2rem;text-align:center;color:var(--text-2)">
                <i class="ti ti-calendar-event" style="font-size:2.5rem;color:var(--text-3);display:block;margin-bottom:.75rem"></i>
                Class and schedule management — connect to your PHP backend for live data.
            </div>
        </div>

        <!-- ─── FEEDBACKS ─────────────────────────── -->
        <div class="page" id="page-feedbacks">
            <div class="sec-head">
                <div class="sec-title">Feedbacks</div>
            </div>
            <div class="grid g-2-1">
                <div id="fb-list"></div>
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">Rating breakdown</div>
                    </div>
                    <div class="card-body" style="text-align:center;margin-bottom:1rem">
                        <div style="font-size:3.5rem;font-weight:900;line-height:1">4.8</div>
                        <div style="color:var(--red);font-size:.9rem;letter-spacing:2px;margin:.3rem 0">★★★★★</div>
                        <div style="font-size:.72rem;color:var(--text-3)">Based on 142 reviews</div>
                    </div>
                    <div class="card-body" style="padding-top:0">
                        <div class="rb-row"><span class="rb-lbl">5★</span>
                            <div class="rb-track">
                                <div class="rb-fill" style="width:72%"></div>
                            </div><span class="rb-pct">72%</span>
                        </div>
                        <div class="rb-row"><span class="rb-lbl">4★</span>
                            <div class="rb-track">
                                <div class="rb-fill" style="width:18%"></div>
                            </div><span class="rb-pct">18%</span>
                        </div>
                        <div class="rb-row"><span class="rb-lbl">3★</span>
                            <div class="rb-track">
                                <div class="rb-fill" style="width:6%"></div>
                            </div><span class="rb-pct">6%</span>
                        </div>
                        <div class="rb-row"><span class="rb-lbl">2★</span>
                            <div class="rb-track">
                                <div class="rb-fill" style="width:3%"></div>
                            </div><span class="rb-pct">3%</span>
                        </div>
                        <div class="rb-row"><span class="rb-lbl">1★</span>
                            <div class="rb-track">
                                <div class="rb-fill" style="width:1%"></div>
                            </div><span class="rb-pct">1%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── REPORTS ───────────────────────────── -->
        <div class="page" id="page-reports">
            <div class="sec-head">
                <div class="sec-title">Reports</div>
            </div>
            <div class="grid g-4" style="margin-bottom:1.25rem">
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-users"></i></div>
                    <div class="stat-val">284</div>
                    <div class="stat-lbl">Total Members</div>
                </div>
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-cash"></i></div>
                    <div class="stat-val">₱148k</div>
                    <div class="stat-lbl">This Month</div>
                </div>
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-calendar-stats"></i></div>
                    <div class="stat-val">1,240</div>
                    <div class="stat-lbl">Attendance</div>
                </div>
                <div class="stat">
                    <div class="stat-icon"><i class="ti ti-repeat"></i></div>
                    <div class="stat-val">4.4</div>
                    <div class="stat-lbl">Avg Visits/Member</div>
                </div>
            </div>
            <div class="grid g-2">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">Revenue trend</div>
                    </div>
                    <div class="card-body" id="rev-bars"></div>
                </div>
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">Revenue by plan</div>
                    </div>
                    <div class="card-body">
                        <div class="rev-row"><span class="rev-label">12 mo</span>
                            <div class="rev-track">
                                <div class="rev-fill" style="width:55%"></div>
                            </div><span class="rev-val">₱72,000</span>
                        </div>
                        <div class="rev-row"><span class="rev-label">6 mo</span>
                            <div class="rev-track">
                                <div class="rev-fill" style="width:35%"></div>
                            </div><span class="rev-val">₱42,000</span>
                        </div>
                        <div class="rev-row"><span class="rev-label">3 mo</span>
                            <div class="rev-track">
                                <div class="rev-fill" style="width:22%"></div>
                            </div><span class="rev-val">₱24,000</span>
                        </div>
                        <div class="rev-row"><span class="rev-label">1 mo</span>
                            <div class="rev-track">
                                <div class="rev-fill" style="width:10%"></div>
                            </div><span class="rev-val">₱10,000</span>
                        </div>
                    </div>
                </div>
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
                                <div class="settings-sub">Admin User</div>
                            </div>
                            <button class="btn sm" onclick="toast('info','Edit profile','Coming soon')">Edit</button>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Email</div>
                                <div class="settings-sub">admin@fitsync.com</div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Password</div>
                                <div class="settings-sub">Last changed 30 days ago</div>
                            </div>
                            <button class="btn sm" onclick="toast('info','Change password','Coming soon')">Change</button>
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
                                <div class="settings-sub">5 active branches</div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-lbl">Total Memberships</div>
                                <div class="settings-sub">327 total / 241 active</div>
                            </div>
                        </div>
                        <div class="settings-row" style="border-bottom:none">
                            <div>
                                <div class="settings-lbl">Feedbacks</div>
                                <div class="settings-sub">142 visible reviews</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
        /* ── MOCK DATA ────────────────────────────── */
        const mockMembers = [{
                id: 1,
                fname: 'Maria',
                lname: 'Santos',
                email: 'maria@email.com',
                plan: '6 Months',
                planCls: 'mo6',
                joined: '2026-01-10',
                expiry: '2026-07-10',
                status: 'active',
                payment: 'paid'
            },
            {
                id: 2,
                fname: 'Jose',
                lname: 'Reyes',
                email: 'jose@email.com',
                plan: '12 Months',
                planCls: 'yr',
                joined: '2026-02-01',
                expiry: '2027-02-01',
                status: 'active',
                payment: 'pending'
            },
            {
                id: 3,
                fname: 'Ana',
                lname: 'Cruz',
                email: 'ana@email.com',
                plan: '3 Months',
                planCls: 'mo3',
                joined: '2026-03-15',
                expiry: '2026-06-15',
                status: 'expired',
                payment: 'paid'
            },
            {
                id: 4,
                fname: 'Carlos',
                lname: 'Tan',
                email: 'carlos@email.com',
                plan: '1 Month',
                planCls: 'mo1',
                joined: '2026-05-05',
                expiry: '2026-06-05',
                status: 'active',
                payment: 'paid'
            },
            {
                id: 5,
                fname: 'Liza',
                lname: 'Gomez',
                email: 'liza@email.com',
                plan: '3 Months',
                planCls: 'mo3',
                joined: '2026-03-10',
                expiry: '2026-06-10',
                status: 'active',
                payment: 'paid'
            },
            {
                id: 6,
                fname: 'Mark',
                lname: 'Uy',
                email: 'mark@email.com',
                plan: '6 Months',
                planCls: 'mo6',
                joined: '2025-12-01',
                expiry: '2026-06-01',
                status: 'frozen',
                payment: 'paid'
            },
            {
                id: 7,
                fname: 'Nina',
                lname: 'Bautista',
                email: 'nina@email.com',
                plan: '12 Months',
                planCls: 'yr',
                joined: '2026-01-20',
                expiry: '2027-01-20',
                status: 'active',
                payment: 'paid'
            },
            {
                id: 8,
                fname: 'Leo',
                lname: 'Dizon',
                email: 'leo@email.com',
                plan: '1 Month',
                planCls: 'mo1',
                joined: '2026-04-01',
                expiry: '2026-05-01',
                status: 'expired',
                payment: 'paid'
            },
        ];

        const mockBranches = [{
                name: 'Makati Branch',
                city: 'Makati',
                address: '123 Ayala Ave, Makati City',
                active: true
            },
            {
                name: 'BGC Branch',
                city: 'Taguig',
                address: '32nd St, BGC, Taguig City',
                active: true
            },
            {
                name: 'Ortigas Branch',
                city: 'Pasig',
                address: 'Emerald Ave, Ortigas Center',
                active: true
            },
            {
                name: 'QC Branch',
                city: 'Quezon City',
                address: 'Katipunan Ave, QC',
                active: true
            },
            {
                name: 'Alabang Branch',
                city: 'Muntinlupa',
                address: 'Filinvest Ave, Alabang',
                active: true
            },
        ];

        const mockFeedbacks = [{
                id: 1,
                name: 'Maria Santos',
                rating: 5,
                text: 'Amazing gym! The trainers are professional and the equipment is top-notch. Highly recommended!',
                branch: 'Makati Branch',
                date: '2026-05-28'
            },
            {
                id: 2,
                name: 'Jose Reyes',
                rating: 4,
                text: 'Great facilities and very clean. The staff is friendly and always ready to help. Will definitely renew.',
                branch: 'BGC Branch',
                date: '2026-05-25'
            },
            {
                id: 3,
                name: 'Ana Cruz',
                rating: 5,
                text: 'Best gym experience I\'ve had. Love the class schedules and the community here is so welcoming.',
                branch: 'Ortigas Branch',
                date: '2026-05-20'
            },
        ];

        const signupData = [12, 18, 14, 22, 19, 28, 31, 24, 17, 21, 26, 29];
        const revenueData = [92000, 105000, 88000, 120000, 98000, 140000, 155000, 132000, 110000, 125000, 138000, 148000];
        const months = ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'];

        /* ── RENDER ───────────────────────────────── */
        function init() {
            buildSparkline();
            buildRevBars();
            renderRecentMembers();
            renderPendingList();
            renderMembers();
            renderAttendanceTables();
            renderBranches();
            renderFeedbacks();
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

        function renderRecentMembers() {
            const el = document.getElementById('recent-tbody');
            if (!el) return;
            el.innerHTML = mockMembers.slice(0, 5).map(m => `
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

        function renderPendingList() {
            const el = document.getElementById('pending-list');
            if (!el) return;
            const pending = mockMembers.filter(m => m.payment === 'pending');
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
        <button class="tbtn success" title="Approve" onclick="confirmAction('Approve payment?','Activate ${m.fname}\'s membership.',()=>toast('success','Payment approved','Membership activated.'))"><i class="ti ti-check"></i></button>
        <button class="tbtn danger" title="Reject" onclick="confirmAction('Reject payment?','This will cancel the request.',()=>toast('error','Payment rejected'))"><i class="ti ti-x"></i></button>
      </div>
    </div>
  `).join('');
        }

        function renderMembers() {
            const q = (document.getElementById('search-input')?.value || '').toLowerCase();
            const sf = (document.getElementById('status-filter')?.value || '');
            const el = document.getElementById('members-tbody');
            if (!el) return;
            const data = mockMembers.filter(m => {
                const txt = (m.fname + ' ' + m.lname + ' ' + m.email).toLowerCase();
                return (!q || txt.includes(q)) && (!sf || m.status === sf);
            });
            const lbl = document.getElementById('member-count');
            if (lbl) lbl.textContent = `${data.length} of ${mockMembers.length}`;
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
        <button class="tbtn" title="Freeze" onclick="confirmAction('Freeze membership?','Member will not be able to check in.',()=>toast('info','Membership frozen'))"><i class="ti ti-player-pause"></i></button>
        <button class="tbtn danger" title="Cancel" onclick="confirmAction('Cancel membership?','This will deactivate the member.',()=>toast('error','Membership cancelled'))"><i class="ti ti-ban"></i></button>
      </div></td>
    </tr>
  `).join('');
        }

        function renderAttendanceTables() {
            const at = document.getElementById('att-tbody');
            if (at) at.innerHTML = [
                ['Maria Santos', 'Makati', '9:14 AM'],
                ['Jose Reyes', 'BGC', '9:08 AM'],
                ['Carlos Tan', 'QC', '8:55 AM'],
                ['Liza Gomez', 'Makati', '8:47 AM'],
                ['Nina Bautista', 'Alabang', '8:30 AM'],
            ].map(([n, b, t]) => `<tr><td style="font-weight:600">${n}</td><td>${b}</td><td style="color:var(--text-2)">${t}</td></tr>`).join('');

            const bat = document.getElementById('branch-att-tbody');
            if (bat) bat.innerHTML = [
                ['Makati', 12, 310, 1240],
                ['BGC', 9, 245, 980],
                ['Ortigas', 8, 198, 820],
                ['QC', 11, 260, 1050],
                ['Alabang', 7, 227, 890],
            ].map(([n, t, m, all]) => `<tr><td style="font-weight:600">${n}</td><td>${t}</td><td>${m}</td><td>${all}</td></tr>`).join('');

            const act = document.getElementById('active-tbody');
            if (act) act.innerHTML = [
                ['Maria Santos', 28, 'Jun 4'],
                ['Carlos Tan', 24, 'Jun 4'],
                ['Jose Reyes', 22, 'Jun 3'],
                ['Nina Bautista', 19, 'Jun 3'],
                ['Liza Gomez', 17, 'Jun 2'],
            ].map(([n, v, d]) => `<tr><td style="font-weight:600">${n}</td><td>${v}</td><td style="color:var(--text-2)">${d}</td></tr>`).join('');

            const ict = document.getElementById('inactive-tbody');
            if (ict) ict.innerHTML = [
                ['Ana Cruz', 'May 2'],
                ['Leo Dizon', 'Apr 28'],
                ['Mark Uy', 'Apr 15'],
            ].map(([n, d]) => `<tr><td style="font-weight:600">${n}</td><td style="color:#e05656">${d}</td></tr>`).join('');
        }

        function renderBranches() {
            const el = document.getElementById('branches-grid');
            if (!el) return;
            el.innerHTML = mockBranches.map(b => `
    <div class="branch-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
        <div>
          <div class="branch-name">${b.name}</div>
          <div class="branch-city">${b.city}</div>
        </div>
        <span class="badge ${b.active?'active':'expired'}">${b.active?'Active':'Inactive'}</span>
      </div>
      <div class="branch-addr"><i class="ti ti-map-pin" style="font-size:.8rem;vertical-align:-1px"></i> ${b.address}</div>
    </div>
  `).join('');
        }

        function renderFeedbacks() {
            const el = document.getElementById('fb-list');
            if (!el) return;
            el.innerHTML = mockFeedbacks.map(f => `
    <div class="fb-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:.88rem">${f.name}</div>
          <div class="fb-stars">${'★'.repeat(f.rating)}${'☆'.repeat(5-f.rating)}</div>
        </div>
        <button class="tbtn danger" onclick="confirmAction('Delete feedback?','This cannot be undone.',()=>toast('success','Feedback deleted'))"><i class="ti ti-trash"></i></button>
      </div>
      <div class="fb-text">"${f.text}"</div>
      <div class="fb-meta"><i class="ti ti-map-pin" style="font-size:.75rem"></i> ${f.branch} · ${fmtDate(f.date)}</div>
    </div>
  `).join('');
        }

        /* ── MEMBER DETAIL MODAL ─────────────────── */
        function showMemberModal(id) {
            const m = mockMembers.find(x => x.id === id);
            if (!m) return;
            document.getElementById('modal-title').textContent = `${m.fname} ${m.lname}`;
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
      <div class="detail-cell"><div class="detail-label">Plan</div><div class="detail-val">${m.plan}</div></div>
      <div class="detail-cell"><div class="detail-label">Joined</div><div class="detail-val">${fmtDate(m.joined)}</div></div>
      <div class="detail-cell"><div class="detail-label">Expires</div><div class="detail-val">${fmtDate(m.expiry)}</div></div>
      <div class="detail-cell"><div class="detail-label">Payment</div><div class="detail-val"><span class="badge ${m.payment==='paid'?'paid':'pending'}">${cap(m.payment)}</span></div></div>
      <div class="detail-cell"><div class="detail-label">Membership</div><div class="detail-val"><span class="badge ${m.status}">${cap(m.status)}</span></div></div>
    </div>
  `;
            const foot = document.getElementById('modal-foot');
            const actions = [];
            if (m.payment === 'pending') actions.push(`<button class="btn success-btn sm" onclick="closeModal();confirmAction('Approve payment?','Activate ${m.fname}\\'s membership.',()=>toast('success','Payment approved'))"><i class="ti ti-check"></i> Approve</button>`);
            if (m.status === 'active') actions.push(`<button class="btn sm" onclick="closeModal();confirmAction('Freeze membership?','Member cannot check in while frozen.',()=>toast('info','Membership frozen'))"><i class="ti ti-player-pause"></i> Freeze</button>`);
            actions.push(`<button class="btn sm" onclick="closeModal()">Close</button>`);
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
            closeConfirm();
            if (confirmCallback) confirmCallback();
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
            settings: 'Settings'
        };
        const pageCrumbs = {
            dashboard: 'Overview',
            members: 'Member Management',
            branches: 'Branch Overview',
            schedules: 'Class Management',
            feedbacks: 'Review Feedbacks',
            reports: 'Analytics',
            settings: 'System Settings'
        };

        function showPage(id, btn) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.sb-link').forEach(l => l.classList.remove('active'));
            const pg = document.getElementById('page-' + id);
            if (pg) pg.classList.add('active');
            if (btn) btn.classList.add('active');
            document.getElementById('tb-title').textContent = pageTitles[id] || id;
            document.getElementById('tb-crumb').textContent = pageCrumbs[id] || id;
            closeSidebar();
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
            h.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
        }
        (() => {
            const s = localStorage.getItem('fs-theme');
            if (s) document.documentElement.setAttribute('data-theme', s);
        })();

        /* ── BOOT ────────────────────────────────── */
        init();
    </script>
</body>

</html>