@extends('layouts.app')
@section('page-title', 'Smart Presentation — ' . ($presentation->patient?->name ?? 'Builder'))

@section('content')
<div class="p-6 space-y-6 max-w-4xl">

    {{-- ══ BACK + STATUS ═══════════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('presentations.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← All presentations</a>
        <span class="px-2.5 py-1 rounded-full text-xs font-medium
            {{ $presentation->status === 'draft' ? 'bg-gray-100 text-gray-600' : 'bg-green-50 text-green-700' }}">
            {{ $presentation->status_label }}
        </span>
    </div>

    {{-- ══ FLASH ═══════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
    @endif

    {{-- ══ READ-ONLY IMPORTED HEADER (Patient + Consultation + Plan) ═════════
         Everything in this card is read-only from Patient/Consultation/
         TreatmentPlan — this module never edits any of it. ═══════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <div class="text-lg font-semibold text-gray-800">{{ $presentation->patient?->name ?? '—' }}</div>
                <div class="text-xs text-gray-400 mt-0.5">
                    {{ $presentation->patient?->age }}{{ $presentation->patient?->gender ? ' · ' . $presentation->patient->gender : '' }}
                </div>
            </div>
            <div class="text-xs text-gray-400">Plan: <span class="font-medium text-gray-600">{{ $presentation->treatmentPlan?->plan_name }}</span></div>
        </div>

        @if($presentation->consultation?->primary_diagnosis)
        <div class="mt-3 text-sm text-gray-600">
            <span class="text-gray-400">Diagnosis:</span> {{ $presentation->consultation->primary_diagnosis }}
        </div>
        @endif

        <div class="mt-4 divide-y divide-gray-50 border-t border-gray-50">
            @foreach($presentation->treatmentPlan?->items ?? [] as $item)
            <div class="flex items-center justify-between py-2 text-sm">
                <div class="flex items-center gap-2 text-gray-600">
                    @if($item->tooth_number)
                        <span class="px-1.5 py-0.5 rounded bg-gray-100 text-xs text-gray-500">{{ $item->tooth_number }}</span>
                    @endif
                    {{ $item->treatment_name }}
                    @if($item->units > 1)<span class="text-xs text-gray-400">&times; {{ $item->units }}</span>@endif
                </div>
                <div class="text-gray-400 text-xs font-medium">Rs. {{ number_format($item->total, 0) }}</div>
            </div>
            @endforeach
        </div>

        {{-- Live cost summary — pulled from Billing, never snapshotted until Finalize --}}
        <div class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-gray-400 text-xs">Cost source: {{ $costSummary['source'] === 'invoice' ? 'Billing invoice (live)' : 'Treatment plan estimate' }}</span>
            <span class="font-semibold text-gray-800">Total: Rs. {{ number_format($costSummary['total'], 0) }}</span>
        </div>
    </div>

    @if(!$canAuthor)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm">
        You have view access to this presentation. Only a dentist can edit the summary, message, or finalize it.
    </div>
    @endif

    @if($presentation->status === 'draft')

        {{-- ══ AI SUMMARY + DOCTOR MESSAGE + MEDIA (author-editable) ═════════ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Patient-facing summary</label>
                    @if($canAuthor)
                    {{-- Separate, plain POST form — deliberately NOT nested inside the
                         PUT-spoofed update form below, so it can't accidentally submit
                         as a PUT to the wrong route. --}}
                    <form method="POST" action="{{ route('presentations.generate-summary', $presentation) }}">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                            {{ $presentation->ai_summary_text ? '↻ Regenerate with AI' : '✨ Generate with AI' }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('presentations.update', $presentation) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <textarea name="ai_summary_text" rows="7" {{ $canAuthor ? '' : 'readonly' }}
                    class="w-full rounded-lg border border-gray-200 text-sm p-3 text-gray-700 focus:ring-brand-500 focus:border-brand-500 {{ !$canAuthor ? 'bg-gray-50' : '' }}"
                    placeholder="Write a plain-language explanation of the diagnosis and treatment, or generate one with AI.">{{ old('ai_summary_text', $presentation->ai_summary_text) }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Always review AI-generated text for clinical accuracy before finalizing.</p>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5 block">Your personal message to the patient</label>
                <textarea name="doctor_message" rows="2" {{ $canAuthor ? '' : 'readonly' }}
                    class="w-full rounded-lg border border-gray-200 text-sm p-3 text-gray-700 focus:ring-brand-500 focus:border-brand-500 {{ !$canAuthor ? 'bg-gray-50' : '' }}"
                    placeholder="e.g. Looking forward to helping you get there!">{{ old('doctor_message', $presentation->doctor_message) }}</textarea>
            </div>

            @if($availableMedia->isNotEmpty())
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5 block">Education content to include</label>
                <div class="space-y-1.5">
                    @foreach($availableMedia as $media)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="media_ids[]" value="{{ $media->id }}"
                            {{ $selectedMediaIds->contains($media->id) ? 'checked' : '' }}
                            {{ $canAuthor ? '' : 'disabled' }}
                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $media->label }} <span class="text-xs text-gray-400">({{ $media->type_label }})</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            @if($canAuthor)
            <div class="pt-2">
                <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition">
                    Save Draft
                </button>
            </div>
            @endif
            </form>
        </div>

        {{-- ══ FINALIZE (hard review gate) ═══════════════════════════════════ --}}
        @if($canAuthor)
        <form method="POST" action="{{ route('presentations.finalize', $presentation) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            @csrf
            <label class="flex items-start gap-2 text-sm text-gray-600">
                <input type="checkbox" name="confirm_reviewed" value="1" required
                    class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                I have reviewed this summary for clinical accuracy and I'm ready to finalize it.
            </label>
            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                Finalize
            </button>
            <p class="text-xs text-gray-400">Sending via WhatsApp / secure link is coming in the next build slice — finalizing just locks this content in.</p>
        </form>
        @endif

    @else

        {{-- ══ FINALIZED — READ ONLY CONTENT ══════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Patient-facing summary</div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $presentation->ai_summary_text }}</p>
            @if($presentation->doctor_message)
            <div class="pt-2 border-t border-gray-50">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Personal message</div>
                <p class="text-sm text-gray-700">{{ $presentation->doctor_message }}</p>
            </div>
            @endif
            <div class="text-xs text-gray-400 pt-2 border-t border-gray-50">
                Reviewed & finalized {{ $presentation->reviewed_at?->format('d M Y, H:i') }} by
                {{ $presentation->creator?->name ?? 'a dentist' }}.
            </div>
        </div>

        {{-- ══ SLICE C: SEND / RESEND (staff-operate — not author-only) ═══════ --}}
        @if($canOperate)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Delivery</div>
                <span class="text-xs text-gray-400">{{ $presentation->view_count }} view{{ $presentation->view_count === 1 ? '' : 's' }}</span>
            </div>

            @if($activeTokenUrl)
            <div class="text-xs text-gray-500 break-all bg-gray-50 rounded-lg px-3 py-2">{{ $activeTokenUrl }}</div>
            @endif

            <div class="flex gap-2">
                @if($presentation->status === 'finalized')
                <form method="POST" action="{{ route('presentations.send', $presentation) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                        Send via WhatsApp
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('presentations.resend', $presentation) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition">
                        Resend (fresh link)
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endif

        {{-- ══ SLICE D: FOLLOW-UP / DECLINE (staff-operate) ═══════════════════ --}}
        @if($canOperate && !in_array($presentation->status, ['accepted', 'declined']))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Follow-up</div>
            <form method="POST" action="{{ route('presentations.mark-follow-up', $presentation) }}" class="space-y-2">
                @csrf
                <textarea name="follow_up_notes" rows="2" placeholder="e.g. Called patient, wants to think it over — call back Friday."
                    class="w-full rounded-lg border border-gray-200 text-sm p-3 text-gray-700">{{ old('follow_up_notes', $presentation->follow_up_notes) }}</textarea>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition">
                        Save Follow-up
                    </button>
                </div>
            </form>
            <form method="POST" action="{{ route('presentations.mark-declined', $presentation) }}"
                  onsubmit="return confirm('Mark this presentation as declined?');">
                @csrf
                <button type="submit" class="text-xs text-red-500 hover:text-red-600 font-medium">Mark Declined</button>
            </form>
        </div>
        @elseif($presentation->follow_up_notes)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Follow-up notes</div>
            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $presentation->follow_up_notes }}</p>
        </div>
        @endif

        {{-- ══ ACTIVITY TIMELINE (reuses the existing Activity log) ═══════════ --}}
        @if($activity->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Activity</div>
            <div class="space-y-2.5">
                @foreach($activity as $entry)
                <div class="flex items-start gap-2.5 text-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-300 mt-1.5 shrink-0"></span>
                    <div>
                        <span class="text-gray-600">{{ $entry->description ?? $entry->event }}</span>
                        <span class="text-xs text-gray-400 block">{{ $entry->occurred_at?->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    @endif

</div>
@endsection
