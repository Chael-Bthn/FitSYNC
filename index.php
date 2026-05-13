<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — Elevate Your Performance</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204, 26, 26, .3);
            --fs-warm: #f5f0eb;
            --fs-dark: #0d0d0d;
        }

        * {
            font-family: 'Outfit', system-ui, sans-serif
        }

        body {
            overflow-x: hidden
        }

        /* ── BRAND ── */
        .fs-red {
            color: var(--fs-red) !important
        }

        .btn-fs {
            background: var(--fs-red);
            border: none;
            color: #fff;
            font-weight: 700;
            letter-spacing: .3px
        }

        .btn-fs:hover {
            background: var(--fs-red-hover);
            color: #fff
        }

        .btn-fs-outline {
            border: 1.5px solid rgba(255, 255, 255, .6);
            color: #fff;
            background: transparent;
            font-weight: 600;
            backdrop-filter: blur(4px)
        }

        .btn-fs-outline:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, .12);
            color: #fff
        }

        .badge-fs {
            background: rgba(204, 26, 26, .15);
            color: var(--fs-red);
            border: 1px solid rgba(204, 26, 26, .3);
            font-weight: 700;
            letter-spacing: .5px
        }

        /* ── NAVBAR ── */
        .navbar {
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            backdrop-filter: blur(18px);
            transition: background .3s
        }

        [data-bs-theme="dark"] .navbar {
            background: rgba(10, 10, 10, .7) !important
        }

        [data-bs-theme="light"] .navbar {
            background: rgba(255, 255, 255, .88) !important;
            border-bottom: 1px solid rgba(0, 0, 0, .08)
        }

        .brand-text .fit {
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: 1px
        }

        .brand-text .sync {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--fs-red);
            letter-spacing: 1px
        }

        .nav-link {
            font-size: .88rem;
            font-weight: 500;
            letter-spacing: .3px;
            transition: color .2s
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--fs-red) !important
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
            transition: background .3s;
            padding: 0;
            flex-shrink: 0
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
            color: #fff
        }

        [data-bs-theme="light"] .theme-toggle .tog-knob {
            transform: translateX(24px)
        }

        .theme-toggle .tog-icon-dark,
        .theme-toggle .tog-icon-light {
            position: absolute;
            top: 50%;
            font-size: .75rem;
            transform: translateY(-50%);
            transition: opacity .3s
        }

        .theme-toggle .tog-icon-dark {
            left: 7px;
            opacity: 1
        }

        .theme-toggle .tog-icon-light {
            right: 7px;
            opacity: .4
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-dark {
            opacity: .4
        }

        [data-bs-theme="light"] .theme-toggle .tog-icon-light {
            opacity: 1
        }

        /* ── HERO ── */
        #home {
            min-height: 100vh;
            padding-top: 0;
            display: flex;
            align-items: flex-end;
            position: relative;
            overflow: hidden;
        }

        /* ── HERO SLIDESHOW ── */
        .hero-slideshow {
            position: absolute;
            inset: 0;
            background-color: #1a1a1a;
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center top;
            opacity: 0;
            transition: opacity 1.4s cubic-bezier(0.4, 0, 0.2, 1);
            /* Ken Burns zoom effect on each slide */
            animation: kenBurns 8s ease-in-out infinite alternate;
        }

        .hero-slide.active {
            opacity: 1;
        }

        /* Stagger ken burns direction per slide */
        .hero-slide:nth-child(odd) {
            animation: kenBurnsA 8s ease-in-out infinite alternate;
        }

        .hero-slide:nth-child(even) {
            animation: kenBurnsB 8s ease-in-out infinite alternate;
        }

        @keyframes kenBurnsA {
            from {
                transform: scale(1) translateX(0) translateY(0);
            }

            to {
                transform: scale(1.07) translateX(-1.5%) translateY(-1%);
            }
        }

        @keyframes kenBurnsB {
            from {
                transform: scale(1.05) translateX(1%) translateY(.5%);
            }

            to {
                transform: scale(1) translateX(0) translateY(0);
            }
        }

        /* Dark overlay on top of slideshow */
        .hero-slideshow::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(to top, rgba(0, 0, 0, .92) 0%, rgba(0, 0, 0, .55) 45%, rgba(0, 0, 0, .18) 100%),
                linear-gradient(100deg, rgba(0, 0, 0, .5) 0%, transparent 60%);
            z-index: 1;
        }

        /* Slide indicator dots */
        .hero-dots {
            position: absolute;
            bottom: 2.5rem;
            right: 2rem;
            z-index: 3;
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }

        .hero-dot {
            width: 3px;
            height: 20px;
            border-radius: 2px;
            background: rgba(255, 255, 255, .25);
            cursor: pointer;
            transition: background .3s, height .3s;
            border: none;
            padding: 0;
        }

        .hero-dot.active {
            background: var(--fs-red);
            height: 32px;
        }

        .hero-dot:hover:not(.active) {
            background: rgba(255, 255, 255, .5);
        }

        /* Progress bar for current slide */
        .hero-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--fs-red);
            z-index: 3;
            width: 0%;
            transition: width linear;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            padding-bottom: 6rem;
            padding-top: 76px
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .15);
            backdrop-filter: blur(8px);
            color: rgba(255, 255, 255, .85);
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: .35rem 1rem;
            border-radius: 50px;
            margin-bottom: 1.4rem;
        }

        .hero-eyebrow span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--fs-red);
            display: inline-block
        }

        .hero-title {
            font-size: clamp(2.4rem, 7vw, 5.2rem);
            font-weight: 900;
            line-height: 1.02;
            color: #fff;
            letter-spacing: -1px;
        }

        .hero-title em {
            font-style: normal;
            color: var(--fs-red)
        }

        .hero-sub {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, .6);
            line-height: 1.8;
            max-width: 520px;
            font-weight: 400
        }

        .hero-divider {
            width: 48px;
            height: 3px;
            background: var(--fs-red);
            border-radius: 2px;
            margin: 1.5rem 0
        }

        .hero-stat {
            border-left: 2px solid rgba(255, 255, 255, .15);
            padding-left: 1rem
        }

        .hero-stat-num {
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            line-height: 1
        }

        .hero-stat-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: rgba(255, 255, 255, .45);
            margin-top: .2rem
        }

        .hero-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 2rem;
        }

        .hero-stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem 2rem;
        }

        .scroll-hint {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .4rem;
            color: rgba(255, 255, 255, .35);
            font-size: .65rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .scroll-dot {
            width: 24px;
            height: 38px;
            border-radius: 50px;
            border: 1.5px solid rgba(255, 255, 255, .2);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 6px;
        }

        .scroll-dot::before {
            content: '';
            width: 4px;
            height: 8px;
            border-radius: 2px;
            background: rgba(255, 255, 255, .4);
            animation: scrollDot 2s ease-in-out infinite;
        }

        @keyframes scrollDot {

            0%,
            100% {
                transform: translateY(0);
                opacity: 1
            }

            60% {
                transform: translateY(10px);
                opacity: .2
            }
        }

        /* ── SECTION HEADERS ── */
        .section-tag {
            display: inline-block;
            padding: .2rem .85rem;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px
        }

        .section-title {
            font-size: clamp(1.7rem, 4vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -.5px
        }

        /* ── STRIP ── */
        .lifestyle-strip {
            background: var(--fs-red);
            overflow: hidden;
            white-space: nowrap;
            padding: .7rem 0;
        }

        .strip-track {
            display: inline-flex;
            gap: 3rem;
            animation: stripScroll 22s linear infinite;
        }

        .strip-track span {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .9)
        }

        .strip-track .dot {
            color: rgba(255, 255, 255, .45);
            font-size: 1rem
        }

        @keyframes stripScroll {
            from {
                transform: translateX(0)
            }

            to {
                transform: translateX(-50%)
            }
        }

        /* ── GALLERY ── */
        #gallery {
            background: var(--bs-secondary-bg)
        }

        .gcard {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            aspect-ratio: 4/3;
            cursor: pointer
        }

        .gcard-img-wrap {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: var(--bs-tertiary-bg)
        }

        .gcard-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .5s cubic-bezier(.25, .46, .45, .94)
        }

        .gcard:hover .gcard-photo {
            transform: scale(1.08)
        }

        .gcard-no-img {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--bs-secondary-color)
        }

        .gcard-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, .8) 0%, transparent 55%);
            opacity: 0;
            transition: opacity .35s
        }

        .gcard:hover .gcard-overlay {
            opacity: 1
        }

        .gcard-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.2rem;
            opacity: 0;
            transform: translateY(8px);
            transition: all .35s
        }

        .gcard:hover .gcard-info {
            opacity: 1;
            transform: translateY(0)
        }

        /* Zoom icon on hover */
        .gcard-zoom {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.7);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(204, 26, 26, 0.85);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            opacity: 0;
            transition: opacity .3s, transform .3s;
            pointer-events: none;
        }

        .gcard:hover .gcard-zoom {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .gcard-tag {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, .45);
            backdrop-filter: blur(6px);
            color: rgba(255, 255, 255, .8);
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: .2rem .65rem;
            border-radius: 50px;
        }

        .gallery-tabs-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 2px;
        }

        .gallery-tabs-wrap::-webkit-scrollbar {
            display: none;
        }

        #gallery-tabs {
            flex-wrap: nowrap;
            min-width: max-content;
        }

        /* ── LIGHTBOX ── */
        .lightbox-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .92);
            z-index: 9000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s;
            backdrop-filter: blur(6px);
        }

        .lightbox-backdrop.open {
            opacity: 1;
            pointer-events: all;
        }

        .lightbox-inner {
            position: relative;
            max-width: min(90vw, 1100px);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: scale(0.92);
            transition: transform .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .lightbox-backdrop.open .lightbox-inner {
            transform: scale(1);
        }

        .lightbox-img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 16px;
            object-fit: contain;
            box-shadow: 0 32px 80px rgba(0, 0, 0, .6);
            display: block;
        }

        .lightbox-caption {
            margin-top: 1rem;
            text-align: center;
        }

        .lightbox-caption .lb-title {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .2rem;
        }

        .lightbox-caption .lb-loc {
            color: rgba(255, 255, 255, .45);
            font-size: .78rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .3rem;
        }

        .lightbox-close {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #fff;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .2s;
            z-index: 9001;
        }

        .lightbox-close:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
        }

        .lightbox-nav {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #fff;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .2s, border-color .2s;
            z-index: 9001;
        }

        .lightbox-nav:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
        }

        .lightbox-nav.prev {
            left: 1rem;
        }

        .lightbox-nav.next {
            right: 1rem;
        }

        .lightbox-counter {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, .4);
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1px;
            z-index: 9001;
        }

        @media (max-width: 575.98px) {
            .lightbox-nav.prev {
                left: .4rem;
            }

            .lightbox-nav.next {
                right: .4rem;
            }

            .lightbox-inner {
                max-width: 96vw;
            }
        }

        /* ── PLANS ── */
        .plan-card {
            border-radius: 20px;
            padding: 1.5rem 1.25rem;
            position: relative;
            transition: transform .25s, border-color .25s, box-shadow .25s
        }

        .plan-card:hover {
            transform: translateY(-6px);
            border-color: rgba(204, 26, 26, .4) !important;
            box-shadow: 0 20px 48px rgba(0, 0, 0, .2)
        }

        .plan-card.popular {
            border-color: var(--fs-red) !important;
            border-width: 2px !important
        }

        .plan-popular-col {
            padding-top: 14px;
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
            white-space: nowrap
        }

        .plan-label {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--fs-red);
            margin-bottom: .35rem;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
            margin-bottom: .15rem;
        }

        .plan-price sub {
            font-size: .75rem;
            font-weight: 400;
            color: var(--bs-secondary-color)
        }

        .plan-orig {
            font-size: .75rem;
            text-decoration: line-through;
            color: var(--bs-secondary-color);
            margin-bottom: 0;
        }

        .plan-divider {
            margin: .75rem 0;
        }

        .plan-features {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .plan-feature {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .8rem;
            color: var(--bs-secondary-color);
            line-height: 1.3;
        }

        .plan-feature.yes {
            color: var(--bs-body-color)
        }

        .plan-feature.yes i {
            color: var(--fs-red);
            flex-shrink: 0;
        }

        .plan-feature:not(.yes) i {
            color: var(--bs-secondary-color);
            flex-shrink: 0;
        }

        .plan-btn {
            font-size: .82rem;
            padding: .45rem .75rem;
        }

        @media (min-width: 1200px) {
            .plan-card {
                padding: 2rem 1.75rem;
            }

            .plan-price {
                font-size: 2.6rem;
            }

            .plan-feature {
                font-size: .84rem;
            }

            .plan-btn {
                font-size: .9rem;
                padding: .5rem 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .plan-card {
                padding: 1rem .85rem;
                border-radius: 14px;
            }

            .plan-label {
                font-size: .62rem;
                margin-bottom: .2rem;
            }

            .plan-price {
                font-size: 1.4rem;
                letter-spacing: -.5px;
            }

            .plan-price sub {
                font-size: .62rem;
            }

            .plan-orig {
                font-size: .68rem;
            }

            .plan-divider {
                margin: .5rem 0;
            }

            .plan-features {
                gap: .35rem;
                margin-bottom: .75rem;
            }

            .plan-feature {
                font-size: .72rem;
                gap: .3rem;
            }

            .plan-btn {
                font-size: .75rem;
                padding: .35rem .5rem;
                border-radius: 50px;
            }

            .plan-popular-col {
                padding-top: 16px;
            }
        }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--bs-border-color)
        }

        .soc-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1rem;
            transition: all .2s
        }

        .soc-btn:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
            color: #fff
        }

        .footer-link {
            color: var(--bs-secondary-color);
            text-decoration: none;
            font-size: .85rem;
            transition: color .2s
        }

        .footer-link:hover {
            color: var(--fs-red)
        }

        .plugin-badge {
            background: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-secondary-color);
            font-size: .7rem;
            padding: .2rem .6rem;
            border-radius: 5px
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
            gap: .55rem
        }

        .fab-menu {
            display: flex;
            flex-direction: column;
            gap: .45rem;
            align-items: flex-end;
            transition: opacity .25s, transform .25s
        }

        .fab-menu.d-none-anim {
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none
        }

        .fab-item {
            display: flex;
            align-items: center;
            gap: .55rem
        }

        .fab-label {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            color: var(--bs-body-color);
            font-size: .76rem;
            padding: .22rem .65rem;
            border-radius: 6px;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15)
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
            flex-shrink: 0
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
            box-shadow: 0 4px 24px var(--fs-red-glow);
            transition: background .2s, transform .25s
        }

        .fab-main:hover {
            background: var(--fs-red-hover)
        }

        .fab-main.open {
            transform: rotate(45deg)
        }

        /* ── LOCATIONS ── */
        .branch-card {
            cursor: pointer;
            transition: border-color .2s, background .2s, color .2s;
            color: var(--bs-body-color);
            background: transparent;
        }

        .branch-card.active-branch {
            border-color: var(--fs-red) !important;
            background: rgba(204, 26, 26, .1) !important;
            color: var(--fs-red) !important;
        }

        .branch-card:hover:not(.active-branch) {
            border-color: rgba(204, 26, 26, .35) !important;
        }

        .loc-map-wrap {
            height: 480px;
        }

        @media (max-width: 991.98px) {
            .loc-map-wrap {
                height: 380px;
            }
        }

        @media (max-width: 575.98px) {
            .loc-map-wrap {
                height: 280px;
            }
        }

        /* ════════ RESPONSIVE ════════ */
        @media (max-width: 991.98px) {
            #navMenu {
                padding: 1rem 0 1.25rem;
                border-top: 1px solid rgba(255, 255, 255, .08);
                margin-top: .5rem;
            }

            [data-bs-theme="light"] #navMenu {
                border-top-color: rgba(0, 0, 0, .08);
            }

            .navbar-nav {
                gap: 0 !important;
                margin-bottom: .75rem;
            }

            .nav-link {
                padding: .55rem .25rem !important;
            }

            #navMenu>.d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: .5rem !important;
                margin-top: 0 !important;
            }

            #navMenu>.d-flex .btn {
                width: 100%;
                text-align: center;
                justify-content: center;
            }

            #navMenu>.d-flex .theme-toggle {
                align-self: flex-start;
                margin-top: .25rem;
            }
        }

        @media (max-width: 767.98px) {
            #home {
                align-items: center;
                min-height: 100svh;
            }

            .hero-content {
                padding-top: 72px;
                padding-bottom: 3rem;
            }

            .hero-sub {
                font-size: .95rem;
                max-width: 100%;
            }

            .hero-divider {
                margin: 1.1rem 0;
            }
        }

        @media (max-width: 575.98px) {
            .hero-content {
                padding-top: 28px;
                padding-bottom: 2.5rem;
            }

            .hero-cta-row {
                flex-direction: column;
            }

            .hero-cta-row .btn {
                width: 100%;
                justify-content: center;
                padding-left: 1.5rem !important;
                padding-right: 1.5rem !important;
            }

            .hero-stat-num {
                font-size: 1.35rem;
            }

            .hero-stat-label {
                font-size: .6rem;
            }

            .hero-stats-row {
                gap: 1rem 1.5rem;
            }

            .hero-eyebrow {
                font-size: .68rem;
                padding: .3rem .8rem;
            }

            /* Hide dots on mobile, use progress bar only */
            .hero-dots {
                display: none;
            }
        }

        @media (max-height: 620px) {
            .scroll-hint {
                display: none;
            }
        }

        @media (max-width: 575.98px) {
            .gcard {
                aspect-ratio: 1/1;
                border-radius: 12px;
            }

            .gcard-info {
                padding: .75rem;
                opacity: 1;
                transform: none;
            }

            .gcard-overlay {
                opacity: 1;
            }

            .gcard-info .fw-bold {
                font-size: .78rem !important;
            }

            .gcard-info .text-white-50 {
                font-size: .62rem !important;
            }

            #gallery-grid .g-3 {
                --bs-gutter-x: .5rem;
                --bs-gutter-y: .5rem;
            }
        }

        @media (max-width: 575.98px) {
            footer .col-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            footer .plugin-badge-row {
                justify-content: flex-start !important;
                flex-wrap: wrap;
            }

            footer .d-flex.justify-content-between {
                flex-direction: column;
                gap: .75rem;
            }
        }

        @media (max-width: 575.98px) {
            .fab-wrap {
                bottom: 1.25rem;
                right: 1.25rem;
            }

            .fab-main {
                width: 46px;
                height: 46px;
                font-size: 1.15rem;
            }
        }

        /* ── HERO THEME BUTTON (matches auth.php panel-theme-btn) ── */
        .hero-theme-btn {
            position: fixed;
            top: 68px;
            right: 1.5rem;
            z-index: 1020;
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

        .hero-theme-btn:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
            color: #fff;
        }

        @media (max-width: 400px) {
            .scroll-hint {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- ════════════════ NAVBAR ════════════════ -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="#home">
                <img class="theme-logo" src="FitSYNC Emblem Light.svg" data-logo-dark="FitSYNC Emblem Light.svg" data-logo-light="FitSYNC Emblem.svg" alt="FitSync" width="36" height="36" style="flex-shrink:0" />
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-label="Toggle navigation">
                <i class="ti ti-menu-2 fs-5"></i>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav mx-auto gap-1">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#locations">Locations</a></li>
                    <li class="nav-item"><a class="nav-link" href="#plans">Plans</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <a href="auth.php?mode=login" class="btn btn-sm btn-fs px-3 rounded-pill">Log In / Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Theme toggle — fixed just below navbar -->
    <button class="hero-theme-btn" onclick="toggleTheme()" aria-label="Toggle theme">
        <i class="ti ti-sun" id="themeIcon"></i>
    </button>

    <!-- ════════════════ HERO ════════════════ -->
    <section id="home">

        <!-- ── SLIDESHOW BACKGROUND ── -->
        <div class="hero-slideshow" id="heroSlideshow">
            <!-- Slides injected by JS -->
        </div>

        <!-- Progress bar at very bottom of hero -->
        <div class="hero-progress" id="heroProgress"></div>

        <!-- Vertical dot navigation (right side) -->
        <div class="hero-dots" id="heroDots"></div>

        <div class="hero-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8 col-xl-7">
                        <div class="hero-eyebrow">
                            <span></span>#1 Gym Network in the City
                        </div>
                        <h1 class="hero-title mb-0">
                            Push Past<br>Your <em>Limits.</em><br>
                            <span style="color:rgba(255,255,255,.88)">Live the</span> <em>Lifestyle.</em>
                        </h1>
                        <div class="hero-divider"></div>
                        <p class="hero-sub mb-4">
                            Train smarter with world-class equipment, expert coaches, and a community that keeps you accountable — every rep, every day.
                        </p>
                        <div class="hero-cta-row mb-4">
                            <a href="auth.php?mode=register" class="btn btn-fs btn-lg px-5 py-3 rounded-pill">
                                <i class="ti ti-bolt me-1"></i>Join Now
                            </a>
                            <a href="#plans" class="btn btn-fs-outline btn-lg px-5 py-3 rounded-pill">View Plans</a>
                        </div>
                        <div class="hero-stats-row">
                            <div class="hero-stat">
                                <div class="hero-stat-num">12K+</div>
                                <div class="hero-stat-label">Members</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-num">8</div>
                                <div class="hero-stat-label">Locations</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-num">200+</div>
                                <div class="hero-stat-label">Equipment</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-num">50+</div>
                                <div class="hero-stat-label">Coaches</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="scroll-hint">
            <div class="scroll-dot"></div>
            Scroll
        </div>
    </section>

    <!-- ════════════════ MARQUEE STRIP ════════════════ -->
    <div class="lifestyle-strip">
        <div class="strip-track" id="stripTrack"></div>
    </div>

    <!-- ════════════════ GALLERY ════════════════ -->
    <section id="gallery" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-tag badge-fs mb-2">Our Space</span>
                <h2 class="section-title">World-Class Facilities</h2>
                <p class="text-secondary">State-of-the-art equipment across all our branches</p>
            </div>
            <div class="gallery-tabs-wrap mb-4">
                <div class="d-flex justify-content-center gap-2" id="gallery-tabs">
                    <button class="btn btn-sm btn-fs rounded-pill active-tab" onclick="filterGallery('all',this)">All</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('gym',this)">Gym Floor</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('class',this)">Classes</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="filterGallery('location',this)">Locations</button>
                </div>
            </div>
            <div class="row g-2 g-sm-3" id="gallery-grid"></div>
        </div>
    </section>

    <!-- ════════════════ LIGHTBOX ════════════════ -->
    <div class="lightbox-backdrop" id="lightbox" onclick="lightboxBackdropClick(event)">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close"><i class="ti ti-x"></i></button>
        <button class="lightbox-nav prev" onclick="lightboxNav(-1)" aria-label="Previous"><i class="ti ti-chevron-left"></i></button>
        <button class="lightbox-nav next" onclick="lightboxNav(1)" aria-label="Next"><i class="ti ti-chevron-right"></i></button>
        <div class="lightbox-inner">
            <img src="" alt="" class="lightbox-img" id="lightbox-img" />
            <div class="lightbox-caption">
                <div class="lb-title" id="lightbox-title"></div>
                <div class="lb-loc"><i class="ti ti-map-pin"></i><span id="lightbox-loc"></span></div>
            </div>
        </div>
        <div class="lightbox-counter" id="lightbox-counter"></div>
    </div>

    <!-- ════════════════ LOCATIONS ════════════════ -->
    <section id="locations" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-tag badge-fs mb-2">Our Branches</span>
                <h2 class="section-title">Find Us Near You</h2>
                <p class="text-secondary">8 locations across Metro Manila — always within reach.</p>
            </div>
            <div class="rounded-4 overflow-hidden border loc-map-wrap mb-3">
                <iframe id="branch-map"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d123989!2d121.03!3d14.586!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c9c0bf0d5e21%3A0xd3cd4887c01849dd!2sMetro%20Manila%2C%20Philippines!5e0!3m2!1sen!2sph!4v1700000000000"
                    width="100%" height="100%" style="border:0;display:block"
                    loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <button class="branch-card btn btn-sm rounded-pill border px-3 py-2 active-branch"
                    data-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.4!2d121.00666!3d14.62192!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b7d5edd1b5af%3A0xd3e98daffd48cde!2sQuezon%20Ave%2C%20Quezon%20City!5e0!3m2!1sen!2sph!4v1700000000001">
                    <i class="ti ti-map-pin me-1"></i>Quezon City
                    <span class="ms-1 badge-fs section-tag px-2 py-0 rounded-pill" style="font-size:.6rem">Main</span>
                </button>
                <button class="branch-card btn btn-sm rounded-pill border px-3 py-2"
                    data-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.9!2d121.01278!3d14.55380!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c99b98784377%3A0x47514e362615f793!2sJ%20Victor%2C%20Makati!5e0!3m2!1sen!2sph!4v1700000000002">
                    <i class="ti ti-map-pin me-1"></i>Makati
                </button>
                <button class="branch-card btn btn-sm rounded-pill border px-3 py-2"
                    data-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.5!2d121.04897!3d14.55115!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c934584d4083%3A0x328bc91a8bc19ff8!2sBonifacio%20High%20Street%2C%20Taguig!5e0!3m2!1sen!2sph!4v1700000000003">
                    <i class="ti ti-map-pin me-1"></i>BGC
                </button>
                <button class="branch-card btn btn-sm rounded-pill border px-3 py-2"
                    data-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.9!2d121.06026!3d14.58992!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c98327224487%3A0xb7b41444b7a18382!2sOrtigas%20Ave%2C%20Pasig!5e0!3m2!1sen!2sph!4v1700000000004">
                    <i class="ti ti-map-pin me-1"></i>Ortigas, Pasig
                </button>
                <button class="branch-card btn btn-sm rounded-pill border px-3 py-2"
                    data-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.6!2d121.08141!3d14.60987!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b925255bbbfb%3A0x596b6b730081a0d8!2sEastwood%20Ave%2C%20Quezon%20City!5e0!3m2!1sen!2sph!4v1700000000005">
                    <i class="ti ti-map-pin me-1"></i>Eastwood City
                </button>
            </div>
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
            <div class="row g-3 g-xl-4 justify-content-center">
                <div class="col-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="plan-label">1 Month</div>
                        <div class="plan-price">₱999<sub>/mo</sub></div>
                        <div class="plan-orig">₱1,299</div>
                        <hr class="plan-divider" />
                        <ul class="list-unstyled plan-features flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Full gym access</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Locker &amp; showers</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>2 classes/week</span></li>
                            <li class="plan-feature"><i class="ti ti-x"></i><span>Personal trainer</span></li>
                            <li class="plan-feature"><i class="ti ti-x"></i><span>Multi-branch</span></li>
                        </ul>
                        <a href="auth.php?plan=1mo" class="btn btn-outline-secondary w-100 fw-semibold rounded-pill plan-btn">Get Started</a>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="plan-label">3 Months</div>
                        <div class="plan-price">₱2,699<sub>/3mo</sub></div>
                        <div class="plan-orig">₱3,897</div>
                        <hr class="plan-divider" />
                        <ul class="list-unstyled plan-features flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Full gym access</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Locker &amp; showers</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Unlimited classes</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>1 PT session/mo</span></li>
                            <li class="plan-feature"><i class="ti ti-x"></i><span>Multi-branch</span></li>
                        </ul>
                        <a href="auth.php?plan=3mo" class="btn btn-outline-secondary w-100 fw-semibold rounded-pill plan-btn">Get Started</a>
                    </div>
                </div>
                <div class="col-6 col-xl-3 plan-popular-col">
                    <div class="plan-card popular border h-100 d-flex flex-column">
                        <div class="plan-popular-badge">Most Popular</div>
                        <div class="plan-label">6 Months</div>
                        <div class="plan-price">₱4,799<sub>/6mo</sub></div>
                        <div class="plan-orig">₱7,794</div>
                        <hr class="plan-divider" />
                        <ul class="list-unstyled plan-features flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Full gym access</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Locker &amp; showers</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Unlimited classes</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>2 PT sessions/mo</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Multi-branch</span></li>
                        </ul>
                        <a href="auth.php?plan=6mo" class="btn btn-fs w-100 fw-semibold rounded-pill plan-btn">Get Started</a>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="plan-card border h-100 d-flex flex-column">
                        <div class="plan-label">12 Months</div>
                        <div class="plan-price">₱7,999<sub>/yr</sub></div>
                        <div class="plan-orig">₱15,588</div>
                        <hr class="plan-divider" />
                        <ul class="list-unstyled plan-features flex-grow-1">
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Full gym access</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Locker &amp; showers</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Unlimited classes</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>4 PT sessions/mo</span></li>
                            <li class="plan-feature yes"><i class="ti ti-check"></i><span>Multi-branch</span></li>
                        </ul>
                        <a href="auth.php?plan=12mo" class="btn btn-outline-secondary w-100 fw-semibold rounded-pill plan-btn">Get Started</a>
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
                        <img class="theme-logo" src="FitSYNC Logo Light.svg" data-logo-dark="FitSYNC Logo Light.svg" data-logo-light="FitSYNC Logo.svg" alt="FitSync" height="30" />
                    </div>
                    <p class="text-secondary" style="font-size:.85rem;line-height:1.8">Your ultimate fitness companion. Track progress, book classes, and connect with coaches — all in one place.</p>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a class="soc-btn" href="#" aria-label="Facebook"><i class="ti ti-brand-facebook"></i></a>
                        <a class="soc-btn" href="#" aria-label="Instagram"><i class="ti ti-brand-instagram"></i></a>
                        <a class="soc-btn" href="#" aria-label="TikTok"><i class="ti ti-brand-tiktok"></i></a>
                        <a class="soc-btn" href="#" aria-label="YouTube"><i class="ti ti-brand-youtube"></i></a>
                        <a class="soc-btn" href="#" aria-label="X"><i class="ti ti-brand-x"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-2 offset-lg-1">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.7px">Company</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="#" class="footer-link">About Us</a></li>
                        <li><a href="#" class="footer-link">Careers</a></li>
                        <li><a href="#" class="footer-link">Press</a></li>
                        <li><a href="#" class="footer-link">Partners</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.7px">Support</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="#" class="footer-link">Help Center</a></li>
                        <li><a href="#" class="footer-link">Contact Us</a></li>
                        <li><a href="#" class="footer-link">Locations</a></li>
                        <li><a href="#" class="footer-link">Schedule</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.7px">Legal</h6>
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
                <p class="text-secondary mb-0" style="font-size:.78rem">© 2025 FitSync. All rights reserved.</p>
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
        /* ── MARQUEE ── */
        const slogans = ['Train Hard · Live Well', 'No Limits · No Excuses', 'Your Best Self Starts Here', 'Sweat · Strength · Sync', 'Built Different', 'Every Rep Counts', 'Community · Commitment · Results'];
        const t = document.getElementById('stripTrack');
        const doubled = [...slogans, ...slogans];
        t.innerHTML = doubled.map(s => `<span>${s}</span><span class="dot">✦</span>`).join('');

        /* ══════════════════════════════════════════
           HERO SLIDESHOW
        ══════════════════════════════════════════ */
        const heroSlides = [{
                img: 'gallery/BG Photo.png',
                label: 'Main Floor'
            },
            {
                img: 'gallery/Plyo area.png',
                label: 'Plyometrics Area'
            },
            {
                img: 'gallery/Boxing ring.png',
                label: 'Boxing Ring'
            },
            {
                img: 'gallery/Mirror room.png',
                label: 'Yoga Studio'
            },
            {
                img: 'gallery/Lounge.png',
                label: 'Lounge Area'
            },
            {
                img: 'gallery/Locker 1.png',
                label: 'Locker Room'
            },
            {
                img: 'gallery/Counter.png',
                label: 'Reception'
            },
        ];

        const SLIDE_DURATION = 5000; // ms each slide stays visible
        const FADE_DURATION = 1400; // ms — must match CSS transition

        let slideIndex = 0;
        let slideTimer = null;
        let progressRAF = null;
        let progressStart = null;

        const slideshowEl = document.getElementById('heroSlideshow');
        const dotsEl = document.getElementById('heroDots');
        const progressEl = document.getElementById('heroProgress');

        /* Build slide elements */
        heroSlides.forEach((s, i) => {
            const el = document.createElement('div');
            el.className = 'hero-slide' + (i === 0 ? ' active' : '');
            el.style.backgroundImage = `url('${s.img}')`;
            slideshowEl.appendChild(el);
        });

        /* Build dot buttons */
        heroSlides.forEach((s, i) => {
            const btn = document.createElement('button');
            btn.className = 'hero-dot' + (i === 0 ? ' active' : '');
            btn.setAttribute('aria-label', `Go to slide: ${s.label}`);
            btn.addEventListener('click', () => goToSlide(i));
            dotsEl.appendChild(btn);
        });

        const slideEls = slideshowEl.querySelectorAll('.hero-slide');
        const dotEls = dotsEl.querySelectorAll('.hero-dot');

        function goToSlide(next) {
            /* Clear running timers */
            clearTimeout(slideTimer);
            cancelAnimationFrame(progressRAF);

            const prev = slideIndex;
            if (next === prev) {
                startProgress();
                return;
            }

            slideIndex = next;

            slideEls[prev].classList.remove('active');
            slideEls[next].classList.add('active');

            dotEls[prev].classList.remove('active');
            dotEls[next].classList.add('active');

            startProgress();
        }

        function nextSlide() {
            goToSlide((slideIndex + 1) % heroSlides.length);
        }

        /* Smooth progress bar via rAF */
        function startProgress() {
            progressEl.style.transition = 'none';
            progressEl.style.width = '0%';

            /* Force reflow so the reset is visible */
            progressEl.getBoundingClientRect();

            progressStart = performance.now();

            function tick(now) {
                const elapsed = now - progressStart;
                const pct = Math.min((elapsed / SLIDE_DURATION) * 100, 100);
                progressEl.style.width = pct + '%';

                if (pct < 100) {
                    progressRAF = requestAnimationFrame(tick);
                } else {
                    /* Tiny pause so the bar reaches 100% visually before resetting */
                    slideTimer = setTimeout(nextSlide, 80);
                }
            }

            progressRAF = requestAnimationFrame(tick);
        }

        /* Pause on hover */
        let pausedAt = null;
        slideshowEl.addEventListener('mouseenter', () => {
            cancelAnimationFrame(progressRAF);
            clearTimeout(slideTimer);
            pausedAt = performance.now() - progressStart;
            progressEl.style.transition = 'none';
        });

        slideshowEl.addEventListener('mouseleave', () => {
            if (pausedAt === null) return;
            /* Resume from where we left off */
            progressStart = performance.now() - pausedAt;
            pausedAt = null;

            function tick(now) {
                const elapsed = now - progressStart;
                const pct = Math.min((elapsed / SLIDE_DURATION) * 100, 100);
                progressEl.style.width = pct + '%';
                if (pct < 100) {
                    progressRAF = requestAnimationFrame(tick);
                } else {
                    slideTimer = setTimeout(nextSlide, 80);
                }
            }
            progressRAF = requestAnimationFrame(tick);
        });

        /* Touch swipe support */
        let touchStartX = 0;
        slideshowEl.addEventListener('touchstart', e => {
            touchStartX = e.touches[0].clientX;
        }, {
            passive: true
        });
        slideshowEl.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 50) goToSlide(dx < 0 ?
                (slideIndex + 1) % heroSlides.length :
                (slideIndex - 1 + heroSlides.length) % heroSlides.length);
        }, {
            passive: true
        });

        /* Kick off */
        startProgress();

        /* ── GALLERY DATA ── */
        const galleryData = [{
                tag: 'gym',
                img: 'gallery/Plyo area.png',
                title: 'Plyometrics Area',
                loc: 'Main Branch — Quezon City'
            },
            {
                tag: 'gym',
                img: 'gallery/Boxing ring.png',
                title: 'Boxing Ring',
                loc: 'Makati Branch'
            },
            {
                tag: 'class',
                img: 'gallery/Mirror room.png',
                title: 'Yoga Studio',
                loc: 'BGC Branch'
            },
            {
                tag: 'class',
                img: 'gallery/Lounge.png',
                title: 'Lounge Area',
                loc: 'Main Branch — Quezon City'
            },
            {
                tag: 'location',
                img: 'gallery/Locker 1.png',
                title: 'Locker Room 1',
                loc: 'Bonifacio Global City'
            },
            {
                tag: 'location',
                img: 'gallery/Locker 2.png',
                title: 'Locker Room 2',
                loc: 'Ortigas Center, Pasig'
            },
            {
                tag: 'gym',
                img: 'gallery/Counter.png',
                title: 'Counter',
                loc: 'Eastwood Branch'
            },
            {
                tag: 'gym',
                img: 'gallery/Boxing ring.png',
                title: 'Boxing Ring',
                loc: 'Makati Branch'
            },
        ];

        /* Filtered list used by lightbox navigation */
        let currentGallery = [...galleryData];
        let lightboxIndex = 0;

        function renderGallery(f) {
            currentGallery = f === 'all' ? galleryData : galleryData.filter(i => i.tag === f);
            document.getElementById('gallery-grid').innerHTML = currentGallery.map((i, idx) => `
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="gcard" onclick="openLightbox(${idx})">
                        <div class="gcard-img-wrap">
                            <img
                                src="${i.img}"
                                alt="${i.title}"
                                class="gcard-photo"
                                loading="lazy"
                                onerror="this.parentElement.classList.add('gcard-no-img')"
                            />
                        </div>
                        <div class="gcard-tag">${i.tag}</div>
                        <div class="gcard-overlay"></div>
                        <div class="gcard-zoom"><i class="ti ti-zoom-in"></i></div>
                        <div class="gcard-info">
                            <div class="fw-bold text-white" style="font-size:.9rem">${i.title}</div>
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

        /* ── LIGHTBOX ── */
        function openLightbox(idx) {
            lightboxIndex = idx;
            updateLightboxContent();
            document.getElementById('lightbox').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('open');
            document.body.style.overflow = '';
        }

        function updateLightboxContent() {
            const item = currentGallery[lightboxIndex];
            const img = document.getElementById('lightbox-img');
            img.src = item.img;
            img.alt = item.title;
            document.getElementById('lightbox-title').textContent = item.title;
            document.getElementById('lightbox-loc').textContent = item.loc;
            document.getElementById('lightbox-counter').textContent = `${lightboxIndex + 1} / ${currentGallery.length}`;
        }

        function lightboxNav(dir) {
            lightboxIndex = (lightboxIndex + dir + currentGallery.length) % currentGallery.length;
            updateLightboxContent();
        }

        function lightboxBackdropClick(e) {
            if (e.target === document.getElementById('lightbox')) closeLightbox();
        }

        /* Keyboard navigation */
        document.addEventListener('keydown', e => {
            if (!document.getElementById('lightbox').classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight') lightboxNav(1);
            if (e.key === 'ArrowLeft') lightboxNav(-1);
        });

        /* ── THEME ── */
        function updateThemeLogos() {
            const isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
            document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
                const nextSrc = isLight ? logo.dataset.logoLight : logo.dataset.logoDark;
                if (logo.getAttribute('src') !== nextSrc) logo.setAttribute('src', nextSrc);
            });
        }

        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
            document.getElementById('themeIcon').className = isDark ? 'ti ti-moon' : 'ti ti-sun';
            updateThemeLogos();
        }

        /* Restore saved theme */
        (function() {
            const saved = localStorage.getItem('fs-theme');
            if (saved) {
                document.documentElement.setAttribute('data-bs-theme', saved);
                document.getElementById('themeIcon').className = saved === 'light' ? 'ti ti-moon' : 'ti ti-sun';
            }
            updateThemeLogos();
        })();

        /* ── FAB ── */
        let fabOpen = false;

        function toggleFab() {
            fabOpen = !fabOpen;
            document.getElementById('fabMenu').classList.toggle('d-none-anim', !fabOpen);
            document.getElementById('fabMain').classList.toggle('open', fabOpen);
        }

        /* ── SCROLL SPY ── */
        window.addEventListener('scroll', () => {
            let cur = 'home';
            ['home', 'gallery', 'locations', 'plans'].forEach(id => {
                const el = document.getElementById(id);
                if (el && window.scrollY >= el.offsetTop - 80) cur = id;
            });
            document.querySelectorAll('.nav-link[href^="#"]').forEach(a => {
                a.classList.toggle('active', a.getAttribute('href') === '#' + cur);
            });
        });

        renderGallery('all');

        /* ── BRANCH MAP SWITCHER ── */
        document.querySelectorAll('.branch-card').forEach((card) => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.branch-card').forEach(c => c.classList.remove('active-branch'));
                card.classList.add('active-branch');
                document.getElementById('branch-map').src = card.dataset.src;
            });
        });
    </script>
</body>

</html>