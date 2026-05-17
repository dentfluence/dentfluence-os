{{--
    partials/investigations.blade.php
    Section 5 — Investigations
    Alpine state: form.investigations[] (checkbox array), invFiles{}, handleInvUpload()
--}}
@php
    $invs = [
        'iopa'        => 'IOPA',
        'opg'         => 'OPG',
        'cbct'        => 'CBCT',
        'photographs' => 'Photographs',
        'intraoral'   => 'Intraoral Scan',
        'blood_tests' => 'Blood Tests',
        'mri_usg'     => 'MRI / USG',
        'other'       => 'Other',
    ];
    $hasUpload = ['iopa', 'opg', 'cbct', 'photographs', 'intraoral'];
    $savedInvestigations = $consultation
        ? (is_array($consultation->investigations)
            ? $consultation->investigations
            : json_decode($consultation->investigations ?? '[]', true))
        : [];
@endphp

<div class="c-card" x-data="{open: {{ count($savedInvestigations) ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">5</span>Investigations</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && form.investigations.length" x-cloak
                  x-text="form.investigations.length + ' selected'"></span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:14px 18px;">
        @foreach($invs as $key => $label)
        <div class="inv-row" style="flex-wrap:wrap;gap:4px;">

            {{-- Checkbox --}}
            <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:140px;">
                <input type="checkbox"
                       id="inv_{{ $key }}"
                       name="investigations[]"
                       value="{{ $key }}"
                       x-model="form.investigations"
                       @if(in_array($key, $savedInvestigations)) checked @endif
                       style="width:13px;height:13px;accent-color:#6a0f70;cursor:pointer;flex-shrink:0;">
                <label for="inv_{{ $key }}" style="font-size:13px;color:#374151;cursor:pointer;">{{ $label }}</label>
            </div>

            {{-- Detail + upload row (visible when checked) --}}
            <template x-if="form.investigations.includes('{{ $key }}')">
                <div style="display:flex;align-items:center;gap:6px;width:100%;padding-left:19px;margin-top:4px;">
                    <input type="text"
                           name="inv_detail_{{ $key }}"
                           placeholder="{{ in_array($key, ['iopa','opg','photographs','intraoral']) ? '# images' : 'details' }}"
                           class="df-input"
                           style="flex:1;padding:4px 8px;font-size:12px;"
                           value="{{ old('inv_detail_'.$key, $consultation?->{'inv_detail_'.$key}) }}">

                    @if(in_array($key, $hasUpload))
                    <label style="display:flex;align-items:center;gap:3px;font-size:11px;color:#6a0f70;font-weight:600;cursor:pointer;white-space:nowrap;border:1px solid rgba(106,15,112,.3);padding:4px 9px;border-radius:4px;background:white;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Upload
                        <input type="file"
                               name="inv_file_{{ $key }}[]"
                               multiple
                               accept="image/*,.pdf,.dcm"
                               style="display:none;"
                               @change="handleInvUpload($event, '{{ $key }}')">
                    </label>
                    @endif

                    <span x-show="(invFiles['{{ $key }}'] || []).length > 0"
                          style="font-size:10px;color:#16a34a;font-weight:600;white-space:nowrap;"
                          x-text="(invFiles['{{ $key }}'] || []).length + ' file(s)'"></span>
                </div>
            </template>
        </div>
        @endforeach
    </div>
</div>
