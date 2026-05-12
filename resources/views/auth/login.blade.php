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
    <title>Login — Dentfluence Infinity</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300;1,9..40,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                        ui: ['"DM Sans"', 'system-ui', 'sans-serif'],
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
            font-family: 'DM Sans', system-ui, sans-serif;
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
        }

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
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 300;
            line-height: 1.55;
            color: rgba(210, 170, 235, 0.58);
        }

        .left-foot {
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
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

        /* OR divider */
        .or-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #b4a4c4;
        }
        .or-divider::before, .or-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e4d8f0;
        }

        /* Google button */
        .btn-google {
            width: 100%;
            height: 50px;
            background: #ffffff;
            border: 1.5px solid #ddd0e8;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #1e0e2c;
            cursor: pointer;
            text-decoration: none;
            transition: border-color 140ms, background 140ms, box-shadow 140ms;
        }
        .btn-google:hover {
            border-color: #c0a0d8;
            background: #fdf8ff;
            box-shadow: 0 2px 10px rgba(90,0,110,0.08);
        }

        /* Security badge */
        .sec-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 18px;
            font-family: 'DM Sans', sans-serif;
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
            font-family: 'DM Sans', sans-serif;
            font-size: 11.5px;
            font-weight: 400;
            color: rgba(192, 158, 215, 0.44);
            white-space: nowrap;
        }
        .btm-link {
            font-family: 'DM Sans', sans-serif;
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

            <!-- Top: Brand wordmark — TEXT ONLY, no SVG logo -->
            <div class="brand-block al d1">
                <div class="brand-wordmark">DENTFLUENCE</div>
                <div class="brand-infinity">
                    <div class="brand-infinity-line"></div>
                    <span class="brand-infinity-text">Infinity</span>
                    <div class="brand-infinity-line"></div>
                </div>
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

                {{-- Form --}}
                <form method="POST" action="#" id="login-form" novalidate>
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

                    {{-- Forgot --}}
                    <div class="forgot-row au d4">
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
                        @endif
                    </div>

                    {{-- Submit --}}
                    <button type="submit" id="login-btn" class="btn-login au d5" aria-live="polite">
                        <span class="btn-spin" id="btn-spin" aria-hidden="true"></span>
                        <span class="btn-lbl" id="btn-lbl">LOGIN</span>
                    </button>

                </form>

                <!-- OR divider -->
                <div class="or-divider au d5">or continue with</div>

                <!-- Google -->
                <a
                    href="#"
                    class="btn-google au d6"
                    aria-label="Sign in with Google"
                    onclick="if(this.getAttribute('href')==='#'){event.preventDefault();}"
                >
                    <svg width="19" height="19" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Continue with Google
                </a>

                <!-- Security badge -->
                <div class="sec-badge au d7">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Your data is secure and encrypted
                </div>

            </div>{{-- /login-card --}}
        </section>

    </div>{{-- /main-split --}}

    <!-- ════════════════════════════════════════════
         BOTTOM BAR — fixed 48px, never scrolls
    ═════════════════════════════════════════════ -->
    <footer class="btm-bar" role="contentinfo">

        <div class="btm-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Auto logout after 8 hours of inactivity
        </div>

        <div class="btm-item">
            &copy; {{ date('Y') }} Dentfluence Infinity. All rights reserved.
        </div>

        <div class="btm-item">
            Need help?&nbsp;<a href="mailto:support@tulipdental.in" class="btm-link">Contact support</a>
        </div>

    </footer>

</div>{{-- /page-root --}}

<script>
    function togglePass() {
        var inp = document.getElementById('password');
        var on  = document.getElementById('eye-on');
        var off = document.getElementById('eye-off');
        if (inp.type === 'password') {
            inp.type = 'text';
            on.style.display  = 'none';
            off.style.display = 'block';
        } else {
            inp.type = 'password';
            on.style.display  = 'block';
            off.style.display = 'none';
        }
    }

    document.getElementById('login-form').addEventListener('submit', function(e) {
        var email = document.getElementById('email');
        var pass  = document.getElementById('password');
        var ok    = true;

        if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            email.classList.add('is-err'); ok = false;
        }
        if (!pass.value.trim()) {
            pass.classList.add('is-err'); ok = false;
        }

        if (!ok) {
            e.preventDefault();
            if (email.classList.contains('is-err')) email.focus();
            else pass.focus();
            return;
        }

        var btn  = document.getElementById('login-btn');
        var spin = document.getElementById('btn-spin');
        var lbl  = document.getElementById('btn-lbl');
        btn.disabled = true;
        btn.classList.add('loading');
        spin.style.display = 'block';
        lbl.textContent = 'Signing in\u2026';
    });

    ['email', 'password'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function() { this.classList.remove('is-err'); });
    });
</script>
</body>
</html>