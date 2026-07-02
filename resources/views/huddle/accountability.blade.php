@extends('layouts.app')

@section('title', 'Accountability — ' . $today->format('D, d M Y'))

@push('scripts')
<style>
    /* ── Inter font ───────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    #df-content-inner, #df-content-inner * {
        font-family: 'Inter', sans-serif !important;
    }

    /* ── Card base ──────────────────────────────────────── */
    .acct-card {
        background: #ffffff;
        border: 1px solid #e8d5f0;
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 18px;
        transition: box-shadow .15s;
    }
    .acct-card:hover {
        box-shadow: 0 4px 18px rgba(56,7,64,.08);
    }

    /* ── Avatar ─────────────────────────────────────────── */
    .acct-avatar {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6a0f70, #380740);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: .5px;
    }

    /* ── Progress bar ───────────────────────────────────── */
    .pbar-track {
        height: 8px;
        background: #f5eefa;
        border-radius: 99px;
        overflow: hidden;
        flex: 1;
        min-width: 80px;
    }
    .pbar-fill {
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #6a0f70, #380740);
        transition: width .4s ease;
    }
    .pbar-fill.done-all {
        background: linear-gradient(90deg, #059669, #047857);
    }
    .pbar-fill.zero {
        background: #e8d5f0;
    }

    /* ── Badges ─────────────────────────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.6;
    }
    .badge-overdue  { background: #fef2f2; color: #dc2626; }
    .badge-done     { background: #ecfdf5; color: #059669; }
    .badge-assigned { background: #f5eefa; color: #6a0f70; }

    /* ── Summary bar ────────────────────────────────────── */
    .summary-bar {
        background: linear-gradient(135deg, #380740 0%, #6a0f70 100%);
        border-radius: 14px;
        padding: 20px 28px;
        color: #fff;
        display: flex;
        gap: 32px;
        flex-wrap: wrap;
        align-items: center;
    }
    .summary-stat { text-align: center; }
    .summary-stat .num { font-size: 28px; font-weight: 700; line-height: 1; }
    .summary-stat .lbl { font-size: 12px; opacity: .75; margin-top: 4px; }

    /* ── Page header ────────────────────────────────────── */
    .page-eyebrow {
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #6a0f70;
        margin-bottom: 4px;
    }
    .page-title {
        font-size: 26px;
        font-weight: 700;
        color: #380740;
        margin: 0;
    }
    .page-subtitle {
        font-size: 14px;
        color: #9b59b6;
        margin-top: 2px;
    }

    /* ── Section header ─────────────────────────────────── */
    .section-heading {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: #6a0f70;
        padding-bottom: 8px;
        border-bottom: 2px solid #e8d5f0;
        margin-bottom: 14px;
    }

    /* ── Empty state ────────────────────────────────────── */
    .empty-state {
        background: #f5eefa;
        border-radius: 12px;
        padding: 32px;
        text-align: center;
        color: #9b59b6;
        font-size: 14px;
    }
</style>
@endpush

@section('content')
<div class="hd-escape" style="margin:-28px -32px -48px;">
<div style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

    {{-- ── Header ────────────────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <div class="page-eyebrow">Daily Huddle</div>
            <h1 class="page-title">Team Accountability</h1>
            <p class="page-subtitle">{{ $today->format('l, d F Y') }}</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="{{ route('huddle.index') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e8d5f0;border-radius:8px;font-size:13px;font-weight:600;color:#6a0f70;text-decoration:none;background:#fff;">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"
                     style="display:inline-block!important;width:14px;height:14px;">
                    <path d="M12 15l-5-5 5-5" stroke="#6a0f70" stroke-width="2"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Huddle
            </a>
            <a href="{{ route('tasks.create') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;text-decoration:none;background:linear-gradient(135deg,#6a0f70,#380740);">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"
                     style="display:inline-block!important;width:14px;height:14px;">
                    <path d="M10 4v12M4 10h12" stroke="#fff" stroke-width="2"
                          stroke-linecap="round"/>
                </svg>
                New Task
            </a>
        </div>
    </div>

    {{-- ── Summary bar ────────────────────────────────────────────────────── --}}
    @php
        $totalAssigned = $staff->sum('assigned');
        $totalDone     = $staff->sum('done');
        $totalOverdue  = $staff->sum('overdue');
        $teamPct       = $totalAssigned > 0 ? round($totalDone / $totalAssigned * 100) : 0;
        $perfectStaff  = $staff->filter(fn($s) => $s['assigned'] > 0 && $s['pct'] === 100)->count();
    @endphp

    <div class="summary-bar" style="margin-bottom:28px;">
        <div class="summary-stat">
            <div class="num">{{ $totalAssigned }}</div>
            <div class="lbl">Tasks Today</div>
        </div>
        <div class="summary-stat">
            <div class="num">{{ $totalDone }}</div>
            <div class="lbl">Completed</div>
        </div>
        <div class="summary-stat">
            <div class="num" style="{{ $totalOverdue > 0 ? 'color:#fca5a5;' : '' }}">{{ $totalOverdue }}</div>
            <div class="lbl">Overdue</div>
        </div>
        <div class="summary-stat">
            <div class="num">{{ $teamPct }}%</div>
            <div class="lbl">Team Completion</div>
        </div>
        @if($perfectStaff > 0)
        <div class="summary-stat">
            <div class="num" style="color:#86efac;">{{ $perfectStaff }}</div>
            <div class="lbl">100% Done</div>
        </div>
        @endif

        {{-- Team progress bar --}}
        <div style="flex:1;min-width:160px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:12px;opacity:.8;">Team Progress</span>
                <span style="font-size:12px;font-weight:700;">{{ $teamPct }}%</span>
            </div>
            <div style="height:10px;background:rgba(255,255,255,.15);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:{{ $teamPct }}%;border-radius:99px;background:{{ $teamPct >= 100 ? '#34d399' : 'rgba(255,255,255,.8)' }};transition:width .4s;"></div>
            </div>
        </div>
    </div>

    {{-- ── Staff cards ────────────────────────────────────────────────────── --}}
    <div class="section-heading">Staff Task Status</div>

    @if($staff->isEmpty())
        <div class="empty-state">
            <p style="margin:0;font-weight:600;">No staff found for this branch.</p>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px;">
            @foreach($staff as $row)
            @php
                $u       = $row['user'];
                $initial = strtoupper(substr($u->name, 0, 1));
                $pct     = $row['pct'];
                $barClass = $pct >= 100 ? 'done-all' : ($pct === 0 ? 'zero' : '');
            @endphp
            <div class="acct-card">

                {{-- Avatar --}}
                <div class="acct-avatar">{{ $initial }}</div>

                {{-- Main info --}}
                <div style="flex:1;min-width:0;">

                    {{-- Name + role --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
                        <div>
                            <span style="font-size:15px;font-weight:700;color:#380740;">{{ $u->name }}</span>
                            @if($u->role ?? null)
                            <span style="font-size:11px;color:#9b59b6;margin-left:6px;text-transform:capitalize;">{{ $u->role }}</span>
                            @endif
                        </div>
                        {{-- Completion % badge --}}
                        @if($row['assigned'] > 0)
                            @if($pct >= 100)
                                <span class="badge badge-done">
                                    <svg width="10" height="10" viewBox="0 0 16 16" fill="none"
                                         style="display:inline-block!important;width:10px;height:10px;">
                                        <path d="M3 8l3.5 3.5L13 5" stroke="#059669" stroke-width="2"
                                              stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    100%
                                </span>
                            @else
                                <span class="badge badge-assigned">{{ $pct }}%</span>
                            @endif
                        @else
                            <span class="badge" style="background:#f5f5f5;color:#aaa;">No tasks</span>
                        @endif
                    </div>

                    {{-- Progress bar --}}
                    @if($row['assigned'] > 0)
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <div class="pbar-track">
                            <div class="pbar-fill {{ $barClass }}" style="width:{{ $pct }}%;"></div>
                        </div>
                        <span style="font-size:12px;color:#6a0f70;white-space:nowrap;font-weight:600;">
                            {{ $row['done'] }}/{{ $row['assigned'] }}
                        </span>
                    </div>
                    @else
                    <div style="height:8px;background:#f5eefa;border-radius:99px;margin-bottom:10px;"></div>
                    @endif

                    {{-- Stat chips --}}
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="badge badge-assigned">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="none"
                                 style="display:inline-block!important;width:10px;height:10px;">
                                <rect x="2" y="2" width="12" height="12" rx="2"
                                      stroke="#6a0f70" stroke-width="1.5"/>
                                <path d="M5 8h6M5 5.5h4M5 10.5h3" stroke="#6a0f70"
                                      stroke-width="1.3" stroke-linecap="round"/>
                            </svg>
                            {{ $row['assigned'] }} assigned
                        </span>

                        <span class="badge badge-done">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="none"
                                 style="display:inline-block!important;width:10px;height:10px;">
                                <path d="M3 8l3.5 3.5L13 5" stroke="#059669" stroke-width="2"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ $row['done'] }} done
                        </span>

                        @if($row['overdue'] > 0)
                        <span class="badge badge-overdue">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="none"
                                 style="display:inline-block!important;width:10px;height:10px;">
                                <circle cx="8" cy="8" r="6" stroke="#dc2626" stroke-width="1.5"/>
                                <path d="M8 5v3.5L10 10" stroke="#dc2626" stroke-width="1.3"
                                      stroke-linecap="round"/>
                            </svg>
                            {{ $row['overdue'] }} overdue
                        </span>
                        @endif

                        @php $pending = $row['assigned'] - $row['done']; @endphp
                        @if($pending > 0)
                        <span class="badge" style="background:#fff7ed;color:#d97706;">
                            {{ $pending }} pending
                        </span>
                        @endif
                    </div>

                </div>{{-- /main info --}}

                {{-- Quick link --}}
                <a href="{{ route('tasks.index', ['assigned_to' => $u->id]) }}"
                   title="View {{ $u->name }}'s tasks"
                   style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid #e8d5f0;color:#6a0f70;text-decoration:none;flex-shrink:0;">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="none"
                         style="display:inline-block!important;width:14px;height:14px;">
                        <path d="M8 15l5-5-5-5" stroke="#6a0f70" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>

            </div>{{-- /acct-card --}}
            @endforeach
        </div>
    @endif

    {{-- ── Footnote ────────────────────────────────────────────────────────── --}}
    <p style="margin-top:24px;font-size:12px;color:#b39ddb;text-align:right;">
        Data refreshes on page load &middot; {{ now()->format('H:i') }}
    </p>

</div>
</div>
@endsection
