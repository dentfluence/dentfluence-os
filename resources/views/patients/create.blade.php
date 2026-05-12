<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Patient — Dentfluence PRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Cormorant+Garamond:wght@500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#f9f3fa',
                            100: '#f3e8f4',
                            200: '#dfc5e1',
                            300: '#b95cb7',
                            400: '#8e24aa',
                            500: '#6a0f70',
                            600: '#4e0a53',
                            700: '#380740',
                            800: '#2d0538',
                            900: '#1a0320',
                        }
                    },
                    fontFamily: {
                        display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                        ui: ['"DM Sans"', 'system-ui', 'sans-serif'],
                    },
                    borderRadius: {
                        'none': '0px',
                        DEFAULT: '0px',
                        'sm': '0px',
                        'md': '0px',
                        'lg': '0px',
                        'xl': '0px',
                        '2xl': '0px',
                        'full': '9999px',
                    },
                    boxShadow: {
                        'modal': '0 20px 60px rgba(15, 10, 16, 0.35), 0 4px 16px rgba(15, 10, 16, 0.15)',
                        'input-focus': '0 0 0 3px rgba(106, 15, 112, 0.15)',
                        'tag-active': '0 0 0 2px rgba(106, 15, 112, 0.3)',
                    }
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: linear-gradient(135deg, #1a0320 0%, #380740 40%, #4e0a53 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* Custom scrollbar */
        .modal-scroll::-webkit-scrollbar { width: 5px; }
        .modal-scroll::-webkit-scrollbar-track { background: #f3e8f4; }
        .modal-scroll::-webkit-scrollbar-thumb { background: #b95cb7; }
        .modal-scroll::-webkit-scrollbar-thumb:hover { background: #6a0f70; }

        /* Tab underline animation */
        .tab-btn {
            position: relative;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #5a4a5c;
            padding: 10px 0;
            border: none;
            background: none;
            cursor: pointer;
            transition: color 150ms ease;
            white-space: nowrap;
        }
        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0; right: 0;
            height: 2px;
            background: #6a0f70;
            transform: scaleX(0);
            transition: transform 150ms ease;
        }
        .tab-btn.active {
            color: #6a0f70;
            font-weight: 600;
        }
        .tab-btn.active::after { transform: scaleX(1); }
        .tab-btn:hover { color: #6a0f70; }

        /* Tag toggle buttons */
        .med-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #e5d8e7;
            background: #ffffff;
            color: #5a4a5c;
            cursor: pointer;
            transition: all 100ms ease;
            user-select: none;
        }
        .med-tag:hover { border-color: #b95cb7; color: #4e0a53; }
        .med-tag.selected {
            background: #f3e8f4;
            border-color: #6a0f70;
            color: #4e0a53;
            font-weight: 600;
        }
        .med-tag.selected .tag-check { display: inline; }
        .med-tag .tag-check { display: none; }

        /* Day toggle */
        .day-btn {
            width: 36px;
            height: 36px;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #e5d8e7;
            background: #ffffff;
            color: #5a4a5c;
            cursor: pointer;
            transition: all 100ms ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .day-btn:hover { border-color: #6a0f70; color: #6a0f70; }
        .day-btn.active {
            background: #6a0f70;
            border-color: #6a0f70;
            color: #ffffff;
        }

        /* Input base style */
        .df-input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 400;
            color: #0f0a10;
            background: #ffffff;
            border: 1px solid #e5d8e7;
            border-radius: 0;
            outline: none;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }
        .df-input:focus {
            border-color: #6a0f70;
            box-shadow: 0 0 0 3px rgba(106, 15, 112, 0.12);
        }
        .df-input::placeholder { color: #c0b2c2; }

        .df-textarea {
            width: 100%;
            padding: 10px 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: #0f0a10;
            background: #ffffff;
            border: 1px solid #e5d8e7;
            border-radius: 0;
            outline: none;
            resize: none;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }
        .df-textarea:focus {
            border-color: #6a0f70;
            box-shadow: 0 0 0 3px rgba(106, 15, 112, 0.12);
        }
        .df-textarea::placeholder { color: #c0b2c2; }

        .df-select {
            width: 100%;
            height: 40px;
            padding: 0 32px 0 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: #0f0a10;
            background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a4a5c' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
            border: 1px solid #e5d8e7;
            border-radius: 0;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }
        .df-select:focus {
            border-color: #6a0f70;
            box-shadow: 0 0 0 3px rgba(106, 15, 112, 0.12);
        }

        /* Phone input */
        .phone-prefix {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 10px;
            height: 40px;
            background: #f9f3fa;
            border: 1px solid #e5d8e7;
            border-right: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #380740;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .phone-input {
            flex: 1;
            height: 40px;
            padding: 0 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: #0f0a10;
            background: #ffffff;
            border: 1px solid #e5d8e7;
            outline: none;
            min-width: 0;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }
        .phone-input:focus {
            border-color: #6a0f70;
            box-shadow: 0 0 0 3px rgba(106, 15, 112, 0.12);
            z-index: 1;
        }
        .phone-input::placeholder { color: #c0b2c2; }

        /* Photo upload */
        .photo-upload-circle {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: #f3e8f4;
            border: 2px dashed #b95cb7;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 150ms ease;
            overflow: hidden;
            position: relative;
        }
        .photo-upload-circle:hover {
            background: #ede0ee;
            border-color: #6a0f70;
        }
        .photo-upload-circle input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* Alert tag */
        .alert-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px 3px 10px;
            background: #fdeaea;
            border: 1px solid #b52020;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #b52020;
        }
        .alert-tag button {
            background: none;
            border: none;
            cursor: pointer;
            color: #b52020;
            display: flex;
            align-items: center;
            padding: 0;
            font-size: 14px;
            line-height: 1;
            transition: color 100ms;
        }
        .alert-tag button:hover { color: #7a1212; }

        /* Tab panels */
        .tab-panel { display: none; }
        .tab-panel.active { display: flex; flex-direction: column; gap: 0; }

        /* Field label */
        .df-label {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #5a4a5c;
            margin-bottom: 5px;
        }
        .df-label .req { color: #b52020; margin-left: 2px; }

        /* Section heading inside form */
        .form-section-title {
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #380740;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0e8f1;
            margin-bottom: 14px;
        }

        /* Checkbox custom */
        .df-checkbox {
            width: 16px;
            height: 16px;
            border: 1.5px solid #b95cb7;
            background: #ffffff;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            flex-shrink: 0;
            position: relative;
            transition: all 100ms;
        }
        .df-checkbox:checked {
            background: #6a0f70;
            border-color: #6a0f70;
        }
        .df-checkbox:checked::after {
            content: '';
            position: absolute;
            left: 4px; top: 1px;
            width: 5px; height: 9px;
            border: 2px solid #fff;
            border-top: none;
            border-left: none;
            transform: rotate(40deg);
        }

        /* Primary button */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 24px;
            background: #6a0f70;
            color: #ffffff;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            border: 2px solid #6a0f70;
            border-radius: 0;
            cursor: pointer;
            transition: all 150ms ease;
            white-space: nowrap;
        }
        .btn-primary:hover { background: #4e0a53; border-color: #4e0a53; }
        .btn-primary:active { background: #380740; border-color: #380740; }

        /* Secondary button */
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 20px;
            background: transparent;
            color: #5a4a5c;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            border: 1.5px solid #e5d8e7;
            border-radius: 0;
            cursor: pointer;
            transition: all 150ms ease;
        }
        .btn-secondary:hover { border-color: #b95cb7; color: #380740; background: #f9f3fa; }

        /* Add condition button */
        .btn-add-condition {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #6a0f70;
            border: 1px dashed #b95cb7;
            background: transparent;
            cursor: pointer;
            transition: all 100ms;
        }
        .btn-add-condition:hover { background: #f3e8f4; border-color: #6a0f70; }

        /* Char counter */
        .char-counter {
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            color: #9e8fa0;
            text-align: right;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════════
     MODAL OVERLAY
═══════════════════════════════════════════════ -->
<div class="w-full flex items-center justify-center" style="min-height: 100vh;">

<!-- Modal Container -->
<div class="bg-white w-full flex flex-col" style="max-width: 1060px; height: 92vh; max-height: 860px; box-shadow: 0 20px 60px rgba(15,10,16,0.4), 0 4px 20px rgba(15,10,16,0.2);">

    <!-- ── MODAL HEADER ─────────────────────────── -->
    <div class="flex items-start justify-between px-7 py-5 border-b border-gray-100 flex-shrink-0" style="background: #faf6fb;">
        <div>
            <h2 class="font-display text-2xl font-semibold" style="color: #380740; letter-spacing: -0.01em;">Create Patient</h2>
            <p class="text-xs mt-0.5" style="color: #9e8fa0; font-weight: 400;">Add a new patient to your system</p>
        </div>
        <button onclick="document.getElementById('modal-backdrop').style.display='none'"
                class="flex items-center justify-center w-8 h-8 transition-colors"
                style="color: #9e8fa0; border: 1px solid #e5d8e7; background: white;"
                onmouseover="this.style.color='#380740';this.style.borderColor='#b95cb7'"
                onmouseout="this.style.color='#9e8fa0';this.style.borderColor='#e5d8e7'"
                aria-label="Close">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- ── TAB NAVIGATION ──────────────────────── -->
    <div class="flex items-center gap-8 px-7 border-b border-gray-100 flex-shrink-0" style="background: #faf6fb;">
        <button class="tab-btn active" data-tab="patient-info" onclick="switchTab(this, 'patient-info')">
            Patient Information
        </button>
        <button class="tab-btn" data-tab="medical-history" onclick="switchTab(this, 'medical-history')">
            Medical &amp; Dental History
        </button>
        <button class="tab-btn" data-tab="additional-info" onclick="switchTab(this, 'additional-info')">
            Additional Information
        </button>
    </div>

    <!-- ── MODAL BODY (Scrollable) ─────────────── -->
    <div class="flex-1 overflow-y-auto modal-scroll" style="background: #f9f3fa;">

        <!-- ======================================
             TAB 1: PATIENT INFORMATION
        ======================================= -->
        <div id="tab-patient-info" class="tab-panel active">
            <div class="grid" style="grid-template-columns: 1fr 360px; min-height: 100%;">

                <!-- LEFT COLUMN -->
                <div class="p-6 border-r" style="border-color: #ede0ee;">

                    <!-- Photo Upload -->
                    <div class="flex items-end gap-5 mb-6">
                        <div>
                            <label class="df-label" style="margin-bottom: 8px;">Patient Photo</label>
                            <div class="photo-upload-circle">
                                <input type="file" accept="image/jpeg,image/png">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#b95cb7" stroke-width="1.5">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                                <span class="text-xs mt-1 font-medium" style="color: #6a0f70; font-size: 10px;">Upload Photo</span>
                            </div>
                            <p class="mt-1.5" style="font-size: 10px; color: #9e8fa0;">JPG, PNG (Max 5MB)</p>
                        </div>

                        <!-- Name row beside photo -->
                        <div class="flex-1 grid grid-cols-2 gap-3">
                            <div>
                                <label class="df-label">First Name <span class="req">*</span></label>
                                <input type="text" class="df-input" placeholder="First name" value="">
                            </div>
                            <div>
                                <label class="df-label">Last Name <span class="req">*</span></label>
                                <input type="text" class="df-input" placeholder="Last name" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Preferred Name + DOB -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="df-label">Preferred Name</label>
                            <input type="text" class="df-input" placeholder="Goes by...">
                        </div>
                        <div>
                            <label class="df-label">Date of Birth <span class="req">*</span></label>
                            <div class="relative">
                                <input type="date" class="df-input pr-10" style="color: #0f0a10;">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color: #9e8fa0;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Gender + Marital Status -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="df-label">Gender <span class="req">*</span></label>
                            <select class="df-select">
                                <option value="">Select gender</option>
                                <option value="female" selected>Female</option>
                                <option value="male">Male</option>
                                <option value="other">Other</option>
                                <option value="prefer_not">Prefer not to say</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Marital Status</label>
                            <select class="df-select">
                                <option value="">Select status</option>
                                <option value="single">Single</option>
                                <option value="married" selected>Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Phone Primary + Secondary -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="df-label">Phone (Primary) <span class="req">*</span></label>
                            <div class="flex">
                                <div class="phone-prefix">
                                    <span>🇮🇳</span>
                                    <span>+91</span>
                                </div>
                                <input type="tel" class="phone-input" placeholder="98765 43210" maxlength="10">
                            </div>
                        </div>
                        <div>
                            <label class="df-label">Phone (Secondary)</label>
                            <div class="flex">
                                <div class="phone-prefix">
                                    <span>🇮🇳</span>
                                    <span>+91</span>
                                </div>
                                <input type="tel" class="phone-input" placeholder="91234 56789" maxlength="10">
                            </div>
                        </div>
                    </div>

                    <!-- Email + WhatsApp -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="df-label">Email</label>
                            <input type="email" class="df-input" placeholder="email@example.com">
                        </div>
                        <div>
                            <label class="df-label">WhatsApp</label>
                            <div class="flex">
                                <div class="phone-prefix" style="gap: 4px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    <span>+91</span>
                                </div>
                                <input type="tel" class="phone-input" placeholder="98765 43210" maxlength="10">
                            </div>
                        </div>
                    </div>

                    <!-- Address (full width) -->
                    <div class="mb-4">
                        <label class="df-label">Address</label>
                        <input type="text" class="df-input" placeholder="Street address, landmark...">
                    </div>

                    <!-- City, State, PIN -->
                    <div class="grid gap-3 mb-4" style="grid-template-columns: 1fr 1fr 120px;">
                        <div>
                            <label class="df-label">City</label>
                            <input type="text" class="df-input" placeholder="City">
                        </div>
                        <div>
                            <label class="df-label">State</label>
                            <select class="df-select">
                                <option value="">Select state</option>
                                <option value="MH">Maharashtra</option>
                                <option value="DL">Delhi</option>
                                <option value="KA">Karnataka</option>
                                <option value="TN">Tamil Nadu</option>
                                <option value="GJ">Gujarat</option>
                                <option value="RJ">Rajasthan</option>
                                <option value="UP">Uttar Pradesh</option>
                                <option value="WB">West Bengal</option>
                                <option value="TS">Telangana</option>
                                <option value="AP">Andhra Pradesh</option>
                                <option value="KL">Kerala</option>
                                <option value="PB">Punjab</option>
                                <option value="HR">Haryana</option>
                                <option value="MP">Madhya Pradesh</option>
                                <option value="CH">Chhattisgarh</option>
                                <option value="BR">Bihar</option>
                                <option value="JH">Jharkhand</option>
                                <option value="OD">Odisha</option>
                                <option value="GA">Goa</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">PIN Code</label>
                            <input type="text" class="df-input" placeholder="421201" maxlength="6" style="font-family: 'DM Mono', monospace; letter-spacing: 0.05em;">
                        </div>
                    </div>

                    <!-- Preferred Language, Referred By, Source -->
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div>
                            <label class="df-label">Preferred Language</label>
                            <select class="df-select">
                                <option value="en">English</option>
                                <option value="hi">Hindi</option>
                                <option value="mr">Marathi</option>
                                <option value="gu">Gujarati</option>
                                <option value="ta">Tamil</option>
                                <option value="te">Telugu</option>
                                <option value="kn">Kannada</option>
                                <option value="ml">Malayalam</option>
                                <option value="bn">Bengali</option>
                                <option value="pa">Punjabi</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Referred By</label>
                            <input type="text" class="df-input" placeholder="Referrer name">
                        </div>
                        <div>
                            <label class="df-label">Source</label>
                            <select class="df-select">
                                <option value="">Select source</option>
                                <option value="walkin" selected>Walk-in</option>
                                <option value="referral">Referral</option>
                                <option value="google">Google Search</option>
                                <option value="instagram">Instagram</option>
                                <option value="facebook">Facebook</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="existing">Existing Patient</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="df-label">Notes</label>
                        <textarea class="df-textarea" rows="3" placeholder="Additional notes about the patient..." maxlength="500" id="notes-field" oninput="updateCharCount(this, 'notes-count', 500)"></textarea>
                        <div class="char-counter"><span id="notes-count">0</span>/500</div>
                    </div>

                </div><!-- /LEFT COLUMN -->

                <!-- RIGHT COLUMN -->
                <div class="p-5 flex flex-col gap-5" style="background: #ffffff; border-left: 1px solid #ede0ee;">

                    <!-- Medical History -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="form-section-title" style="border:none; padding:0; margin:0;">Medical History</span>
                            <span style="font-size: 10px; color: #9e8fa0; font-weight: 400;">Select all that apply</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Diabetes
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Hypertension
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Heart Disease
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Thyroid Disorder
                            </button>
                            <button type="button" class="med-tag selected" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Allergies
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Asthma
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Blood Disorder
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Arthritis
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Pregnancy
                            </button>
                            <button type="button" class="med-tag selected" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Smoking
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Cancer
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Hepatitis
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Other
                            </button>
                            <button type="button" class="btn-add-condition" onclick="addConditionPrompt('medical')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Condition
                            </button>
                        </div>
                    </div>

                    <div style="height: 1px; background: #f0e8f1;"></div>

                    <!-- Dental History -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="form-section-title" style="border:none; padding:0; margin:0;">Dental History</span>
                            <span style="font-size: 10px; color: #9e8fa0; font-weight: 400;">Select all that apply</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="med-tag selected" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Sensitivity
                            </button>
                            <button type="button" class="med-tag selected" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Cavities
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Bleeding Gums
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Bad Breath
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Broken Teeth
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Missing Teeth
                            </button>
                            <button type="button" class="med-tag selected" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Crowns
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Braces / Aligners
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Root Canal
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Implants
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Gum Disease
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Teeth Grinding
                            </button>
                            <button type="button" class="med-tag" onclick="toggleTag(this)">
                                <span class="tag-check">✓</span> Other
                            </button>
                            <button type="button" class="btn-add-condition" onclick="addConditionPrompt('dental')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Condition
                            </button>
                        </div>
                    </div>

                    <div style="height: 1px; background: #f0e8f1;"></div>

                    <!-- Chief Complaint -->
                    <div>
                        <label class="df-label">Current Dental Concern / Chief Complaint <span class="req">*</span></label>
                        <select class="df-select">
                            <option value="">Select chief complaint</option>
                            <option value="whitening" selected>Teeth Whitening</option>
                            <option value="pain">Tooth Pain</option>
                            <option value="cleaning">Cleaning / Scaling</option>
                            <option value="rct">Root Canal Treatment</option>
                            <option value="extraction">Extraction</option>
                            <option value="crown">Crown / Cap</option>
                            <option value="implant">Implant</option>
                            <option value="braces">Braces / Aligner</option>
                            <option value="filling">Filling / Restoration</option>
                            <option value="sensitivity">Sensitivity</option>
                            <option value="bleeding">Bleeding Gums</option>
                            <option value="checkup">Routine Check-up</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div style="height: 1px; background: #f0e8f1;"></div>

                    <!-- Medical Alerts -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b52020" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span class="form-section-title" style="border:none; padding:0; margin:0; color: #b52020;">Medical Alerts</span>
                        </div>
                        <div id="alerts-container" class="flex flex-wrap gap-1.5 mb-2">
                            <div class="alert-tag" id="alert-penicillin">
                                Penicillin Allergy
                                <button onclick="removeAlert('alert-penicillin')" aria-label="Remove">×</button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-condition" onclick="addAlert()" style="border-color: #f5a0a0; color: #b52020;"
                                onmouseover="this.style.background='#fdeaea'"
                                onmouseout="this.style.background='transparent'">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Alert
                        </button>
                    </div>

                    <div style="height: 1px; background: #f0e8f1;"></div>

                    <!-- Appointment Preferences -->
                    <div>
                        <span class="form-section-title">Appointment Preference</span>
                        <div class="mb-3">
                            <label class="df-label" style="margin-bottom: 8px;">Preferred Days</label>
                            <div class="flex gap-1.5 flex-wrap">
                                <button type="button" class="day-btn active" onclick="toggleDay(this)">Mon</button>
                                <button type="button" class="day-btn" onclick="toggleDay(this)">Tue</button>
                                <button type="button" class="day-btn" onclick="toggleDay(this)">Wed</button>
                                <button type="button" class="day-btn" onclick="toggleDay(this)" style="background: #f3e8f4; border-color: #b95cb7; color: #4e0a53;">Thu</button>
                                <button type="button" class="day-btn active" onclick="toggleDay(this)">Fri</button>
                                <button type="button" class="day-btn" onclick="toggleDay(this)">Sat</button>
                                <button type="button" class="day-btn" onclick="toggleDay(this)">Sun</button>
                            </div>
                        </div>
                        <div>
                            <label class="df-label">Preferred Time Slot</label>
                            <select class="df-select">
                                <option value="">Any time</option>
                                <option value="morning" selected>Morning (9 AM – 1 PM)</option>
                                <option value="afternoon">Afternoon (1 PM – 5 PM)</option>
                                <option value="evening">Evening (5 PM – 9 PM)</option>
                            </select>
                        </div>
                    </div>

                </div><!-- /RIGHT COLUMN -->

            </div>
        </div><!-- /TAB 1 -->


        <!-- ======================================
             TAB 2: MEDICAL & DENTAL HISTORY (extended)
        ======================================= -->
        <div id="tab-medical-history" class="tab-panel">
            <div class="p-7" style="max-width: 860px;">

                <!-- Medical History detailed -->
                <div class="mb-7">
                    <div class="form-section-title">Detailed Medical History</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="df-label">Known Allergies</label>
                            <textarea class="df-textarea" rows="2" placeholder="List all known allergies and reactions..."></textarea>
                        </div>
                        <div>
                            <label class="df-label">Current Medications</label>
                            <textarea class="df-textarea" rows="2" placeholder="Medications currently taken, dosage..."></textarea>
                        </div>
                        <div>
                            <label class="df-label">Previous Surgeries / Hospitalizations</label>
                            <textarea class="df-textarea" rows="2" placeholder="Previous surgical history..."></textarea>
                        </div>
                        <div>
                            <label class="df-label">Family Medical History</label>
                            <textarea class="df-textarea" rows="2" placeholder="Relevant family medical conditions..."></textarea>
                        </div>
                    </div>
                </div>

                <div style="height: 1px; background: #ede0ee; margin-bottom: 24px;"></div>

                <!-- Dental History detailed -->
                <div class="mb-7">
                    <div class="form-section-title">Detailed Dental History</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="df-label">Previous Dental Treatments</label>
                            <textarea class="df-textarea" rows="2" placeholder="Past treatments received..."></textarea>
                        </div>
                        <div>
                            <label class="df-label">Last Dental Visit</label>
                            <input type="date" class="df-input">
                        </div>
                        <div>
                            <label class="df-label">Dental Anxiety Level</label>
                            <select class="df-select">
                                <option value="">Select level</option>
                                <option value="none">None — Very comfortable</option>
                                <option value="mild">Mild — Slight nervousness</option>
                                <option value="moderate">Moderate — Anxious</option>
                                <option value="severe">Severe — Very anxious / phobic</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Dental Insurance</label>
                            <input type="text" class="df-input" placeholder="Insurance provider name">
                        </div>
                        <div class="col-span-2">
                            <label class="df-label">Additional Dental Notes</label>
                            <textarea class="df-textarea" rows="3" placeholder="Any other relevant dental history..."></textarea>
                        </div>
                    </div>
                </div>

                <div style="height: 1px; background: #ede0ee; margin-bottom: 24px;"></div>

                <!-- Lifestyle -->
                <div>
                    <div class="form-section-title">Lifestyle &amp; Habits</div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="df-label">Smoking / Tobacco</label>
                            <select class="df-select">
                                <option value="never">Never</option>
                                <option value="former">Former smoker</option>
                                <option value="current">Current smoker</option>
                                <option value="tobacco">Tobacco user</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Alcohol Consumption</label>
                            <select class="df-select">
                                <option value="never">Never</option>
                                <option value="occasional">Occasional</option>
                                <option value="moderate">Moderate</option>
                                <option value="frequent">Frequent</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Diet Type</label>
                            <select class="df-select">
                                <option value="veg">Vegetarian</option>
                                <option value="nonveg">Non-vegetarian</option>
                                <option value="vegan">Vegan</option>
                                <option value="jain">Jain</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /TAB 2 -->


        <!-- ======================================
             TAB 3: ADDITIONAL INFORMATION
        ======================================= -->
        <div id="tab-additional-info" class="tab-panel">
            <div class="p-7" style="max-width: 860px;">

                <!-- Emergency Contact -->
                <div class="mb-7">
                    <div class="form-section-title">Emergency Contact</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="df-label">Contact Name</label>
                            <input type="text" class="df-input" placeholder="Full name">
                        </div>
                        <div>
                            <label class="df-label">Relationship</label>
                            <select class="df-select">
                                <option value="">Select relationship</option>
                                <option value="spouse">Spouse</option>
                                <option value="parent">Parent</option>
                                <option value="sibling">Sibling</option>
                                <option value="child">Child</option>
                                <option value="friend">Friend</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="df-label">Emergency Phone</label>
                            <div class="flex">
                                <div class="phone-prefix"><span>🇮🇳</span><span>+91</span></div>
                                <input type="tel" class="phone-input" placeholder="Phone number" maxlength="10">
                            </div>
                        </div>
                        <div>
                            <label class="df-label">Emergency Email</label>
                            <input type="email" class="df-input" placeholder="Emergency contact email">
                        </div>
                    </div>
                </div>

                <div style="height: 1px; background: #ede0ee; margin-bottom: 24px;"></div>

                <!-- Consent & Documents -->
                <div class="mb-7">
                    <div class="form-section-title">Consent &amp; Communication</div>
                    <div class="flex flex-col gap-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" class="df-checkbox mt-0.5" checked>
                            <span style="font-size: 13px; color: #0f0a10; line-height: 1.5;">
                                Patient consents to receive appointment reminders via WhatsApp and SMS.
                            </span>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" class="df-checkbox mt-0.5" checked>
                            <span style="font-size: 13px; color: #0f0a10; line-height: 1.5;">
                                Patient consents to dental treatment and has been informed of procedures.
                            </span>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" class="df-checkbox mt-0.5">
                            <span style="font-size: 13px; color: #0f0a10; line-height: 1.5;">
                                Patient consents to X-rays and diagnostic imaging as required.
                            </span>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" class="df-checkbox mt-0.5">
                            <span style="font-size: 13px; color: #0f0a10; line-height: 1.5;">
                                Patient agrees to the clinic's privacy policy and data storage terms.
                            </span>
                        </label>
                    </div>
                </div>

                <div style="height: 1px; background: #ede0ee; margin-bottom: 24px;"></div>

                <!-- Tags / Labels -->
                <div>
                    <div class="form-section-title">Patient Labels (Internal)</div>
                    <p class="text-xs mb-3" style="color: #9e8fa0;">Tags visible only to clinic staff. Not shared with patient.</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> VIP Patient
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> Corporate Account
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> High Anxiety
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> Diabetic Protocol
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> Child Patient
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> Senior Citizen
                        </button>
                        <button type="button" class="med-tag" onclick="toggleTag(this)">
                            <span class="tag-check">✓</span> Orthodontics
                        </button>
                        <button type="button" class="btn-add-condition">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Label
                        </button>
                    </div>
                </div>

            </div>
        </div><!-- /TAB 3 -->

    </div><!-- /Modal Body -->

    <!-- ── MODAL FOOTER ─────────────────────────── -->
    <div class="flex items-center justify-between px-7 py-4 border-t flex-shrink-0" style="border-color: #ede0ee; background: #faf6fb;">

        <!-- Left: Add another patient -->
        <label class="flex items-center gap-2.5 cursor-pointer select-none">
            <input type="checkbox" class="df-checkbox">
            <span style="font-size: 13px; color: #5a4a5c; font-weight: 400;">Add another patient after saving</span>
        </label>

        <!-- Right: Actions -->
        <div class="flex items-center gap-3">
            <!-- Progress indicator -->
            <div class="flex items-center gap-2 mr-3">
                <span style="font-size: 11px; color: #9e8fa0;">Completion</span>
                <div class="relative" style="width: 80px; height: 4px; background: #ede0ee;">
                    <div class="absolute left-0 top-0 h-full" style="width: 65%; background: #6a0f70; transition: width 300ms;"></div>
                </div>
                <span style="font-size: 11px; color: #6a0f70; font-weight: 600;">65%</span>
            </div>
            <button type="button" class="btn-secondary">Cancel</button>
            <button type="button" class="btn-primary" onclick="createPatient(this)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 6px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Create Patient
            </button>
        </div>
    </div>

</div><!-- /Modal -->

</div><!-- /Overlay -->


<!-- ═══════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════ -->
<script>
    // Tab switching
    function switchTab(btn, tabId) {
        // Deactivate all tabs
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        // Activate selected
        btn.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
    }

    // Toggle medical/dental history tags
    function toggleTag(el) {
        el.classList.toggle('selected');
        updateCompletionProgress();
    }

    // Toggle day buttons
    function toggleDay(el) {
        el.classList.toggle('active');
    }

    // Remove alert
    function removeAlert(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // Add alert prompt
    function addAlert() {
        const name = prompt('Enter medical alert (e.g., Latex Allergy):');
        if (!name || !name.trim()) return;
        const container = document.getElementById('alerts-container');
        const id = 'alert-' + Date.now();
        const div = document.createElement('div');
        div.className = 'alert-tag';
        div.id = id;
        div.innerHTML = name.trim() + ' <button onclick="removeAlert(\'' + id + '\')" aria-label="Remove">×</button>';
        container.appendChild(div);
    }

    // Add condition prompt
    function addConditionPrompt(type) {
        const label = type === 'medical' ? 'medical condition' : 'dental condition';
        const name = prompt('Enter ' + label + ':');
        if (!name || !name.trim()) return;

        // Find the button that was clicked and insert before it
        const buttons = document.querySelectorAll('button[onclick="addConditionPrompt(\'' + type + '\')"]');
        if (!buttons.length) return;
        const insertBefore = buttons[0];

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'med-tag selected';
        btn.setAttribute('onclick', 'toggleTag(this)');
        btn.innerHTML = '<span class="tag-check">✓</span> ' + name.trim();
        insertBefore.parentNode.insertBefore(btn, insertBefore);
    }

    // Character counter for textarea
    function updateCharCount(el, counterId, max) {
        const counter = document.getElementById(counterId);
        if (counter) counter.textContent = el.value.length;
    }

    // Simple completion progress (demo)
    function updateCompletionProgress() {
        const selectedTags = document.querySelectorAll('.med-tag.selected').length;
        const filledInputs = [...document.querySelectorAll('.df-input, .df-textarea, .df-select')]
            .filter(i => i.value && i.value.trim()).length;
        const total = document.querySelectorAll('.df-input, .df-textarea, .df-select').length;
        const tagBonus = Math.min(selectedTags * 2, 20);
        const pct = Math.min(Math.round(((filledInputs / total) * 80) + tagBonus), 100);
        const bar = document.querySelector('[style*="background: #6a0f70; transition"]');
        const label = document.querySelector('[style*="color: #6a0f70; font-weight: 600"]');
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = pct + '%';
    }

    // Create patient handler
    function createPatient(btn) {
        // Basic validation
        const requiredFields = document.querySelectorAll('input[required], select[required]');
        let valid = true;

        // Check first name and last name (first two text inputs in patient tab)
        const textInputs = document.querySelectorAll('#tab-patient-info .df-input');
        const firstNameInput = textInputs[0];
        const lastNameInput = textInputs[1];

        if (!firstNameInput.value.trim() || !lastNameInput.value.trim()) {
            valid = false;
            if (!firstNameInput.value.trim()) {
                firstNameInput.style.borderColor = '#b52020';
                firstNameInput.focus();
            }
            if (!lastNameInput.value.trim()) {
                lastNameInput.style.borderColor = '#b52020';
            }
            setTimeout(() => {
                firstNameInput.style.borderColor = '';
                lastNameInput.style.borderColor = '';
            }, 3000);
        }

        if (!valid) {
            showToast('Please fill in the required fields (First Name and Last Name).', 'error');
            return;
        }

        // Loading state
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="spinning" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;animation:spin 0.6s linear infinite"><path d="M21 12a9 9 0 11-4-7.5"/></svg>Creating...';
        btn.style.opacity = '0.8';

        setTimeout(() => {
            btn.innerHTML = original;
            btn.disabled = false;
            btn.style.opacity = '';
            showToast('Patient created successfully!', 'success');
        }, 1800);
    }

    // Toast notification
    function showToast(message, type) {
        const existing = document.getElementById('df-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'df-toast';
        const isSuccess = type === 'success';
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; max-width: 320px;
            background: ${isSuccess ? '#e8f7ef' : '#fdeaea'};
            border: 1px solid ${isSuccess ? '#1a7a45' : '#b52020'};
            border-left: 3px solid ${isSuccess ? '#1a7a45' : '#b52020'};
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            color: ${isSuccess ? '#1a7a45' : '#b52020'}; font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            animation: slideInToast 150ms ease-out;
        `;
        toast.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                ${isSuccess
                    ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                    : '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'}
            </svg>
            ${message}
        `;
        document.body.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4000);
    }

    // Keyframes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes slideInToast {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);

    // Initialize completion on any input change
    document.addEventListener('change', updateCompletionProgress);
    document.addEventListener('input', updateCompletionProgress);
    updateCompletionProgress();
</script>

</body>
</html>