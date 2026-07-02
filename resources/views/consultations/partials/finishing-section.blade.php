{{--
    partials/finishing-section.blade.php
    Section 12 — Finishing Section
    Alpine state: form.finishing.{notes, next_visit_type, next_visit_date, recall_interval, recall_custom, responsible_id}
                  attachments[], handleAttachments()
    Requires: $patient (for route), $doctors collection
--}}
@php
    // Recall interval removed from Consultation — managed by PRM / Recall Engine instead.
    $nextVisitTypes  = ['Review & Treatment','Follow-up','Procedure','Consultation','Recall'];
    $saved           = $consultation?->finishing_notes;
@endphp

<div class="c-card" x-data="{open: {{ $consultation?->finishing_notes || $consultation?->next_visit_date ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">12</span>Finishing Section</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && form.finishing.next_visit_date" x-cloak
                  x-text="'Next: ' + form.finishing.next_visit_date"></span>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'finishing-section',label:'Finishing Section'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">

        {{-- Notes --}}
        <div>
            <label class="df-label">Notes / Additional Comments</label>
            <textarea name="finishing_notes"
                      x-model="form.finishing.notes"
                      class="df-input"
                      rows="4"
                      placeholder="Type your notes here…">{{ old('finishing_notes', $consultation?->finishing_notes) }}</textarea>
        </div>

        {{-- Next Visit --}}
        <div>
            <label class="df-label">Next Visit Type</label>
            <select name="next_visit_type" x-model="form.finishing.next_visit_type" class="df-input" style="margin-bottom:8px;">
                <option value="">Select</option>
                @foreach($nextVisitTypes as $v)
                    <option {{ old('next_visit_type', $consultation?->next_visit_type) === $v ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <label class="df-label">Date</label>
            <input type="date"
                   name="next_visit_date"
                   x-model="form.finishing.next_visit_date"
                   class="df-input"
                   value="{{ old('next_visit_date', $consultation?->next_visit_date) }}">
        </div>
        {{-- Recall interval removed — managed by PRM / Recall Engine on the patient page --}}

        {{-- Doctor + Attachments --}}
        <div>
            <label class="df-label">Responsible Doctor</label>
            <select name="responsible_user_id" x-model="form.finishing.responsible_id" class="df-input" style="margin-bottom:8px;">
                <option value="">Select Doctor</option>
                @foreach($doctors ?? [] as $doc)
                    <option value="{{ $doc->id }}" {{ old('responsible_user_id', $consultation?->responsible_user_id) == $doc->id ? 'selected' : '' }}>
                        {{ $doc->name }}
                    </option>
                @endforeach
            </select>

            <label class="df-label">Attachments</label>
            <div class="upload-zone" @click="document.getElementById('att-input').click()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d1d5db"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                     style="margin:0 auto 3px;display:block;">
                    <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                </svg>
                <div style="font-size:11px;color:#9ca3af;">+ Add File</div>
                <input type="file" id="att-input" name="attachments[]" multiple style="display:none;"
                       @change="handleAttachments($event)">
            </div>

            <template x-for="(f, i) in attachments" :key="i">
                <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#374151;margin-top:4px;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span x-text="f.name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <button type="button" @click="attachments.splice(i,1)"
                            style="background:none;border:none;color:#d1d5db;cursor:pointer;font-size:14px;line-height:1;">×</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Footer action bar --}}
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6;background:#fafafa;display:flex;align-items:center;gap:10px;">
        <a href="{{ route('patients.show', $patient) }}"
           style="padding:7px 16px;font-size:13px;border:1px solid #e5e7eb;color:#6b7280;border-radius:3px;text-decoration:none;"
           onmouseover="this.style.borderColor='#fca5a5';this.style.color='#dc2626';"
           onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280';">
            Cancel Consultation
        </a>
        <div style="flex:1;"></div>
        <button type="button" class="btn-draft" @click="saveDraft()">Save Draft</button>
        <button type="submit" class="btn-save">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Save &amp; Continue
        </button>
    </div>
</div>
