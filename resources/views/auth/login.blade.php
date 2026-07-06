{{--
|=============================================================
| Dentfluence Infinity — Login
| resources/views/auth/login.blade.php
|=============================================================
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — Dentfluence Infinity</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/logo-mark-purple-square.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                        ui: ['"Inter"', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        /* ── Hard lock: zero scroll, full viewport ── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            height: 100%;
            overflow: hidden; /* prevent any scroll on html */
        }

        body {
            height: 100%;
            overflow: hidden; /* prevent any scroll on body */
            font-family: 'Inter', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ════════════════════════════════════════
           ROOT — exactly 100vh, flex column
        ════════════════════════════════════════ */
        .page-root {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ════════════════════════════════════════
           MAIN SPLIT — flex:1 fills all remaining space
        ════════════════════════════════════════ */
        .main-split {
            flex: 1;
            display: grid;
            grid-template-columns: 520px 1fr;
            overflow: hidden; /* children must not push height */
            min-height: 0;    /* critical: allows flex child to shrink */
        }

        /* ════════════════════════════════════════
           LEFT PANEL
        ════════════════════════════════════════ */
        .left-panel {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 52px 48px 40px;

            background:
                radial-gradient(ellipse 110% 65% at 75% 0%,   rgba(165, 40, 195, 0.65) 0%, transparent 48%),
                radial-gradient(ellipse 80%  60% at 10% 100%, rgba(110, 12, 140, 0.55) 0%, transparent 55%),
                radial-gradient(ellipse 55%  45% at 50% 52%,  rgba(80,   5, 105, 0.28) 0%, transparent 68%),
                linear-gradient(170deg, #450058 0%, #2f0042 22%, #1e002e 52%, #120019 100%);
        }

        /* Subtle grid texture */
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.020) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.020) 1px, transparent 1px);
            background-size: 54px 54px;
            mask-image: radial-gradient(ellipse 88% 88% at 50% 50%, black 25%, transparent 80%);
            pointer-events: none;
        }

        /* Top-right radial glow */
        .left-panel::after {
            content: '';
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(175, 50, 210, 0.22) 0%, transparent 68%);
            top: -190px;
            right: -190px;
            pointer-events: none;
        }

        /* ── Top: wordmark block ── */
        .brand-block {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 30px;
        }

        .brand-site-link {
            margin-top: 16px;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.16em;
            color: rgba(215, 165, 240, 0.65);
            text-decoration: none;
            transition: color .15s;
        }
        .brand-site-link:hover { color: rgba(230, 190, 250, 0.9); }

        /* "DENTFLUENCE" — pure text, no SVG */
        .brand-wordmark {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 58px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #ffffff;
            text-align: center;
            line-height: 1;
            text-shadow: 0 0 60px rgba(190, 90, 220, 0.30);
        }

        /* — INFINITY — */
        .brand-infinity {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 10px;
        }
        .brand-infinity-line {
            width: 58px;
            height: 1px;
            background: rgba(200, 110, 225, 0.50);
        }
        .brand-infinity-text {
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 400;
            letter-spacing: 0.42em;
            text-transform: uppercase;
            color: rgba(215, 165, 240, 0.65);
        }

        /* ── Middle: tagline ── */
        .left-tagline {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        .left-tagline p {
            font-family: 'Cormorant Garamond', serif;
            font-size: 30px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.87);
            line-height: 1.48;
        }
        .left-tagline em {
            font-style: italic;
            color: rgba(210, 135, 240, 0.95);
        }
        .tagline-rule {
            width: 84px;
            height: 2px;
            background: rgba(185, 75, 215, 0.52);
            margin: 13px auto 0;
        }

        /* ── Bottom: feature grid + tagline ── */
        .left-bottom {
            position: relative;
            z-index: 2;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
        }

        /* 4-col icon feature grid */
        .feat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            width: 100%;
            padding-top: 22px;
            border-top: 1px solid rgba(255,255,255,0.09);
            gap: 0;
        }
        .feat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            padding: 0 4px;
            text-align: center;
        }
        .feat-item + .feat-item {
            border-left: 1px solid rgba(255,255,255,0.10);
        }
        .feat-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(215, 165, 238, 0.78);
        }
        .feat-label {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 300;
            line-height: 1.55;
            color: rgba(210, 170, 235, 0.58);
        }

        .left-foot {
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 300;
            color: rgba(195, 150, 218, 0.52);
            text-align: center;
        }
        .left-foot em {
            font-style: italic;
            color: rgba(210, 168, 228, 0.72);
        }

        /* ════════════════════════════════════════
           RIGHT PANEL
        ════════════════════════════════════════ */
        .right-panel {
            background: #ece5f0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 28px;
            position: relative;
            overflow: hidden;
        }

        /* Soft decorative rings */
        .r-deco {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(148, 68, 180, 0.09);
            pointer-events: none;
        }

        /* ════════════════════════════════════════
           LOGIN CARD
        ════════════════════════════════════════ */
        .login-card {
            background: #ffffff;
            border: 1px solid rgba(158, 78, 192, 0.16);
            border-radius: 4px;
            width: 100%;
            max-width: 468px;
            padding: 44px 44px 38px;
            position: relative;
            z-index: 1;
        }

        .card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 46px;
            font-weight: 600;
            color: #18082a;
            text-align: center;
            line-height: 1.05;
            letter-spacing: -0.005em;
        }
        .card-bar {
            width: 54px;
            height: 3px;
            background: #5a006e;
            margin: 12px auto 13px;
        }
        .card-sub {
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 300;
            color: #7a6884;
            text-align: center;
            margin-bottom: 30px;
            letter-spacing: 0.01em;
        }

        /* ── Field ── */
        .f-group { margin-bottom: 18px; }
        .f-label {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #1e0e2c;
            margin-bottom: 7px;
        }
        .f-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .f-icon {
            position: absolute;
            left: 15px;
            color: #aaa0b8;
            pointer-events: none;
            display: flex;
            align-items: center;
            z-index: 1;
        }
        .f-input {
            width: 100%;
            height: 50px;
            padding: 0 46px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 300;
            color: #1e0e2c;
            background: #ffffff;
            border: 1.5px solid #ddd0e8;
            border-radius: 6px;
            outline: none;
            -webkit-appearance: none;
            transition: border-color 140ms, box-shadow 140ms;
        }
        .f-input::placeholder { color: #c4b4d0; font-weight: 300; }
        .f-input:hover:not(:focus) { border-color: #bba0cc; }
        .f-input:focus {
            border-color: #5a006e;
            box-shadow: 0 0 0 3px rgba(90,0,110,0.10);
        }
        .f-input.is-err { border-color: #b52020; }
        .f-input.is-err:focus { box-shadow: 0 0 0 3px rgba(181,32,32,0.10); }

        .f-eye {
            position: absolute;
            right: 13px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: #b0a0c0;
            display: flex;
            align-items: center;
            transition: color 140ms;
        }
        .f-eye:hover { color: #5a006e; }

        /* Forgot link */
        .forgot-row {
            display: flex;
            justify-content: flex-end;
            margin-top: -8px;
            margin-bottom: 22px;
        }
        .forgot-link {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #7c18a0;
            text-decoration: none;
            transition: color 140ms;
        }
        .forgot-link:hover { color: #5a006e; text-decoration: underline; }

        /* ── Login button ── */
        .btn-login {
            width: 100%;
            height: 50px;
            background: #5a006e;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 140ms, box-shadow 140ms, transform 70ms;
        }
        .btn-login:hover {
            background: #480058;
            box-shadow: 0 4px 16px rgba(90,0,110,0.34);
        }
        .btn-login:active { transform: translateY(1px); background: #3a0046; }
        .btn-login:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(90,0,110,0.28); }
        .btn-login:disabled { background: #a888b8; cursor: not-allowed; box-shadow: none; transform: none; }

        /* Spinner */
        .btn-spin {
            width: 15px; height: 15px;
            border: 2px solid rgba(255,255,255,0.30);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.65s linear infinite;
            display: none; flex-shrink: 0;
        }
        .btn-login.loading .btn-spin { display: block; }
        .btn-login.loading .btn-lbl  { opacity: 0.75; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Login method tabs ── */
        .login-tabs {
            display: flex;
            background: #f5eefb;
            border: 1.5px solid #e0cef0;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 22px;
            gap: 4px;
        }
        .tab-btn {
            flex: 1;
            height: 38px;
            border: none;
            border-radius: 5px;
            background: transparent;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #9878b0;
            cursor: pointer;
            transition: background 140ms, color 140ms, box-shadow 140ms;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .tab-btn.active {
            background: #ffffff;
            color: #5a006e;
            box-shadow: 0 1px 6px rgba(90,0,110,0.14);
        }
        .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.6); color: #6a1080; }

        /* ── OTP / PIN digit boxes ── */
        .otp-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 18px 0;
        }
        .otp-box {
            width: 48px;
            height: 54px;
            text-align: center;
            font-family: 'Inter', sans-serif;
            font-size: 22px;
            font-weight: 600;
            color: #1e0e2c;
            border: 1.5px solid #ddd0e8;
            border-radius: 8px;
            outline: none;
            caret-color: #5a006e;
            background: #fff;
            transition: border-color 120ms, box-shadow 120ms;
        }
        .otp-box:focus { border-color: #5a006e; box-shadow: 0 0 0 3px rgba(90,0,110,0.10); }
        .otp-box.filled { border-color: #9040b8; background: #fdf6ff; }

        /* Outlined secondary button */
        .btn-outline {
            width: 100%;
            height: 50px;
            background: transparent;
            border: 1.5px solid #5a006e;
            border-radius: 6px;
            color: #5a006e;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 140ms, box-shadow 140ms;
        }
        .btn-outline:hover { background: rgba(90,0,110,0.06); box-shadow: 0 2px 10px rgba(90,0,110,0.12); }
        .btn-outline:disabled { opacity: 0.45; cursor: not-allowed; }

        /* Resend countdown */
        .resend-row {
            text-align: center;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #9878b0;
            margin-top: 10px;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #7c18a0;
            font-weight: 600;
            cursor: pointer;
            font-size: 12.5px;
            padding: 0;
        }
        .resend-btn:disabled { color: #b0a0c0; cursor: default; }

        /* OTP sent notice */
        .otp-notice {
            background: #f0fdf4;
            border: 1px solid rgba(26,122,69,0.20);
            border-left: 3px solid #1a7a45;
            padding: 9px 12px;
            border-radius: 4px;
            font-size: 12px;
            color: #1a7a45;
            margin-bottom: 4px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ── Forgot Password Modal ── */
        .fp-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10,0,20,0.55);
            backdrop-filter: blur(4px);
            z-index: 9000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .fp-overlay.open { display: flex; }
        .fp-card {
            background: #fff;
            border-radius: 8px;
            width: 100%;
            max-width: 420px;
            padding: 38px 36px 32px;
            position: relative;
            box-shadow: 0 20px 60px rgba(60,0,90,0.28);
        }
        .fp-close {
            position: absolute;
            top: 14px;
            right: 14px;
            background: none;
            border: none;
            cursor: pointer;
            color: #b0a0c0;
            padding: 4px;
            display: flex;
            align-items: center;
            transition: color 120ms;
        }
        .fp-close:hover { color: #5a006e; }
        .fp-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 600;
            color: #18082a;
            text-align: center;
            line-height: 1.1;
        }
        .fp-bar { width: 40px; height: 3px; background: #5a006e; margin: 10px auto 6px; }
        .fp-sub {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #8070a0;
            text-align: center;
            margin-bottom: 22px;
            line-height: 1.55;
        }
        .fp-step { display: none; }
        .fp-step.active { display: block; }
        .fp-steps-indicator {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 22px;
        }
        .fp-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #e0cef0;
            transition: background 200ms, transform 200ms;
        }
        .fp-dot.active { background: #5a006e; transform: scale(1.3); }
        .fp-dot.done { background: #9040b8; }

        /* Security badge */
        .sec-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 18px;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 400;
            color: #a898b4;
        }

        /* Error banners */
        .banner-err {
            background: #fdf5f5;
            border: 1px solid rgba(181,32,32,0.20);
            border-left: 3px solid #b52020;
            padding: 11px 13px;
            display: flex;
            gap: 9px;
            align-items: flex-start;
            margin-bottom: 18px;
            border-radius: 4px;
        }
        .banner-ok {
            background: #f4fbf7;
            border: 1px solid rgba(26,122,69,0.20);
            border-left: 3px solid #1a7a45;
            padding: 11px 13px;
            display: flex;
            gap: 9px;
            align-items: flex-start;
            margin-bottom: 18px;
            border-radius: 4px;
        }
        .field-err {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #b52020;
            margin-top: 4px;
        }

        /* ════════════════════════════════════════
           BOTTOM BAR — fixed height, never grows
        ════════════════════════════════════════ */
        .btm-bar {
            flex-shrink: 0;         /* never grows or shrinks */
            height: 48px;           /* exact fixed height */
            background: #160720;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            flex-wrap: nowrap;
            overflow: hidden;
        }
        .btm-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 400;
            color: rgba(192, 158, 215, 0.44);
            white-space: nowrap;
        }
        .btm-link {
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            color: rgba(192, 158, 215, 0.44);
            text-decoration: none;
            transition: color 140ms;
        }
        .btm-link:hover { color: rgba(215, 185, 235, 0.80); }

        /* ════════════════════════════════════════
           ANIMATIONS
        ════════════════════════════════════════ */
        @keyframes slideLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .al  { animation: slideLeft 0.60s cubic-bezier(0.16,1,0.3,1) both; }
        .au  { animation: slideUp   0.50s cubic-bezier(0.16,1,0.3,1) both; }
        .d1  { animation-delay: 0.05s; }
        .d2  { animation-delay: 0.12s; }
        .d3  { animation-delay: 0.19s; }
        .d4  { animation-delay: 0.26s; }
        .d5  { animation-delay: 0.33s; }
        .d6  { animation-delay: 0.40s; }
        .d7  { animation-delay: 0.47s; }
        .d8  { animation-delay: 0.54s; }

        /* ════════════════════════════════════════
           RESPONSIVE — mobile: left panel hides,
           right panel fills full screen
        ════════════════════════════════════════ */
        @media (max-width: 860px) {
            .main-split { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .btm-bar { padding: 0 20px; }
        }
        @media (max-width: 460px) {
            .login-card { padding: 32px 20px 28px; }
            .card-title { font-size: 36px; }
            .right-panel { padding: 20px 12px; }
        }
    </style>
</head>

<body>
<div class="page-root">

    <!-- ════════════════════════════════════════════
         MAIN SPLIT
    ═════════════════════════════════════════════ -->
    <div class="main-split">

        <!-- ── LEFT PANEL ─────────────────────── -->
        <section class="left-panel" aria-hidden="true">

            <!-- Top: Brand logo -->
            <div class="brand-block al d1">
                <img src="{{ asset('images/logo-full-white.png') }}"
                     alt="Dentfluence — Influence, Grow Beyond Limits"
                     style="width:320px;max-width:90%;height:auto;filter:drop-shadow(0 0 28px rgba(150,40,200,0.30));">
                <a href="https://dentfluence.in" target="_blank" rel="noopener" class="brand-site-link">dentfluence.in</a>
            </div>

            <!-- Middle: tagline -->
            <div class="left-tagline al d3">
                <p>Systemising <em>Dentistry.</em></p>
                <p>Scaling <em>Clarity.</em></p>
                <div class="tagline-rule"></div>
            </div>

            <!-- Bottom: features + foot note -->
            <div class="left-bottom al d5">

                <!-- 4-column feature grid -->
                <div class="feat-grid">
                    <div class="feat-item">
                        <div class="feat-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.20" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="feat-label">One System<br>All Operations</div>
                    </div>
                    <div class="feat-item">
                        <div class="feat-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.20" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="2" width="14" height="20" rx="1"/>
                                <line x1="9" y1="7"  x2="15" y2="7"/>
                                <line x1="9" y1="11" x2="15" y2="11"/>
                                <line x1="9" y1="15" x2="12" y2="15"/>
                                <polyline points="14 17 16 19 20 15"/>
                            </svg>
                        </div>
                        <div class="feat-label">Better Data<br>Better Decisions</div>
                    </div>
                    <div class="feat-item">
                        <div class="feat-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.20" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                        <div class="feat-label">Improve Today<br>Grow Tomorrow</div>
                    </div>
                    <div class="feat-item">
                        <div class="feat-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.20" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <polyline points="9 12 11 14 15 10"/>
                            </svg>
                        </div>
                        <div class="feat-label">Secure<br>Always</div>
                    </div>
                </div>

                <div class="left-foot">
                    Dentfluence Infinity &ndash; Your Practice. <em>Perfectly Managed.</em>
                </div>
            </div>

        </section>

        <!-- ── RIGHT PANEL ────────────────────── -->
        <section class="right-panel">

            <!-- Background rings -->
            <div class="r-deco" style="width:360px;height:360px;bottom:-120px;right:-120px;"></div>
            <div class="r-deco" style="width:220px;height:220px;bottom:-70px;right:-70px;border-color:rgba(148,68,180,0.07);"></div>
            <div class="r-deco" style="width:140px;height:140px;top:-45px;right:100px;"></div>

            <!-- Card -->
            <div class="login-card">

                <!-- Heading -->
                <h1 class="card-title au d1">Welcome Back</h1>
                <div class="card-bar au d2"></div>
                <p class="card-sub au d2">Login to your Dentfluence Infinity account</p>

                {{-- Tab Toggle: Email / Mobile --}}
                <div class="login-tabs au d2">
                    <button type="button" class="tab-btn active" id="tab-email-btn" onclick="switchTab('email')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email
                    </button>
                    <button type="button" class="tab-btn" id="tab-mobile-btn" onclick="switchTab('mobile')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        Mobile OTP
                    </button>
                </div>

                {{-- Error banner --}}
                @if ($errors->any())
                    <div class="banner-err au d2" role="alert">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b52020" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <p style="font-size:12px;font-weight:600;color:#b52020;margin-bottom:2px;">Authentication failed</p>
                            @foreach ($errors->all() as $error)
                                <p style="font-size:11.5px;color:#7a1212;">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Status message --}}
                @if (session('status'))
                    <div class="banner-ok au d2" role="status">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <p style="font-size:12px;color:#1a7a45;">{{ session('status') }}</p>
                    </div>
                @endif

                {{-- ══ EMAIL TAB ══ --}}
                <div id="tab-email-pane">

                {{-- Form --}}
                <form method="POST" action="{{ route('login.post') }}" id="login-form" novalidate>
                    @csrf

                    {{-- Email --}}
                    <div class="f-group au d3">
                        <label class="f-label" for="email">Email Address</label>
                        <div class="f-wrap">
                            <span class="f-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="f-input {{ $errors->has('email') ? 'is-err' : '' }}"
                                value="{{ old('email') }}"
                                placeholder="Enter your email"
                                autocomplete="email"
                                autofocus
                                required
                                aria-label="Email address"
                            >
                        </div>
                        @error('email')
                            <div class="field-err" role="alert">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="f-group au d4">
                        <label class="f-label" for="password">Password</label>
                        <div class="f-wrap">
                            <span class="f-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="f-input {{ $errors->has('password') ? 'is-err' : '' }}"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                aria-label="Password"
                            >
                            <button type="button" class="f-eye" onclick="togglePass()" aria-label="Toggle password" tabindex="-1">
                                <svg id="eye-on" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <div class="field-err" role="alert">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Forgot password --}}
                    <div class="forgot-row au d5">
                        <a href="#" class="forgot-link" onclick="alert('Please contact your administrator to reset your password.'); return false;">Forgot password?</a>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" class="btn-login au d6" id="login-btn">
                        <span class="btn-spin"></span>
                        <span class="btn-lbl">Sign In</span>
                        <svg class="btn-arrow" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>

                </form>
                </div>{{-- /#tab-email-pane --}}

                {{-- ══ MOBILE OTP TAB ══ --}}
                <div id="tab-mobile-pane" style="display:none;">

                    <form method="POST" action="{{ route('mobile.send-otp') }}" id="otp-send-form" novalidate>
                        @csrf
                        <div class="f-group au d3">
                            <label class="f-label" for="mobile">Mobile Number</label>
                            <div class="f-wrap">
                                <span class="f-icon">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="5" y="2" width="14" height="20" rx="2"/>
                                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                                    </svg>
                                </span>
                                <input
                                    type="tel"
                                    id="mobile"
                                    name="mobile"
                                    class="f-input"
                                    placeholder="+91 XXXXX XXXXX"
                                    maxlength="13"
                                    required
                                    aria-label="Mobile number"
                                >
                            </div>
                        </div>
                        <button type="submit" class="btn-login au d4">
                            <span class="btn-spin"></span>
                            <span class="btn-lbl">Send OTP</span>
                        </button>
                    </form>

                    <div id="otp-verify-block" style="display:none; margin-top:20px;">
                        <form method="POST" action="{{ route('mobile.verify') }}" id="otp-verify-form" novalidate>
                            @csrf
                            <input type="hidden" name="mobile" id="otp-mobile-hidden">
                            <div class="f-group au d3">
                                <label class="f-label" for="otp">Enter OTP</label>
                                <div class="f-wrap">
                                    <span class="f-icon">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                    </span>
                                    <input
                                        type="text"
                                        id="otp"
                                        name="otp"
                                        class="f-input"
                                        placeholder="6-digit OTP"
                                        maxlength="6"
                                        inputmode="numeric"
                                        required
                                        aria-label="OTP"
                                    >
                                </div>
                            </div>
                            <button type="submit" class="btn-login au d4">
                                <span class="btn-spin"></span>
                                <span class="btn-lbl">Verify &amp; Login</span>
                            </button>
                        </form>
                    </div>

                </div>{{-- /#tab-mobile-pane --}}

            </div>{{-- /.login-card --}}

        </section>{{-- /.right-panel --}}

    </div>{{-- /.main-split --}}

</div>{{-- /.page-root --}}

<script>
    /* ── Tab switcher ── */
    function switchTab(tab) {
        const emailPane  = document.getElementById('tab-email-pane');
        const mobilePane = document.getElementById('tab-mobile-pane');
        const emailBtn   = document.getElementById('tab-email-btn');
        const mobileBtn  = document.getElementById('tab-mobile-btn');

        if (tab === 'email') {
            emailPane.style.display  = '';
            mobilePane.style.display = 'none';
            emailBtn.classList.add('active');
            mobileBtn.classList.remove('active');
        } else {
            emailPane.style.display  = 'none';
            mobilePane.style.display = '';
            mobileBtn.classList.add('active');
            emailBtn.classList.remove('active');
        }
    }

    /* ── Password visibility toggle ── */
    function togglePass() {
        const inp    = document.getElementById('password');
        const eyeOn  = document.getElementById('eye-on');
        const eyeOff = document.getElementById('eye-off');
        if (inp.type === 'password') {
            inp.type = 'text';
            eyeOn.style.display  = 'none';
            eyeOff.style.display = '';
        } else {
            inp.type = 'password';
            eyeOn.style.display  = '';
            eyeOff.style.display = 'none';
        }
    }

    /* ── Login button loading state ── */
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function () {
            const btn = document.getElementById('login-btn');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('loading');
            }
        });
    }

    /* ── OTP send form: show verify block ── */
    const otpSendForm = document.getElementById('otp-send-form');
    if (otpSendForm) {
        otpSendForm.addEventListener('submit', function (e) {
            const mobile = document.getElementById('mobile').value.trim();
            if (mobile) {
                const hidden = document.getElementById('otp-mobile-hidden');
                if (hidden) hidden.value = mobile;
            }
        });
    }

    /* ── Animate on load ── */
    document.querySelectorAll('.au').forEach(function (el) {
        el.classList.add('animated');
    });
</script>

</body>
</html>
