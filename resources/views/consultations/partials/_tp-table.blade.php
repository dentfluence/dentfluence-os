{{--
    partials/_tp-table.blade.php  (private sub-partial)
    Variables: $planType ('best'|'acceptable'), $ref (JS array name), $totalRef (JS total var name)
--}}
<div style="overflow-x:auto;">
    <table class="tp-table">
        <thead>
            <tr>
                <th style="width:160px;">Tooth / Area</th>
                <th>Treatment</th>
                <th style="width:100px;text-align:right;">Cost (Rs. )</th>
                <th style="width:28px;"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(row, i) in {{ $ref }}" :key="'{{ $planType }}'+i">
                <tr>
                    <td>
                        <div style="display:flex;gap:3px;">
                            <input type="text"
                                   x-model="row.tooth"
                                   class="df-input"
                                   placeholder="#14, Full Mouth"
                                   style="padding:4px 7px;font-size:12px;flex:1;"
                                   readonly
                                   @click="$dispatch('open-tp-picker', {type:'{{ $planType }}', idx:i})">
                            <button type="button"
                                    @click="$dispatch('open-tp-picker', {type:'{{ $planType }}', idx:i})"
                                    style="border:1px solid #e5e7eb;border-radius:4px;padding:0 7px;background:white;cursor:pointer;color:#6a0f70;flex-shrink:0;"
                                    title="Select teeth">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <input type="text"
                                   x-model="row.treatment"
                                   class="df-input"
                                   placeholder="e.g. Composite Filling"
                                   style="padding:4px 7px;font-size:12px;flex:1;">
                            {{-- Quick-fill from treatments already selected in section 10 --}}
                            <select @change="row.treatment=$event.target.value;$event.target.value=''"
                                    style="border:1px solid #e5e7eb;border-radius:4px;font-size:11px;color:#9ca3af;padding:0 4px;background:white;cursor:pointer;max-width:70px;">
                                <option value="">Quick</option>
                                <template x-for="tx in allTxSelected" :key="tx">
                                    <option :value="tx" x-text="tx"></option>
                                </template>
                            </select>
                        </div>
                    </td>
                    <td>
                        <input type="number"
                               x-model="row.cost"
                               class="df-input"
                               placeholder="0"
                               @input="$dispatch('recalc-tp', {type:'{{ $planType }}'})"
                               style="padding:4px 7px;font-size:12px;text-align:right;">
                    </td>
                    <td>
                        <button type="button"
                                @click="$dispatch('remove-tp-row', {type:'{{ $planType }}', idx:i})"
                                style="color:#d1d5db;background:none;border:none;cursor:pointer;padding:2px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            </template>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="color:#6b7280;font-size:11px;">Estimated Total</td>
                <td style="text-align:right;color:#6a0f70;">Rs.  <span x-text="{{ $totalRef }}.toLocaleString('en-IN')"></span></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
