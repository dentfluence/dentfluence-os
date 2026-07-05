{{--
|==========================================================================
| PRE — Recalls (Phase 1 · Workstream D, slice 3; rebuilt 2026-07-06)
| Route: GET /relationship/recalls   [relationship.recalls]
|
| Was a read-only 4-column kanban ("Recall Pipeline") — dropped that framing
| since recalls don't move through funnel stages, they're a work queue you
| clear. Rebuilt as a flat, filterable, actionable list — same proven
| pattern as Missed Calls (resources/views/relationship/today/missed-calls.blade.php):
| filters, checkboxes, bulk dismiss/assign with a "select all matching
| filter" path. Added Convert-to-Opportunity for recalls that reveal a real
| treatment need.
|
| Variables from RecallPipelineController@index:
|   $recalls (paginator), $total, $openCount, $overdueCount, $showIgnored,
|   $filters, $staff, $statuses
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Recalls')

@section('head-extra')
@unless(app()->has('tabler_icons_loaded'))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
@endunless
<style>
    .rl-page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:12px; }
    .rl-page-title { font-family:'Cormorant Garamond', Georgia, serif; font-size:26px; font-weight:600; color:#1a0320; margin:0 0 4px; }
    .rl-page-sub { font-size:13px; color:#9a7aaa; margin:0; }

    .rl-stats { display:flex; gap:22px; flex-wrap:wrap; margin-bottom:16px; color:#6b7280; font-size:13px; }

    .rl-filter-bar { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; background:#faf5fc; border:1px solid #e8dff0; border-radius:12px; padding:12px 14px; margin-bottom:14px; }
    .rl-filter-group { display:flex; flex-direction:column; gap:3px; }
    .rl-filter-label { font-size:10px; font-weight:700; color:#9a7aaa; text-transform:uppercase; letter-spacing:.05em; }
    .rl-filter-input, .rl-filter-select { border:1px solid #dfc5e1; border-radius:7px; padding:6px 10px; font-size:12.5px; color:#1a0320; background:#fff; outline:none; min-width:140px; }
    .rl-filter-input:focus, .rl-filter-select:focus { border-color:#6a0f70; }
    .rl-filter-check { display:flex; align-items:center; gap:6px; font-size:12.5px; color:#4e0a53; padding-bottom:6px; }

    .rl-btn { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:7px; border:1px solid #dfc5e1; background:#fff; color:#6a0f70; cursor:pointer; text-decoration:none; }
    .rl-btn:hover { background:#f3e8f4; }
    .rl-btn--primary { background:#6a0f70; color:#fff; border-color:#6a0f70; }
    .rl-btn--primary:hover { background:#4e0a53; }

    .rl-table-wrap { background:#fff; border:1px solid #e8dff0; border-radius:14px; overflow:hidden; }
    .rl-table { width:100%; border-collapse:collapse; font-size:13px; }
    .rl-table thead th { padding:10px 12px; font-size:10.5px; font-weight:700; color:#9a7aaa; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #e8dff0; text-align:left; white-space:nowrap; background:#faf5fc; }
    .rl-table tbody tr { border-bottom:1px solid #f8f4fc; }
    .rl-table tbody tr:hover { background:#fdf9ff; }
    .rl-table tbody tr.rl-row--ignored { opacity:.5; }
    .rl-table tbody td { padding:10px 12px; vertical-align:middle; }
    .rl-table input[type=checkbox] { cursor:pointer; width:14px; height:14px; accent-color:#6a0f70; }

    .rl-name { font-weight:600; color:#1a0320; font-size:13px; }
    .rl-reason { font-size:12px; color:#6a5a76; margin-top:1px; }
    .rl-phone-link { font-size:12px; color:#6a0f70; text-decoration:none; }
    .rl-phone-link:hover { text-decoration:underline; }

    .rl-status { font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
    .rl-status--pending { background:#eff6ff; color:#1e40af; }
    .rl-status--waiting_for_patient { background:#e6f1fb; color:#185fa5; }
    .rl-status--overdue { background:#fdecec; color:#8a1f1f; }
    .rl-status--closed { background:#eaf3de; color:#3b6d11; }

    .rl-priority { font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:.04em; }
    .rl-priority--high { background:#fdeaea; color:#b52020; }
    .rl-priority--medium { background:#fff4e0; color:#a05c00; }
    .rl-priority--low { background:#e8f7ef; color:#1a7a45; }

    .rl-assigned { font-size:11.5px; padding:2px 8px; border-radius:99px; background:#eeedfe; color:#534ab7; white-space:nowrap; }

    .rl-actions { display:flex; align-items:center; gap:4px; }
    .rl-action-btn { display:inline-flex; align-items:center; justify-content:center; width:27px; height:27px; border-radius:6px; border:1px solid #dfc5e1; background:#fff; color:#6a0f70; cursor:pointer; text-decoration:none; font-size:12px; }
    .rl-action-btn:hover { background:#f3e8f4; }
    .rl-action-btn--muted { color:#9a7aaa; }

    .rl-bulk-bar { position:sticky; bottom:0; background:#2d0538; color:#fff; padding:10px 16px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; border-radius:0 0 14px 14px; }
    .rl-bulk-count { font-size:13px; font-weight:600; }
    .rl-bulk-btn { padding:6px 14px; font-size:12.5px; font-weight:600; border-radius:6px; border:none; cursor:pointer; background:rgba(255,255,255,.14); color:#fff; }
    .rl-bulk-btn:hover { background:rgba(255,255,255,.24); }
    .rl-bulk-btn--danger { background:rgba(239,68,68,.28); }
    .rl-bulk-btn--danger:hover { background:rgba(239,68,68,.42); }
    .rl-bulk-select { border:none; border-radius:6px; padding:6px 8px; font-size:12.5px; color:#1a0320; }

    .rl-empty { padding:48px 24px; text-align:center; color:#b3a0b8; font-size:13px; }
    .rl-empty-icon { font-size:30px; color:#dfc5e1; margin-bottom:8px; }

    .rl-flash { margin-bottom:12px; padding:9px 16px; border-radius:8px; font-size:13px; font-weight:500; background:#e8f7ef; border:1px solid #b8e0ca; color:#1a7a45; }
    .rl-flash--error { background:#fdecec; border-color:#f5b5b5; color:#8a1f1f; }

    #df-content-inner { padding:10px 24px 24px !important; }
</style>
@endsection

@section('relationship-content')

<div x-data="recallsList()">

    <div class="rl-page-header">
        <div>
            <h1 class="rl-page-title">Recalls</h1>
            <p class="rl-page-sub">Patients due to return — work the queue: dismiss, assign, or convert to an opportunity.</p>
        </div>
        <button type="button" onclick="rlOpenAddRecall()" class="rl-btn rl-btn--primary">
            <i class="ti ti-plus"></i> Add Recall
        </button>
    </div>

    @if(session('success'))
        <div class="rl-flash">{{ session('success') }}</div>
    @endif
    @if ($errors->any() && !$errors->has('patient_id'))
        <div class="rl-flash rl-flash--error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="rl-stats">
        <span>Total recalls: <strong style="color:#1f2937;">{{ number_format($total) }}</strong></span>
        <span>Open: <strong style="color:#1f2937;">{{ number_format($openCount) }}</strong></span>
        <span>Overdue: <strong style="color:{{ $overdueCount > 0 ? '#8A1F1F' : '#1f2937' }};">{{ number_format($overdueCount) }}</strong></span>
    </div>

    {{-- ── Filters ─────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('relationship.recalls') }}" class="rl-filter-bar">
        <div class="rl-filter-group">
            <label class="rl-filter-label">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or phone" class="rl-filter-input">
        </div>

        <div class="rl-filter-group">
            <label class="rl-filter-label">Status</label>
            <select name="status" class="rl-filter-select">
                <option value="">All</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="rl-filter-group">
            <label class="rl-filter-label">Priority</label>
            <select name="priority" class="rl-filter-select">
                <option value="">All</option>
                <option value="high" @selected(($filters['priority'] ?? '') === 'high')>High</option>
                <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>Medium</option>
                <option value="low" @selected(($filters['priority'] ?? '') === 'low')>Low</option>
            </select>
        </div>

        <div class="rl-filter-group">
            <label class="rl-filter-label">Assigned To</label>
            <select name="assigned_to" class="rl-filter-select">
                <option value="">Anyone</option>
                @foreach($staff as $member)
                    <option value="{{ $member->name }}" @selected(($filters['assigned_to'] ?? '') === $member->name)>{{ $member->name }}</option>
                @endforeach
            </select>
        </div>

        <label class="rl-filter-check">
            <input type="checkbox" name="show_ignored" value="1" @checked($showIgnored) onchange="this.form.submit()">
            Show ignored
        </label>

        <button type="submit" class="rl-btn rl-btn--primary"><i class="ti ti-filter"></i> Apply</button>
        @if(!empty(array_filter($filters)) || $showIgnored)
            <a href="{{ route('relationship.recalls') }}" class="rl-btn">Clear</a>
        @endif
    </form>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="rl-table-wrap">
        @if($recalls->isEmpty())
            <div class="rl-empty">
                <div class="rl-empty-icon"><i class="ti ti-circle-check"></i></div>
                Nothing here — no recalls match this filter.
            </div>
        @else
            <table class="rl-table">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" @change="toggleAll($event)"></th>
                        <th>Patient</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due</th>
                        <th>Assigned</th>
                        <th style="width:190px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recalls as $recall)
                    @php
                        $patient   = $recall->patient;
                        $phone     = $patient?->phone ?? $recall->phone;
                        $due       = $recall->follow_up_date ?? $recall->due_at;
                        $isOverdue = ($recall->is_overdue || $recall->status === 'overdue') && $recall->status !== 'closed';
                    @endphp
                    <tr class="{{ $recall->ignored_at ? 'rl-row--ignored' : '' }}">
                        <td><input type="checkbox" class="rl-row-check" value="{{ $recall->id }}" @change="toggleRow({{ $recall->id }})"></td>
                        <td>
                            <div class="rl-name">{{ $patient?->name ?? $recall->person_name ?: 'Unnamed' }}</div>
                            @if($phone)<a href="tel:{{ $phone }}" class="rl-phone-link">{{ $phone }}</a>@endif
                        </td>
                        <td><div class="rl-reason">{{ $recall->note ?: 'Recall due' }}</div></td>
                        <td><span class="rl-status rl-status--{{ $recall->status }}">{{ $statuses[$recall->status] ?? $recall->status }}</span></td>
                        <td><span class="rl-priority rl-priority--{{ $recall->priority ?? 'medium' }}">{{ ucfirst($recall->priority ?? 'medium') }}</span></td>
                        <td style="white-space:nowrap;color:#6a5a76;font-size:12px;">
                            {{ $due ? \Illuminate\Support\Carbon::parse($due)->format('d M Y') : '—' }}
                            @if($isOverdue)<br><span style="color:#8A1F1F;font-size:10.5px;font-weight:700;">Overdue</span>@endif
                        </td>
                        <td>
                            @if($recall->assigned_to)
                                <span class="rl-assigned">{{ $recall->assigned_to }}</span>
                            @else
                                <span style="color:#c2c6cd;font-size:12px;">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="rl-actions">
                                @if($patient)
                                <a href="{{ route('patients.show', $patient->id) }}" class="rl-action-btn" title="Open record">
                                    <i class="ti ti-external-link"></i>
                                </a>
                                @endif

                                @if($patient && $recall->status !== 'closed')
                                <button type="button" class="rl-action-btn" title="Convert to Opportunity"
                                        onclick="rlOpenConvert({{ $recall->id }}, '{{ addslashes($patient->name) }}')">
                                    <i class="ti ti-star"></i>
                                </button>
                                @endif

                                @if(!$recall->ignored_at)
                                <form method="POST" action="{{ route('relationship.recalls.ignore', $recall->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="rl-action-btn rl-action-btn--muted" title="Ignore — exclude this item from the queue"
                                            onclick="return confirm('Ignore this recall? It will be hidden from this list until restored.')">
                                        <i class="ti ti-eye-off"></i>
                                    </button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('relationship.recalls.unignore', $recall->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="rl-action-btn" title="Restore to queue"><i class="ti ti-eye"></i></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ── Bulk bar ────────────────────────────────────────────── --}}
            <div class="rl-bulk-bar" x-show="selected.length > 0 || selectAllMatching" x-transition style="display:none;">
                <span class="rl-bulk-count" x-show="!selectAllMatching" x-text="selected.length + ' selected'"></span>
                <span class="rl-bulk-count" x-show="selectAllMatching">All {{ $recalls->total() }} matching this filter selected</span>

                <template x-if="!selectAllMatching && selected.length === {{ $recalls->count() }} && {{ $recalls->total() }} > {{ $recalls->count() }}">
                    <button type="button" class="rl-bulk-btn" @click="selectAllMatching = true">
                        Select all {{ $recalls->total() }} matching this filter
                    </button>
                </template>
                <template x-if="selectAllMatching">
                    <button type="button" class="rl-bulk-btn" @click="selectAllMatching = false">Just these {{ $recalls->count() }}</button>
                </template>

                {{-- Bulk assign --}}
                <form method="POST" action="{{ route('relationship.recalls.bulk-assign') }}" style="display:inline;display:flex;align-items:center;gap:6px;"
                      onsubmit="return confirm('Assign the selected recall(s) to this staff member?')">
                    @csrf
                    <template x-if="selectAllMatching">
                        <div style="display:inline;">
                            <input type="hidden" name="select_all" value="1">
                            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                            <input type="hidden" name="priority" value="{{ $filters['priority'] ?? '' }}">
                            <input type="hidden" name="assigned_to" value="{{ $filters['assigned_to'] ?? '' }}">
                            <input type="hidden" name="show_ignored" value="{{ $showIgnored ? '1' : '' }}">
                        </div>
                    </template>
                    <template x-for="id in selected" :key="'a'+id">
                        <input type="hidden" name="recall_ids[]" :value="id" x-show="!selectAllMatching">
                    </template>
                    <select name="assigned_to" class="rl-bulk-select" required>
                        <option value="">Assign to…</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rl-bulk-btn"><i class="ti ti-user-check"></i> Assign</button>
                </form>

                {{-- Bulk dismiss --}}
                <form method="POST" action="{{ route('relationship.recalls.bulk-dismiss') }}" style="display:inline;"
                      onsubmit="return confirm('Dismiss the selected recall(s)? They will be marked closed.')">
                    @csrf
                    <template x-if="selectAllMatching">
                        <div style="display:inline;">
                            <input type="hidden" name="select_all" value="1">
                            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                            <input type="hidden" name="priority" value="{{ $filters['priority'] ?? '' }}">
                            <input type="hidden" name="assigned_to" value="{{ $filters['assigned_to'] ?? '' }}">
                            <input type="hidden" name="show_ignored" value="{{ $showIgnored ? '1' : '' }}">
                        </div>
                    </template>
                    <template x-for="id in selected" :key="'d'+id">
                        <input type="hidden" name="recall_ids[]" :value="id" x-show="!selectAllMatching">
                    </template>
                    <button type="submit" class="rl-bulk-btn rl-bulk-btn--danger">
                        <i class="ti ti-check"></i>
                        <span x-text="selectAllMatching ? 'Dismiss all {{ $recalls->total() }}' : 'Bulk Dismiss'"></span>
                    </button>
                </form>

                <button type="button" @click="clearSelection()" style="margin-left:auto;background:none;border:none;color:#c9a8d4;cursor:pointer;font-size:12px;">
                    ✕ Clear
                </button>
            </div>
        @endif
    </div>

    {{-- ── Pagination ──────────────────────────────────────────────────── --}}
    @if($recalls->hasPages())
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:12px;color:#9a7aaa;">
            <span>Showing {{ $recalls->firstItem() }}–{{ $recalls->lastItem() }} of {{ $recalls->total() }}</span>
            <div style="display:flex;gap:6px;">
                @if($recalls->onFirstPage())
                    <span class="rl-btn" style="opacity:.4;cursor:not-allowed;">&larr; Prev</span>
                @else
                    <a href="{{ $recalls->previousPageUrl() }}" class="rl-btn">&larr; Prev</a>
                @endif
                @if($recalls->hasMorePages())
                    <a href="{{ $recalls->nextPageUrl() }}" class="rl-btn">Next &rarr;</a>
                @else
                    <span class="rl-btn" style="opacity:.4;cursor:not-allowed;">Next &rarr;</span>
                @endif
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════
         Add Recall modal — unchanged from the original Recall Pipeline:
         patient search + priority + follow-up date + note. Posts to
         relationship.recalls.store → RecallEngineService::createManual().
    ══════════════════════════════════════════════════════════════════ --}}
    @php $addRecallFailed = $errors->has('patient_id'); @endphp
    <div id="rlAddRecallModal" style="display:{{ $addRecallFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Add Recall</h3>
                <button type="button" onclick="rlCloseAddRecall()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Manually queue a patient for a recall call.</p>

            @if ($addRecallFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->get('patient_id') as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.recalls.store') }}">
                @csrf
                <div style="margin-bottom:16px;position:relative;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Patient <span style="color:#c0392b;">*</span></label>
                    <input type="text" id="rlPatientSearch" placeholder="Search by name or phone…" autocomplete="off"
                           oninput="rlSearchPatients(this.value)"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    <input type="hidden" name="patient_id" id="rlPatientId" value="{{ old('patient_id') }}">
                    <div id="rlPatientResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;max-height:220px;overflow-y:auto;z-index:5;box-shadow:0 4px 14px rgba(0,0,0,.08);"></div>
                    <div id="rlPatientSelected" style="display:none;margin-top:8px;padding:8px 10px;background:#EEEDFE;border-radius:8px;font-size:12.5px;color:#534AB7;">
                        <span id="rlPatientSelectedName"></span>
                        <button type="button" onclick="rlClearPatient()" style="float:right;background:none;border:none;color:#534AB7;cursor:pointer;font-weight:600;">Change</button>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Priority <span style="color:#c0392b;">*</span></label>
                    <select name="priority" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="follow_up_date" required value="{{ old('follow_up_date', today()->toDateString()) }}"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Note</label>
                    <textarea name="note" rows="3" placeholder="Why is this patient being recalled?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;">{{ old('note') }}</textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">Add Recall</button>
                    <button type="button" onclick="rlCloseAddRecall()" style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Convert-to-Opportunity modal — per-row, for when the recall call
         reveals a real treatment need. Posts to relationship.recalls.convert.
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="rlConvertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Convert to Opportunity</h3>
                <button type="button" onclick="rlCloseConvert()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">For <strong id="rlConvertPatientName"></strong> — this recall will be closed and a new opportunity opened.</p>

            <form id="rlConvertForm" method="POST" action="">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Treatment <span style="color:#c0392b;">*</span></label>
                    <select name="type" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="">Select treatment</option>
                        @foreach(\App\Models\TreatmentOpportunity::TREATMENT_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;gap:12px;margin-bottom:16px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Priority <span style="color:#c0392b;">*</span></label>
                        <select name="priority" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Est. Value (₹)</label>
                        <input type="number" name="estimated_value" min="0" placeholder="e.g. 15000"
                               style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="follow_up_date" required value="{{ today()->toDateString() }}"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" rows="3" placeholder="What did the patient say?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">Convert</button>
                    <button type="button" onclick="rlCloseConvert()" style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recallsList() {
    return {
        selected: [],
        selectAllMatching: false,
        toggleAll(e) {
            const boxes = document.querySelectorAll('.rl-row-check');
            this.selected = e.target.checked ? Array.from(boxes).map(b => parseInt(b.value)) : [];
            boxes.forEach(b => b.checked = e.target.checked);
            if (!e.target.checked) this.selectAllMatching = false;
        },
        toggleRow(id) {
            this.selectAllMatching = false;
            const idx = this.selected.indexOf(id);
            idx === -1 ? this.selected.push(id) : this.selected.splice(idx, 1);
        },
        clearSelection() {
            this.selected = [];
            this.selectAllMatching = false;
            document.querySelectorAll('.rl-row-check, thead input[type=checkbox]').forEach(b => b.checked = false);
        },
    };
}

function rlOpenAddRecall() { document.getElementById('rlAddRecallModal').style.display = 'flex'; }
function rlCloseAddRecall() { document.getElementById('rlAddRecallModal').style.display = 'none'; }

function rlOpenConvert(recallId, patientName) {
    document.getElementById('rlConvertPatientName').textContent = patientName;
    document.getElementById('rlConvertForm').action = '{{ url('/relationship/recalls') }}/' + recallId + '/convert';
    document.getElementById('rlConvertModal').style.display = 'flex';
}
function rlCloseConvert() { document.getElementById('rlConvertModal').style.display = 'none'; }

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { rlCloseAddRecall(); rlCloseConvert(); }
});
document.getElementById('rlAddRecallModal')?.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
document.getElementById('rlConvertModal')?.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });

function rlClearPatient() {
    document.getElementById('rlPatientId').value = '';
    document.getElementById('rlPatientSelected').style.display = 'none';
    document.getElementById('rlPatientSearch').style.display = 'block';
    document.getElementById('rlPatientSearch').value = '';
    document.getElementById('rlPatientSearch').focus();
}

let rlSearchTimer = null;
function rlSearchPatients(q) {
    clearTimeout(rlSearchTimer);
    const box = document.getElementById('rlPatientResults');
    if (q.trim().length < 3) { box.style.display = 'none'; box.innerHTML = ''; return; }

    rlSearchTimer = setTimeout(function () {
        fetch("{{ route('relationship.search') }}?q=" + encodeURIComponent(q))
            .then(r => r.json())
            .then(function (results) {
                const withPatient = results.filter(r => r.patient_id);
                box.innerHTML = '';
                if (withPatient.length === 0) {
                    box.innerHTML = '<div style="padding:10px 12px;font-size:12.5px;color:#9ca3af;">No matching patients found.</div>';
                    box.style.display = 'block';
                    return;
                }
                withPatient.forEach(function (r) {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:9px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f3f4f6;';
                    row.innerHTML = '<div style="font-weight:600;color:#1f2937;">' + (r.name || 'Unnamed') + '</div>' +
                                    '<div style="font-size:11.5px;color:#9ca3af;">' + (r.meta || '') + '</div>';
                    row.onmouseenter = () => row.style.background = '#f7f8fa';
                    row.onmouseleave = () => row.style.background = '#fff';
                    row.onclick = function () { rlSelectPatient(r); };
                    box.appendChild(row);
                });
                box.style.display = 'block';
            })
            .catch(() => { box.style.display = 'none'; });
    }, 250);
}

function rlSelectPatient(r) {
    document.getElementById('rlPatientId').value = r.patient_id || '';
    document.getElementById('rlPatientSelectedName').textContent = r.name + (r.phone ? ' — ' + r.phone : '');
    document.getElementById('rlPatientSelected').style.display = 'block';
    document.getElementById('rlPatientSearch').style.display = 'none';
    document.getElementById('rlPatientResults').style.display = 'none';
    document.getElementById('rlPatientResults').innerHTML = '';
}
</script>
@endsection
