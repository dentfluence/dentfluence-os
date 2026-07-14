@extends('layouts.app')
@section('page-title', 'Lab Cases')

@section('content')
<div
    x-data="labModule()"
    class="p-6 space-y-6"
>

    {{-- ══ NAV TABS ═══════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-2 border-b border-gray-200 pb-0">
        <a href="{{ route('lab.dashboard') }}"
           class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-brand-700 border-b-2 border-transparent -mb-px transition">
            Dashboard
        </a>
        <a href="{{ route('lab.index') }}"
           class="px-4 py-2 text-sm font-medium text-brand-700 border-b-2 border-brand-600 -mb-px">
            Lab Cases
        </a>
        <a href="{{ route('lab-vendors.index') }}"
           class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-brand-700 border-b-2 border-transparent -mb-px transition">
            Lab Vendors
        </a>
    </div>

    {{-- ══ HEADER ══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-700">Lab Cases</h1>
            <p class="text-sm text-gray-500 mt-0.5">Track dental lab work sent to external labs</p>
        </div>
        <button
            @click="openDrawer()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Lab Case
        </button>
    </div>

    {{-- ══ FLASH ════════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- ══ PHASE 2: BILLING TOTALS STRIP ════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">This Month — Estimated</p>
            <p class="text-xl font-bold text-gray-700 mt-1">Rs. {{ number_format($billingTotals['month_estimated'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">This Month — Actual</p>
            <p class="text-xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($billingTotals['month_actual'], 0) }}</p>
            @if($billingTotals['month_estimated'] > 0)
                @php $variance = $billingTotals['month_actual'] - $billingTotals['month_estimated']; @endphp
                <p class="text-xs mt-0.5 {{ $variance > 0 ? 'text-red-500' : 'text-green-600' }}">
                    {{ $variance > 0 ? '+' : '' }}Rs. {{ number_format($variance, 0) }} vs estimate
                </p>
            @endif
        </div>
        <a href="{{ route('lab.reconciliation.index') }}"
           class="bg-orange-50 rounded-xl border border-orange-200 shadow-sm p-4 hover:bg-orange-100 transition-colors block">
            <p class="text-xs text-orange-500 uppercase tracking-widest">Unbilled Cases</p>
            <p class="text-xl font-bold text-orange-600 mt-1">Rs. {{ number_format($billingTotals['unbilled'], 0) }}</p>
            <p class="text-xs text-orange-400 mt-0.5">{{ $billingTotals['unbilled_count'] }} case{{ $billingTotals['unbilled_count'] != 1 ? 's' : '' }} · Start reconciliation →</p>
        </a>
        <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-4">
            <p class="text-xs text-green-500 uppercase tracking-widest">Billed (Pending Payment)</p>
            <p class="text-xl font-bold text-green-700 mt-1">Rs. {{ number_format($billingTotals['billed'], 0) }}</p>
        </div>
    </div>

    {{-- ══ FILTER TABS + SEARCH ════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">

            {{-- Status tabs --}}
            <div class="flex gap-1 flex-wrap">
                @php
                $tabs = [
                    ['key'=>'all',             'label'=>'All'],
                    ['key'=>'active',          'label'=>'Active'],
                    ['key'=>'draft',           'label'=>'Draft'],
                    ['key'=>'order_placed',    'label'=>'Order Placed'],
                    ['key'=>'impression_sent', 'label'=>'Impression / Scan'],
                    ['key'=>'trial',           'label'=>'Trial Loop'],
                    ['key'=>'final_received',  'label'=>'Final In'],
                    ['key'=>'complete',        'label'=>'Complete'],
                    ['key'=>'rejected',        'label'=>'Rejected'],
                ];
                @endphp
                @foreach($tabs as $tab)
                <a
                    href="{{ route('lab.index', array_merge(request()->query(), ['status' => $tab['key']])) }}"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                        {{ $status === $tab['key']
                            ? 'bg-brand-100 text-brand-700'
                            : 'text-gray-500 hover:bg-gray-50' }}"
                >
                    {{ $tab['label'] }}
                    <span class="ml-1 text-xs font-semibold {{ $status === $tab['key'] ? 'text-brand-500' : 'text-gray-400' }}">
                        {{ $counts[$tab['key']] ?? 0 }}
                    </span>
                </a>
                @endforeach
            </div>

            {{-- Search --}}
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search patient, lab vendor…"
                    class="w-56 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-300 focus:outline-none"
                >
                <button type="submit" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition">Search</button>
            </form>
        </div>

        {{-- ══ TABLE ══════════════════════════════════════════════════════ --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-4 py-3 text-left">Patient</th>
                        <th class="px-4 py-3 text-left">Work Type</th>
                        <th class="px-4 py-3 text-left">Tooth / Shade</th>
                        <th class="px-4 py-3 text-left">Lab Vendor</th>
                        <th class="px-4 py-3 text-left">Expected</th>
                        <th class="px-4 py-3 text-left">Status &amp; Next Step</th>
                        <th class="px-4 py-3 text-left">Est. Cost</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($cases as $case)
                    @php
                        // ── Authorization for this row ──────────────────────
                        $canEditCase = ! in_array($case->status, ['complete', 'rejected']);

                        // Delete: complete/rejected → nobody | draft → any | else → admin only
                        $labDeleteMode = match(true) {
                            in_array($case->status, ['complete', 'rejected']) => 'none',
                            $case->status === 'draft'                         => 'any',
                            $isAdmin                                          => 'admin_only',
                            default                                           => 'none',
                        };

                        // ── Next-step targets from model's STATUS_FLOW ──────
                        $nextSteps = \App\Models\LabCase::STATUS_FLOW[$case->status] ?? [];
                        // Filter out 'rejected' from quick-step buttons (keep it but show separately)
                        $primarySteps  = array_diff($nextSteps, ['rejected']);
                        $canReject     = in_array('rejected', $nextSteps);

                        // ── Labels for next-step buttons ────────────────────
                        $stepLabels = \App\Models\LabCase::STATUS_LABELS;

                        // ── Overdue flag ─────────────────────────────────────
                        $isOverdue = $case->expected_return_date
                            && ! in_array($case->status, ['final_received','complete','rejected'])
                            && $case->expected_return_date->isPast();
                    @endphp
                    <tr class="hover:bg-gray-50 group border-b border-gray-50 cursor-pointer" onclick="window.location='{{ route('lab.show', $case) }}'">
                        {{-- Patient --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.show', $case->patient_id) }}"
                               onclick="event.stopPropagation()"
                               class="font-medium text-brand-700 hover:underline">
                                {{ $case->patient->name ?? '—' }}
                            </a>
                            @if($case->doctor)
                            <div class="text-xs text-gray-400">{{ $case->doctor->doctor_name }}</div>
                            @endif
                            @if($case->is_remake ?? false)
                            <span class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-bold bg-red-100 text-red-700 rounded">
                                REPEAT WORK
                            </span>
                            @endif
                        </td>
                        {{-- Work type --}}
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-700">{{ $case->workTypeLabel() }}</span>
                            @if($case->work_subtype)
                            <div class="text-xs text-gray-400">{{ $case->work_subtype }}</div>
                            @endif
                            @if(($case->priority ?? 'routine') !== 'routine')
                            <span class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-bold rounded
                                {{ $case->priority === 'urgent' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700' }}">
                                {{ strtoupper($case->priority) }}
                            </span>
                            @endif
                        </td>
                        {{-- Tooth / Shade --}}
                        <td class="px-4 py-3 text-gray-600">
                            {{ $case->tooth_number ?: '—' }}
                            @if($case->shade)
                            <span class="ml-1 text-xs text-gray-400">{{ $case->shade }}</span>
                            @endif
                        </td>
                        {{-- Lab Vendor --}}
                        <td class="px-4 py-3 text-gray-700">
                            {{ $case->vendor?->name ?? $case->lab_vendor ?? '—' }}
                        </td>
                        {{-- Expected --}}
                        <td class="px-4 py-3">
                            @if($case->expected_return_date)
                                <span class="{{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                    {{ $case->expected_return_date->format('d M') }}
                                </span>
                                @if($isOverdue)
                                <div class="text-[10px] text-red-500 font-semibold">OVERDUE</div>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        {{-- Status + Trial badge + Next step buttons --}}
                        <td class="px-4 py-3 min-w-[180px]">
                            {{-- Status badge --}}
                            <div class="flex flex-wrap items-center gap-1 mb-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ \App\Models\LabCase::STATUS_COLORS[$case->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ \App\Models\LabCase::STATUS_LABELS[$case->status] ?? ucfirst($case->status) }}
                                </span>
                                {{-- Trial round badge --}}
                                @if(($case->trial_round ?? 0) > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Trial #{{ $case->trial_round }}
                                </span>
                                @endif
                                {{-- Pending task badge --}}
                                @if($case->active_task_id)
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100"
                                      title="Task pending for this case">
                                    ✓ Task
                                </span>
                                @endif
                            </div>
                            {{-- Quick next-step transition buttons --}}
                            @if(count($primarySteps) > 0)
                            <div class="flex flex-wrap gap-1" onclick="event.stopPropagation()">
                                @foreach($primarySteps as $step)
                                <form method="POST" action="{{ route('lab.transition', [$case, $step]) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-0.5 px-2 py-0.5 text-[11px] font-medium
                                                   bg-brand-50 hover:bg-brand-100 text-brand-700 border border-brand-200
                                                   rounded-md transition cursor-pointer"
                                            onclick="return confirm('Move to: {{ $stepLabels[$step] ?? $step }}?')">
                                        → {{ $stepLabels[$step] ?? ucfirst(str_replace('_', ' ', $step)) }}
                                    </button>
                                </form>
                                @endforeach
                                @if($canReject && $isAdmin)
                                <form method="POST" action="{{ route('lab.transition', [$case, 'rejected']) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium
                                                   bg-red-50 hover:bg-red-100 text-red-600 border border-red-200
                                                   rounded-md transition cursor-pointer"
                                            onclick="return confirm('Mark this case as Rejected?')">
                                        ✕ Reject
                                    </button>
                                </form>
                                @endif
                            </div>
                            @endif
                        </td>
                        {{-- Estimated Cost --}}
                        <td class="px-4 py-3 text-gray-600 text-sm">
                            @if($case->estimated_cost)
                            <div class="text-xs text-gray-400">Est.</div>
                            Rs.{{ number_format($case->estimated_cost, 0) }}
                            @elseif($case->lab_cost)
                            Rs.{{ number_format($case->lab_cost, 0) }}
                            @else
                            —
                            @endif
                        </td>
                        {{-- Three-dot menu --}}
                        <td class="px-4 py-3 text-right">
                            <button
                                onclick="event.stopPropagation(); toggleLabMenu(event,
                                    {{ $case->id }},
                                    {{ (int)$canEditCase }},
                                    '{{ $labDeleteMode }}',
                                    {{ $case->toJson() }})"
                                style="background:#f5f0f8;border:1px solid #ede4f3;border-radius:6px;
                                       padding:5px 10px;cursor:pointer;color:#6a0f70;font-size:18px;
                                       line-height:1;font-weight:700;display:inline-flex;align-items:center;">
                                &#8942;
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <div class="text-gray-400 text-sm">No lab cases found.</div>
                            <button @click="openDrawer()" class="mt-3 text-brand-600 text-sm hover:underline">
                                + Create your first lab case
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $cases->links() }}
        </div>
        @endif
    </div>

    {{-- ══ FLOATING THREE-DOT MENU (position:fixed to escape table overflow) ══ --}}
    <div id="lab-floating-menu"
         style="display:none;position:fixed;background:#fff;border:1px solid #ede4f3;
                border-radius:8px;box-shadow:0 6px 24px rgba(14,1,24,.15);z-index:9999;
                min-width:148px;overflow:hidden;padding:4px 0;">
        <button id="lmenu-view"
                style="width:100%;text-align:left;padding:10px 16px;background:none;
                       border:none;font-size:13px;color:#6a0f70;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
            View Details
        </button>
        <button id="lmenu-edit"
                style="display:none;width:100%;text-align:left;padding:10px 16px;background:none;
                       border:none;font-size:13px;color:#1e0a2c;cursor:pointer;font-family:'Inter',sans-serif;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
            Edit
        </button>
        <button id="lmenu-print"
                style="width:100%;text-align:left;padding:10px 16px;background:none;
                       border:none;font-size:13px;color:#1e0a2c;cursor:pointer;font-family:'Inter',sans-serif;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='none'">
            Print
        </button>
        <div id="lmenu-divider" style="display:none;height:1px;background:#f0eaf5;margin:4px 0;"></div>
        <button id="lmenu-delete"
                style="display:none;width:100%;text-align:left;padding:10px 16px;background:none;
                       border:none;font-size:13px;color:#b52020;cursor:pointer;font-family:'Inter',sans-serif;"
                onmouseover="this.style.background='#fdeaea'" onmouseout="this.style.background='none'">
            Delete
        </button>
    </div>

    {{-- Hidden delete form (submitted via JS after modal confirm) --}}
    <form id="lab-delete-form" method="POST" action="" style="display:none;">
        @csrf
        @method('DELETE')
        <input type="hidden" name="delete_reason" id="lab-delete-reason-val">
    </form>

    {{-- DELETE LAB CASE CONFIRMATION MODAL --}}
    <div id="modal-lab-delete" style="display:none;position:fixed;inset:0;z-index:9998;
         align-items:center;justify-content:center;padding:24px 16px;
         background:rgba(14,1,24,0.60);backdrop-filter:blur(3px);">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:440px;
                    box-shadow:0 20px 60px rgba(14,1,24,0.25);">

            <div style="display:flex;align-items:center;gap:12px;
                        padding:16px 20px;border-bottom:1px solid #fde8e8;
                        background:#fff8f8;border-radius:8px 8px 0 0;">
                <div>
                    <div style="font-size:15px;font-weight:600;color:#1e0a2c;">Delete Lab Case</div>
                    <div id="lab-del-subtitle" style="font-size:12px;color:#b52020;margin-top:2px;"></div>
                </div>
                <button onclick="document.getElementById('modal-lab-delete').style.display='none'"
                        style="margin-left:auto;background:none;border:none;cursor:pointer;color:#9a7aaa;font-size:18px;">×</button>
            </div>

            <div style="padding:20px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#b52020;margin-bottom:6px;">
                    Reason for deletion <span style="color:#b52020;">*</span>
                </label>
                <textarea id="lab-del-reason" rows="3"
                          placeholder="Explain why this lab case is being deleted…"
                          style="width:100%;padding:9px 12px;border:1.5px solid #f5cccc;border-radius:5px;
                                 font-size:13px;font-family:'Inter',sans-serif;color:#1e0a2c;
                                 outline:none;box-sizing:border-box;resize:vertical;"
                          oninput="labDelReasonInput(this)"></textarea>
                <div style="font-size:11px;color:#9a7aaa;margin-top:4px;">Minimum 5 characters required</div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                    <button type="button"
                            onclick="document.getElementById('modal-lab-delete').style.display='none'"
                            style="padding:8px 18px;background:#fff;color:#6a0f70;border:1px solid #ede4f3;
                                   border-radius:5px;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        Cancel
                    </button>
                    <button id="lab-del-submit" disabled onclick="submitLabDelete()"
                            style="padding:8px 20px;background:#b52020;color:#fff;border:none;
                                   border-radius:5px;font-size:13px;font-weight:600;
                                   cursor:pointer;font-family:'Inter',sans-serif;opacity:0.5;">
                        Delete Case
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ DRAWER BACKDROP ════════════════════════════════════════════════ --}}
    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/40 z-40"
        @click.self="closeDrawer()"
        style="display:none"
    ></div>

    {{-- ══ DRAWER PANEL ═══════════════════════════════════════════════════ --}}
    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95vw] max-w-lg max-h-[90vh] bg-white shadow-2xl z-50 flex flex-col overflow-hidden rounded-2xl"
        style="display:none"
    >
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <h2 class="text-lg font-semibold text-gray-800" x-text="editingId ? 'Edit Lab Case' : 'New Lab Case'"></h2>
            <button @click="closeDrawer()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form
                :action="editingId ? '/lab/' + editingId : '/lab'"
                method="POST"
                id="lab-form"
                class="space-y-5"
            >
                @csrf
                <input type="hidden" name="_method" :value="editingId ? 'PUT' : 'POST'">

                {{-- Patient search --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Patient *</label>
                    <div style="position:relative;">
                        <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"
                             width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" id="lab-patient-search"
                               placeholder="Search by name or phone…"
                               autocomplete="off"
                               oninput="labSearchPatient(this.value)"
                               class="w-full border border-gray-200 rounded-lg py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none"
                               style="padding-left:30px;padding-right:12px;">
                        <div id="lab-patient-dropdown"
                             style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;
                                    background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;
                                    max-height:200px;overflow-y:auto;z-index:9999;
                                    box-shadow:0 4px 16px rgba(0,0,0,.12);">
                        </div>
                        <input type="hidden" name="patient_id" id="lab-patient-id" x-model="form.patient_id">
                    </div>
                    <div id="lab-patient-selected" style="display:none;margin-top:5px;font-size:12px;color:#6a0f70;font-weight:600;"></div>
                </div>

                {{-- Doctor --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Doctor *</label>
                    <select name="doctor_id" required x-model="form.doctor_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <option value="">— Select doctor —</option>
                        @foreach($doctors as $d)
                        @php
                            $drName  = str_starts_with($d->name, 'Dr') ? $d->name : 'Dr. ' . $d->name;
                            $roleMap = [
                                'doctor'               => '',
                                'resident_dentist'     => 'Resident',
                                'associate_dentist'    => 'Associate',
                                'visiting_consultant'  => 'Consultant',
                            ];
                            $roleTag = $roleMap[$d->role] ?? '';
                        @endphp
                        <option value="{{ $d->id }}">{{ $drName }}{{ $roleTag ? ' (' . $roleTag . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Work Category + Subtype --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Work Category *</label>
                        <select name="work_category" required x-model="form.work_category" @change="form.work_subtype = ''; autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select category —</option>
                            @foreach(\App\Models\LabCase::WORK_CATEGORIES as $cat => $subs)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Subtype / Material</label>
                        <select name="work_subtype" x-model="form.work_subtype" @change="autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none"
                            :disabled="!form.work_category">
                            <option value="">— Select subtype —</option>
                            <template x-for="sub in subtypesFor(form.work_category)" :key="sub">
                                <option :value="sub" x-text="sub"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Tooth Chart (shared partial — also used by the patient-profile Lab tab) --}}
                @include('partials.tooth-chart-assets')
                @include('partials.shade-select-assets')
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tooth Selection</label>
                    @include('partials.tooth-chart', ['target' => 'form.item', 'pickerId' => "'lab'"])
                </div>

                {{-- Shade (shared partial) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Shade</label>
                    @include('partials.shade-select', ['target' => 'form.item'])
                </div>

                {{-- items_json carries the tooth/shade rows the backend turns into LabCaseItem records --}}
                <input type="hidden" name="items_json" :value="JSON.stringify(itemsPayloadFromState(form.item, form.work_subtype, form.work_subtype))">

                {{-- Lab vendor & cost (Phase 2: estimated_cost added; Phase B: vendor select with badges) --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Vendor</label>
                        <select name="lab_vendor_id" x-model="form.lab_vendor_id" @change="autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select Lab —</option>
                            @foreach($vendors as $vnd)
                            <option value="{{ $vnd->id }}"
                                    x-bind:selected="form.lab_vendor_id == {{ $vnd->id }}">
                                {{ $vnd->name }}
                                @if($vnd->recommendationBadge()) [★ {{ $vnd->recommendationBadge() }}] @endif
                            </option>
                            @endforeach
                        </select>
                        {{-- Recommendation hint for selected vendor --}}
                        @foreach($vendors as $vnd)
                        @if($vnd->recommendationBadge())
                        <span x-show="form.lab_vendor_id == {{ $vnd->id }}"
                              class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 text-[10px] font-bold rounded-full {{ $vnd->recommendationBadgeColor() }}">
                            ★ {{ $vnd->recommendationBadge() }}
                            @if($vnd->avgQualityScore()) · Quality {{ number_format($vnd->avgQualityScore(), 1) }}/5 @endif
                        </span>
                        @endif
                        @endforeach
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Estimated Cost (Rs. )</label>
                        <input type="number" name="estimated_cost" placeholder="Quoted amount" step="0.01" min="0" x-model="form.estimated_cost"
                            @input="costAutoFilled = false"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <p x-show="costAutoFilled" class="text-[10px] text-gray-400 mt-0.5">From the vendor's price list — edit if this job differs.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Final Lab Cost (Rs. )</label>
                        <input type="number" name="lab_cost" placeholder="Actual charged" step="0.01" min="0" x-model="form.lab_cost"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Payment Status</label>
                        <select name="payment_status" x-model="form.payment_status"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="pending">Pending</option>
                            <option value="monthly_account">Monthly Account</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sent Date</label>
                        <input type="date" name="sent_date" x-model="form.sent_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Expected Return</label>
                        <input type="date" name="expected_return_date" x-model="form.expected_return_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Received Date</label>
                        <input type="date" name="received_date" x-model="form.received_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status *</label>
                    <div class="flex gap-3 flex-wrap">
                        @foreach(['draft'=>'Draft','order_placed'=>'Order Placed','impression_sent'=>'Impression Sent','scan_sent'=>'Scan Sent','trial_received'=>'Trial Received','trial_returned'=>'Trial Returned','final_received'=>'Final Work In','complete'=>'Complete','rejected'=>'Rejected'] as $val => $lbl)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="status" value="{{ $val }}" x-model="form.status" class="text-brand-600">
                            <span class="text-sm text-gray-700">{{ $lbl }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Instructions --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Instructions to Lab</label>
                    <textarea name="instructions" rows="3" placeholder="Specific instructions for the lab…" x-model="form.instructions"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>

                {{-- Internal notes --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Internal Notes</label>
                    <textarea name="notes" rows="2" placeholder="Notes for clinic use only…" x-model="form.notes"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
            <button @click="closeDrawer()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button type="submit" form="lab-form"
                class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                <span x-text="editingId ? 'Save Changes' : 'Create Lab Case'"></span>
            </button>
        </div>
    </div>

</div>
@endsection

@section('head-extra')
<script>
// ── Patient search data (branch-scoped, loaded once) ─────────────
{{-- @json escapes < > / (JSON_HEX_TAG etc.) — raw json_encode allowed a
     patient name containing </script> to break out and execute (stored XSS) --}}
const __LAB_PATIENTS = @json($patients);

function labSearchPatient(q) {
    const dd  = document.getElementById('lab-patient-dropdown');
    const sel = document.getElementById('lab-patient-selected');
    if (!q || q.length < 1) { dd.style.display = 'none'; return; }
    const lower = q.toLowerCase();
    const hits  = __LAB_PATIENTS.filter(p =>
        p.name.toLowerCase().includes(lower) ||
        (p.phone && p.phone.includes(q))
    ).slice(0, 10);

    if (!hits.length) {
        dd.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:#94a3b8;">No patients found</div>';
        dd.style.display = 'block';
        return;
    }
    dd.innerHTML = hits.map(p => `
        <div onclick="labSelectPatient(${p.id},'${p.name.replace(/'/g,"\\'")}','${p.phone || ''}')"
             style="padding:8px 12px;cursor:pointer;font-size:12.5px;color:#1e293b;
                    border-bottom:1px solid #f1f5f9;transition:background .1s;"
             onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <div>${p.name}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:1px;">${p.phone || 'No phone'}</div>
        </div>
    `).join('');
    dd.style.display = 'block';
}

function labSelectPatient(id, name, phone) {
    document.getElementById('lab-patient-id').value       = id;
    document.getElementById('lab-patient-search').value   = name;
    document.getElementById('lab-patient-dropdown').style.display = 'none';
    document.getElementById('lab-patient-selected').style.display = 'block';
    document.getElementById('lab-patient-selected').textContent   = '✓ ' + name + (phone ? '  ·  ' + phone : '');
    // Also sync to Alpine form
    const alpine = document.querySelector('[x-data]');
    if (alpine && alpine._x_dataStack) {
        alpine._x_dataStack[0].form.patient_id = String(id);
    }
}

// Close patient dropdown on outside click
document.addEventListener('click', function(e) {
    const dd = document.getElementById('lab-patient-dropdown');
    if (dd && !dd.contains(e.target) && e.target.id !== 'lab-patient-search') {
        dd.style.display = 'none';
    }
});

// Vendor id => active services ([{category, service_name, default_rate}]) — used to
// auto-fill Estimated Cost from the vendor's own price list.
@php
    $labVendorServices = $vendors->mapWithKeys(fn ($v) => [
        $v->id => $v->services->map(fn ($s) => [
            'category'     => $s->category,
            'service_name' => $s->service_name,
            'default_rate' => (float) $s->default_rate,
        ]),
    ]);
@endphp
const LAB_VENDOR_SERVICES = @json($labVendorServices);

function labModule() {
    return {
        ...toothChartMixin(),
        ...shadeSelectMixin(),

        drawerOpen: false,
        editingId: null,
        form: {},
        costAutoFilled: false,

        // Generated from LabCase::WORK_CATEGORIES — single source of truth
        subtypes: @json(\App\Models\LabCase::WORK_CATEGORIES),

        subtypesFor(cat) { return this.subtypes[cat] ?? []; },

        // Look up the vendor's priced service for the selected category/subtype and
        // fill Estimated Cost — only while the field is empty or holds a value we
        // auto-filled ourselves, so it never overwrites a manual edit.
        autoFillCost() {
            if (!this.form.lab_vendor_id || !this.form.work_category) return;
            if (this.form.estimated_cost && !this.costAutoFilled) return;

            const services = LAB_VENDOR_SERVICES[this.form.lab_vendor_id] || [];
            const match = services.find(s => s.category === this.form.work_category
                    && (!this.form.work_subtype || s.service_name === this.form.work_subtype))
                || services.find(s => s.category === this.form.work_category);

            if (match) {
                this.form.estimated_cost = match.default_rate;
                this.costAutoFilled = true;
            }
        },

        _blankForm() {
            return {
                patient_id: '', doctor_id: '', work_category: '', work_subtype: '',
                lab_vendor: '', lab_vendor_id: '',
                estimated_cost: '', lab_cost: '',
                payment_status: 'pending',
                sent_date: '{{ now()->format("Y-m-d") }}',
                expected_return_date: '', received_date: '',
                status: 'draft', instructions: '', notes: '',
                item: { teeth: [], tooth_number: '', shade_guide: 'vita_classical', shade: '', per_tooth_shade: false, tooth_shades: {} },
            };
        },

        openDrawer() {
            this.editingId = null;
            this.costAutoFilled = false;
            this.resetForm();
            this.drawerOpen = true;
            this.$nextTick(() => {
                // Clear patient search
                const inp = document.getElementById('lab-patient-search');
                const sel = document.getElementById('lab-patient-selected');
                if (inp) inp.value = '';
                if (sel) sel.style.display = 'none';
            });
        },

        init() {
            // Expose editCase to non-Alpine JS (used by the ⋮ three-dot menu)
            window.labEditCase = (c) => this.editCase(c);
        },

        editCase(c) {
            this.editingId = c.id;
            this.costAutoFilled = false;
            this.form = {
                patient_id:           String(c.patient_id ?? ''),
                doctor_id:            String(c.doctor_id ?? ''),
                work_category:        c.work_category ?? c.work_type ?? '',
                work_subtype:         c.work_subtype ?? '',
                lab_vendor:           c.lab_vendor ?? '',
                lab_vendor_id:        c.lab_vendor_id ?? '',
                estimated_cost:       c.estimated_cost ?? '',   // Phase 2
                lab_cost:             c.lab_cost ?? '',
                payment_status:       c.payment_status ?? 'pending',  // Phase 2
                sent_date:            c.sent_date ?? '',
                expected_return_date: c.expected_return_date ?? '',
                received_date:        c.received_date ?? '',
                status:               c.status ?? 'draft',
                instructions:         c.instructions ?? '',
                notes:                c.internal_notes ?? c.notes ?? '',
                item: itemStateFromCaseItems(c.items),
            };
            this.drawerOpen = true;
            // Pre-fill patient search
            this.$nextTick(() => {
                const inp = document.getElementById('lab-patient-search');
                const sel = document.getElementById('lab-patient-selected');
                if (inp) {
                    const p = __LAB_PATIENTS.find(x => x.id == c.patient_id);
                    inp.value = p ? p.name : '';
                    if (sel) sel.style.display = 'none';
                }
            });
        },

        closeDrawer() { this.drawerOpen = false; },

        resetForm() {
            this.form = this._blankForm();
        },
    };
}

// ─── Lab Case Three-dot menu ──────────────────────────────────
let _lMenuId = null, _lMenuCanEdit = false, _lMenuDeleteMode = 'none', _lMenuCase = null;

function toggleLabMenu(e, id, canEdit, deleteMode, caseData) {
    e.stopPropagation();
    const menu = document.getElementById('lab-floating-menu');

    if (menu.style.display === 'block' && _lMenuId === id) {
        menu.style.display = 'none';
        return;
    }

    _lMenuId         = id;
    _lMenuCanEdit    = canEdit;
    _lMenuDeleteMode = deleteMode;
    _lMenuCase       = caseData;

    const rect = e.currentTarget.getBoundingClientRect();
    menu.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    menu.style.left = (rect.right  - 155 + window.scrollX) + 'px';

    const editBtn = document.getElementById('lmenu-edit');
    const delBtn  = document.getElementById('lmenu-delete');
    const divider = document.getElementById('lmenu-divider');

    if (editBtn) editBtn.style.display = canEdit ? 'block' : 'none';
    if (delBtn)  delBtn.style.display  = (deleteMode !== 'none') ? 'block' : 'none';
    if (divider) divider.style.display = (deleteMode !== 'none') ? 'block' : 'none';

    menu.style.display = 'block';
}

// Close menu on outside click
document.addEventListener('click', function(e) {
    const menu = document.getElementById('lab-floating-menu');
    if (menu && !menu.contains(e.target)) menu.style.display = 'none';
});

// Wire up menu buttons
document.addEventListener('DOMContentLoaded', function() {
    const btnEdit  = document.getElementById('lmenu-edit');
    const btnPrint = document.getElementById('lmenu-print');
    const btnDel   = document.getElementById('lmenu-delete');
    const btnView = document.getElementById('lmenu-view');
    if (btnView)  btnView.addEventListener('click',  labMenuView);
    if (btnEdit)  btnEdit.addEventListener('click',  labMenuEdit);
    if (btnPrint) btnPrint.addEventListener('click', labMenuPrint);
    if (btnDel)   btnDel.addEventListener('click',   labMenuDelete);
});

function labMenuView() {
    document.getElementById('lab-floating-menu').style.display = 'none';
    if (!_lMenuId) return;
    window.location.href = '/lab/' + _lMenuId;
}

function labMenuEdit() {
    document.getElementById('lab-floating-menu').style.display = 'none';
    if (!_lMenuCase) return;
    // Call the global bridge set up by the Alpine component
    if (typeof window.labEditCase === 'function') window.labEditCase(_lMenuCase);
}

function labMenuPrint() {
    document.getElementById('lab-floating-menu').style.display = 'none';
    if (!_lMenuId) return;
    window.open('/lab/' + _lMenuId + '/print', '_blank');
}

function labMenuDelete() {
    document.getElementById('lab-floating-menu').style.display = 'none';
    if (!_lMenuId || _lMenuDeleteMode === 'none') return;

    const c = _lMenuCase;
    const subtitle = document.getElementById('lab-del-subtitle');
    const caseNo   = c ? (c.case_number || 'this case') : 'this case';
    const patient  = c && c.patient ? c.patient.name : '';
    if (subtitle) subtitle.textContent = caseNo + (patient ? ' · ' + patient : '');

    const reasonEl = document.getElementById('lab-del-reason');
    const submitEl = document.getElementById('lab-del-submit');
    if (reasonEl) reasonEl.value = '';
    if (submitEl) { submitEl.disabled = true; submitEl.style.opacity = '0.5'; }

    const form = document.getElementById('lab-delete-form');
    if (form) form.action = '/lab/' + _lMenuId;

    document.getElementById('modal-lab-delete').style.display = 'flex';
}

function labDelReasonInput(el) {
    const btn = document.getElementById('lab-del-submit');
    if (!btn) return;
    const ok = el.value.trim().length >= 5;
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '0.5';
}

function submitLabDelete() {
    const reason = (document.getElementById('lab-del-reason')?.value || '').trim();
    if (reason.length < 5) { alert('Please enter a reason (at least 5 characters).'); return; }
    document.getElementById('lab-delete-reason-val').value = reason;
    document.getElementById('lab-delete-form').submit();
}
</script>
@endsection
