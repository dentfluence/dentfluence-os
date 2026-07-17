@extends('layouts.app')
@section('page-title', $prescription->prescription_number)

@section('content')
<div class="p-4 md:p-6 max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5 gap-4">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-display font-semibold text-brand-800 font-mono">
                    {{ $prescription->prescription_number }}
                </h1>
                @php
                $sStyle = match($prescription->status) {
                    'issued','printed'       => 'bg-green-100 text-green-700',
                    'draft'                  => 'bg-amber-100 text-amber-700',
                    'whatsapp_sent'          => 'bg-lime-100 text-lime-700',
                    'email_sent'             => 'bg-sky-100 text-sky-700',
                    'revised'                => 'bg-purple-100 text-purple-700',
                    'cancelled'              => 'bg-red-100 text-red-500',
                    default                  => 'bg-gray-100 text-gray-500',
                };
                $sLabel = match($prescription->status) {
                    'whatsapp_sent' => 'WhatsApp Sent',
                    'email_sent'    => 'Emailed',
                    default         => ucfirst($prescription->status),
                };
            @endphp
            <span class="text-sm px-2.5 py-0.5 rounded-full font-medium {{ $sStyle }}">{{ $sLabel }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $patient->name }}
                &nbsp;·&nbsp; {{ $prescription->created_at->format('d M Y, h:i A') }}
                &nbsp;·&nbsp; {{ $prescription->prescribedBy?->doctor_name ?? '—' }}
            </p>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-2 flex-wrap shrink-0">
            @if(!$prescription->isCancelled())
                <a href="{{ route('patients.prescriptions.edit', [$patient, $prescription]) }}"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    ✏️ Edit
                </a>

                <form method="POST" action="{{ route('patients.prescriptions.repeat', [$patient, $prescription]) }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-3 py-1.5 text-sm border border-brand-300 text-brand-700 rounded-xl hover:bg-brand-50 transition">
                        Repeat Rx
                    </button>
                </form>

                {{-- Print / PDF --}}
                <a href="{{ route('patients.prescriptions.print', [$patient, $prescription]) }}"
                   target="_blank"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition"
                   title="Open print / PDF view">
                    Print / PDF
                </a>

                {{-- WhatsApp Send --}}
                @php $hasPhone = !empty($patient->phone); @endphp
                <button
                    id="wa-send-btn"
                    data-url="{{ route('patients.prescriptions.whatsapp-send', [$patient, $prescription]) }}"
                    data-has-phone="{{ $hasPhone ? 'true' : 'false' }}"
                    onclick="rxSendWhatsApp(this)"
                    class="px-3 py-1.5 text-sm rounded-xl font-medium transition flex items-center gap-1
                           {{ $prescription->status === 'whatsapp_sent' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-[#25D366] text-white hover:bg-[#1ebe5c]' }}"
                    title="{{ $hasPhone ? 'Send prescription to patient via WhatsApp' : 'No phone number saved for this patient' }}">
                    {{ $prescription->status === 'whatsapp_sent' ? 'Resend WhatsApp' : 'Send WhatsApp' }}
                </button>
                @if($prescription->whatsapp_sent_at)
                    <span class="text-xs text-green-600 self-center">
                        ✓ Sent {{ $prescription->whatsapp_sent_at->diffForHumans() }}
                    </span>
                @endif
            @endif

            <a href="{{ route('patients.prescriptions.index', $patient) }}"
               class="px-3 py-1.5 text-sm text-gray-500 hover:text-brand-700 transition">
                ← Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ── Main Rx Card ── --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Clinical context --}}
            @if($prescription->chief_complaint || $prescription->diagnosis || $prescription->weight || $prescription->follow_up_date || $prescription->follow_up_after_days)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b border-gray-100">Clinical Context</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    @if($prescription->chief_complaint)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Chief Complaint</dt>
                            <dd class="text-gray-800">{{ $prescription->chief_complaint }}</dd>
                        </div>
                    @endif
                    @if($prescription->diagnosis)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Diagnosis</dt>
                            <dd class="text-gray-800">{{ $prescription->diagnosis }}</dd>
                        </div>
                    @endif
                    @if($prescription->weight)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Weight</dt>
                            <dd class="text-gray-800">{{ $prescription->weight }} kg</dd>
                        </div>
                    @endif
                    @if($prescription->follow_up_date || $prescription->follow_up_after_days)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Follow-up</dt>
                            <dd class="text-gray-800">{{ $prescription->followUpLabel() }}</dd>
                        </div>
                    @endif
                    @if($prescription->language && $prescription->language !== 'en')
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Language</dt>
                            <dd class="text-gray-800">{{ strtoupper($prescription->language) }}</dd>
                        </div>
                    @endif
                </dl>
                @if($prescription->general_instructions)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400 font-medium mb-1">General Instructions</p>
                        <p class="text-sm text-gray-700">{{ $prescription->general_instructions }}</p>
                    </div>
                @endif
            </div>
            @endif

            {{-- Drug items table — styled to match the visit prescription panel --}}
            <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                {{-- Panel-style header --}}
                <div class="flex items-center gap-2 px-5 py-3 bg-red-50 border-b border-red-200">
                    <svg width="14" height="14" fill="none" stroke="#dc2626" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"/>
                    </svg>
                    <span class="text-xs font-bold text-red-700 uppercase tracking-wide">Prescription</span>
                    @if($prescription->items->isNotEmpty())
                        <span class="text-xs text-gray-400">· {{ $prescription->items->count() }} {{ Str::plural('drug', $prescription->items->count()) }}</span>
                    @endif
                </div>

                <div class="p-5">
                @if($prescription->items->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">No medications on this prescription.</p>
                @else
                    {{-- Column header row --}}
                    <div class="hidden sm:grid grid-cols-[2fr_auto_1fr_1fr_1fr_1.5fr_1fr] gap-2 text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 px-1">
                        <span>Drug</span>
                        <span class="text-center w-10">SOS</span>
                        <span class="text-center">Morn</span>
                        <span class="text-center">Noon</span>
                        <span class="text-center">Night</span>
                        <span>Duration</span>
                        <span class="text-center">Total</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($prescription->items as $item)
                            <div class="grid grid-cols-1 sm:grid-cols-[2fr_auto_1fr_1fr_1fr_1.5fr_1fr] gap-2 items-center
                                        border border-red-100 rounded-lg px-3 py-2.5 bg-red-50/30 text-sm">
                                {{-- Drug name + sub --}}
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $item->drug_name }}</p>
                                    <p class="text-xs text-gray-400 flex gap-1.5 flex-wrap mt-0.5">
                                        @if($item->generic_name) <span>{{ $item->generic_name }}</span> @endif
                                        @if($item->strength)     <span>· {{ $item->strength }}</span> @endif
                                        @if($item->dosage_form)  <span>· {{ $item->dosage_form }}</span> @endif
                                    </p>
                                    @if($item->food_advice)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $item->food_advice }}</p>
                                    @endif
                                    @if($item->instructions)
                                        <p class="text-xs text-gray-400 italic mt-0.5">{{ $item->instructions }}</p>
                                    @endif
                                </div>
                                {{-- SOS --}}
                                <div class="flex justify-center w-10">
                                    @if($item->is_sos)
                                        <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded font-bold">SOS</span>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </div>
                                {{-- Morn / Noon / Night — "5 ml" for liquids, plain count otherwise --}}
                                <p class="text-center font-semibold text-gray-700">{{ $item->doseCell($item->morning) }}</p>
                                <p class="text-center font-semibold text-gray-700">{{ $item->doseCell($item->afternoon) }}</p>
                                <p class="text-center font-semibold text-gray-700">{{ $item->doseCell($item->night) }}</p>
                                {{-- Duration --}}
                                <p class="text-gray-700 text-xs">{{ $item->duration ? $item->duration.' '.($item->duration_unit ?? 'days') : '—' }}</p>
                                {{-- Total --}}
                                <p class="text-center font-bold text-gray-800">
                                    {{ $item->quantityLabel() }}
                                    @if($item->quantity_manual)
                                        <span class="text-amber-400 text-xs" title="Manually set">✎</span>
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>
            </div>

            {{-- CDSS Overrides (if any) --}}
            @if($prescription->overrides->isNotEmpty())
                <div class="bg-amber-50 rounded-xl border border-amber-200 p-5">
                    <h2 class="text-sm font-semibold text-amber-800 mb-3 pb-2 border-b border-amber-200">
                        CDSS Overrides ({{ $prescription->overrides->count() }})
                    </h2>
                    <div class="space-y-2">
                        @foreach($prescription->overrides as $ov)
                            <div class="text-xs text-amber-900">
                                <span class="font-semibold uppercase">{{ $ov->alert_type }}</span>
                                — {{ $ov->alert_message }}
                                <span class="block text-amber-700 mt-0.5 italic">Reason: {{ $ov->override_reason ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>{{-- /left --}}

        {{-- ── Right: meta + audit ── --}}
        <div class="space-y-4">

            {{-- Meta --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Details</h3>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Rx Number</dt>
                        <dd class="font-mono font-medium text-gray-700">{{ $prescription->prescription_number }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Version</dt>
                        <dd class="text-gray-700">v{{ $prescription->version }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Created</dt>
                        <dd class="text-gray-700">{{ $prescription->created_at->format('d M Y') }}</dd>
                    </div>
                    @if($prescription->printed_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Last Printed</dt>
                        <dd class="text-gray-700">{{ $prescription->printed_at->format('d M Y') }}</dd>
                    </div>
                    @endif
                    @if($prescription->repeatedFrom)
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Repeated From</dt>
                        <dd class="font-mono text-brand-600 text-xs">{{ $prescription->repeatedFrom->prescription_number }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Audit log --}}
            @if($prescription->auditLogs->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Audit Log</h3>
                <ol class="relative border-l border-gray-200 ml-2 space-y-3">
                    @foreach($prescription->auditLogs as $log)
                        <li class="ml-4">
                            <span class="absolute -left-1.5 w-3 h-3 rounded-full border-2 border-white
                                @if($log->action === 'finalized') bg-green-400
                                @elseif($log->action === 'created') bg-brand-400
                                @elseif($log->action === 'override') bg-amber-400
                                @elseif($log->action === 'cancelled') bg-red-400
                                @else bg-gray-300 @endif"></span>
                            <p class="text-xs font-semibold text-gray-700 capitalize">{{ $log->action }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $log->user?->name ?? 'System' }}
                                · {{ $log->created_at->format('d M y, H:i') }}
                            </p>
                            @if($log->notes)
                                <p class="text-xs text-gray-500 italic mt-0.5">{{ $log->notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
            @endif

            {{-- Cancel --}}
            @if($prescription->status !== 'cancelled')
            <form method="POST"
                  action="{{ route('patients.prescriptions.destroy', [$patient, $prescription]) }}"
                  onsubmit="return confirm('Cancel this prescription?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="w-full py-2 text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-300 rounded-xl transition">
                    Cancel Prescription
                </button>
            </form>
            @endif

        </div>{{-- /right --}}
    </div>{{-- /grid --}}
</div>

<script>
/**
 * Send prescription via WhatsApp.
 * 1. POSTs to mark status as whatsapp_sent + get wa.me URL.
 * 2. Opens the URL in a new tab (patient's WhatsApp opens with pre-filled message).
 * 3. Reloads the page to reflect updated status.
 */
function rxSendWhatsApp(btn) {
    const hasPhone = btn.dataset.hasPhone === 'true';

    if (!hasPhone) {
        alert('No phone number saved for this patient.\n\nPlease add a phone number to the patient profile first, then try again.');
        return;
    }

    if (!confirm('Open WhatsApp and send this prescription to the patient?')) return;

    btn.disabled    = true;
    btn.textContent = 'Sending…';

    fetch(btn.dataset.url, {
        method : 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept'      : 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        // Blocked (e.g. DPDP consent gate) — show why, don't reload.
        if (data.success === false) {
            alert(data.message || 'This message was blocked. Please check with admin.');
            btn.disabled    = false;
            btn.textContent = 'Send WhatsApp';
            return;
        }
        if (data.url) window.open(data.url, '_blank');
        // Short delay so the new tab can open before the page reloads
        setTimeout(() => window.location.reload(), 800);
    })
    .catch(err => {
        console.error('WhatsApp send failed:', err);
        alert('Something went wrong. Please try again.');
        btn.disabled    = false;
        btn.textContent = 'Send WhatsApp';
    });
}
</script>

@endsection
