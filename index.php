<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — Elevate Your Performance</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204, 26, 26, .25);
            --fs-hero-grid: rgba(255, 255, 255, .04);
        }

        [data-bs-theme="light"] {
            --fs-hero-grid: rgba(0, 0, 0, .06);
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow-x: hidden;
        }

        /* ── BRAND ── */
        .fs-red {
            color: var(--fs-red) !important;
        }

        .btn-fs {
            background: var(--fs-red);
            border: none;
            color: #fff;
            font-weight: 700;
        }

        .btn-fs:hover,
        .btn-fs:focus {
            background: var(--fs-red-hover);
            color: #fff;
        }

        .btn-fs-outline {
            border: 1px solid var(--fs-red);
            color: var(--fs-red);
            background: transparent;
            font-weight: 600;
        }

        .btn-fs-outline:hover {
            background: var(--fs-red);
            color: #fff;
        }

        .badge-fs {
            background: rgba(204, 26, 26, .15);
            color: var(--fs-red);
            border: 1px solid rgba(204, 26, 26, .3);
            font-weight: 700;
            letter-spacing: .5px;
        }

        /* ── NAVBAR ── */
        .navbar {
            border-bottom: 1px solid var(--bs-border-color);
            backdrop-filter: blur(10px);
        }

        [data-bs-theme="dark"] .navbar {
            background: rgba(17, 17, 17, .92) !important;
        }

        [data-bs-theme="light"] .navbar {
            background: rgba(255, 255, 255, .92) !important;
        }

        .nav-logo-svg {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
        }

        .brand-text .fit {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .5px;
        }

        .brand-text .sync {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--fs-red);
            letter-spacing: .5px;
        }

        .nav-link {
            font-size: .88rem;
            font-weight: 500;
            transition: color .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--fs-red) !important;
        }

        /* ── THEME TOGGLE ── */
        .theme-toggle {
            width: 52px;
            height: 28px;
            border-radius: 50px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            position: relative;
            cursor: pointer;
            transition: background .3s, border-color .3s;
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
        }

        .theme-toggle .tog-icon-light {
            right: 7px;
            opacity: .4;
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-dark {
            opacity: .4;
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-light {
            opacity: 1;
        }

        /* ── HERO ── */
        #home {
            min-height: 100vh;
            padding-top: 72px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-bg-glow {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 60% 40%, var(--fs-red-glow) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-grid-lines {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(var(--fs-hero-grid) 1px, transparent 1px), linear-gradient(90deg, var(--fs-hero-grid) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
        }

        .hero-title {
            font-size: clamp(2.2rem, 5.5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.1;
        }

        .hero-stat-num {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--fs-red);
        }

        .hero-stat-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--bs-secondary-color);
        }

        /* ── SECTION HEADERS ── */
        .section-tag {
            display: inline-block;
            padding: .2rem .85rem;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .section-title {
            font-size: clamp(1.6rem, 4vw, 2.3rem);
            font-weight: 800;
        }

        /* ── GALLERY ── */
        #gallery {
            background: var(--bs-secondary-bg);
        }

        .gcard {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 4/3;
            cursor: pointer;
        }

        .gcard-fake {
            width: 100%;
            height: 100%;
            background: var(--bs-tertiary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            transition: transform .4s;
        }

        .gcard:hover .gcard-fake {
            transform: scale(1.06);
        }

        .gcard-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, .78) 0%, transparent 55%);
            opacity: 0;
            transition: opacity .3s;
        }

        .gcard:hover .gcard-overlay {
            opacity: 1;
        }

        .gcard-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            opacity: 0;
            transform: translateY(6px);
            transition: all .3s;
        }

        .gcard:hover .gcard-info {
            opacity: 1;
            transform: translateY(0);
        }

        /* ── PLANS ── */
        .plan-card {
            border-radius: 16px;
            padding: 1.75rem 1.5rem;
            position: relative;
            transition: transform .2s, border-color .2s;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            border-color: rgba(204, 26, 26, .4) !important;
        }

        .plan-card.popular {
            border-color: var(--fs-red) !important;
            border-width: 2px !important;
        }

        .plan-popular-badge {
            position: absolute;
            top: -13px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--fs-red);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            padding: .2rem .9rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        .plan-price {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .plan-price sup {
            font-size: 1rem;
            vertical-align: super;
            font-weight: 600;
        }

        .plan-price sub {
            font-size: .82rem;
            font-weight: 400;
            color: var(--bs-secondary-color);
        }

        .plan-orig {
            font-size: .8rem;
            text-decoration: line-through;
            color: var(--bs-secondary-color);
        }

        .plan-feature {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .84rem;
            color: var(--bs-secondary-color);
        }

        .plan-feature.yes {
            color: var(--bs-body-color);
        }

        .plan-feature i.yes {
            color: var(--fs-red);
        }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--bs-border-color);
        }

        .soc-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1rem;
            transition: background .2s, color .2s, border-color .2s;
        }

        .soc-btn:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
            color: #fff;
        }

        .footer-link {
            color: var(--bs-secondary-color);
            text-decoration: none;
            font-size: .85rem;
            transition: color .2s;
        }

        .footer-link:hover {
            color: var(--fs-red);
        }

        .plugin-badge {
            background: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-secondary-color);
            font-size: .7rem;
            padding: .2rem .6rem;
            border-radius: 5px;
        }

        /* ── FAB ── */
        .fab-wrap {
            position: fixed;
            bottom: 1.75rem;
            right: 1.75rem;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: .55rem;
        }

        .fab-menu {
            display: flex;
            flex-direction: column;
            gap: .45rem;
            align-items: flex-end;
            transition: opacity .25s, transform .25s;
        }

        .fab-menu.d-none-anim {
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
        }

        .fab-item {
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        .fab-label {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-body-color);
            font-size: .76rem;
            padding: .22rem .65rem;
            border-radius: 6px;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
        }

        .fab-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        .fab-main {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--fs-red);
            border: none;
            color: #fff;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 18px var(--fs-red-glow);
            transition: background .2s, transform .25s;
        }

        .fab-main:hover {
            background: var(--fs-red-hover);
        }

        .fab-main.open {
            transform: rotate(45deg);
        }
    </style>
</head>

<body>

    <!-- ════════════════ NAVBAR ════════════════ -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="#home">
                <svg class="nav-logo-svg" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="#cc1a1a" stroke-width="7" />
                    <path d="M60 6 A54 54 0 1 1 14 77" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round" opacity=".15" />
                    <path d="M60 6 A54 54 0 0 1 106 77" fill="none" stroke="#cc1a1a" stroke-width="7" stroke-linecap="round" />
                    <polygon points="109,63 99,81 119,81" fill="#cc1a1a" />
                    <rect x="27" y="52" width="11" height="16" rx="3" fill="currentColor" />
                    <rect x="82" y="52" width="11" height="16" rx="3" fill="currentColor" />
                    <rect x="19" y="55" width="11" height="10" rx="2" fill="currentColor" />
                    <rect x="90" y="55" width="11" height="10" rx="2" fill="currentColor" />
                    <rect x="38" y="55" width="44" height="10" rx="3" fill="currentColor" />
                </svg>
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="ti ti-menu-2 fs-5"></i>
            </button>

            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav mx-auto gap-1">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#plans">Plans</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <!-- THEME TOGGLE -->
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme">
                        <i class="ti ti-moon tog-icon-dark text-body-secondary"></i>
                        <i class="ti ti-sun tog-icon-light text-warning"></i>
                        <span class="tog-knob"><i class="ti ti-moon" id="knob-icon"></i></span>
                    </button>
                    <a href="auth.php?mode=login" class="btn btn-sm btn-outline-secondary px-3">Log In</a>
                    <a href="auth.php?mode=register" class="btn btn-sm btn-fs px-3">Join Free</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ════════════════ HERO ════════════════ -->
    <section id="home">
        <div class="hero-bg-glow"></div>
        <div class="hero-grid-lines"></div>
        <div class="container position-relative py-5">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6">
                    <span class="badge badge-fs rounded-pill mb-3 fs-6 px-3 py-2">
                        <i class="ti ti-flame me-1"></i>#1 Gym Network in the City
                    </span>
                    <h1 class="hero-title mb-3">
                        Push Past Your <span class="fs-red">Limits.</span><br>
                        Sync Your <span class="fs-red">Progress.</span>
                    </h1>
                    <p class="text-secondary mb-4" style="font-size:1.05rem;line-height:1.75;">
                        Train smarter with world-class equipment, expert coaches, and a community that keeps you accountable — every rep, every day.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-5">
                        <a href="auth.php?mode=register" class="btn btn-fs btn-lg px-4">
                            <i class="ti ti-bolt me-1"></i>Join Now
                        </a>
                        <a href="#plans" class="btn btn-fs-outline btn-lg px-4">View Plans</a>
                    </div>
                    <div class="row g-3 text-center text-lg-start">
                        <div class="col-3">
                            <div class="hero-stat-num">12K+</div>
                            <div class="hero-stat-label">Members</div>
                        </div>
                        <div class="col-3">
                            <div class="hero-stat-num">8</div>
                            <div class="hero-stat-label">Locations</div>
                        </div>
                        <div class="col-3">
                            <div class="hero-stat-num">200+</div>
                            <div class="hero-stat-label">Equipment</div>
                        </div>
                        <div class="col-3">
                            <div class="hero-stat-num">50+</div>
                            <div class="hero-stat-label">Coaches</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-flex justify-content-center">
                    <div class="p-4 rounded-4 text-center" style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);max-width:340px;width:100%;">
                        <div style="font-size:6rem;line-height:1;">🏋️</div>
                        <p class="text-secondary mt-2 mb-0" style="font-size:.82rem;">Replace with hero image / video</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════════════════ GALLERY ════════════════ -->
    <section id="gallery" class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <span class="section-tag badge-fs mb-2">Our Space</span>
                <h2 class="section-title">World-Class Facilities</h2>
                <p class="text-secondary">State-of-the-art equipment across all our branches</p>
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-2 mb-4" id="gallery-tabs">
                <button class="btn btn-sm btn-fs rounded-pill active-tab" onclick="filterGallery('all',this)">All</button>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('gym',this)">Gym Floor</button>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('class',this)">Classes</button>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('location',this)">Locations</button>
            </div>
            <div class="row g-3" id="gallery-grid"></div>
        </div>
    </section>

    <!-- ════════════════ PLANS ════════════════ -->
    <section id="plans" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-tag badge-fs mb-2">Membership</span>
                <h2 class="section-title">Choose Your Plan</h2>
                <p class="text-secondary">Flexible memberships. No hidden fees. Cancel anytime.</p>
            </div>
            <div class="row g-4 justify-content-center">

                <div class="col-sm-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="text-uppercase fs-red fw-bold mb-1" style="font-size:.75rem;letter-spacing:.5px;">1 Month</div>
                        <div class="plan-price">₱<span>999</span><sub>/mo</sub></div>
                        <div class="plan-orig mb-3">₱1,299</div>
                        <hr class="my-2" />
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Full gym access</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Locker room &amp; showers</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>2 group classes/week</li>
                            <li class="plan-feature"><i class="ti ti-x text-secondary"></i>Personal trainer</li>
                            <li class="plan-feature"><i class="ti ti-x text-secondary"></i>Multi-branch access</li>
                        </ul>
                        <a href="auth.php?plan=1mo" class="btn btn-outline-secondary w-100 fw-semibold">Get Started</a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="text-uppercase fs-red fw-bold mb-1" style="font-size:.75rem;letter-spacing:.5px;">3 Months</div>
                        <div class="plan-price">₱<span>2,699</span><sub>/3mo</sub></div>
                        <div class="plan-orig mb-3">₱3,897</div>
                        <hr class="my-2" />
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Full gym access</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Locker room &amp; showers</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Unlimited group classes</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>1 PT session/month</li>
                            <li class="plan-feature"><i class="ti ti-x text-secondary"></i>Multi-branch access</li>
                        </ul>
                        <a href="auth.php?plan=3mo" class="btn btn-outline-secondary w-100 fw-semibold">Get Started</a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="plan-card popular border h-100 d-flex flex-column">
                        <div class="plan-popular-badge">Most Popular</div>
                        <div class="text-uppercase fs-red fw-bold mb-1" style="font-size:.75rem;letter-spacing:.5px;">6 Months</div>
                        <div class="plan-price">₱<span>4,799</span><sub>/6mo</sub></div>
                        <div class="plan-orig mb-3">₱7,794</div>
                        <hr class="my-2" />
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Full gym access</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Locker room &amp; showers</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Unlimited group classes</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>2 PT sessions/month</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Multi-branch access</li>
                        </ul>
                        <a href="auth.php?plan=6mo" class="btn btn-fs w-100 fw-semibold">Get Started</a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="text-uppercase fs-red fw-bold mb-1" style="font-size:.75rem;letter-spacing:.5px;">12 Months</div>
                        <div class="plan-price">₱<span>7,999</span><sub>/yr</sub></div>
                        <div class="plan-orig mb-3">₱15,588</div>
                        <hr class="my-2" />
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Full gym access</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Locker room &amp; showers</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Unlimited group classes</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>4 PT sessions/month</li>
                            <li class="plan-feature yes"><i class="ti ti-check fs-red yes"></i>Multi-branch access</li>
                        </ul>
                        <a href="auth.php?plan=12mo" class="btn btn-outline-secondary w-100 fw-semibold">Get Started</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ════════════════ FOOTER ════════════════ -->
    <footer class="py-5">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <svg width="30" height="30" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="60" cy="60" r="54" fill="none" stroke="#cc1a1a" stroke-width="7" />
                            <path d="M60 6 A54 54 0 0 1 106 77" fill="none" stroke="#cc1a1a" stroke-width="7" stroke-linecap="round" />
                            <polygon points="109,63 99,81 119,81" fill="#cc1a1a" />
                            <rect x="27" y="52" width="11" height="16" rx="3" fill="currentColor" />
                            <rect x="82" y="52" width="11" height="16" rx="3" fill="currentColor" />
                            <rect x="19" y="55" width="11" height="10" rx="2" fill="currentColor" />
                            <rect x="90" y="55" width="11" height="10" rx="2" fill="currentColor" />
                            <rect x="38" y="55" width="44" height="10" rx="3" fill="currentColor" />
                        </svg>
                        <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
                    </div>
                    <p class="text-secondary" style="font-size:.85rem;line-height:1.75;">Your ultimate fitness companion. Track progress, book classes, and connect with coaches — all in one place.</p>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a class="soc-btn" href="#" aria-label="Facebook"><i class="ti ti-brand-facebook"></i></a>
                        <a class="soc-btn" href="#" aria-label="Instagram"><i class="ti ti-brand-instagram"></i></a>
                        <a class="soc-btn" href="#" aria-label="TikTok"><i class="ti ti-brand-tiktok"></i></a>
                        <a class="soc-btn" href="#" aria-label="YouTube"><i class="ti ti-brand-youtube"></i></a>
                        <a class="soc-btn" href="#" aria-label="X / Twitter"><i class="ti ti-brand-x"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-2 offset-lg-1">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.78rem;letter-spacing:.5px;">Company</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="#" class="footer-link">About Us</a></li>
                        <li><a href="#" class="footer-link">Careers</a></li>
                        <li><a href="#" class="footer-link">Press</a></li>
                        <li><a href="#" class="footer-link">Partners</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.78rem;letter-spacing:.5px;">Support</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="#" class="footer-link">Help Center</a></li>
                        <li><a href="#" class="footer-link">Contact Us</a></li>
                        <li><a href="#" class="footer-link">Locations</a></li>
                        <li><a href="#" class="footer-link">Schedule</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.78rem;letter-spacing:.5px;">Legal</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="#" class="footer-link">Privacy Policy</a></li>
                        <li><a href="#" class="footer-link">Terms of Use</a></li>
                        <li><a href="#" class="footer-link">Cookie Policy</a></li>
                        <li><a href="#" class="footer-link">Refund Policy</a></li>
                    </ul>
                </div>
            </div>

            <hr class="border-secondary" />
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <p class="text-secondary mb-0" style="font-size:.78rem;">© 2025 FitSync. All rights reserved.</p>
                <div class="d-flex gap-1 flex-wrap">
                    <span class="plugin-badge">PHP 8.2</span>
                    <span class="plugin-badge">MySQL</span>
                    <span class="plugin-badge">Stripe</span>
                    <span class="plugin-badge">Google Maps</span>
                    <span class="plugin-badge">reCAPTCHA</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- ════════════════ FAB ════════════════ -->
    <div class="fab-wrap">
        <div class="fab-menu d-none-anim" id="fabMenu">
            <div class="fab-item">
                <span class="fab-label">Send Feedback</span>
                <button class="fab-sm" style="background:#555" onclick="alert('Feedback modal')"><i class="ti ti-message-circle-2"></i></button>
            </div>
            <div class="fab-item">
                <span class="fab-label">Live Chat</span>
                <button class="fab-sm" style="background:#1a6fcc" onclick="alert('Chat widget')"><i class="ti ti-message-dots"></i></button>
            </div>
            <div class="fab-item">
                <span class="fab-label">Contact Us</span>
                <button class="fab-sm" style="background:var(--fs-red)" onclick="alert('Contact form')"><i class="ti ti-phone"></i></button>
            </div>
        </div>
        <button class="fab-main" id="fabMain" onclick="toggleFab()" aria-label="Open contact options">
            <i class="ti ti-plus" id="fabIcon"></i>
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const galleryData = [{
                tag: 'gym',
                icon: '🏋️',
                title: 'Weight Room',
                loc: 'Main Branch — Quezon City'
            },
            {
                tag: 'gym',
                icon: '💪',
                title: 'Cardio Zone',
                loc: 'Makati Branch'
            },
            {
                tag: 'class',
                icon: '🧘',
                title: 'Yoga Studio',
                loc: 'BGC Branch'
            },
            {
                tag: 'class',
                icon: '🥊',
                title: 'Boxing Ring',
                loc: 'Main Branch — Quezon City'
            },
            {
                tag: 'location',
                icon: '🏢',
                title: 'BGC Flagship',
                loc: 'Bonifacio Global City'
            },
            {
                tag: 'location',
                icon: '🏙️',
                title: 'Ortigas Hub',
                loc: 'Ortigas Center, Pasig'
            },
            {
                tag: 'gym',
                icon: '🚴',
                title: 'Cycling Studio',
                loc: 'Eastwood Branch'
            },
            {
                tag: 'location',
                icon: '🌆',
                title: 'Makati Branch',
                loc: 'Ayala Ave, Makati'
            },
        ];

        function renderGallery(f) {
            const items = f === 'all' ? galleryData : galleryData.filter(i => i.tag === f);
            document.getElementById('gallery-grid').innerHTML = items.map(i => `
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="gcard">
        <div class="gcard-fake">${i.icon}</div>
        <div class="gcard-overlay"></div>
        <div class="gcard-info">
          <div class="fw-bold text-white" style="font-size:.88rem">${i.title}</div>
          <div class="d-flex align-items-center gap-1 text-white-50" style="font-size:.72rem">
            <i class="ti ti-map-pin"></i>${i.loc}
          </div>
        </div>
      </div>
    </div>`).join('');
        }

        function filterGallery(tag, btn) {
            document.querySelectorAll('#gallery-tabs button').forEach(b => {
                b.classList.remove('btn-fs', 'active-tab');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-fs', 'active-tab');
            renderGallery(tag);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            document.getElementById('knob-icon').className = isDark ? 'ti ti-sun' : 'ti ti-moon';
        }

        let fabOpen = false;

        function toggleFab() {
            fabOpen = !fabOpen;
            const m = document.getElementById('fabMenu');
            const btn = document.getElementById('fabMain');
            m.classList.toggle('d-none-anim', !fabOpen);
            btn.classList.toggle('open', fabOpen);
        }

        window.addEventListener('scroll', () => {
            const links = document.querySelectorAll('.nav-link[href^="#"]');
            let cur = 'home';
            ['home', 'gallery', 'plans'].forEach(id => {
                const el = document.getElementById(id);
                if (el && window.scrollY >= el.offsetTop - 80) cur = id;
            });
            links.forEach(a => {
                a.classList.toggle('active', a.getAttribute('href') === '#' + cur);
            });
        });

        renderGallery('all');
    </script>
</body>

</html>