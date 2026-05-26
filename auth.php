<?php
// ============================================================
//  FitSync — Auth Page  (auth.php)
// ============================================================
session_start();

if (!empty($_SESSION['user_role'])) {
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'profile.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
// Load public branches for branch selection during registration
require_once __DIR__ . '/config/db.php';
$pdo = db();
$publicBranches = $pdo->query('SELECT id, name, city FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — Join or Log In</title>
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
            --fs-dark: #0d0d0d;
            --fs-panel: rgba(13, 13, 13, .82);
        }
        [data-bs-theme="light"] { --fs-panel: rgba(255,255,255,.88) }

        * { font-family: 'Outfit', system-ui, sans-serif }
        html, body { height: 100%; overflow: hidden }

        .fs-red { color: var(--fs-red) !important }
        .btn-fs { background: var(--fs-red); border: none; color: #fff; font-weight: 700; letter-spacing: .3px }
        .btn-fs:hover { background: var(--fs-red-hover); color: #fff }
        .badge-fs { background: rgba(204,26,26,.15); color: var(--fs-red); border: 1px solid rgba(204,26,26,.3); font-weight: 700; letter-spacing: .5px }

        /* ── SPLIT LAYOUT ── */
        .auth-wrap { display: flex; height: 100vh; overflow: hidden }

        .auth-visual { flex: 0 0 46%; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end }
        .auth-visual-bg { position: absolute; inset: 0; background-image: url('assets/Counter.png'); background-size: cover; background-position: center; background-color: #1a1a1a }
        .auth-visual-bg::after { content: ''; position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.95) 0%, rgba(0,0,0,.55) 45%, rgba(0,0,0,.2) 100%),
                        linear-gradient(120deg, rgba(0,0,0,.45) 0%, transparent 65%) }
        .auth-visual-content { position: relative; z-index: 2; padding: 2.5rem }

        .auth-brand { position: absolute; top: 2rem; left: 2.5rem; z-index: 3; display: flex; align-items: center; gap: .6rem; text-decoration: none }
        .brand-text .fit  { font-size: 1.15rem; font-weight: 900; letter-spacing: 1px; color: #fff }
        .brand-text .sync { font-size: 1.15rem; font-weight: 900; color: var(--fs-red); letter-spacing: 1px }
        [data-bs-theme="light"] .auth-form-panel .brand-text .fit { color: var(--bs-body-color) }

        .auth-quote { font-size: clamp(1.6rem,3vw,2.5rem); font-weight: 800; color: #fff; line-height: 1.1; letter-spacing: -1px; margin-bottom: 1rem }
        .auth-quote em { font-style: normal; color: var(--fs-red) }
        .auth-tagline { font-size: .88rem; color: rgba(255,255,255,.55); max-width: 320px; line-height: 1.8 }

        .auth-stats { display: flex; gap: 1.5rem; margin-top: 1.8rem; flex-wrap: wrap }
        .auth-stat { border-left: 2px solid rgba(255,255,255,.15); padding-left: .9rem }
        .auth-stat-num { font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1 }
        .auth-stat-lbl { font-size: .62rem; text-transform: uppercase; letter-spacing: .7px; color: rgba(255,255,255,.4); margin-top: .15rem }

        /* ── FORM PANEL ── */
        .auth-form-panel { flex: 1; display: flex; flex-direction: column; background: var(--bs-body-bg); position: relative; overflow-y: auto }
        .auth-form-inner { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 3rem 2.5rem; max-width: 500px; margin: 0 auto; width: 100% }

        /* Tabs */
        .auth-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--bs-border-color); margin-bottom: 2rem }
        .auth-tab { flex: 1; text-align: center; padding: .7rem; font-size: .88rem; font-weight: 700; letter-spacing: .3px; cursor: pointer; border-bottom: 2.5px solid transparent; color: var(--bs-secondary-color); transition: color .2s, border-color .2s; background: none; border-top: none; border-left: none; border-right: none }
        .auth-tab.active { color: var(--fs-red); border-bottom-color: var(--fs-red) }
        .auth-tab:hover:not(.active) { color: var(--bs-body-color) }

        /* Fields */
        .auth-label { font-size: .72rem; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--bs-secondary-color); margin-bottom: .35rem }
        .auth-input { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); color: var(--bs-body-color); font-family: 'Outfit', sans-serif; font-size: .9rem; padding: .65rem 1rem; border-radius: 12px; width: 100%; transition: border-color .2s, box-shadow .2s; outline: none }
        .auth-input:focus { border-color: var(--fs-red); box-shadow: 0 0 0 3px var(--fs-red-glow) }
        .auth-input::placeholder { color: var(--bs-secondary-color); opacity: .6 }

        .input-icon-wrap { position: relative }
        .input-icon-wrap .auth-input { padding-left: 2.6rem }
        .input-icon-wrap .ii { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--bs-secondary-color); font-size: 1rem; pointer-events: none }
        .input-icon-wrap .ii-right { left: auto; right: .85rem; cursor: pointer; pointer-events: all }
        .auth-input-wrap { padding-right: 2.6rem }

        /* Plan selector */
        .plan-select-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem }
        .plan-opt { border: 1.5px solid var(--bs-border-color); border-radius: 12px; padding: .75rem .9rem; cursor: pointer; transition: border-color .2s, background .2s; position: relative }
        .plan-opt:hover { border-color: rgba(204,26,26,.4) }
        .plan-opt.selected { border-color: var(--fs-red); background: rgba(204,26,26,.06) }
        .plan-opt input[type="radio"] { position: absolute; opacity: 0; pointer-events: none }
        .plan-opt-name  { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--fs-red) }
        .plan-opt-price { font-size: 1.05rem; font-weight: 800; letter-spacing: -.3px; margin-top: .1rem; color: var(--bs-body-color) }
        .plan-opt-save  { font-size: .62rem; color: var(--bs-secondary-color) }
        .plan-opt.selected .plan-check { display: flex }
        .plan-check { display: none; position: absolute; top: .5rem; right: .5rem; width: 16px; height: 16px; border-radius: 50%; background: var(--fs-red); align-items: center; justify-content: center; font-size: .6rem; color: #fff }



        /* Auth divider */
        .auth-divider { display: flex; align-items: center; gap: .75rem; margin: 1.25rem 0; color: var(--bs-secondary-color); font-size: .72rem }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: var(--bs-border-color) }

        /* Nav bar */
        .auth-panel-nav { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem .5rem; background: var(--bs-body-bg); pointer-events: none }
        .auth-panel-nav > * { pointer-events: all }

        .panel-theme-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--bs-border-color); background: var(--bs-secondary-bg); color: var(--bs-secondary-color); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.05rem; transition: all .2s }
        .panel-theme-btn:hover { background: var(--fs-red); border-color: var(--fs-red); color: #fff }

        .auth-back { display: flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); text-decoration: none; transition: color .2s }
        .auth-back:hover { color: var(--fs-red) }

        /* Password strength */
        .pw-strength-bar { display: flex; gap: 3px; margin-top: .4rem }
        .pw-bar-seg { flex: 1; height: 3px; border-radius: 2px; background: var(--bs-border-color); transition: background .3s }
        .pw-bar-seg.active-weak { background: #e74c3c }
        .pw-bar-seg.active-fair { background: #e67e22 }
        .pw-bar-seg.active-good { background: #2ecc71 }
        .pw-strength-label { font-size: .68rem; font-weight: 600; margin-top: .3rem }

        /* Terms */
        .auth-terms { font-size: .72rem; color: var(--bs-secondary-color); line-height: 1.6; margin-top: 1rem }
        .auth-terms a { color: var(--fs-red); text-decoration: none }
        .auth-terms a:hover { text-decoration: underline }

        /* Alert */
        .auth-alert { border-radius: 10px; font-size: .82rem; padding: .65rem 1rem; margin-bottom: 1rem; display: none }
        .form-view { display: none }
        .form-view.active { display: block }

        /* Select styling to match auth-input */
        .auth-select {
            background: var(--bs-secondary-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-body-color);
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            padding: .65rem 2.4rem .65rem 1rem;
            border-radius: 12px;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
        }
        .auth-select:focus { border-color: var(--fs-red); box-shadow: 0 0 0 3px var(--fs-red-glow) }
        [data-bs-theme="dark"] .auth-select option { background: #1a1a1a }

        @media (max-width: 767.98px) {
            .auth-visual { display: none }
            .auth-form-inner { padding: 2rem 1.5rem }
            .auth-mobile-brand { display: flex !important }
        }
        @media (min-width: 768px) {
            .auth-mobile-brand { display: none !important }
        }
    </style>
</head>

<body>

<div class="auth-wrap">

    <!-- ── LEFT VISUAL ── -->
    <div class="auth-visual">
        <div class="auth-visual-bg"></div>
        <a class="auth-brand" href="index.php">
            <img class="theme-logo" src="assets/FitSYNC%20Emblem%20Light.svg"
                 data-logo-dark="assets/FitSYNC%20Emblem%20Light.svg"
                 data-logo-light="assets/FitSYNC%20Emblem.svg"
                 alt="FitSync" width="32" height="32" />
            <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
        </a>
        <div class="auth-visual-content">
            <p class="auth-quote">Your next<br><em>chapter</em><br>starts here.</p>
            <p class="auth-tagline">Join thousands who train smarter, live better, and push past limits — every single day.</p>
            <div class="auth-stats">
                <div class="auth-stat"><div class="auth-stat-num">12K+</div><div class="auth-stat-lbl">Members</div></div>
                <div class="auth-stat"><div class="auth-stat-num">8</div><div class="auth-stat-lbl">Locations</div></div>
                <div class="auth-stat"><div class="auth-stat-num">50+</div><div class="auth-stat-lbl">Coaches</div></div>
                <div class="auth-stat"><div class="auth-stat-num">4.9★</div><div class="auth-stat-lbl">Avg Rating</div></div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT FORM PANEL ── -->
    <div class="auth-form-panel">

        <div class="auth-panel-nav">
            <a class="auth-back" href="index.php"><i class="ti ti-arrow-left"></i> Back</a>
            <button class="panel-theme-btn" onclick="toggleTheme()" aria-label="Toggle theme">
                <i class="ti ti-sun" id="themeIcon"></i>
            </button>
        </div>

        <div class="auth-form-inner">

            <!-- Mobile brand -->
            <div class="auth-mobile-brand align-items-center gap-2 mb-4" style="display:none">
                <img class="theme-logo" src="assets/FitSYNC%20Emblem%20Light.svg"
                     data-logo-dark="assets/FitSYNC%20Emblem%20Light.svg"
                     data-logo-light="assets/FitSYNC%20Emblem.svg"
                     alt="FitSync" width="30" height="30" />
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </div>

            <!-- Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab active" id="tab-login"    onclick="switchTab('login')">Log In</button>
                <button class="auth-tab"         id="tab-register" onclick="switchTab('register')">Register</button>
            </div>

            <!-- Alert -->
            <div class="alert auth-alert" id="authAlert" role="alert"></div>

            <!-- ══ LOGIN ══ -->
            <div class="form-view active" id="view-login">
                <div class="mb-2" style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-.5px;margin-bottom:.25rem">Welcome back.</h2>
                    <p style="font-size:.85rem;color:var(--bs-secondary-color)">Sign in to your FitSync account.</p>
                </div>

                <div id="loginForm">
                    <div class="mb-3">
                        <div class="auth-label">Email</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-mail ii"></i>
                            <input class="auth-input" type="email" id="loginEmail"
                                   placeholder="you@example.com" autocomplete="email" />
                        </div>
                    </div>
                    <div class="mb-1">
                        <div class="auth-label">Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock ii"></i>
                            <input class="auth-input auth-input-wrap" type="password" id="loginPassword"
                                   placeholder="••••••••" autocomplete="current-password" />
                            <i class="ti ti-eye ii ii-right" id="loginPwToggle"
                               onclick="togglePw('loginPassword','loginPwToggle')"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mb-3">
                        <a href="#" style="font-size:.78rem;color:var(--fs-red);text-decoration:none">Forgot password?</a>
                    </div>
                    <button type="button" class="btn btn-fs w-100 py-3 rounded-pill fw-bold mb-3"
                            id="loginBtn" onclick="handleLogin()">
                        <span id="loginBtnText"><i class="ti ti-bolt me-1"></i>Sign In</span>
                        <span id="loginBtnSpinner" class="d-none spinner-border spinner-border-sm" role="status"></span>
                    </button>


                </div>

                <p class="auth-terms text-center mt-3">
                    Don't have an account? <a href="#" onclick="switchTab('register');return false">Create one free</a>
                </p>
            </div>

            <!-- ══ REGISTER ══ -->
            <div class="form-view" id="view-register">
                <div class="mb-2" style="margin-bottom:1.25rem">
                    <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-.5px;margin-bottom:.25rem">Let's get started.</h2>
                    <p style="font-size:.85rem;color:var(--bs-secondary-color)">Create your free account and choose a plan.</p>
                </div>

                <div id="registerForm">

                    <!-- Name row -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="auth-label">First Name</div>
                            <div class="input-icon-wrap">
                                <i class="ti ti-user ii"></i>
                                <input class="auth-input" type="text" id="regFirst" placeholder="Juan" autocomplete="given-name" />
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="auth-label">Last Name</div>
                            <input class="auth-input" type="text" id="regLast" placeholder="Dela Cruz" autocomplete="family-name" />
                        </div>
                    </div>

                    <!-- Gender -->
                    <div class="mb-3">
                        <div class="auth-label">Gender</div>
                        <select class="auth-select" id="regGender">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="nonbinary">Non-binary</option>
                            <option value="other">Prefer not to say</option>
                        </select>
                    </div>

                    <!-- Birthdate -->
                    <div class="mb-3">
                        <div class="auth-label">Birthdate</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-calendar ii"></i>
                            <input class="auth-input" type="date" id="regBirthdate"
                                   max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
                                   autocomplete="bday" />
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <div class="auth-label">Email</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-mail ii"></i>
                            <input class="auth-input" type="email" id="regEmail" placeholder="you@example.com" autocomplete="email" />
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-2">
                        <div class="auth-label">Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock ii"></i>
                            <input class="auth-input auth-input-wrap" type="password" id="regPassword"
                                   placeholder="Min. 8 characters" autocomplete="new-password"
                                   oninput="checkStrength(this.value)" />
                            <i class="ti ti-eye ii ii-right" id="regPwToggle"
                               onclick="togglePw('regPassword','regPwToggle')"></i>
                        </div>
                        <div class="pw-strength-bar mt-2">
                            <div class="pw-bar-seg" id="ps1"></div>
                            <div class="pw-bar-seg" id="ps2"></div>
                            <div class="pw-bar-seg" id="ps3"></div>
                            <div class="pw-bar-seg" id="ps4"></div>
                        </div>
                        <div class="pw-strength-label" id="pwLabel" style="color:var(--bs-secondary-color)"></div>
                    </div>

                    <!-- Confirm password -->
                    <div class="mb-3">
                        <div class="auth-label">Confirm Password</div>
                        <div class="input-icon-wrap">
                            <i class="ti ti-lock-check ii"></i>
                            <input class="auth-input" type="password" id="regConfirm"
                                   placeholder="Repeat password" autocomplete="new-password" />
                        </div>
                    </div>

                    <!-- ── Member-only section (plan + payment) ── -->
                    <div id="memberSection">

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
                                    <div class="plan-check"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                    <div class="plan-opt-name" style="display:flex;align-items:center;gap:.3rem">
                                        6 Months
                                        <span style="background:var(--fs-red);color:#fff;font-size:.5rem;padding:.1rem .4rem;border-radius:50px">HOT</span>
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

                        <!-- Branch selection -->
                        <div class="mb-3">
                            <div class="auth-label">Branch</div>
                            <select class="auth-select" id="regBranch">
                                <?php foreach ($publicBranches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name'] . ' - ' . $b['city']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>

                        <!-- Payment method -->
                        <div class="mb-3">
                            <div class="auth-label">Payment Method</div>
                            <div class="input-icon-wrap">
                                <i class="ti ti-credit-card ii"></i>
                                <select class="auth-select" id="regPayment" style="padding-left:2.6rem">
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash" selected>Cash / Walk-in</option>
                                </select>
                            </div>
                        </div>

                    </div><!-- /#memberSection -->

                    <!-- Submit -->
                    <button type="button" class="btn btn-fs w-100 py-3 rounded-pill fw-bold"
                            id="regBtn" onclick="handleRegister()">
                        <span id="regBtnText"><i class="ti ti-bolt me-1"></i>Create Account</span>
                        <span id="regBtnSpinner" class="d-none spinner-border spinner-border-sm" role="status"></span>
                    </button>

                    <p class="auth-terms text-center">
                        By joining you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                        No hidden fees. Cancel anytime.
                    </p>
                </div>

                <p class="auth-terms text-center">
                    Already a member? <a href="#" onclick="switchTab('login');return false">Sign in here</a>
                </p>
            </div>

        </div><!-- /auth-form-inner -->
    </div><!-- /auth-form-panel -->

</div><!-- /auth-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── CSRF token ────────────────────────────────────────────
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

// ── THEME ─────────────────────────────────────────────────
function updateThemeLogos() {
    const isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
    document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
        logo.setAttribute('src', isLight ? logo.dataset.logoLight : logo.dataset.logoDark);
    });
}
function toggleTheme() {
    const html  = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
    document.getElementById('themeIcon').className = isDark ? 'ti ti-moon' : 'ti ti-sun';
    updateThemeLogos();
}
(function () {
    const saved = localStorage.getItem('fs-theme');
    if (saved) {
        document.documentElement.setAttribute('data-bs-theme', saved);
        document.getElementById('themeIcon').className = saved === 'light' ? 'ti ti-moon' : 'ti ti-sun';
    }
    updateThemeLogos();
})();

// ── TABS ──────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.form-view').forEach(v => v.classList.remove('active'));
    document.getElementById('tab-'  + tab).classList.add('active');
    document.getElementById('view-' + tab).classList.add('active');
    hideAlert();
    document.querySelector('.auth-form-panel').scrollTop = 0;
}
(function () {
    const params = new URLSearchParams(location.search);
    if (params.get('mode') === 'register') {
        switchTab('register');
        const plan = params.get('plan');
        if (plan) selectPlan(plan);
    }
})();

// ── PLAN SELECTOR ─────────────────────────────────────────
let selectedPlan = '6mo';
function selectPlan(plan) {
    document.querySelectorAll('.plan-opt').forEach(el => el.classList.remove('selected'));
    document.getElementById('plan-' + plan)?.classList.add('selected');
    document.querySelectorAll('.plan-opt input').forEach(r => r.checked = false);
    const radio = document.querySelector(`#plan-${plan} input`);
    if (radio) radio.checked = true;
    selectedPlan = plan;
}

// ── PASSWORD TOGGLE ───────────────────────────────────────
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'text' ? 'ti ti-eye-off ii ii-right' : 'ti ti-eye ii ii-right';
}

// ── PASSWORD STRENGTH ─────────────────────────────────────
function checkStrength(val) {
    const segs = ['ps1','ps2','ps3','ps4'].map(id => document.getElementById(id));
    const lbl  = document.getElementById('pwLabel');
    segs.forEach(s => s.className = 'pw-bar-seg');
    if (!val) { lbl.textContent = ''; return; }
    let score = 0;
    if (val.length >= 8)          score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const cls    = score <= 1 ? 'active-weak' : score <= 2 ? 'active-fair' : 'active-good';
    const labels = { 'active-weak': 'Weak', 'active-fair': 'Fair', 'active-good': score === 4 ? 'Strong 💪' : 'Good' };
    const colors = { 'active-weak': '#e74c3c', 'active-fair': '#e67e22', 'active-good': '#2ecc71' };
    for (let i = 0; i < score; i++) segs[i].classList.add(cls);
    lbl.textContent = labels[cls];
    lbl.style.color = colors[cls];
}

// ── ALERT ─────────────────────────────────────────────────
function showAlert(msg, type = 'danger') {
    const a = document.getElementById('authAlert');
    a.className = `alert auth-alert alert-${type}`;
    a.textContent = msg;
    a.style.display = 'block';
}
function hideAlert() { document.getElementById('authAlert').style.display = 'none' }

// ── LOADING STATE ─────────────────────────────────────────
function setLoading(textId, spinnerId, loading) {
    document.getElementById(textId).style.display = loading ? 'none' : '';
    document.getElementById(spinnerId).classList.toggle('d-none', !loading);
}

// ── API CALL ──────────────────────────────────────────────
async function authRequest(payload) {
    const res = await fetch('handlers/auth_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, csrf_token: CSRF_TOKEN }),
    });
    if (!res.ok) throw new Error('Server error ' + res.status);
    return res.json();
}

// ── LOGIN ─────────────────────────────────────────────────
async function handleLogin() {
    hideAlert();
    const email    = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    if (!email || !password) { showAlert('Please enter your email and password.'); return }

    setLoading('loginBtnText', 'loginBtnSpinner', true);
    try {
        const data = await authRequest({ action: 'login', email, password });
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => { window.location.href = data.redirect }, 900);
        } else {
            showAlert(data.message);
        }
    } catch {
        showAlert('Connection error. Please try again.');
    } finally {
        setLoading('loginBtnText', 'loginBtnSpinner', false);
    }
}

// ── REGISTER ──────────────────────────────────────────────
async function handleRegister() {
    hideAlert();
    const first       = document.getElementById('regFirst').value.trim();
    const last        = document.getElementById('regLast').value.trim();
    const birthdate   = document.getElementById('regBirthdate').value;
    const email       = document.getElementById('regEmail').value.trim();
    const password    = document.getElementById('regPassword').value;
    const confirm     = document.getElementById('regConfirm').value;

    // Client-side validation
    if (!first || !last)      { showAlert('Please enter your full name.'); return }
    if (!email)               { showAlert('Please enter your email.'); return }
    if (password.length < 8)  { showAlert('Password must be at least 8 characters.'); return }
    if (password !== confirm)  { showAlert('Passwords do not match.'); return }

    const gender = document.getElementById('regGender').value;
    if (!gender) { showAlert('Please select your gender.'); return }

    const payload = {
        action:         'register',
        first_name:     first,
        last_name:      last,
        birthdate,
        email,
        password,
        confirm,
        gender,
        plan:           selectedPlan,
        branch_id:      parseInt(document.getElementById('regBranch').value, 10),
        payment_method: document.getElementById('regPayment').value,
    };

    setLoading('regBtnText', 'regBtnSpinner', true);
    try {
        const data = await authRequest(payload);
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => { window.location.href = data.redirect }, 1100);
        } else {
            showAlert(data.message);
        }
    } catch {
        showAlert('Connection error. Please try again.');
    } finally {
        setLoading('regBtnText', 'regBtnSpinner', false);
    }
}

// ── ENTER KEY ─────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    const loginActive = document.getElementById('view-login').classList.contains('active');
    if (loginActive) handleLogin(); else handleRegister();
});
</script>
</body>
</html>
