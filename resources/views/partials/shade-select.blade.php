{{--
    partials/shade-select.blade.php
    Shade-guide picker: Vita Classical / Vita 3D Master toggle + shade
    dropdown, with an optional per-tooth override when more than one
    tooth is selected. Pair with partials.shade-select-assets (included
    once per page, outside any loop).

    Params:
      $target  required. Raw JS expression for the object holding
               .shade_guide (string), .shade (string), .per_tooth_shade
               (bool), .tooth_shades (object keyed by tooth code), and
               .teeth (array — shared with partials.tooth-chart).
               Initialize these on the object, e.g.:
                 { shade_guide: 'vita_classical', shade: '',
                   per_tooth_shade: false, tooth_shades: {}, teeth: [] }

    Requires the enclosing Alpine component's x-data to include
    ...shadeSelectMixin() (and ...toothChartMixin() for .teeth).
--}}
<div class="space-y-2">
    {{-- Case-level shade (hidden once per-tooth override is on) --}}
    <div x-show="!{!! $target !!}.per_tooth_shade" class="flex items-center gap-2">
        <select x-model="{!! $target !!}.shade_guide" @change="{!! $target !!}.shade = ''"
                class="text-xs border border-gray-200 rounded-md px-2 py-1.5 bg-white">
            <template x-for="key in Object.keys(window.SHADE_GUIDES)" :key="key">
                <option :value="key" x-text="guideLabel(key)"></option>
            </template>
        </select>
        <select x-model="{!! $target !!}.shade"
                class="text-xs border border-gray-200 rounded-md px-2 py-1.5 bg-white flex-1">
            <option value="">Select shade…</option>
            <template x-for="s in shadesForGuide({!! $target !!}.shade_guide)" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </select>
    </div>

    {{-- Per-tooth override toggle — only worth showing with 2+ teeth selected --}}
    <label x-show="({!! $target !!}.teeth || []).length > 1"
           class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none">
        <input type="checkbox" x-model="{!! $target !!}.per_tooth_shade" class="rounded border-gray-300">
        Set a different shade per tooth
    </label>

    {{-- Per-tooth rows --}}
    <div x-show="{!! $target !!}.per_tooth_shade" class="space-y-1.5 border border-gray-100 rounded-md p-2 bg-gray-50">
        <template x-for="t in ({!! $target !!}.teeth || [])" :key="t">
            <div class="flex items-center gap-2">
                <span class="tp-tooth-badge" x-text="t" style="min-width:28px;text-align:center;"></span>
                <select x-model="toothShade({!! $target !!}, t).shade_guide"
                        @change="toothShade({!! $target !!}, t).shade = ''"
                        class="text-xs border border-gray-200 rounded-md px-2 py-1 bg-white">
                    <template x-for="key in Object.keys(window.SHADE_GUIDES)" :key="key">
                        <option :value="key" x-text="guideLabel(key)"></option>
                    </template>
                </select>
                <select x-model="toothShade({!! $target !!}, t).shade"
                        class="text-xs border border-gray-200 rounded-md px-2 py-1 bg-white flex-1">
                    <option value="">Select shade…</option>
                    <template x-for="s in shadesForGuide(toothShade({!! $target !!}, t).shade_guide)" :key="s">
                        <option :value="s" x-text="s"></option>
                    </template>
                </select>
            </div>
        </template>
    </div>
</div>
