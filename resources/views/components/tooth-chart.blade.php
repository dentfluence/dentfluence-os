{{--
    Interactive FDI tooth chart — ONE copy, used everywhere a file is tagged.

    @props
      model  Alpine expression holding the selected teeth as an array of numbers
             (e.g. "uploadSelectedTeeth" or "ctxTeeth"). Required.
      label  Field label. Pass null to render the chart with no label.

    Usage:
      <x-tooth-chart model="uploadSelectedTeeth" />
      <x-tooth-chart model="ctx.teeth" label="Teeth in this photo" />

    Why a component: this chart existed once, inline, in the Documents upload
    modal — about 200 lines of markup repeated four times for four quadrants.
    The File Viewer needed the same chart to let a tooth be corrected after
    upload. Copying it would have made two charts that drift, in a module whose
    every bug so far has been two things that were supposed to be one. The
    selection it produces is a plain array of numbers; the caller decides how to
    send it (`.join(', ')` is what the API stores, and what FIND_IN_SET reads
    back tooth by tooth).

    The small A/P button under each tooth swaps the permanent code for its
    primary (child) equivalent — Tulip Kids files into the same library.
--}}
@props(['model', 'label' => 'Tooth Number(s)'])

@php
    $primaryMap = config('dental_notation.permanent_to_primary', []);
    $rows = [
        'upper' => [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]],
        'lower' => [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
    ];
@endphp

<div>
    @if($label)
    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
        {{ $label }}
        <span class="text-gray-400 font-normal text-[10px]">FDI &middot; click to select</span>
    </label>
    @endif

    <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/50 select-none">

        @foreach($rows as $arch => $halves)
            @if($arch === 'lower')
                <p class="text-[8px] font-bold text-gray-400 uppercase tracking-widest text-center mt-1.5 mb-1.5">Lower</p>
            @else
                <p class="text-[8px] font-bold text-gray-400 uppercase tracking-widest text-center mb-1.5">Upper</p>
            @endif

            <div class="flex justify-center items-center gap-0.5 {{ $arch === 'upper' ? 'mb-0.5' : 'mt-0.5' }}">
                @foreach($halves as $index => $teeth)
                    @if($index === 1)
                        <div class="w-px h-5 bg-gray-300 mx-1 flex-shrink-0"></div>
                    @endif

                    @foreach($teeth as $t)
                        @php $childCode = $primaryMap[$t] ?? null; @endphp
                        <div class="flex flex-col items-center gap-0.5" x-data="{ code: {{ $t }} }">
                            <button type="button"
                                    @click="{{ $model }}.includes(code)
                                        ? {{ $model }} = {{ $model }}.filter(n => n !== code)
                                        : {{ $model }}.push(code)"
                                    :class="{{ $model }}.includes(code)
                                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60 hover:text-[#6a0f70]'"
                                    class="w-[26px] h-[26px] text-[9px] font-bold border rounded
                                           transition-colors flex-shrink-0 flex items-center justify-center"
                                    x-text="code"></button>
                            @if($childCode)
                            <button type="button"
                                    @click.stop="const nc = (code === {{ $t }} ? {{ $childCode }} : {{ $t }});
                                        if ({{ $model }}.includes(code)) { {{ $model }} = {{ $model }}.filter(n => n !== code); {{ $model }}.push(nc); }
                                        code = nc;"
                                    :class="code === {{ $childCode }}
                                        ? 'bg-pink-100 text-pink-600 border-pink-300'
                                        : 'bg-white text-gray-400 border-gray-200 hover:border-[#6a0f70]/60'"
                                    :title="code === {{ $childCode }} ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                    class="w-[26px] h-[10px] text-[7px] font-bold border rounded flex items-center justify-center"
                                    x-text="code === {{ $childCode }} ? 'P' : 'A'"></button>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>

            @if($arch === 'upper')
                <div class="border-t border-dashed border-gray-200 my-2"></div>
            @endif
        @endforeach

        {{-- Selected teeth — the only readout, so a mis-click is visible immediately --}}
        <div x-show="{{ $model }}.length > 0"
             style="display:none"
             class="mt-2.5 pt-2.5 border-t border-gray-200 flex flex-wrap items-center gap-1">
            <span class="text-[9px] text-gray-500 font-semibold mr-0.5">Selected:</span>
            <template x-for="t in {{ $model }}.slice().sort((a,b)=>a-b)" :key="t">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px]
                             font-bold bg-[#6a0f70] text-white rounded">
                    <span x-text="t"></span>
                </span>
            </template>
            <button type="button"
                    @click="{{ $model }} = []"
                    class="ml-auto text-[9px] text-gray-400 hover:text-red-500 transition-colors">
                Clear all
            </button>
        </div>

    </div>
</div>
