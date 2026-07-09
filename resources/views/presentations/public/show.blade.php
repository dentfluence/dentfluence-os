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

    {{-- ══ CASE SUMMARY — deterministic, always accurate (no AI required) ═══ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4 space-y-3">
        @if($narrative['complaint'])
        <p class="text-sm text-gray-700"><span class="text-gray-400">You came in because:</span> {{ $narrative['complaint'] }}</p>
        @endif
        @if($narrative['hopi'])
        <p class="text-sm text-gray-700">{{ $narrative['hopi'] }}</p>
        @endif
        @if($narrative['diagnosis'])
        <p class="text-sm text-gray-700"><span class="text-gray-400">What we found:</span> {{ $narrative['diagnosis'] }}</p>
        @endif
    </div>

    {{-- ══ OPTIONAL OVERVIEW PARAGRAPH (AI-drafted or dentist-written) ══════ --}}
    @if($presentation->ai_summary_text)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $presentation->ai_summary_text }}</p>
    </div>
    @endif

    {{-- ══ TREATMENT + COST ════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Your Treatment</div>
        <div class="divide-y divide-gray-50">
            @foreach($narrative['treatment'] as $t)
            <div class="py-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">{{ $t['treatment_name'] }}{{ $t['units'] > 1 ? ' × ' . $t['units'] : '' }}</span>
                    <span class="text-gray-400 font-medium">Rs. {{ number_format($t['total'], 0) }}</span>
                </div>
                @if($t['tooth_phrase'])
                <div class="text-xs text-gray-400">{{ ucfirst($t['tooth_phrase']) }}</div>
                @endif
            </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between pt-3 mt-2 border-t border-gray-100">
            <span class="text-sm font-semibold text-gray-800">Total</span>
            <span class="text-lg font-bold text-brand-600">Rs. {{ number_format($costSummary['total'], 0) }}</span>
        </div>
    </div>

    {{-- ══ ALTERNATIVES (other options discussed for this consultation) ═══ --}}
    @if(!empty($narrative['alternatives']))
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Other Options We Discussed</div>
        <div class="space-y-2">
            @foreach($narrative['alternatives'] as $alt)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-700">{{ $alt['plan_name'] }}</span>
                <span class="text-gray-400 font-medium">Rs. {{ number_format($alt['total'], 0) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

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

    <div class="text-center text-xs text-gray-300 mt-6 space-y-0.5">
        <p>Sent securely by {{ $narrative['clinic']['name'] ?? 'your clinic' }}.</p>
        @if($narrative['clinic']['phone'] ?? null)
        <p>{{ $narrative['clinic']['phone'] }}</p>
        @endif
        @if($narrative['clinic']['address'] ?? null)
        <p>{{ $narrative['clinic']['address'] }}</p>
        @endif
    </div>

@endsection
