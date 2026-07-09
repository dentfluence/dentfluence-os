@extends('layouts.public-presentation')
@section('title', 'Your Treatment Plan')

@section('content')

    <div class="text-center mb-6">
        <div class="text-xs uppercase tracking-wide text-gray-400">{{ \App\Models\AppSetting::get('clinic_name', 'Your Clinic') }}</div>
        <h1 class="text-xl font-semibold text-gray-800 mt-1">Dear {{ $presentation->patient?->name }},</h1>
    </div>

    @if($presentation->status === 'accepted')
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm text-center mb-4">
        Thank you — you've accepted this treatment plan. Our team will be in touch to schedule your visit.
    </div>
    @elseif($presentation->status === 'declined')
    <div class="bg-gray-100 border border-gray-200 text-gray-600 rounded-xl px-4 py-3 text-sm text-center mb-4">
        You've let us know this isn't the right time. If anything changes, we're here whenever you're ready.
    </div>
    @endif

    {{-- ══ AI SUMMARY ══════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $presentation->ai_summary_text }}</p>
    </div>

    {{-- ══ TREATMENT + COST ════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Your Treatment</div>
        <div class="divide-y divide-gray-50">
            @foreach($presentation->treatmentPlan?->items ?? [] as $item)
            <div class="flex items-center justify-between py-2 text-sm">
                <span class="text-gray-700">{{ $item->treatment_name }}</span>
                <span class="text-gray-400 font-medium">Rs. {{ number_format($item->total, 0) }}</span>
            </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between pt-3 mt-2 border-t border-gray-100">
            <span class="text-sm font-semibold text-gray-800">Total</span>
            <span class="text-lg font-bold text-brand-600">Rs. {{ number_format($costSummary['total'], 0) }}</span>
        </div>
    </div>

    {{-- ══ EDUCATION CONTENT ═══════════════════════════════════════════════ --}}
    @if($includedMedia->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Helpful Resources</div>
        <div class="space-y-2">
            @foreach($includedMedia as $media)
            <a href="{{ $media->url }}" target="_blank" class="flex items-center gap-2 text-sm text-brand-600 hover:text-brand-700">
                <span>{{ $media->label }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ DOCTOR MESSAGE ══════════════════════════════════════════════════ --}}
    @if($presentation->doctor_message)
    <div class="bg-brand-50 rounded-xl px-4 py-3 text-sm text-gray-700 italic mb-4">
        "{{ $presentation->doctor_message }}"
    </div>
    @endif

    {{-- ══ ACCEPT / DECLINE ════════════════════════════════════════════════ --}}
    @if(!in_array($presentation->status, ['accepted', 'declined']))
    <div class="flex gap-3 mt-6">
        <form method="POST" action="{{ route('presentations.public.decline', $token) }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full py-3 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium">
                Not right now
            </button>
        </form>
        <form method="POST" action="{{ route('presentations.public.accept', $token) }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full py-3 rounded-xl bg-brand-600 text-white text-sm font-semibold shadow-sm">
                Accept Treatment Plan
            </button>
        </form>
    </div>
    @endif

    <p class="text-center text-xs text-gray-300 mt-6">Sent securely by {{ \App\Models\AppSetting::get('clinic_name', 'your clinic') }}.</p>

@endsection
