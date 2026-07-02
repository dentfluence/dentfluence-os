{{--
| Marketing — Settings
| File: resources/views/marketing/settings/index.blade.php
| Phase 2.8 — 6-tab settings UI with Alpine.js tab switching
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Settings'; @endphp

@section('page-title', 'Marketing — Settings')

@section('marketing-content')

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="df-page-header" style="margin-bottom: 28px;">
    <div>
        <h1 class="df-page-title">Marketing Settings</h1>
        <p class="df-page-subtitle">Configure defaults, workflows, schedules, and team preferences.</p>
    </div>
</div>

{{-- ── Settings Layout: Left Tabs + Right Panel ─────────────────────── --}}
<div
    x-data="{ activeTab: 'general' }"
    style="display:flex; gap:24px; align-items:flex-start;"
>

    {{-- ── Left Tab Navigation ───────────────────────────────────────── --}}
    <div style="
        width:200px;
        flex-shrink:0;
        display:flex;
        flex-direction:column;
        background:#fff;
        border:1px solid rgba(185,92,183,0.13);
        border-radius:12px;
        overflow:hidden;
        box-shadow:0 1px 4px rgba(30,10,44,0.04);
    ">
        @php
            $tabs = [
                ['id' => 'general',   'label' => 'General',           'icon' => 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z'],
                ['id' => 'approval',  'label' => 'Approval Workflow',  'icon' => 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11'],
                ['id' => 'schedule',  'label' => 'Scheduling',         'icon' => 'M12 2v10l4 2M12 22a10 10 0 110-20 10 10 0 010 20z'],
                ['id' => 'notif',     'label' => 'Notifications',      'icon' => 'M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0'],
                ['id' => 'perms',     'label' => 'Permissions',        'icon' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
                ['id' => 'ai',        'label' => 'AI Defaults',        'icon' => 'M12 2a9 9 0 019 9c0 3.6-2.1 6.7-5.1 8.2L14 22h-4l-1.9-2.8A9 9 0 0112 2z'],
            ];
        @endphp

        @foreach ($tabs as $tab)
        <button
            @click="activeTab = '{{ $tab['id'] }}'"
            :style="activeTab === '{{ $tab['id'] }}' ? 'background:#faf5ff; color:#6a0f70; border-right:2px solid #6a0f70; font-weight:600;' : 'background:transparent; color:#5a4868; border-right:2px solid transparent; font-weight:400;'"
            style="
                width:100%; text-align:left;
                display:flex; flex-direction:row; align-items:center; justify-content:flex-start; gap:10px;
                padding:12px 16px; box-sizing:border-box;
                font-family:'Inter',sans-serif; font-size:13px;
                border:none; cursor:pointer;
                transition: background 150ms, color 150ms;
                border-bottom: 1px solid rgba(185,92,183,0.07);
            "
            onmouseover="if(this.style.borderRightColor !== 'rgb(106, 15, 112)') this.style.background='#fdf8ff'"
            onmouseout="if(this.style.borderRightColor !== 'rgb(106, 15, 112)') this.style.background='transparent'"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; display:block;">
                <path d="{{ $tab['icon'] }}"/>
            </svg>
            <span style="flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $tab['label'] }}</span>
        </button>
        @endforeach
    </div>

    {{-- ── Right Panel ───────────────────────────────────────────────── --}}
    <div style="flex:1; min-width:0;">

        {{-- ════════════════════════════════════════════════════════════
             TAB 1 — GENERAL
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'general'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">General</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Workspace-wide defaults that apply to all marketing activity.</p>

            <div style="display:flex; flex-direction:column; gap:24px;">

                {{-- Timezone --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin-bottom:6px;">
                        Timezone
                    </label>
                    <select style="
                        width:100%; max-width:320px;
                        border:1px solid rgba(185,92,183,0.25); border-radius:8px;
                        font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                        padding:9px 12px; background:#fff; cursor:pointer;
                        appearance:none; -webkit-appearance:none;
                        background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237a6884' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\");
                        background-repeat:no-repeat; background-position:right 10px center;
                        outline:none;
                    ">
                        <option selected>(UTC+5:30) Asia/Kolkata — India Standard Time</option>
                        <option>(UTC+0:00) UTC — Coordinated Universal Time</option>
                        <option>(UTC+1:00) Europe/London — British Summer Time</option>
                        <option>(UTC-5:00) America/New_York — Eastern Time</option>
                        <option>(UTC-8:00) America/Los_Angeles — Pacific Time</option>
                        <option>(UTC+8:00) Asia/Singapore — Singapore Time</option>
                    </select>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af; margin:5px 0 0;">
                        Used for scheduling posts and reading analytics.
                    </p>
                </div>

                {{-- Default Post Status --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin-bottom:10px;">
                        Default Post Status
                    </label>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        @foreach ([
                            ['val' => 'draft',    'label' => 'Draft',               'desc' => 'Save new posts as drafts for review before publishing.'],
                            ['val' => 'pending',  'label' => 'Pending Approval',    'desc' => 'Route all new posts through the approval workflow.'],
                            ['val' => 'scheduled','label' => 'Scheduled',           'desc' => 'Immediately schedule posts when a time is set.'],
                        ] as $opt)
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                            <input type="radio" name="default_post_status" value="{{ $opt['val'] }}"
                                {{ $opt['val'] === 'draft' ? 'checked' : '' }}
                                style="margin-top:3px; accent-color:#6a0f70;">
                            <div>
                                <span style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c;">{{ $opt['label'] }}</span>
                                <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:2px 0 0;">{{ $opt['desc'] }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Publishing Confirmation Toggle --}}
                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:#faf7fc; border-radius:10px; border:1px solid rgba(185,92,183,0.1);">
                    <div>
                        <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0 0 2px;">Publishing Confirmation</p>
                        <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:0;">Show a confirmation dialog before publishing or scheduling any post.</p>
                    </div>
                    <div x-data="{ enabled: true }">
                        <button @click="enabled = !enabled"
                            :style="enabled ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                            style="
                                position:relative; width:44px; height:24px; border-radius:12px;
                                border:none; cursor:pointer; transition: background 200ms;
                            ">
                            <span :style="enabled ? 'transform:translateX(20px);' : 'transform:translateX(2px);'"
                                style="
                                    position:absolute; top:2px; left:0; width:20px; height:20px;
                                    background:#fff; border-radius:50%;
                                    transition:transform 200ms; display:block;
                                    box-shadow:0 1px 3px rgba(0,0,0,0.2);
                                ">
                            </span>
                        </button>
                    </div>
                </div>

            </div>

            {{-- Save button --}}
            <div style="margin-top:28px; padding-top:20px; border-top:1px solid rgba(185,92,183,0.1);">
                <button style="
                    background: linear-gradient(135deg, #7a1fa2, #6a0f70);
                    border:none; color:#fff; border-radius:8px;
                    font-family:'Inter',sans-serif; font-size:13px; font-weight:500;
                    padding:10px 24px; cursor:pointer; transition:opacity 150ms;
                " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Save Changes
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             TAB 2 — APPROVAL WORKFLOW
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'approval'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">Approval Workflow</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Require content to pass through approval stages before it goes live.</p>

            {{-- Enable toggle --}}
            <div x-data="{ approvalEnabled: true }" style="display:flex; flex-direction:column; gap:20px;">

                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:#faf7fc; border-radius:10px; border:1px solid rgba(185,92,183,0.12);">
                    <div>
                        <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 2px;">Enable Approval Workflow</p>
                        <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:0;">All posts must be approved before publishing.</p>
                    </div>
                    <button @click="approvalEnabled = !approvalEnabled"
                        :style="approvalEnabled ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                        style="position:relative; width:44px; height:24px; border-radius:12px; border:none; cursor:pointer; transition:background 200ms; flex-shrink:0;">
                        <span :style="approvalEnabled ? 'transform:translateX(20px);' : 'transform:translateX(2px);'"
                            style="position:absolute; top:2px; left:0; width:20px; height:20px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                        </span>
                    </button>
                </div>

                {{-- 3 Step approval chain --}}
                <div :style="approvalEnabled ? '' : 'opacity:0.4; pointer-events:none;'">
                    <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0 0 14px;">Approval Steps</p>

                    <div style="display:flex; flex-direction:column; gap:0;">

                        @php
                            $approvalSteps = [
                                ['step' => 1, 'role' => 'Content Writer',  'desc' => 'Creates and submits the post for review.'],
                                ['step' => 2, 'role' => 'Reviewer',        'desc' => 'Reviews content quality, tone, and accuracy.'],
                                ['step' => 3, 'role' => 'Publisher',       'desc' => 'Final approval and publishes the post.'],
                            ];
                        @endphp

                        @foreach ($approvalSteps as $i => $step)
                        <div style="display:flex; align-items:stretch; gap:0;">
                            {{-- Step indicator --}}
                            <div style="display:flex; flex-direction:column; align-items:center; width:40px; flex-shrink:0;">
                                <div style="
                                    width:32px; height:32px; border-radius:50%;
                                    background: linear-gradient(135deg, #7a1fa2, #6a0f70);
                                    color:#fff; display:flex; align-items:center; justify-content:center;
                                    font-family:'Inter',sans-serif; font-size:12px; font-weight:700;
                                    flex-shrink:0;
                                ">{{ $step['step'] }}</div>
                                @if (!$loop->last)
                                <div style="width:2px; flex:1; background:rgba(106,15,112,0.15); min-height:20px;"></div>
                                @endif
                            </div>
                            {{-- Card --}}
                            <div style="
                                flex:1; margin-left:14px;
                                background:#faf7fc; border:1px solid rgba(185,92,183,0.12); border-radius:10px;
                                padding:14px 16px;
                                margin-bottom: {{ $loop->last ? '0' : '10px' }};
                            ">
                                <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:600; color:#1e0a2c; margin:0 0 3px;">{{ $step['role'] }}</p>
                                <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:0;">{{ $step['desc'] }}</p>
                            </div>
                        </div>
                        @endforeach

                    </div>
                </div>

                {{-- Notify toggles --}}
                <div :style="approvalEnabled ? '' : 'opacity:0.4; pointer-events:none;'" style="display:flex; flex-direction:column; gap:12px;">
                    <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0;">Notification Triggers</p>

                    @foreach ([
                        ['label' => 'Notify reviewer when post is submitted for approval', 'default' => true],
                        ['label' => 'Notify author on approval', 'default' => true],
                        ['label' => 'Notify author on rejection with comments', 'default' => true],
                    ] as $notif)
                    <div x-data="{ on: {{ $notif['default'] ? 'true' : 'false' }} }" style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border:1px solid rgba(185,92,183,0.1); border-radius:8px;">
                        <span style="font-family:'Inter',sans-serif; font-size:12px; color:#5a4868;">{{ $notif['label'] }}</span>
                        <button @click="on = !on" :style="on ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                            style="position:relative; width:38px; height:20px; border-radius:10px; border:none; cursor:pointer; transition:background 200ms; flex-shrink:0;">
                            <span :style="on ? 'transform:translateX(18px);' : 'transform:translateX(2px);'"
                                style="position:absolute; top:2px; left:0; width:16px; height:16px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 2px rgba(0,0,0,0.2);">
                            </span>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>

            <div style="margin-top:28px; padding-top:20px; border-top:1px solid rgba(185,92,183,0.1);">
                <button style="background:linear-gradient(135deg,#7a1fa2,#6a0f70); border:none; color:#fff; border-radius:8px; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; padding:10px 24px; cursor:pointer;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             TAB 3 — SCHEDULING
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'schedule'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">Scheduling</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Set default publish times per platform and manage posting queue behaviour.</p>

            {{-- Platform default times --}}
            <div style="margin-bottom:28px;">
                <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0 0 14px;">Default Publish Times</p>

                @php
                    $platforms = [
                        ['name' => 'Instagram',        'color' => 'linear-gradient(135deg,#f09433,#dc2743)', 'time' => '10:00'],
                        ['name' => 'Facebook',         'color' => '#1877f2',                                  'time' => '11:00'],
                        ['name' => 'Google Business',  'color' => '#fff',                                     'time' => '09:00', 'dark' => true],
                        ['name' => 'WhatsApp Business','color' => '#25d366',                                  'time' => '08:30'],
                        ['name' => 'WordPress',        'color' => '#21759b',                                  'time' => '07:00'],
                        ['name' => 'Google Analytics', 'color' => '#f57c00',                                  'time' => '12:00'],
                    ];
                @endphp

                <div style="display:flex; flex-direction:column; gap:0; border:1px solid rgba(185,92,183,0.12); border-radius:10px; overflow:hidden;">
                    @foreach ($platforms as $i => $p)
                    <div style="
                        display:flex; align-items:center; justify-content:space-between;
                        padding:12px 16px;
                        {{ !$loop->last ? 'border-bottom:1px solid rgba(185,92,183,0.08);' : '' }}
                        background:#fff;
                    ">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="
                                width:28px; height:28px; border-radius:7px;
                                background:{{ $p['color'] }};
                                {{ isset($p['dark']) ? 'border:1px solid #e5e7eb;' : '' }}
                                display:flex; align-items:center; justify-content:center; flex-shrink:0;
                            ">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ isset($p['dark']) ? '#5a4868' : '#fff' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                </svg>
                            </div>
                            <span style="font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;">{{ $p['name'] }}</span>
                        </div>
                        <input type="time" value="{{ $p['time'] }}" style="
                            border:1px solid rgba(185,92,183,0.2); border-radius:6px;
                            font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                            padding:6px 10px; outline:none; cursor:pointer;
                            accent-color:#6a0f70;
                        ">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Queue Spacing Slider --}}
            <div x-data="{ spacing: 15 }" style="margin-bottom:28px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0;">Queue Spacing</p>
                    <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#6a0f70;" x-text="spacing + ' min'"></span>
                </div>
                <input type="range" x-model="spacing" min="5" max="120" step="5"
                    style="width:100%; accent-color:#6a0f70; cursor:pointer;">
                <div style="display:flex; justify-content:space-between; margin-top:4px;">
                    <span style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">5 min</span>
                    <span style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">120 min</span>
                </div>
                <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:6px 0 0;">Minimum gap between queued posts on the same platform.</p>
            </div>

            {{-- Blackout Days --}}
            <div>
                <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0 0 8px;">Blackout Days</p>
                <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:0 0 12px;">No posts will be published on these days (e.g. holidays).</p>
                <input type="text" placeholder="Add dates — e.g. 2026-01-26, 2026-08-15" style="
                    width:100%; max-width:400px;
                    border:1px solid rgba(185,92,183,0.2); border-radius:8px;
                    font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                    padding:9px 12px; outline:none; box-sizing:border-box;
                ">
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                    @foreach (['Jan 26', 'Aug 15', 'Oct 2'] as $day)
                    <span style="
                        display:inline-flex; align-items:center; gap:6px;
                        background:#faf5ff; border:1px solid rgba(185,92,183,0.2);
                        color:#5a4868; border-radius:6px;
                        font-family:'Inter',sans-serif; font-size:12px;
                        padding:4px 10px;
                    ">
                        {{ $day }}
                        <button style="background:none; border:none; cursor:pointer; color:#9ca3af; padding:0; line-height:1; font-size:14px;">&times;</button>
                    </span>
                    @endforeach
                </div>
            </div>

            <div style="margin-top:28px; padding-top:20px; border-top:1px solid rgba(185,92,183,0.1);">
                <button style="background:linear-gradient(135deg,#7a1fa2,#6a0f70); border:none; color:#fff; border-radius:8px; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; padding:10px 24px; cursor:pointer;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             TAB 4 — NOTIFICATIONS
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'notif'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">Notifications</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Choose what alerts you receive and through which channels.</p>

            {{-- Header row --}}
            <div style="
                display:grid; grid-template-columns:1fr 90px 90px 90px;
                gap:0; padding:0 0 10px 0;
                border-bottom:2px solid rgba(185,92,183,0.1);
                margin-bottom:4px;
            ">
                <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#7a6884; text-transform:uppercase; letter-spacing:.5px;">Event</span>
                <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#7a6884; text-transform:uppercase; letter-spacing:.5px; text-align:center;">In-App</span>
                <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#7a6884; text-transform:uppercase; letter-spacing:.5px; text-align:center;">Email</span>
                <span style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#7a6884; text-transform:uppercase; letter-spacing:.5px; text-align:center;">WhatsApp</span>
            </div>

            @php
                $notifEvents = [
                    ['event' => 'Post published successfully',       'inapp' => true,  'email' => false, 'wa' => false],
                    ['event' => 'Post submitted for approval',       'inapp' => true,  'email' => true,  'wa' => false],
                    ['event' => 'Post approved',                     'inapp' => true,  'email' => true,  'wa' => false],
                    ['event' => 'Post rejected with comments',       'inapp' => true,  'email' => true,  'wa' => true],
                    ['event' => 'Scheduled post failed to publish',  'inapp' => true,  'email' => true,  'wa' => true],
                    ['event' => 'New lead captured from campaign',   'inapp' => true,  'email' => false, 'wa' => true],
                ];
            @endphp

            @foreach ($notifEvents as $notif)
            <div x-data="{
                    inapp: {{ $notif['inapp'] ? 'true' : 'false' }},
                    email: {{ $notif['email'] ? 'true' : 'false' }},
                    wa:    {{ $notif['wa']    ? 'true' : 'false' }}
                }" style="
                display:grid; grid-template-columns:1fr 90px 90px 90px;
                align-items:center; gap:0;
                padding:12px 0;
                border-bottom:1px solid rgba(185,92,183,0.07);
            ">
                <span style="font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;">{{ $notif['event'] }}</span>

                {{-- In-App toggle --}}
                <div style="display:flex; justify-content:center;">
                    <button @click="inapp = !inapp" :style="inapp ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                        style="position:relative; width:36px; height:20px; border-radius:10px; border:none; cursor:pointer; transition:background 200ms;">
                        <span :style="inapp ? 'transform:translateX(16px);' : 'transform:translateX(2px);'"
                            style="position:absolute; top:2px; left:0; width:16px; height:16px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 2px rgba(0,0,0,0.2);">
                        </span>
                    </button>
                </div>
                {{-- Email toggle --}}
                <div style="display:flex; justify-content:center;">
                    <button @click="email = !email" :style="email ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                        style="position:relative; width:36px; height:20px; border-radius:10px; border:none; cursor:pointer; transition:background 200ms;">
                        <span :style="email ? 'transform:translateX(16px);' : 'transform:translateX(2px);'"
                            style="position:absolute; top:2px; left:0; width:16px; height:16px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 2px rgba(0,0,0,0.2);">
                        </span>
                    </button>
                </div>
                {{-- WhatsApp toggle --}}
                <div style="display:flex; justify-content:center;">
                    <button @click="wa = !wa" :style="wa ? 'background:#25d366;' : 'background:#d1d5db;'"
                        style="position:relative; width:36px; height:20px; border-radius:10px; border:none; cursor:pointer; transition:background 200ms;">
                        <span :style="wa ? 'transform:translateX(16px);' : 'transform:translateX(2px);'"
                            style="position:absolute; top:2px; left:0; width:16px; height:16px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 2px rgba(0,0,0,0.2);">
                        </span>
                    </button>
                </div>
            </div>
            @endforeach

            <div style="margin-top:28px; padding-top:20px; border-top:1px solid rgba(185,92,183,0.1);">
                <button style="background:linear-gradient(135deg,#7a1fa2,#6a0f70); border:none; color:#fff; border-radius:8px; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; padding:10px 24px; cursor:pointer;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             TAB 5 — PERMISSIONS
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'perms'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">Permissions</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Role-based access matrix for the Marketing module. Contact your admin to change permissions.</p>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-family:'Inter',sans-serif; font-size:12px;">

                    {{-- Header --}}
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:10px 14px; font-weight:600; color:#7a6884; font-size:11px; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid rgba(185,92,183,0.1); min-width:180px;">Ability</th>
                            @foreach (['Admin','Marketing Manager','Content Writer','Reviewer','Viewer'] as $role)
                            <th style="text-align:center; padding:10px 12px; font-weight:600; color:#7a6884; font-size:11px; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid rgba(185,92,183,0.1); white-space:nowrap;">{{ $role }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                    @php
                        // columns: Admin, Mktg Mgr, Content Writer, Reviewer, Viewer
                        // true = ✓, false = –
                        $matrix = [
                            ['ability' => 'View posts',             'perms' => [true,  true,  true,  true,  true]],
                            ['ability' => 'Create / edit posts',    'perms' => [true,  true,  true,  false, false]],
                            ['ability' => 'Submit for approval',    'perms' => [true,  true,  true,  false, false]],
                            ['ability' => 'Approve / reject',       'perms' => [true,  true,  false, true,  false]],
                            ['ability' => 'Publish directly',       'perms' => [true,  true,  false, false, false]],
                            ['ability' => 'Schedule posts',         'perms' => [true,  true,  false, false, false]],
                            ['ability' => 'Manage integrations',    'perms' => [true,  true,  false, false, false]],
                            ['ability' => 'View analytics',         'perms' => [true,  true,  false, true,  true]],
                            ['ability' => 'Manage settings',        'perms' => [true,  false, false, false, false]],
                            ['ability' => 'Manage team roles',      'perms' => [true,  false, false, false, false]],
                        ];
                    @endphp

                    @foreach ($matrix as $i => $row)
                    <tr style="{{ $i % 2 === 0 ? 'background:#faf7fc;' : 'background:#fff;' }}">
                        <td style="padding:10px 14px; color:#1e0a2c; font-weight:400; border-bottom:1px solid rgba(185,92,183,0.06);">
                            {{ $row['ability'] }}
                        </td>
                        @foreach ($row['perms'] as $has)
                        <td style="text-align:center; padding:10px 12px; border-bottom:1px solid rgba(185,92,183,0.06);">
                            @if ($has)
                                <span style="color:#16a34a; font-size:16px;" title="Allowed">✓</span>
                            @else
                                <span style="color:#d1d5db; font-size:14px;" title="Not allowed">—</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px; padding:12px 14px; background:#faf7fc; border-radius:8px; border:1px solid rgba(185,92,183,0.1);">
                <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline; vertical-align:-1px; margin-right:4px;">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    This matrix is read-only. Role permissions are managed by your Dentfluence admin.
                </p>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             TAB 6 — AI DEFAULTS
        ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'ai'" x-cloak
            style="background:#fff; border:1px solid rgba(185,92,183,0.13); border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(30,10,44,0.04);">

            <h2 style="font-family:'Inter',sans-serif; font-size:16px; font-weight:600; color:#1e0a2c; margin:0 0 6px;">AI Defaults</h2>
            <p style="font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; margin:0 0 24px;">Control how AI generates captions, hashtags, and scheduling suggestions.</p>

            <div style="display:flex; flex-direction:column; gap:24px;">

                {{-- Language select --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin-bottom:6px;">
                        Default Content Language
                    </label>
                    <select style="
                        width:100%; max-width:280px;
                        border:1px solid rgba(185,92,183,0.25); border-radius:8px;
                        font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c;
                        padding:9px 12px; background:#fff; cursor:pointer; outline:none;
                        appearance:none; -webkit-appearance:none;
                        background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237a6884' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\");
                        background-repeat:no-repeat; background-position:right 10px center;
                    ">
                        <option selected>English</option>
                        <option>Hindi</option>
                        <option>Marathi</option>
                        <option>Hinglish (English + Hindi mix)</option>
                        <option>Tamil</option>
                        <option>Telugu</option>
                    </select>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af; margin:5px 0 0;">AI will generate captions in this language by default.</p>
                </div>

                {{-- Hashtag count slider --}}
                <div x-data="{ hashCount: 10 }">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <label style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c;">
                            Default Hashtag Count
                        </label>
                        <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:600; color:#6a0f70;" x-text="hashCount + ' hashtags'"></span>
                    </div>
                    <input type="range" x-model="hashCount" min="0" max="30" step="1"
                        style="width:100%; accent-color:#6a0f70; cursor:pointer;">
                    <div style="display:flex; justify-content:space-between; margin-top:4px;">
                        <span style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">0</span>
                        <span style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">30</span>
                    </div>
                    <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:6px 0 0;">AI will suggest this many hashtags per post. You can always edit before publishing.</p>
                </div>

                {{-- Auto-suggest best time --}}
                <div x-data="{ autoTime: true }" style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:#faf7fc; border-radius:10px; border:1px solid rgba(185,92,183,0.1);">
                    <div>
                        <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin:0 0 2px;">Auto-Suggest Best Time to Post</p>
                        <p style="font-family:'Inter',sans-serif; font-size:11px; color:#7a6884; margin:0;">AI will recommend optimal publish times based on audience activity data.</p>
                    </div>
                    <button @click="autoTime = !autoTime"
                        :style="autoTime ? 'background:#6a0f70;' : 'background:#d1d5db;'"
                        style="position:relative; width:44px; height:24px; border-radius:12px; border:none; cursor:pointer; transition:background 200ms; flex-shrink:0; margin-left:16px;">
                        <span :style="autoTime ? 'transform:translateX(20px);' : 'transform:translateX(2px);'"
                            style="position:absolute; top:2px; left:0; width:20px; height:20px; background:#fff; border-radius:50%; transition:transform 200ms; display:block; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                        </span>
                    </button>
                </div>

                {{-- AI tone preset --}}
                <div>
                    <label style="display:block; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; color:#1e0a2c; margin-bottom:10px;">Default Caption Tone</label>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        @foreach (['Professional', 'Friendly', 'Informative', 'Promotional'] as $i => $tone)
                        <label style="cursor:pointer;">
                            <input type="radio" name="ai_tone" value="{{ strtolower($tone) }}" {{ $i === 0 ? 'checked' : '' }} style="display:none;" class="ai-tone-radio">
                            <span onclick="document.querySelectorAll('.ai-tone-badge').forEach(el => { el.style.background='#f3f4f6'; el.style.color='#5a4868'; el.style.borderColor='#e5e7eb'; }); this.style.background='#faf5ff'; this.style.color='#6a0f70'; this.style.borderColor='rgba(106,15,112,0.3)';"
                                class="ai-tone-badge" style="
                                display:inline-block; padding:7px 16px; border-radius:20px;
                                font-family:'Inter',sans-serif; font-size:12px; font-weight:500;
                                border:1px solid;
                                {{ $i === 0 ? 'background:#faf5ff; color:#6a0f70; border-color:rgba(106,15,112,0.3);' : 'background:#f3f4f6; color:#5a4868; border-color:#e5e7eb;' }}
                                transition: all 150ms; cursor:pointer;
                            ">{{ $tone }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

            </div>

            <div style="margin-top:28px; padding-top:20px; border-top:1px solid rgba(185,92,183,0.1);">
                <button style="background:linear-gradient(135deg,#7a1fa2,#6a0f70); border:none; color:#fff; border-radius:8px; font-family:'Inter',sans-serif; font-size:13px; font-weight:500; padding:10px 24px; cursor:pointer;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Save Changes</button>
            </div>
        </div>

    </div>{{-- /right panel --}}
</div>{{-- /settings layout --}}

@endsection
