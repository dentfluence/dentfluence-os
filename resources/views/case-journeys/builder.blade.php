@extends('layouts.app')
@section('page-title', 'Case Journey — ' . ($journey->patient?->name ?? 'Builder'))

@section('content')
<div class="p-6 space-y-6 max-w-4xl">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Case Acceptance Journey</h1>
            <p class="text-sm text-gray-500">
                {{ $journey->patient?->name }} ·
                Plan #{{ $journey->treatment_plan_id }} ·
                Tree: {{ $dto['tree']['title'] ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('case-journeys.preview', $journey) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 border border-brand-300 rounded-md px-3 py-1.5 hover:bg-brand-50">
                👁 Preview as patient
            </a>
            <span class="text-xs font-semibold uppercase px-2.5 py-1 rounded-full
                {{ $journey->status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : ($journey->status === 'declined' ? 'bg-gray-200 text-gray-600' : ($journey->status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-brand-100 text-brand-600')) }}">
                {{ str_replace('_', ' ', $journey->status) }}
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    {{-- Public link (after send) --}}
    @if ($publicUrl)
        <div class="rounded-lg bg-brand-50 border border-brand-200 p-4">
            <p class="text-xs font-semibold text-brand-600 uppercase mb-1">Patient link</p>
            <div class="flex items-center gap-2">
                <input type="text" readonly value="{{ $publicUrl }}"
                       class="flex-1 text-sm bg-white border border-gray-200 rounded-md px-3 py-1.5"
                       onclick="this.select()">
                <a href="{{ $publicUrl }}" target="_blank" class="text-sm text-brand-600 font-semibold whitespace-nowrap">Open ↗</a>
            </div>
        </div>
    @endif

    @php $readOnly = in_array($journey->status, ['sent', 'viewed', 'accepted'], true); @endphp

    {{-- Curation --}}
    <form method="POST" action="{{ route('case-journeys.curation', $journey) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm">
        @csrf
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">What to show this patient</h2>
            @unless ($readOnly)
                <button class="text-sm bg-gray-800 text-white rounded-md px-3 py-1.5">Save curation</button>
            @endunless
        </div>

        <table class="w-full text-sm">
            <thead class="text-xs text-gray-400 uppercase">
                <tr>
                    <th class="text-left font-medium px-5 py-2">Node</th>
                    <th class="text-left font-medium px-2 py-2">Treatment · price (live)</th>
                    <th class="text-center font-medium px-2 py-2">Visible</th>
                    <th class="text-center font-medium px-2 py-2">Recommended</th>
                    <th class="text-center font-medium px-2 py-2 w-20">Order</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($nodes as $node)
                    @php $c = $curationMap->get($node->id); @endphp
                    <tr class="border-t border-gray-50">
                        <td class="px-5 py-2">
                            <span class="text-gray-800">{{ $node->label ?? ('#' . $node->id) }}</span>
                            <span class="ml-1 text-[10px] uppercase text-gray-400">{{ $node->node_type }}</span>
                            @if ($node->parent_node_id)<span class="text-gray-300 text-xs"> ↳</span>@endif
                        </td>
                        <td class="px-2 py-2">
                            @php $np = $nodePricing[$node->id] ?? null; @endphp
                            @if ($np && $np['treatment'])
                                <span class="text-gray-700">{{ $np['treatment'] }}</span>
                                <span class="ml-1 font-semibold text-brand-600">₹{{ number_format($np['price']) }}</span>
                                @if ($np['group'])<span class="block text-[10px] text-gray-400">{{ str_replace('_',' ',$np['group']) }}</span>@endif
                            @elseif (in_array($node->node_type, ['option','material','addon'], true))
                                <span class="text-[11px] text-amber-600">No treatment/price linked</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-center px-2 py-2">
                            <input type="hidden" name="nodes[{{ $node->id }}][visible]" value="0">
                            <input type="checkbox" name="nodes[{{ $node->id }}][visible]" value="1"
                                   class="accent-brand-500" @checked($c ? $c->visible : true) @disabled($readOnly)>
                        </td>
                        <td class="text-center px-2 py-2">
                            <input type="hidden" name="nodes[{{ $node->id }}][is_recommended]" value="0">
                            <input type="checkbox" name="nodes[{{ $node->id }}][is_recommended]" value="1"
                                   class="accent-brand-500" @checked($c ? $c->is_recommended : false) @disabled($readOnly)>
                        </td>
                        <td class="text-center px-2 py-2">
                            <input type="number" name="nodes[{{ $node->id }}][sort_order]"
                                   value="{{ $c->sort_order ?? $node->sort_order }}"
                                   class="w-14 text-center border border-gray-200 rounded-md py-1" @disabled($readOnly)>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>

    {{-- Doctor-added options from the Treatment list --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" x-data="caseCustomOptions()">
        <h2 class="text-sm font-semibold text-gray-700">Add treatments from your list</h2>
        <p class="text-xs text-gray-500 mt-1 mb-3">Offer any treatment from your Treatment module as an extra option — priced live. The patient sees it as an option card alongside the authored ones.</p>

        @unless ($readOnly)
        <div class="flex gap-2 mb-4">
            <select x-model="pick" class="flex-1 border border-gray-200 rounded-md px-3 py-2 text-sm">
                <option value="">Choose a treatment…</option>
                @foreach ($treatments as $t)
                    <option value="{{ $t->id }}" data-name="{{ $t->name }}" data-price="{{ (float) $t->default_price }}">{{ $t->name }} — ₹{{ number_format($t->default_price) }}</option>
                @endforeach
            </select>
            <button type="button" @click="add()" class="bg-gray-800 text-white text-sm rounded-md px-4 py-2">Add</button>
        </div>
        @endunless

        <form method="POST" action="{{ route('case-journeys.options', $journey) }}">
            @csrf
            <p class="text-xs text-gray-400" x-show="items.length === 0">No added treatments — the patient sees only the authored options above.</p>
            <template x-for="(o, idx) in items" :key="o.treatment_id">
                <div class="flex items-center gap-3 border-t border-gray-50 py-2">
                    <span class="flex-1 text-sm text-gray-800" x-text="o.name"></span>
                    <span class="text-sm font-semibold text-brand-600" x-text="'₹' + Number(o.price).toLocaleString('en-IN')"></span>
                    <label class="flex items-center gap-1 text-xs text-gray-500">
                        <input type="checkbox" x-model="o.is_recommended" class="accent-brand-500" @disabled($readOnly)> Recommended
                    </label>
                    @unless ($readOnly)<button type="button" @click="remove(idx)" class="text-gray-400 hover:text-red-500 text-sm">✕</button>@endunless
                    <input type="hidden" :name="`options[${idx}][treatment_id]`" :value="o.treatment_id">
                    <input type="hidden" :name="`options[${idx}][is_recommended]`" :value="o.is_recommended ? 1 : 0">
                </div>
            </template>
            @unless ($readOnly)
            <button type="submit" class="bg-brand-500 text-white text-sm rounded-md px-4 py-2 mt-3">Save added options</button>
            @endunless
        </form>
    </div>

    {{-- Send --}}
    <form method="POST" action="{{ route('case-journeys.send', $journey) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        @csrf
        <h2 class="text-sm font-semibold text-gray-700 mb-3">
            {{ in_array($journey->status, ['sent','viewed'], true) ? 'Re-send (creates a new revision)' : 'Send to patient' }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <label class="text-sm">
                <span class="block text-gray-500 mb-1">Delivery</span>
                <select name="delivery_mode" class="w-full border border-gray-200 rounded-md px-3 py-2">
                    <option value="chairside" @selected($journey->delivery_mode==='chairside')>Chairside (with you)</option>
                    <option value="take_home" @selected($journey->delivery_mode==='take_home')>Take-home link</option>
                    <option value="both" @selected($journey->delivery_mode==='both')>Both</option>
                </select>
            </label>
            <label class="text-sm">
                <span class="block text-gray-500 mb-1">Cost visibility</span>
                <select name="cost_visibility" class="w-full border border-gray-200 rounded-md px-3 py-2">
                    <option value="full" @selected($journey->cost_visibility==='full')>Show full estimate</option>
                    <option value="starting_from" @selected($journey->cost_visibility==='starting_from')>Starting-from only</option>
                    <option value="hidden_until_booking" @selected($journey->cost_visibility==='hidden_until_booking')>Hide until booking</option>
                </select>
            </label>
            <button class="bg-brand-500 text-white text-sm font-semibold rounded-md px-4 py-2.5">
                {{ in_array($journey->status, ['sent','viewed'], true) ? 'Re-send' : 'Send journey' }}
            </button>
        </div>
    </form>

</div>

@php
    $customOptionsJs = $customOptions->map(fn ($c) => [
        'treatment_id'   => $c->treatment_id,
        'name'           => $c->treatment?->name,
        'price'          => (float) ($c->treatment?->default_price ?? 0),
        'is_recommended' => (bool) $c->is_recommended,
    ])->values();
@endphp
<script>
function caseCustomOptions(){
    return {
        pick: '',
        items: @json($customOptionsJs),
        add(){
            if(!this.pick) return;
            const id = parseInt(this.pick);
            if(this.items.some(o => o.treatment_id === id)){ this.pick=''; return; }
            const opt = document.querySelector(`option[value="${id}"]`);
            if(!opt) return;
            this.items.push({treatment_id:id, name:opt.dataset.name, price:parseFloat(opt.dataset.price)||0, is_recommended:false});
            this.pick='';
        },
        remove(i){ this.items.splice(i,1); }
    }
}
</script>
@endsection
