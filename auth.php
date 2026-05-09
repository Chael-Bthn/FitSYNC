auth.php
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — Join or Log In</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204, 26, 26, .28);
            --fs-dark: #0d0d0d;
            --fs-panel: rgba(13, 13, 13, .82);
        }

        [data-bs-theme="light"] {
            --fs-panel: rgba(255, 255, 255, .88);
        }

        * { font-family: 'Outfit', system-ui, sans-serif }
        body { overflow-x: hidden; min-height: 100vh }

        /* ── BRAND ── */
        .fs-red { color: var(--fs-red) !important }
        .btn-fs {
            background: var(--fs-red); border: none;
            color: #fff; font-weight: 700; letter-spacing: .3px
        }
        .btn-fs:hover { background: var(--fs-red-hover); color: #fff }
        .badge-fs {
            background: rgba(204, 26, 26, .15); color: var(--fs-red);
            border: 1px solid rgba(204, 26, 26, .3);
            font-weight: 700; letter-spacing: .5px
        }

        /* ── SPLIT LAYOUT ── */
        .auth-wrap {
            display: flex;
            min-height: 100vh;
        }

        /* LEFT — lifestyle visual panel */
        .auth-visual {
            flex: 0 0 46%;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .auth-visual-bg {
            position: absolute;
            inset: 0;
            background-image: url('gallery/BG Photo.png');
            background-size: cover;
            background-position: center;
            background-color: #1a1a1a;
        }

        .auth-visual-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(to top, rgba(0,0,0,.95) 0%, rgba(0,0,0,.55) 45%, rgba(0,0,0,.2) 100%),
                linear-gradient(120deg, rgba(0,0,0,.45) 0%, transparent 65%);
        }

        .auth-visual-content {
            position: relative;
            z-index: 2;
            padding: 2.5rem;
        }

        .auth-brand {
            position: absolute;
            top: 2rem;
            left: 2.5rem;
            z-index: 3;
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
        }

        .brand-text .fit { font-size: 1.15rem; font-weight: 900; letter-spacing: 1px; color: #fff }
        .brand-text .sync { font-size: 1.15rem; font-weight: 900; color: var(--fs-red); letter-spacing: 1px }

        .auth-quote {
            font-size: clamp(1.6rem, 3vw, 2.5rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 1rem;
        }

        .auth-quote em { font-style: normal; color: var(--fs-red) }

        .auth-tagline {
            font-size: .88rem;
            color: rgba(255,255,255,.55);
            max-width: 320px;
            line-height: 1.8;
        }

        /* Stats row on visual */
        .auth-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.8rem;
            flex-wrap: wrap;
        }

        .auth-stat {
            border-left: 2px solid rgba(255,255,255,.15);
            padding-left: .9rem;
        }

        .auth-stat-num { font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1 }
        .auth-stat-lbl { font-size: .62rem; text-transform: uppercase; letter-spacing: .7px; color: rgba(255,255,255,.4); margin-top: .15rem }

        /* Marquee on visual (bottom strip) */
        .auth-strip {
            background: var(--fs-red);
            overflow: hidden;
            white-space: nowrap;
            padding: .55rem 0;
            position: relative;
            z-index: 3;
        }

        .auth-strip-track {
            display: inline-flex;
            gap: 2.5rem;
            animation: stripScroll 20s linear infinite;
        }

        .auth-strip-track span { font-size: .68rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,.9) }
        .auth-strip-track .dot { color: rgba(255,255,255,.4); font-size: .85rem }

        @keyframes stripScroll {
            from { transform: translateX(0) }
            to { transform: translateX(-50%) }
        }

        /* RIGHT — form panel */
        .auth-form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bs-body-bg);
            position: relative;
            overflow-y: auto;
        }

        .auth-form-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.5rem;
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
        }

        /* Tabs */
        .auth-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--bs-border-color);
            margin-bottom: 2rem;
        }

        .auth-tab {
            flex: 1;
            text-align: center;
            padding: .7rem;
            font-size: .88rem;
            font-weight: 700;
            letter-spacing: .3px;
            cursor: pointer;
            border-bottom: 2.5px solid transparent;
            color: var(--bs-secondary-color);
            transition: color .2s, border-color .2s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }

        .auth-tab.active {
            color: var(--fs-red);
            border-bottom-color: var(--fs-red);
        }

        .auth-tab:hover:not(.active) {
            color: var(--bs-body-color);
        }

        /* Form fields */
        .auth-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--bs-secondary-color);
            margin-bottom: .35rem;
        }

        .auth-input {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-body-color);
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            padding: .65rem 1rem;
            border-radius: 12px;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .auth-input:focus {
            border-color: var(--fs-red);
            box-shadow: 0 0 0 3px var(--fs-red-glow);
        }

        .auth-input::placeholder { color: var(--bs-secondary-color); opacity: .6 }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap .auth-input { padding-left: 2.6rem }

        .input-icon-wrap .ii {
            position: absolute;
            left: .85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bs-secondary-color);
            font-size: 1rem;
            pointer-events: none;
        }

        .input-icon-wrap .ii-right {
            left: auto;
            right: .85rem;
            cursor: pointer;
            pointer-events: all;
        }

        .auth-input-wrap { padding-right: 2.6rem }

        /* Plan selector */
        .plan-select-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .55rem;
        }

        .plan-opt {
            border: 1.5px solid var(--bs-border-color);
            border-radius: 12px;
            padding: .75rem .9rem;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }

        .plan-opt:hover { border-color: rgba(204,26,26,.4) }
        .plan-opt.selected { border-color: var(--fs-red); background: rgba(204,26,26,.06) }

        .plan-opt input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .plan-opt-name {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--fs-red);
        }

        .plan-opt-price {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -.3px;
            margin-top: .1rem;
            color: var(--bs-body-color);
        }

        .plan-opt-save {
            font-size: .62rem;
            color: var(--bs-secondary-color);
        }

        .plan-opt.selected .plan-check {
            display: flex;
        }

        .plan-check {
            display: none;
            position: absolute;
            top: .5rem;
            right: .5rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--fs-red);
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            color: #fff;
        }

        /* Divider */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.25rem 0;
            color: var(--bs-secondary-color);
            font-size: .72rem;
        }

        .auth-divider::before, .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--bs-border-color);
        }

        /* Theme toggle (top-right of panel) */
        .panel-theme-btn {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.05rem;
            transition: all .2s;
        }

        .panel-theme-btn:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
            color: #fff;
        }

        /* Back link */
        .auth-back {
            position: absolute;
            top: 1.25rem;
            left: 1.5rem;
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--bs-secondary-color);
            text-decoration: none;
            transition: color .2s;
        }

        .auth-back:hover { color: var(--fs-red) }

        /* Password strength */
        .pw-strength-bar {
            display: flex;
            gap: 3px;
            margin-top: .4rem;
        }

        .pw-bar-seg {
            flex: 1;
            height: 3px;
            border-radius: 2px;
            background: var(--bs-border-color);
            transition: background .3s;
        }

        .pw-bar-seg.active-weak { background: #e74c3c }
        .pw-bar-seg.active-fair { background: #e67e22 }
        .pw-bar-seg.active-good { background: #2ecc71 }

        .pw-strength-label {
            font-size: .68rem;
            font-weight: 600;
            margin-top: .3rem;
        }

        /* Terms */
        .auth-terms {
            font-size: .72rem;
            color: var(--bs-secondary-color);
            line-height: 1.6;
            margin-top: 1rem;
        }

        .auth-terms a { color: var(--fs-red); text-decoration: none }
        .auth-terms a:hover { text-decoration: underline }

        /* Social login */
        .btn-social {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            font-size: .85rem;
            font-weight: 600;
            padding: .55rem 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all .2s;
            width: 100%;
        }

        .btn-social:hover {
            border-color: rgba(204,26,26,.35);
            background: rgba(204,26,26,.05);
            color: var(--bs-body-color);
        }

        /* Alert */
        .auth-alert {
            border-radius: 10px;
            font-size: .82rem;
            padding: .65rem 1rem;
            margin-bottom: 1rem;
            display: none;
        }

        /* Form panels */
        .form-view { display: none }
        .form-view.active { display: block }

        /* MOBILE: stack vertically */
        @media (max-width: 767.98px) {
            .auth-visual { display: none }

            .auth-form-inner {
                padding: 2rem 1.5rem;
            }

            .auth-back { left: 1rem }
            .panel-theme-btn { right: 1rem }

            .auth-mobile-brand {
                display: flex !important;
            }
        }

        @media (min-width: 768px) {
            .auth-mobile-brand { display: none !important }
        }

        @media (max-width: 400px) {
            .plan-select-grid { grid-template-columns: 1fr 1fr }
            .plan-opt-price { font-size: .9rem }
        }
    </style>
</head>

<body>

<div class="auth-wrap">

    <!-- ── LEFT: LIFESTYLE VISUAL ── -->
    <div class="auth-visual">
        <div class="auth-visual-bg"></div>

        <a class="auth-brand" href="index.php">
            <img src="FitSYNC Emblem.svg" alt="FitSync" width="32" height="32" />
            <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
        </a>

        <div class="auth-visual-content">
            <p class="auth-quote">
                Your next<br><em>chapter</em><br>starts here.
            </p>
            <p class="auth-tagline">
                Join thousands who train smarter, live better, and push past limits — every single day.
            </p>
            <div class="auth-stats">
                <div class="auth-stat">
                    <div class="auth-stat-num">12K+</div>
                    <div class="auth-stat-lbl">Members</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-num">8</div>
                    <div class="auth-stat-lbl">Locations</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-num">50+</div>
                    <div class="auth-stat-lbl">Coaches</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-num">4.9★</div>
                    <div class="auth-stat-lbl">Avg Rating</div>
                </div>
            </div>
        </div>

        <!-- Marquee strip -->
        <div class="auth-strip">
            <div class="auth-strip-track" id="stripTrack"></div>
        </div>
    </div>

    <!-- ── RIGHT: FORM PANEL ── -->
    <div class="auth-form-panel">

        <a class="auth-back" href="index.php">
            <i class="ti ti-arrow-left"></i> Back
        </a>

        <button class="panel-theme-btn" onclick="toggleTheme()" aria-label="Toggle theme">
            <i class="ti ti-sun" id="themeIcon"></i>
        </button>

        <div class="auth-form-inner">

            <!-- Mobile brand (only shows on small screens) -->
            <div class="auth-mobile-brand align-items-center gap-2 mb-4" style="display:none">
                <img src="FitSYNC Emblem.svg" alt="FitSync" width="30" height="30" />
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </div>

            <!-- Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab active" id="tab-login" onclick="switchTab('login')">Log In</button>
                <button class="auth-tab" id="tab-register" onclick="switchTab('register')">Join Free</button>
            </div>

            <!-- Alert box -->
            <div class="alert auth-alert" id="authAlert" role="alert"></div>

            <!-- ══════ LOGIN FORM ══════ -->
            <div class="form-view active" id="view-login">

                <div class="mb-2" style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-.5px;margin-bottom:.25rem">Welcome back.</h2>
                    <p style="font-size:.85rem;color:var(--bs-secondary-color)">Sign in to your FitSync account.</p>
                </div>

                <form id="loginForm" onsubmit="handleLogin(event)">

                    <div class="mb-3">
                        <div class="auth-label">Email</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-mail ii"></i>
                            <input class="auth-input" type="email" id="loginEmail" placeholder="you@example.com" autocomplete="email" required />
                        </div>
                    </div>

                    <div class="mb-1">
                        <div class="auth-label">Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock ii"></i>
                            <input class="auth-input auth-input-wrap" type="password" id="loginPassword" placeholder="••••••••" autocomplete="current-password" required />
                            <i class="ti ti-eye ii ii-right" id="loginPwToggle" onclick="togglePw('loginPassword','loginPwToggle')" title="Show password"></i>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-3">
                        <a href="#" style="font-size:.78rem;color:var(--fs-red);text-decoration:none" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-fs w-100 py-3 rounded-pill fw-bold mb-3" id="loginBtn">
                        <span id="loginBtnText"><i class="ti ti-bolt me-1"></i>Sign In</span>
                        <span id="loginBtnSpinner" class="d-none spinner-border spinner-border-sm" role="status"></span>
                    </button>

                    <div class="auth-divider">or continue with</div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn-social" onclick="socialLogin('google')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                            Google
                        </button>
                        <button type="button" class="btn-social" onclick="socialLogin('facebook')">
                            <i class="ti ti-brand-facebook" style="color:#1877F2;font-size:1.05rem"></i>
                            Facebook
                        </button>
                    </div>

                </form>

                <p class="auth-terms text-center mt-4">
                    Don't have an account? <a href="#" onclick="switchTab('register');return false">Create one free</a>
                </p>

            </div>

            <!-- ══════ REGISTER FORM ══════ -->
            <div class="form-view" id="view-register">

                <div class="mb-2" style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-.5px;margin-bottom:.25rem">Let's get started.</h2>
                    <p style="font-size:.85rem;color:var(--bs-secondary-color)">Create your free account and choose a plan.</p>
                </div>

                <form id="registerForm" onsubmit="handleRegister(event)">

                    <!-- Name row -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="auth-label">First Name</div>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user ii"></i>
                                <input class="auth-input" type="text" id="regFirst" placeholder="Juan" autocomplete="given-name" required />
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="auth-label">Last Name</div>
                            <input class="auth-input" type="text" id="regLast" placeholder="Dela Cruz" autocomplete="family-name" required />
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="auth-label">Email</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-mail ii"></i>
                            <input class="auth-input" type="email" id="regEmail" placeholder="you@example.com" autocomplete="email" required />
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="auth-label">Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock ii"></i>
                            <input class="auth-input auth-input-wrap" type="password" id="regPassword" placeholder="Min. 8 characters" autocomplete="new-password" required oninput="checkStrength(this.value)" />
                            <i class="ti ti-eye ii ii-right" id="regPwToggle" onclick="togglePw('regPassword','regPwToggle')" title="Show password"></i>
                        </div>
                        <div class="pw-strength-bar mt-2">
                            <div class="pw-bar-seg" id="ps1"></div>
                            <div class="pw-bar-seg" id="ps2"></div>
                            <div class="pw-bar-seg" id="ps3"></div>
                            <div class="pw-bar-seg" id="ps4"></div>
                        </div>
                        <div class="pw-strength-label" id="pwLabel" style="color:var(--bs-secondary-color)"></div>
                    </div>

                    <div class="mb-3">
                        <div class="auth-label">Confirm Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock-check ii"></i>
                            <input class="auth-input" type="password" id="regConfirm" placeholder="Repeat password" autocomplete="new-password" required />
                        </div>
                    </div>

                    <!-- Plan picker -->
                    <div class="mb-3">
                        <div class="auth-label mb-2">Choose Your Plan</div>
                        <div class="plan-select-grid">

                            <label class="plan-opt" id="plan-1mo" onclick="selectPlan('1mo')">
                                <input type="radio" name="plan" value="1mo" />
                                <div class="plan-check"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                <div class="plan-opt-name">1 Month</div>
                                <div class="plan-opt-price">₱999</div>
                                <div class="plan-opt-save">Save ₱300</div>
                            </label>

                            <label class="plan-opt" id="plan-3mo" onclick="selectPlan('3mo')">
                                <input type="radio" name="plan" value="3mo" />
                                <div class="plan-check"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                <div class="plan-opt-name">3 Months</div>
                                <div class="plan-opt-price">₱2,699</div>
                                <div class="plan-opt-save">Save ₱1,198</div>
                            </label>

                            <label class="plan-opt selected" id="plan-6mo" onclick="selectPlan('6mo')">
                                <input type="radio" name="plan" value="6mo" checked />
                                <div class="plan-check" style="display:flex"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                <div class="plan-opt-name" style="display:flex;align-items:center;gap:.3rem">
                                    6 Months
                                    <span style="background:var(--fs-red);color:#fff;font-size:.5rem;padding:.1rem .4rem;border-radius:50px;letter-spacing:.3px">HOT</span>
                                </div>
                                <div class="plan-opt-price">₱4,799</div>
                                <div class="plan-opt-save">Save ₱2,995</div>
                            </label>

                            <label class="plan-opt" id="plan-12mo" onclick="selectPlan('12mo')">
                                <input type="radio" name="plan" value="12mo" />
                                <div class="plan-check"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                <div class="plan-opt-name">12 Months</div>
                                <div class="plan-opt-price">₱7,999</div>
                                <div class="plan-opt-save">Save ₱7,589</div>
                            </label>

                        </div>
                    </div>

                    <button type="submit" class="btn btn-fs w-100 py-3 rounded-pill fw-bold" id="regBtn">
                        <span id="regBtnText"><i class="ti ti-bolt me-1"></i>Create Account</span>
                        <span id="regBtnSpinner" class="d-none spinner-border spinner-border-sm" role="status"></span>
                    </button>

                    <p class="auth-terms text-center">
                        By joining you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>. No hidden fees. Cancel anytime.
                    </p>

                </form>

                <p class="auth-terms text-center">
                    Already a member? <a href="#" onclick="switchTab('login');return false">Sign in here</a>
                </p>

            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

/* ── MARQUEE ── */
const slogans = ['Train Hard · Live Well','No Limits · No Excuses','Your Best Self Starts Here','Sweat · Strength · Sync','Built Different','Every Rep Counts'];
const t = document.getElementById('stripTrack');
const doubled = [...slogans, ...slogans];
t.innerHTML = doubled.map(s => `<span>${s}</span><span class="dot">✦</span>`).join('');

/* ── THEME ── */
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
    document.getElementById('themeIcon').className = isDark ? 'ti ti-moon' : 'ti ti-sun';
}

/* ── TABS ── */
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.form-view').forEach(v => v.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('view-' + tab).classList.add('active');
    hideAlert();
}

/* On page load: check URL param ?mode= */
(function () {
    const params = new URLSearchParams(location.search);
    const mode = params.get('mode');
    const plan = params.get('plan');
    if (mode === 'register') {
        switchTab('register');
        if (plan) selectPlan(plan);
    }
})();

/* ── PLAN SELECTOR ── */
let selectedPlan = '6mo';
function selectPlan(plan) {
    document.querySelectorAll('.plan-opt').forEach(el => el.classList.remove('selected'));
    document.getElementById('plan-' + plan).classList.add('selected');
    document.querySelectorAll('.plan-opt input').forEach(r => r.checked = false);
    document.querySelector(`#plan-${plan} input`).checked = true;
    selectedPlan = plan;
}

/* ── PASSWORD TOGGLE ── */
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'ti ti-eye-off ii ii-right';
    } else {
        inp.type = 'password';
        ico.className = 'ti ti-eye ii ii-right';
    }
}

/* ── PASSWORD STRENGTH ── */
function checkStrength(val) {
    const segs = [document.getElementById('ps1'), document.getElementById('ps2'), document.getElementById('ps3'), document.getElementById('ps4')];
    const lbl = document.getElementById('pwLabel');
    segs.forEach(s => { s.className = 'pw-bar-seg' });

    if (!val) { lbl.textContent = ''; return; }

    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const cls = score <= 1 ? 'active-weak' : score <= 2 ? 'active-fair' : 'active-good';
    const labels = { 'active-weak': 'Weak', 'active-fair': 'Fair', 'active-good': score === 4 ? 'Strong 💪' : 'Good' };
    const colors = { 'active-weak': '#e74c3c', 'active-fair': '#e67e22', 'active-good': '#2ecc71' };

    for (let i = 0; i < score; i++) segs[i].classList.add(cls);
    lbl.textContent = labels[cls];
    lbl.style.color = colors[cls];
}

/* ── ALERT ── */
function showAlert(msg, type = 'danger') {
    const a = document.getElementById('authAlert');
    a.className = `alert auth-alert alert-${type}`;
    a.textContent = msg;
    a.style.display = 'block';
}

function hideAlert() {
    const a = document.getElementById('authAlert');
    a.style.display = 'none';
}

/* ── LOADING STATE ── */
function setLoading(btnId, spinnerId, loading) {
    document.getElementById(btnId).style.display = loading ? 'none' : '';
    document.getElementById(spinnerId).classList.toggle('d-none', !loading);
}

/* ══════════════════════════════════════
   FRONTEND-ONLY DEMO LOGIC
   Replace these functions with real fetch() calls to auth.php
══════════════════════════════════════ */

/* Demo users (replace with real DB check) */
const DEMO_USERS = [
    { email: 'admin@fitsync.com', password: 'Admin123!', role: 'admin' },
    { email: 'member@fitsync.com', password: 'Member123!', role: 'member' },
];

function handleLogin(e) {
    e.preventDefault();
    hideAlert();
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;

    setLoading('loginBtnText', 'loginBtnSpinner', true);

    /* Simulate API delay */
    setTimeout(() => {
        setLoading('loginBtnText', 'loginBtnSpinner', false);

        const user = DEMO_USERS.find(u => u.email === email && u.password === password);

        if (!user) {
            showAlert('Invalid email or password. Try: member@fitsync.com / Member123!');
            return;
        }

        showAlert('Login successful! Redirecting…', 'success');

        setTimeout(() => {
            /* Redirect based on role */
            if (user.role === 'admin') {
                window.location.href = 'admin.php';
            } else {
                window.location.href = 'profile.php';
            }
        }, 1000);

    }, 1200);
}

function handleRegister(e) {
    e.preventDefault();
    hideAlert();

    const first    = document.getElementById('regFirst').value.trim();
    const last     = document.getElementById('regLast').value.trim();
    const email    = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirm  = document.getElementById('regConfirm').value;

    if (password !== confirm) {
        showAlert('Passwords do not match.');
        return;
    }
    if (password.length < 8) {
        showAlert('Password must be at least 8 characters.');
        return;
    }

    setLoading('regBtnText', 'regBtnSpinner', true);

    /* Simulate API delay */
    setTimeout(() => {
        setLoading('regBtnText', 'regBtnSpinner', false);
        showAlert(`Welcome, ${first}! Your ${selectedPlan} plan is ready. Redirecting to your profile…`, 'success');
        setTimeout(() => { window.location.href = 'profile.php'; }, 1400);
    }, 1400);
}

function socialLogin(provider) {
    showAlert(`${provider.charAt(0).toUpperCase() + provider.slice(1)} OAuth coming soon!`, 'info');
}

</script>
</body>
</html>