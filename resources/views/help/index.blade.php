@extends('layouts.app')

@section('page-title', 'Help Centre')

@section('content')
{{--
|==========================================================================
| Help Centre — fully driven by resources/help/content.php via
| App\Support\HelpContent. Never hardcode guide text here: add or edit
| entries in the registry and both this page and the on-screen guide
| (hint strip + panel) update together.
|
| Receives: $workflows (cross-module stories), $screens (role-resolved
| screen guides, keyed), $isAdmin.
|==========================================================================
--}}
<div class="p-6 max-w-4xl mx-auto font-[DM_Sans]">

    {{-- ── Header ── --}}
    <div class="flex items-start justify-between mb-2">
        <div>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Help Centre</h1>
            <p class="text-sm text-gray-500 mt-1">How Dentfluence works — workflow by workflow, screen by screen</p>
        </div>
        <span class="text-xs text-gray-400 border border-gray-200 px-3 py-1 rounded-full whitespace-nowrap">{{ now()->format('M Y') }}</span>
    </div>

    {{-- ── Live guide callout ── --}}
    <div class="flex items-start gap-3 px-4 py-3 mb-6 bg-[#faf6fc] border border-[#e8d8ec] border-l-[3px] border-l-[#8e24aa] text-[13px] text-[#4a3a56] leading-relaxed">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5">
            <path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.4 1 2.3h6c0-.9.4-1.8 1-2.3A7 7 0 0 0 12 2z"/><path d="M9 20h6"/>
        </svg>
        <span><strong class="text-[#5a006e]">Tip — the guide follows you:</strong> the bulb button in the top bar turns the on-screen guide on or off. When it is on, every major screen shows a one-line hint with an example, and <em>More&nbsp;&rarr;</em> opens the full guide for that screen.</span>
    </div>

    {{-- ── Quick jump ── --}}
    <div class="flex flex-wrap gap-1.5 mb-8">
        <a href="#workflows" class="text-[11px] uppercase tracking-wider font-semibold px-3 py-1.5 bg-[#5a006e] text-white rounded-sm hover:bg-[#3a0050] transition">Core workflows</a>
        @foreach($screens as $key => $s)
            <a href="#guide-{{ $key }}" class="text-[11px] uppercase tracking-wider px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-sm hover:border-[#b95cb7]/40 hover:text-[#6a0f70] transition">{{ $s['title'] }}</a>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         CORE WORKFLOWS
    ════════════════════════════════════════════════ --}}
    <h2 id="workflows" class="text-[11px] uppercase tracking-[0.12em] font-semibold text-gray-400 mb-3 scroll-mt-4">Core workflows — how the pieces work together</h2>

    <div class="space-y-2 mb-10">
        @foreach($workflows as $i => $wf)
        <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-gray-50 transition">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="shrink-0 w-6 h-6 flex items-center justify-center bg-[#f5eef9] text-[#6a0f70] text-[11px] font-semibold rounded-sm">{{ $i + 1 }}</span>
                    <span class="text-sm font-semibold text-[#380740] truncate">{{ $wf['title'] }}</span>
                </span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <div class="px-5 py-4">
                    <p class="text-[13px] text-gray-600 leading-relaxed mb-4">{{ $wf['goal'] }}</p>

                    <div class="space-y-0">
                        @foreach($wf['steps'] as $j => $step)
                        <div class="flex gap-3 py-2 {{ $loop->last ? '' : 'border-b border-gray-50' }}">
                            <span class="shrink-0 w-5 h-5 flex items-center justify-center bg-gray-100 text-gray-500 text-[10px] font-semibold rounded-full mt-0.5">{{ $j + 1 }}</span>
                            <div class="min-w-0">
                                <span class="inline-block text-[11px] font-semibold uppercase tracking-wide {{ str_starts_with($step[0], '(') ? 'text-emerald-700' : 'text-[#6a0f70]' }}">{{ $step[0] }}</span>
                                <span class="block text-[13px] text-gray-700 leading-relaxed">{{ $step[1] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if(!empty($wf['payoff']))
                    <div class="mt-4 px-3.5 py-2.5 bg-[#fdf8ff] border border-[#e8d8ec] text-[12.5px] text-[#4a3a56] leading-relaxed">
                        <strong class="text-[#8e24aa] text-[11px] uppercase tracking-wide block mb-0.5">Why this pays</strong>
                        {{ $wf['payoff'] }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         SCREEN BY SCREEN
    ════════════════════════════════════════════════ --}}
    <h2 class="text-[11px] uppercase tracking-[0.12em] font-semibold text-gray-400 mb-3">Screen by screen</h2>

    <div class="space-y-4">
        @foreach($screens as $key => $s)
        <section id="guide-{{ $key }}" class="border border-gray-200 bg-white scroll-mt-4">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-[#fdfbfe]">
                <h3 class="text-[15px] font-semibold text-[#380740]">{{ $s['title'] }}</h3>
                @if($s['what'])
                <p class="text-[12.5px] text-gray-500 leading-relaxed mt-0.5">{{ $s['what'] }}</p>
                @endif
            </div>

            <div class="px-5 py-4">
                @if(count($s['tasks']))
                <p class="text-[10.5px] uppercase tracking-[0.1em] font-semibold text-gray-400 mb-2">Common tasks</p>
                <div class="mb-4">
                    @foreach($s['tasks'] as $i => $task)
                    <div class="flex gap-3 py-2 {{ $loop->last ? '' : 'border-b border-gray-50' }}">
                        <span class="shrink-0 w-5 h-5 flex items-center justify-center bg-[#f5eef9] text-[#6a0f70] text-[10px] font-semibold rounded-sm mt-0.5">{{ $i + 1 }}</span>
                        <div class="min-w-0">
                            <span class="block text-[13px] font-semibold text-[#2a1440] leading-snug">{{ $task[0] }}</span>
                            <span class="block text-[12.5px] text-gray-600 leading-relaxed mt-0.5">{{ $task[1] ?? '' }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if(count($s['flows']))
                <p class="text-[10.5px] uppercase tracking-[0.1em] font-semibold text-gray-400 mb-2">Where this goes</p>
                <div class="mb-4">
                    @foreach($s['flows'] as $flow)
                    <div class="flex gap-2 py-1">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-1"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        <span class="text-[12.5px] text-gray-700 leading-relaxed">{{ $flow }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($s['roi'])
                <div class="px-3.5 py-2.5 bg-[#fdf8ff] border border-[#e8d8ec] text-[12.5px] text-[#4a3a56] leading-relaxed">
                    <strong class="text-[#8e24aa] text-[11px] uppercase tracking-wide block mb-0.5">Why it matters</strong>
                    {{ $s['roi'] }}
                </div>
                @endif
            </div>
        </section>
        @endforeach
    </div>

    <p class="text-[12px] text-gray-400 mt-8 mb-4 text-center">
        Missing something? The on-screen guide (bulb in the top bar) explains each screen right where you are working.
    </p>

</div>
@endsection
