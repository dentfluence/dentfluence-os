@extends('layouts.app')
@section('page-title', 'Lab Case — ' . $labCase->case_number)

@section('content')

@php
    $canEdit  = !in_array($labCase->status, ['complete', 'rejected']);
    $isAdmin  = auth()->user()?->isAdminRole();
    $rx       = $labCase->prescription;

    $nextActionMap = [
        'order_placed'    => ['label' => 'Mark as Sent to Lab',   'to' => 'impression_sent', 'color' => 'indigo'],
        'impression_sent' => ['label' => 'Trial Received',         'to' => 'trial_received',  'color' => 'amber'],
        'scan_sent'       => ['label' => 'Trial Received',         'to' => 'trial_received',  'color' => 'amber'],
        'trial_received'  => ['label' => 'Return Trial to Lab',    'to' => 'trial_returned',  'color' => 'orange'],
        'trial_returned'  => ['label' => 'Trial Received Again',   'to' => 'trial_received',  'color' => 'amber'],
        'final_received'  => ['label' => 'Mark as Delivered ✓',   'to' => 'complete',         'color' => 'green'],
    ];

    if ($labCase->status === 'draft') {
        $primaryAction = ['label' => 'Place Order', 'to' => 'order_placed', 'color' => 'brand'];
        $secondaryAction = null;
    } elseif (isset($nextActionMap[$labCase->status])) {
        $primaryAction = $nextActionMap[$labCase->status];
        $secondaryAction = in_array($labCase->status, ['impression_sent', 'scan_sent'])
            ? ['label' => 'Skip Trial → Final Received', 'to' => 'final_received', 'color' => 'blue']
            : null;
    } else {
        $primaryAction = null;
        $secondaryAction = null;
    }

    $colorMap = [
        'brand'  => 'bg-[#6a0f70] hover:bg-[#380740]',
        'indigo' => 'bg-indigo-600 hover:bg-indigo-700',
        'amber'  => 'bg-amber-500 hover:bg-amber-600',
        'orange' => 'bg-orange-500 hover:bg-orange-600',
        'green'  => 'bg-green-600 hover:bg-green-700',
        'blue'   => 'bg-blue-600 hover:bg-blue-700',
    ];
@endphp

<div class="p-4 md:p-6 max-w-6xl mx-auto space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('lab.index') }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-[#6a0f70] mb-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Lab Cases
            </a>
            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                <h1 class="text-2xl font-display font-semibold text-[#6a0f70]">{{ $labCase->case_number }}</h1>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ \App\Models\LabCase::PRIORITY_COLORS[$labCase->priority] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($labCase->priority) }}
                </span>
                <span class="px-3 py-0.5 rounded-full text-xs font-semibold {{ $labCase->statusColor() }}">
                    {{ $labCase->statusLabel() }}
                    @if($labCase->isInTrialLoop()) · {{ $labCase->trialLabel() }} @endif
                </span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ \App\Models\LabCase::BILLING_STATUS_COLORS[$labCase->billing_status ?? 'unbilled'] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ \App\Models\LabCase::BILLING_STATUSES[$labCase->billing_status ?? 'unbilled'] ?? 'Unbilled' }}
                </span>
                @if($labCase->is_remake)
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">🔁 Remake</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $labCase->work_category }}@if($labCase->work_subtype) · {{ $labCase->work_subtype }}@endif
                · {{ $labCase->agingLabel() }} aging
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('lab.print', $labCase) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                🖨️ Print
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- NEXT ACTION BANNER --}}
    @if($primaryAction)
    <div class="bg-white rounded-xl border-2 border-[#d8b4e2] shadow-sm p-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-0.5">Next Action</p>
            <p class="font-semibold text-gray-800">
                @switch($labCase->status)
                    @case('draft')          Ready to place the order with the lab? @break
                    @case('order_placed')   Has the impression / scan been sent? @break
                    @case('impression_sent')
                    @case('scan_sent')      Has the trial arrived from the lab? @break
                    @case('trial_received') Doctor: approve the trial or return it. @break
                    @case('trial_returned') Waiting for lab correction. @break
                    @case('final_received') Final work in — book patient for delivery. @break
                    @default @break
                @endswitch
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('lab.transition', [$labCase, $primaryAction['to']]) }}">
                @csrf
                <button type="submit"
                    class="px-5 py-2.5 text-white text-sm font-semibold rounded-lg shadow transition {{ $colorMap[$primaryAction['color']] ?? 'bg-[#6a0f70] hover:bg-[#380740]' }}">
                    {{ $primaryAction['label'] }}
                </button>
            </form>
            @if($secondaryAction)
            <form method="POST" action="{{ route('lab.transition', [$labCase, $secondaryAction['to']]) }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2.5 text-white text-xs font-medium rounded-lg shadow transition {{ $colorMap[$secondaryAction['color']] ?? 'bg-blue-600 hover:bg-blue-700' }}">
                    {{ $secondaryAction['label'] }}
                </button>
            </form>
            @endif
            @if(!in_array($labCase->status, ['final_received','complete','rejected']) && $isAdmin)
            <form method="POST" action="{{ route('lab.transition', [$labCase, 'rejected']) }}">
                @csrf
                <button type="submit"
                    onclick="return confirm('Mark this case as rejected?')"
                    class="px-4 py-2.5 text-red-600 text-xs font-medium border border-red-200 rounded-lg hover:bg-red-50 transition">
                    Reject
                </button>
            </form>
            @endif
        </div>
    </div>
    @elseif($labCase->status === 'complete')
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-2xl">✅</span>
            <div>
                <p class="font-semibold text-green-800">Case Complete</p>
                <p class="text-sm text-green-600">Delivered {{ $labCase->delivered_date?->format('d M Y') ?? '' }}</p>
            </div>
        </div>
        @include('lab.partials.rating-modal', ['labCase' => $labCase])
    </div>
    @elseif($labCase->status === 'rejected')
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
        <span class="text-2xl">❌</span>
        <p class="font-semibold text-red-700">Case Rejected / Cancelled</p>
    </div>
    @endif

    {{-- MAIN GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT COLUMN (2/3) --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- 1. PATIENT + TREATMENT --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Patient & Treatment</h2>
                    @if($canEdit)
                    <button type="button"
                        onclick="document.getElementById('edit-info-panel').classList.toggle('hidden')"
                        class="text-xs text-[#6a0f70] hover:text-[#380740] font-medium">Edit</button>
                    @endif
                </div>
                <dl class="p-5 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Patient</dt>
                        <dd class="font-medium text-gray-800">
                            @if($labCase->patient)
                                <a href="{{ route('patients.show', $labCase->patient_id) }}" class="text-[#6a0f70] hover:underline">{{ $labCase->patient->name }}</a>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $labCase->patient->phone }}</p>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Doctor</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->doctor?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Priority</dt>
                        <dd><span class="px-2 py-0.5 rounded text-xs font-semibold {{ \App\Models\LabCase::PRIORITY_COLORS[$labCase->priority] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($labCase->priority) }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Treatment</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->work_category }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Sub-type</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->work_subtype ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Teeth</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->toothSummary() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Order Date</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->order_placed_date?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Sent to Lab</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->impression_sent_date?->format('d M Y') ?? ($labCase->sent_date?->format('d M Y') ?? '—') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Expected Return</dt>
                        <dd class="font-medium {{ $labCase->isOverdue() ? 'text-red-600' : 'text-gray-800' }}">
                            {{ $labCase->expected_return_date?->format('d M Y') ?? '—' }}
                            @if($labCase->isOverdue())
                                <span class="ml-1 text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">{{ $labCase->overdueDays() }}d overdue</span>
                            @endif
                        </dd>
                    </div>
                    @if($labCase->technician_name)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Technician</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->technician_name }}</dd>
                    </div>
                    @endif
                    @if($labCase->trial_round > 0)
                    <div>
                        <dt class="text-xs text-gray-400 mb-0.5">Trial Rounds</dt>
                        <dd class="font-medium text-gray-800">{{ $labCase->trial_round }}</dd>
                    </div>
                    @endif
                    @if($labCase->internal_notes)
                    <div class="col-span-2 md:col-span-3">
                        <dt class="text-xs text-gray-400 mb-0.5">Internal Notes</dt>
                        <dd class="text-gray-700 text-sm bg-gray-50 rounded-lg px-3 py-2">{{ $labCase->internal_notes }}</dd>
                    </div>
                    @endif
                </dl>

                @if($canEdit)
                <div id="edit-info-panel" class="hidden border-t border-gray-200 p-5">
                    <form method="POST" action="{{ route('lab.update', $labCase) }}">
                        @csrf @method('PUT')
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Priority</label>
                                <select name="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    @foreach(\App\Models\LabCase::PRIORITIES as $p)
                                    <option value="{{ $p }}" {{ $labCase->priority === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Expected Return</label>
                                <input type="date" name="expected_return_date" value="{{ $labCase->expected_return_date?->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Technician</label>
                                <input type="text" name="technician_name" value="{{ $labCase->technician_name }}" placeholder="Lab technician" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div class="col-span-2 md:col-span-3">
                                <label class="text-xs text-gray-500 mb-1 block">Internal Notes</label>
                                <textarea name="internal_notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none">{{ $labCase->internal_notes }}</textarea>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <button type="submit" class="px-4 py-2 bg-[#6a0f70] text-white text-xs font-medium rounded-lg hover:bg-[#380740] transition">Save Changes</button>
                            <button type="button" onclick="document.getElementById('edit-info-panel').classList.add('hidden')" class="px-4 py-2 text-gray-500 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>

            {{-- 2. REMAKE BANNER --}}
            @if($labCase->is_remake)
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
                <span class="text-xl mt-0.5">🔁</span>
                <div>
                    <p class="font-semibold text-red-800 text-sm">Remake Case — {{ $labCase->repeatReasonLabel() }}</p>
                    @if($labCase->remakeOf)
                    <p class="text-xs text-red-500 mt-0.5">
                        Original: <a href="{{ route('lab.show', $labCase->remake_of_id) }}" class="font-medium underline hover:text-red-700">{{ $labCase->remakeOf->case_number }}</a>
                        ({{ $labCase->remakeOf->patient?->name }})
                    </p>
                    @endif
                </div>
            </div>
            @endif

            {{-- 3. CLINICAL PRESCRIPTION --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                 x-data="{ showRxForm: {{ $rx ? 'false' : 'true' }} }">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-gray-700">Clinical Prescription</h2>
                        @if($rx)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">On file</span>
                        @else
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Not added yet</span>
                        @endif
                    </div>
                    <button type="button" @click="showRxForm = !showRxForm"
                        class="text-xs text-[#6a0f70] hover:text-[#380740] font-medium">
                        <span x-text="showRxForm ? 'Hide' : '{{ $rx ? 'Edit Prescription' : 'Add Prescription' }}'"></span>
                    </button>
                </div>

                {{-- Prescription read view --}}
                @if($rx)
                <div class="p-5" x-show="!showRxForm">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-4">
                        @if($rx->material)<div><dt class="text-xs text-gray-400 mb-0.5">Material</dt><dd class="font-semibold text-gray-800">{{ $rx->material }}</dd></div>@endif
                        @if($rx->shade)<div><dt class="text-xs text-gray-400 mb-0.5">Shade</dt><dd class="font-semibold text-gray-800">{{ $rx->shade }}</dd></div>@endif
                        @if($rx->stump_shade)<div><dt class="text-xs text-gray-400 mb-0.5">Stump Shade</dt><dd class="font-semibold text-gray-800">{{ $rx->stump_shade }}</dd></div>@endif
                    </div>
                    @php $schema = \App\Models\LabCasePrescription::FIELD_SCHEMA[$labCase->work_category] ?? \App\Models\LabCasePrescription::FIELD_SCHEMA['Other']; @endphp
                    @if($rx->hasClinicalData())
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2 text-sm border-t border-gray-100 pt-4 mb-4">
                        @foreach($schema as $field)
                        @php $val = $rx->field($field['key']); @endphp
                        @if($val !== null && $val !== '' && $val !== false && $val !== '0')
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ $field['label'] }}</dt>
                            <dd class="font-medium text-gray-800 text-sm">
                                @if($field['type'] === 'boolean') {{ $val ? '✓ Yes' : '✗ No' }}
                                @elseif($field['type'] === 'textarea') <span class="text-gray-600 text-xs">{{ $val }}</span>
                                @else {{ $val }} @endif
                            </dd>
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @endif
                    @if($rx->special_instructions)
                    <div class="bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700 border-t border-gray-100 pt-3 mt-2">
                        <p class="text-xs text-gray-400 mb-1">Special Instructions</p>
                        {{ $rx->special_instructions }}
                    </div>
                    @endif
                    <p class="text-xs text-gray-400 mt-3">By {{ $rx->createdBy?->name ?? 'staff' }} · {{ $rx->updated_at->format('d M Y') }}</p>
                </div>
                @endif

                {{-- Prescription builder form --}}
                <div x-show="showRxForm" class="p-5">
                    <form method="POST" action="{{ $rx ? route('lab.prescription.update', $labCase) : route('lab.prescription.store', $labCase) }}">
                        @csrf
                        @if($rx) @method('PUT') @endif
                        @include('lab.partials.prescription-builder', ['labCase' => $labCase, 'hideSubmit' => false])
                    </form>
                </div>
            </div>

            {{-- 4. ATTACHMENTS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Attachments @if($labCase->attachments->count())<span class="ml-1 text-xs bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded-full">{{ $labCase->attachments->count() }}</span>@endif</h2>
                </div>
                <div class="p-5">
                    @if($labCase->attachments->isEmpty())
                    <p class="text-center text-sm text-gray-400 py-4">No attachments yet.</p>
                    @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                        @foreach($labCase->attachments as $att)
                        @php $isImg = str_starts_with($att->mime_type ?? '', 'image/'); @endphp
                        <div class="group relative bg-gray-50 border border-gray-200 rounded-xl p-3 text-center hover:border-[#d8b4e2] transition">
                            @if($isImg)
                            <a href="{{ Storage::url($att->file_path) }}" target="_blank">
                                <img src="{{ Storage::url($att->file_path) }}" alt="{{ $att->file_name }}" class="w-full h-20 object-cover rounded-lg mb-2">
                            </a>
                            @else
                            <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="flex flex-col items-center gap-1 mb-2">
                                <div class="w-12 h-12 bg-[#f3e8f5] rounded-lg flex items-center justify-center text-xl">
                                    @if(str_ends_with(strtolower($att->file_name), '.pdf')) 📄
                                    @elseif(str_ends_with(strtolower($att->file_name), '.stl')) 🦷
                                    @else 📎 @endif
                                </div>
                            </a>
                            @endif
                            <p class="text-xs text-gray-600 truncate" title="{{ $att->file_name }}">{{ $att->file_name }}</p>
                            <p class="text-xs text-gray-400">{{ round($att->file_size / 1024) }} KB</p>
                            <form method="POST" action="{{ route('lab.attachments.destroy', $att) }}" class="mt-1 opacity-0 group-hover:opacity-100 transition">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('Remove?')" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <form method="POST" action="{{ route('lab.attachments.store', $labCase) }}" enctype="multipart/form-data"
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:border-[#d8b4e2] transition">
                        @csrf
                        <input type="file" name="file" id="direct-file-input" accept=".jpg,.jpeg,.png,.gif,.pdf,.stl,.zip" class="hidden" onchange="this.form.submit()">
                        <label for="direct-file-input" class="cursor-pointer text-sm text-gray-400 hover:text-[#6a0f70]">
                            📂 Click to upload · Photos, X-rays, STL, PDF (max 10MB)
                        </label>
                    </form>
                </div>
            </div>

            {{-- 5. TIMELINE --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">Timeline & Audit Log</h2>
                </div>
                <div class="p-5">
                    @if($labCase->events->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-4">No events recorded yet.</p>
                    @else
                    <ol class="relative border-l border-gray-200 space-y-5 ml-3">
                        @foreach($labCase->events->sortByDesc('created_at') as $event)
                        <li class="ml-4">
                            <div class="absolute w-2.5 h-2.5 rounded-full -left-1.5 border border-white
                                @switch($event->event_type)
                                    @case('status_changed')       bg-[#6a0f70] @break
                                    @case('created')              bg-green-500 @break
                                    @case('prescription_saved')
                                    @case('prescription_updated') bg-purple-500 @break
                                    @case('archived')             bg-red-400 @break
                                    @default                      bg-gray-300
                                @endswitch"></div>
                            <p class="text-sm font-medium text-gray-800">{{ $event->description }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $event->created_at->format('d M Y, H:i') }}
                                @if($event->createdBy) · {{ $event->createdBy->name }} @endif
                                @if($event->from_status && $event->to_status)
                                · <span class="text-gray-500">{{ \App\Models\LabCase::STATUS_LABELS[$event->from_status] ?? $event->from_status }} → {{ \App\Models\LabCase::STATUS_LABELS[$event->to_status] ?? $event->to_status }}</span>
                                @endif
                            </p>
                        </li>
                        @endforeach
                    </ol>
                    @endif
                </div>
            </div>

        </div>{{-- /left col --}}

        {{-- RIGHT COLUMN (1/3) --}}
        <div class="space-y-5">

            {{-- VENDOR --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Laboratory</h2>
                    @if($canEdit)
                    <button type="button" onclick="document.getElementById('vendor-edit-panel').classList.toggle('hidden')"
                        class="text-xs text-[#6a0f70] hover:text-[#380740] font-medium">Change</button>
                    @endif
                </div>
                <div class="p-4">
                    @if($labCase->vendor)
                        <p class="font-semibold text-gray-800 text-sm">{{ $labCase->vendor->name }}</p>
                        @if($labCase->vendor->phone)
                        <a href="tel:{{ $labCase->vendor->phone }}" class="text-xs text-blue-600 hover:underline flex items-center gap-1 mt-1">📞 {{ $labCase->vendor->phone }}</a>
                        @endif
                        @if($labCase->vendor->whatsapp_number)
                        <a href="https://wa.me/91{{ preg_replace('/\D/', '', $labCase->vendor->whatsapp_number) }}?text={{ urlencode('Hi, regarding case ' . $labCase->case_number) }}"
                           target="_blank" class="text-xs text-green-600 hover:underline flex items-center gap-1 mt-1">💬 WhatsApp</a>
                        @endif
                    @else
                        <p class="text-sm text-gray-400">No lab assigned.</p>
                    @endif
                    <div id="vendor-edit-panel" class="hidden mt-3 pt-3 border-t border-gray-100">
                        <form method="POST" action="{{ route('lab.update', $labCase) }}">
                            @csrf @method('PUT')
                            <select name="lab_vendor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2">
                                <option value="">— No Lab —</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}" {{ $labCase->lab_vendor_id == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full px-3 py-2 bg-[#6a0f70] text-white text-xs font-medium rounded-lg hover:bg-[#380740] transition">Update Lab</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- FINANCIALS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Financials</h2>
                    @if($canEdit)
                    <button type="button" onclick="document.getElementById('finance-edit-panel').classList.toggle('hidden')"
                        class="text-xs text-[#6a0f70] hover:text-[#380740] font-medium">Edit</button>
                    @endif
                </div>
                <div class="p-4 space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Estimated</span><span class="font-medium text-gray-800">{{ $labCase->estimated_cost ? '₹ ' . number_format($labCase->estimated_cost, 0) : '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Actual Cost</span><span class="font-semibold {{ $labCase->lab_cost ? 'text-gray-800' : 'text-gray-400' }}">{{ $labCase->lab_cost ? '₹ ' . number_format($labCase->lab_cost, 0) : '—' }}</span></div>
                    @if($labCase->lab_cost && $labCase->estimated_cost)
                    @php $variance = $labCase->costVariance(); @endphp
                    <div class="flex justify-between text-xs"><span class="text-gray-400">Variance</span><span class="{{ $variance > 0 ? 'text-red-600' : 'text-green-600' }} font-medium">{{ $variance > 0 ? '+' : '' }}₹ {{ number_format($variance, 0) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <span class="text-gray-500">Payment</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $labCase->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($labCase->payment_status === 'monthly_account' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                            {{ ucfirst(str_replace('_', ' ', $labCase->payment_status ?? 'pending')) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Billing</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ \App\Models\LabCase::BILLING_STATUS_COLORS[$labCase->billing_status ?? 'unbilled'] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ \App\Models\LabCase::BILLING_STATUSES[$labCase->billing_status ?? 'unbilled'] ?? 'Unbilled' }}
                        </span>
                    </div>
                    <div id="finance-edit-panel" class="hidden border-t border-gray-100 pt-3">
                        <form method="POST" action="{{ route('lab.update', $labCase) }}">
                            @csrf @method('PUT')
                            <div class="space-y-2">
                                <div><label class="text-xs text-gray-400 mb-0.5 block">Estimated Cost (₹)</label><input type="number" name="estimated_cost" step="0.01" value="{{ $labCase->estimated_cost }}" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm"></div>
                                <div><label class="text-xs text-gray-400 mb-0.5 block">Actual Cost (₹)</label><input type="number" name="lab_cost" step="0.01" value="{{ $labCase->lab_cost }}" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm"></div>
                                <div>
                                    <label class="text-xs text-gray-400 mb-0.5 block">Payment Status</label>
                                    <select name="payment_status" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                        <option value="pending" {{ ($labCase->payment_status ?? 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="paid" {{ $labCase->payment_status === 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="monthly_account" {{ $labCase->payment_status === 'monthly_account' ? 'selected' : '' }}>Monthly Account</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full px-3 py-2 bg-[#6a0f70] text-white text-xs font-medium rounded-lg hover:bg-[#380740] transition">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- WORKFLOW PROGRESS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Workflow Progress</h2>
                @php
                $steps = [
                    ['label' => 'Case Created',        'date' => $labCase->created_at,           'done' => true],
                    ['label' => 'Order Placed',         'date' => $labCase->order_placed_date,    'done' => (bool) $labCase->order_placed_date],
                    ['label' => 'Sent to Lab',          'date' => $labCase->impression_sent_date, 'done' => (bool) $labCase->impression_sent_date],
                    ['label' => 'Trial / Review',       'date' => null,                           'done' => $labCase->trial_round > 0, 'note' => $labCase->trial_round > 0 ? $labCase->trial_round . ' round(s)' : null],
                    ['label' => 'Final Work In',        'date' => $labCase->final_received_date,  'done' => (bool) $labCase->final_received_date],
                    ['label' => 'Delivered to Patient', 'date' => $labCase->delivered_date,       'done' => (bool) $labCase->delivered_date],
                ];
                @endphp
                <div class="space-y-2">
                    @foreach($steps as $step)
                    <div class="flex items-center gap-2.5">
                        <div class="w-4 h-4 rounded-full flex-shrink-0 flex items-center justify-center {{ $step['done'] ? 'bg-green-500' : 'bg-gray-200' }}">
                            @if($step['done'])
                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 flex items-center justify-between">
                            <span class="text-xs {{ $step['done'] ? 'text-gray-700 font-medium' : 'text-gray-400' }}">
                                {{ $step['label'] }}@if(isset($step['note']) && $step['note']) <span class="text-gray-400">({{ $step['note'] }})</span>@endif
                            </span>
                            @if($step['date'])
                            <span class="text-xs text-gray-400">{{ is_string($step['date']) ? $step['date'] : $step['date']->format('d M') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- QUICK ACTIONS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4" x-data="{ showDelete: false }">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('patients.show', $labCase->patient_id) }}?tab=lab" class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#6a0f70] py-1">👤 View Patient Profile</a>
                    <a href="{{ route('lab.print', $labCase) }}" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#6a0f70] py-1">🖨️ Print Case Sheet</a>
                    @if($labCase->vendor?->whatsapp_number)
                    <a href="https://wa.me/91{{ preg_replace('/\D/', '', $labCase->vendor->whatsapp_number) }}?text={{ urlencode('Hi, following up on lab case ' . $labCase->case_number . ' for ' . ($labCase->patient?->name ?? '') . '. Expected: ' . ($labCase->expected_return_date?->format('d M Y') ?? 'TBD')) }}"
                       target="_blank" class="flex items-center gap-2 text-sm text-green-600 hover:text-green-800 py-1">💬 WhatsApp Lab</a>
                    @endif
                    <form method="POST" action="{{ route('lab.duplicate', $labCase) }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#6a0f70] py-1 w-full text-left">📋 Duplicate Case</button>
                    </form>
                    @if($isAdmin && !in_array($labCase->status, ['final_received','complete']))
                    <button type="button" @click="showDelete = !showDelete" class="flex items-center gap-2 text-sm text-red-500 hover:text-red-700 py-1">🗑️ Archive Case</button>
                    <div x-show="showDelete" class="mt-1">
                        <form method="POST" action="{{ route('lab.destroy', $labCase) }}">
                            @csrf @method('DELETE')
                            <input type="text" name="delete_reason" required placeholder="Reason for archiving…" class="w-full border border-red-200 rounded-lg px-3 py-1.5 text-xs mb-2">
                            <button type="submit" onclick="return confirm('Archive this case?')" class="w-full px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">Confirm Archive</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- /right col --}}
    </div>{{-- /grid --}}
</div>
@endsection
