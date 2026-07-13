{{--
    partials/tooth-chart.blade.php
    FDI multi-select tooth-chart button + popup. Markup only — pair with
    partials.tooth-chart-assets (CSS + toothChartMixin(), included once
    per page, outside any loop).

    Params:
      $target      required. Raw JS expression for the object holding
                   .teeth (array) and .tooth_number (string), e.g. "item"
                   or "caseForm".
      $pickerId    required. Raw JS expression uniquely identifying this
                   picker instance for the shared activeToothPicker
                   open/close state — a loop variable like "idx", or a
                   quoted string literal like "'lab'" for a single
                   instance form.
      $buttonLabel optional. Placeholder text on the trigger button
                   before any tooth is picked. Default "Tooth".

    Requires the enclosing Alpine component's x-data to include
    ...toothChartMixin() (see tooth-chart-assets.blade.php).
--}}
@php($buttonLabel = $buttonLabel ?? 'Tooth')
<div class="relative" @click.outside="if(activeToothPicker==={!! $pickerId !!}) activeToothPicker=null">
    <button type="button" class="tp-tooth-btn"
            @click.stop="activeToothPicker = (activeToothPicker==={!! $pickerId !!} ? null : {!! $pickerId !!})">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
        <span x-text="{!! $target !!}.tooth_number || '{{ $buttonLabel }}'"></span>
    </button>
    {{-- Tooth chart popup (multi-select) --}}
    <div x-show="activeToothPicker === {!! $pickerId !!}" class="tp-tooth-popup" x-cloak>
        <div style="font-size:10px;color:#9ca3af;margin-bottom:5px;">Tap teeth to select one or more. Corner chip toggles primary (child) tooth.</div>
        <div class="tp-tooth-labels"><span>Upper Right &#x2192;</span><span>&#x2190; Upper Left</span></div>
        <div class="tp-tooth-grid">
            <template x-for="pos in [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28]" :key="'u'+pos">
                <div class="tp-tooth-cell"
                     :class="isToothSelected({!! $target !!}, codeAt(pos)) ? 'selected' : ''"
                     @click="toggleTooth({!! $target !!}, codeAt(pos))">
                    <span x-text="codeAt(pos)"></span>
                    <span x-show="canToggleDentition(pos)" x-cloak class="tp-tooth-dchip"
                          :class="isChildPos(pos) ? 'is-child' : ''"
                          @click.stop="toggleDentitionMode(pos)"
                          :title="isChildPos(pos) ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                          x-text="isChildPos(pos) ? 'P' : 'A'"></span>
                </div>
            </template>
            <div class="tp-tooth-midline"></div>
            <template x-for="pos in [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38]" :key="'l'+pos">
                <div class="tp-tooth-cell"
                     :class="isToothSelected({!! $target !!}, codeAt(pos)) ? 'selected' : ''"
                     @click="toggleTooth({!! $target !!}, codeAt(pos))">
                    <span x-text="codeAt(pos)"></span>
                    <span x-show="canToggleDentition(pos)" x-cloak class="tp-tooth-dchip"
                          :class="isChildPos(pos) ? 'is-child' : ''"
                          @click.stop="toggleDentitionMode(pos)"
                          :title="isChildPos(pos) ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                          x-text="isChildPos(pos) ? 'P' : 'A'"></span>
                </div>
            </template>
        </div>
        <div class="tp-tooth-labels" style="margin-top:3px;"><span>Lower Right &#x2192;</span><span>&#x2190; Lower Left</span></div>
        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">
            <template x-for="lbl in ['UL','UR','LL','LR','Full Arch','Multiple']" :key="lbl">
                <div class="tp-tooth-cell" style="padding:3px 6px;font-size:9px;"
                     :class="isToothSelected({!! $target !!}, lbl) ? 'selected' : ''"
                     @click="toggleTooth({!! $target !!}, lbl)"
                     x-text="lbl"></div>
            </template>
        </div>
        {{-- Footer: count + actions --}}
        <div class="tp-tooth-foot">
            <span class="tp-tooth-count" x-text="({!! $target !!}.teeth?.length || 0) + ' selected'"></span>
            <div style="display:flex;gap:6px;">
                <button type="button" class="tp-tooth-clear" @click="clearTeeth({!! $target !!})">Clear</button>
                <button type="button" class="tp-tooth-done" @click="activeToothPicker=null">Done</button>
            </div>
        </div>
    </div>
</div>
