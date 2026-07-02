@extends('layouts.app')
@php
    // Ensure $consultation is always defined (null for create, model for edit)
    $consultation = $consultation ?? null;
@endphp

@section('page-title', $consultation ? 'Edit Consultation — '.$patient->name : 'New Consultation — '.$patient->name)

@section('head-extra')
<style>
    #df-topbar        { display: none !important; }
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }
    * { box-sizing: border-box; }

    /* ── Topbar ── */
    #consult-topbar {
        position:sticky; top:0; z-index:50; background:white;
        border-bottom:1px solid #e5e7eb; height:52px;
        display:flex; align-items:center; justify-content:space-between;
        padding:0 24px; box-shadow:0 1px 4px rgba(106,15,112,.07);
    }
    .btn-save  { padding:7px 18px; font-size:12px; font-weight:700;
        background:#6a0f70; color:white; border:none; border-radius:3px;
        cursor:pointer; transition:background .15s; display:flex; align-items:center; gap:6px; }
    .btn-save:hover  { background:#380740; }
    .btn-save:disabled { opacity:.5; cursor:not-allowed; }
    .btn-draft { padding:7px 14px; font-size:12px; font-weight:600;
        border:1px solid #d1d5db; background:white; color:#374151;
        border-radius:3px; cursor:pointer; transition:all .15s; }
    .btn-draft:hover { border-color:#6a0f70; color:#6a0f70; }

    /* ── Patient strip ── */
    #patient-strip {
        background:white; border-bottom:1px solid #e5e7eb;
        padding:12px 24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    }
    .ps-avatar {
        width:44px; height:44px; border-radius:50%; flex-shrink:0;
        background:linear-gradient(135deg,#6a0f70,#380740);
        display:flex; align-items:center; justify-content:center;
        color:white; font-size:16px; font-weight:600; font-family:'Cormorant Garamond',serif;
    }
    .ps-name { font-size:15px; font-weight:700; color:#111827; font-family:'Cormorant Garamond',serif; }
    .ps-meta span { font-size:11px; color:#6b7280; }

    /* ── Brain workspace ── */
    #brain-textarea {
        width:100%; min-height:220px; max-height:420px;
        border:2px solid #e5e7eb; border-radius:8px;
        padding:16px; font-size:14px; line-height:1.7;
        font-family:'Inter',sans-serif; color:#111827;
        resize:vertical; outline:none; transition:border-color .2s, box-shadow .2s;
        background:white;
    }
    #brain-textarea:focus { border-color:#6a0f70; box-shadow:0 0 0 3px rgba(106,15,112,.07); }
    #brain-textarea::placeholder { color:#9ca3af; font-size:13px; }

    /* ── Extracted field ── */
    .ext-field { background:white; border:1px solid #e5e7eb; border-radius:6px; padding:12px 14px; transition:border-color .15s; }
    .ext-field:focus-within { border-color:#6a0f70; }
    .ext-label { font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.07em; margin-bottom:5px; display:flex; align-items:center; justify-content:space-between; }
    .ext-input { width:100%; border:none; outline:none; font-size:13px; color:#111827; font-family:'Inter',sans-serif; background:transparent; }
    .ext-textarea { width:100%; border:none; outline:none; font-size:13px; color:#111827; font-family:'Inter',sans-serif; resize:none; background:transparent; }

    /* ── Confidence badges ── */
    .conf-high   { background:#dcfce7; color:#15803d; font-size:9px; font-weight:700; padding:1px 6px; border-radius:99px; }
    .conf-medium { background:#fef9c3; color:#a16207; font-size:9px; font-weight:700; padding:1px 6px; border-radius:99px; }
    .conf-low    { background:#f3f4f6; color:#6b7280; font-size:9px; font-weight:700; padding:1px 6px; border-radius:99px; }
    .conf-none   { background:#f3f4f6; color:#d1d5db; font-size:9px; font-weight:700; padding:1px 6px; border-radius:99px; }

    /* ── Tooth tag pills ── */
    .tooth-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:99px;
        background:#f5eef9; color:#6a0f70; font-size:11px; font-weight:700; border:1px solid #d8b4fe; }

    /* ── Pulse animation for extracting ── */
    @keyframes brain-pulse { 0%,100%{opacity:.4} 50%{opacity:1} }
    .brain-pulse { animation:brain-pulse 1.2s infinite; }
</style>
@endsection

@section('content')
<div x-data="consultBrain()" x-init="init()" style="font-family:'Inter',sans-serif;">

{{-- ══ STICKY TOPBAR ══ --}}
<div id="consult-topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="{{ route('patients.show', $patient) }}"
           style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;text-decoration:none;"
           onmouseover="this.style.color='#6a0f70'" onmouseout="this.style.color='#6b7280'">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
            </svg>
            {{ $patient->name }}
        </a>
        <span style="color:#d1d5db;font-size:12px;">/</span>
        <span style="font-size:12px;font-weight:600;color:#374151;">
            {{ isset($consultation) ? 'Edit Consultation' : 'New Consultation' }}
        </span>

        {{-- View toggle — capsule pill --}}
        <div style="display:flex;gap:3px;margin-left:12px;background:#f0e6f2;border-radius:10px;padding:4px 5px;">
            <button x-on:click="viewMode='brain'"
                :style="viewMode==='brain' ? 'background:#ffffff;color:#6a0f70;font-weight:600;box-shadow:0 1px 4px rgba(106,15,112,0.15),0 0 0 1px rgba(106,15,112,0.10);' : 'background:transparent;color:#6b7280;font-weight:500;'"
                style="padding:4px 12px;font-size:11px;border:none;border-radius:7px;cursor:pointer;transition:all .15s;">
                Brain Mode
            </button>
            <button x-on:click="viewMode='form'"
                :style="viewMode==='form' ? 'background:#ffffff;color:#6a0f70;font-weight:600;box-shadow:0 1px 4px rgba(106,15,112,0.15),0 0 0 1px rgba(106,15,112,0.10);' : 'background:transparent;color:#6b7280;font-weight:500;'"
                style="padding:4px 12px;font-size:11px;border:none;border-radius:7px;cursor:pointer;transition:all .15s;">
                Form Mode
            </button>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:10px;">
        {{-- Extraction status --}}
        <div x-show="extracting" style="display:flex;align-items:center;gap:6px;font-size:11px;color:#6a0f70;">
            <span class="brain-pulse">●</span> Extracting…
        </div>
        <div x-show="extracted && !extracting" style="font-size:11px;color:#15803d;display:flex;align-items:center;gap:4px;">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
            Extracted
        </div>

        <button class="btn-draft" x-on:click="saveDraft()">Save Draft</button>
        <button class="btn-save" x-on:click="saveConsultation()" :disabled="saving">
            <svg x-show="saving" style="animation:spin 1s linear infinite;width:12px;height:12px;" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25;"/>
                <path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" style="opacity:.75;"/>
            </svg>
            <span x-text="saving ? 'Saving…' : (viewMode==='brain' && !extracted ? 'Extract & Save' : 'Save Consultation')"></span>
        </button>
    </div>
</div>

{{-- ══ PATIENT STRIP ══ --}}
<div id="patient-strip">
    <div class="ps-avatar">{{ $patient->initials }}</div>
    <div>
        <div class="ps-name">
            {{ $patient->name }}
            @if($patient->is_aocp_active)
                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;background:#fdf3ff;color:#6a0f70;border:1px solid #d8b4fe;">★ AOCP</span>
            @endif
        </div>
        <div class="ps-meta" style="display:flex;gap:14px;margin-top:3px;">
            @if($patient->age)<span>{{ $patient->age }}</span>@endif
            @if($patient->gender)<span>{{ ucfirst($patient->gender) }}</span>@endif
            <span>{{ $patient->phone }}</span>
            @if($patient->area ?? $patient->city)<span>{{ $patient->area ?? $patient->city }}</span>@endif
        </div>
    </div>

    <div style="width:1px;height:40px;background:#f3f4f6;margin:0 4px;flex-shrink:0;"></div>

    {{-- Medical alerts ── --}}
    @if($patient->medical_alert)
    <div style="display:flex;align-items:center;gap:6px;padding:4px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:4px;">
        <svg width="12" height="12" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
        <span style="font-size:11px;font-weight:700;color:#dc2626;">{{ $patient->medical_alert }}</span>
    </div>
    @endif

    @if($patient->medical_conditions && count($patient->medical_conditions ?? []) > 0)
    <div style="display:flex;gap:4px;flex-wrap:wrap;">
        @foreach($patient->medical_conditions as $cond)
        <span style="font-size:10px;padding:2px 6px;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;border-radius:3px;font-weight:600;">{{ $cond }}</span>
        @endforeach
    </div>
    @endif

    @if($patient->current_medications)
    <div style="font-size:11px;color:#6b7280;">
        <span style="font-weight:600;color:#374151;">Meds:</span> {{ $patient->current_medications }}
    </div>
    @endif

    <div style="margin-left:auto;font-size:11px;color:#9ca3af;">
        {{ now()->format('d M Y') }} · {{ now()->format('h:i A') }}
    </div>
</div>

{{-- ══ MAIN WORKSPACE ══ --}}
<div style="max-width:1400px;margin:0 auto;padding:20px 24px;display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

    {{-- ══ LEFT: BRAIN INPUT ══ --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Visit type + date row --}}
        <div style="background:white;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <div style="flex:1;min-width:140px;">
                <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;">Visit Type</label>
                <select x-model="form.visit_type" style="width:100%;border:1px solid #e5e7eb;border-radius:4px;padding:6px 8px;font-size:13px;color:#374151;background:white;outline:none;" onchange="this.style.borderColor='#6a0f70'">
                    <option value="routine">Routine Consultation</option>
                    <option value="followup">Follow-up</option>
                    <option value="emergency">Emergency</option>
                    <option value="review">Review</option>
                    <option value="new_patient">New Patient</option>
                    <option value="second_opinion">Second Opinion</option>
                </select>
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;">Date</label>
                <input type="date" x-model="form.consultation_date"
                    style="width:100%;border:1px solid #e5e7eb;border-radius:4px;padding:6px 8px;font-size:13px;color:#374151;outline:none;"
                    onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#e5e7eb'" />
            </div>
            <div style="flex:1;min-width:140px;">
                <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;">Doctor</label>
                <select x-model="form.doctor_id" style="width:100%;border:1px solid #e5e7eb;border-radius:4px;padding:6px 8px;font-size:13px;color:#374151;background:white;outline:none;">
                    <option value="">— Select —</option>
                    @foreach($doctors ?? \App\Models\User::where('role','doctor')->orWhere('role','admin')->get() as $doc)
                    <option value="{{ $doc->id }}">{{ $doc->doctor_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- BRAIN MODE --}}
        <div x-show="viewMode === 'brain'">

            {{-- The natural language textarea --}}
            <div style="background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:10px 16px;background:#faf5fb;border-bottom:1px solid #f0e6f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div>
                            <div style="font-size:12px;font-weight:700;color:#6a0f70;">Clinical Note</div>
                            <div style="font-size:10px;color:#9ca3af;">Write naturally — the brain will extract the structure</div>
                        </div>
                    </div>
                    <button type="button" x-on:click="extractFromNote()"
                        :disabled="!form.raw_note.trim() || extracting"
                        style="padding:6px 14px;font-size:11px;font-weight:700;background:#6a0f70;color:white;border:none;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:5px;transition:background .15s;"
                        onmouseover="if(!this.disabled)this.style.background='#380740'" onmouseout="this.style.background='#6a0f70'">
                        <span x-show="extracting" class="brain-pulse">●</span>
                        <span x-text="extracting ? 'Extracting…' : 'Extract'"></span>
                    </button>
                </div>
                <div style="padding:16px;">
                    <textarea
                        id="brain-textarea"
                        x-model="form.raw_note"
                        x-on:input.debounce.2000ms="form.raw_note.length > 30 && autoExtract()"
                        placeholder="Tell me what happened today...

Examples:
• Patient complains of pain in lower right region since 3 days. Deep caries seen in 46. RCT initiated — working length 21mm. Temporary restoration placed. Crown advised within 2 weeks.

• Routine check-up. Scaling done. Patient has mild gingivitis. Advised better brushing technique. Review after 6 months.

• 35yr female with sensitivity in 25, 26. OPG advised. Possible periapical pathology. Antibiotics prescribed."
                    ></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                        <span style="font-size:10px;color:#d1d5db;" x-text="form.raw_note.length + ' chars'"></span>
                        <span style="font-size:10px;color:#9ca3af;">Auto-extracts after you stop typing · or click Extract</span>
                    </div>
                </div>
            </div>

            {{-- Extraction status panel (only shown once extracted) --}}
            <div x-show="extracted" style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:11px;color:#15803d;font-weight:600;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                Dentfluence Brain has extracted the structured data. Review and edit on the right, then save.
                <button x-on:click="clearExtraction()" style="margin-left:auto;color:#6b7280;background:none;border:none;cursor:pointer;font-size:10px;text-decoration:underline;">Clear</button>
            </div>
        </div>

        {{-- FORM MODE — traditional structured form --}}
        <div x-show="viewMode === 'form'" style="display:flex;flex-direction:column;gap:12px;">
            @foreach([
                ['chief_complaint','Chief Complaint','textarea','What brought the patient in today?'],
                ['examination_notes','Examination Notes','textarea','Clinical findings, observations…'],
                ['primary_diagnosis','Diagnosis','textarea','Primary diagnosis and any secondary diagnoses…'],
                ['treatment_done','Treatment Done Today','textarea','Procedures completed in this visit…'],
                ['treatment_plan_note','Treatment Advice','textarea','Recommended next steps, pending treatments…'],
                ['follow_up_note','Follow-up Instructions','textarea','When to return, what to watch for…'],
                ['risks_discussed','Risks Discussed','input','Risks/complications communicated to patient'],
                ['prescription_notes','Prescription','textarea','Medications prescribed, dosage, duration…'],
            ] as [$field, $label, $type, $ph])
            <div class="ext-field">
                <div class="ext-label">{{ $label }}</div>
                @if($type === 'textarea')
                <textarea class="ext-textarea" rows="2" x-model="form.{{ $field }}" placeholder="{{ $ph }}"></textarea>
                @else
                <input class="ext-input" type="text" x-model="form.{{ $field }}" placeholder="{{ $ph }}" />
                @endif
            </div>
            @endforeach
        </div>

    </div>
    {{-- /left --}}

    {{-- ══ RIGHT: EXTRACTED STRUCTURED DATA ══ --}}
    <div style="position:sticky;top:60px;display:flex;flex-direction:column;gap:12px;">

        <div style="background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <div style="padding:10px 14px;background:#faf5fb;border-bottom:1px solid #f0e6f6;display:flex;align-items:center;justify-content:space-between;">
                <div style="font-size:11px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.07em;">
                    Structured Data
                </div>
                <div x-show="extracted" style="font-size:10px;color:#9ca3af;">Doctor verified before saving</div>
                <div x-show="!extracted" style="font-size:10px;color:#d1d5db;">Waiting for extraction…</div>
            </div>

            <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">

                {{-- Chief Complaint --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Chief Complaint
                        <span :class="confClass(confidence.chief_complaint)" x-text="confLabel(confidence.chief_complaint)"></span>
                    </div>
                    <input class="ext-input" type="text" x-model="form.chief_complaint"
                        placeholder="e.g. Pain in lower right region" />
                </div>

                {{-- Tooth Numbers --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Tooth Number(s)
                        <span :class="confClass(confidence.tooth_numbers)" x-text="confLabel(confidence.tooth_numbers)"></span>
                    </div>
                    <div x-show="form.tooth_numbers.length > 0" style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">
                        <template x-for="(t,i) in form.tooth_numbers" :key="i">
                            <span class="tooth-pill">
                                <span x-text="t"></span>
                                <button type="button" x-on:click="form.tooth_numbers.splice(i,1)"
                                    style="background:none;border:none;cursor:pointer;color:#9ca3af;line-height:1;padding:0;font-size:12px;">×</button>
                            </span>
                        </template>
                    </div>
                    <input class="ext-input" type="text" x-model="toothInput"
                        placeholder="e.g. 46 — press Enter"
                        x-on:keydown.enter.prevent="addTooth()" />
                </div>

                {{-- Diagnosis --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Diagnosis
                        <span :class="confClass(confidence.primary_diagnosis)" x-text="confLabel(confidence.primary_diagnosis)"></span>
                    </div>
                    <textarea class="ext-textarea" rows="2" x-model="form.primary_diagnosis"
                        placeholder="e.g. Deep caries, Periapical pathology"></textarea>
                </div>

                {{-- Procedure Done --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Procedure Done
                        <span :class="confClass(confidence.treatment_done)" x-text="confLabel(confidence.treatment_done)"></span>
                    </div>
                    <textarea class="ext-textarea" rows="2" x-model="form.treatment_done"
                        placeholder="e.g. RCT initiated, Temporary restoration"></textarea>
                </div>

                {{-- Treatment Advice --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Treatment Advice
                        <span :class="confClass(confidence.treatment_plan_note)" x-text="confLabel(confidence.treatment_plan_note)"></span>
                    </div>
                    <textarea class="ext-textarea" rows="2" x-model="form.treatment_plan_note"
                        placeholder="e.g. Crown within 2 weeks"></textarea>
                </div>

                {{-- Follow-up --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Follow-up
                        <span :class="confClass(confidence.follow_up_note)" x-text="confLabel(confidence.follow_up_note)"></span>
                    </div>
                    <input class="ext-input" type="text" x-model="form.follow_up_note"
                        placeholder="e.g. Review after 7 days" />
                    <div x-show="form.follow_up_days > 0" style="margin-top:4px;">
                        <input type="date" x-model="form.follow_up_date"
                            style="width:100%;border:1px solid #e5e7eb;border-radius:3px;padding:4px 8px;font-size:12px;outline:none;"
                            onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#e5e7eb'" />
                    </div>
                </div>

                {{-- Risks Discussed --}}
                <div class="ext-field">
                    <div class="ext-label">
                        Risks Discussed
                        <span :class="confClass(confidence.risks_discussed)" x-text="confLabel(confidence.risks_discussed)"></span>
                    </div>
                    <input class="ext-input" type="text" x-model="form.risks_discussed"
                        placeholder="Risks/complications explained to patient" />
                </div>

                {{-- Treatment Acceptance --}}
                <div class="ext-field">
                    <div class="ext-label">Treatment Acceptance</div>
                    <div style="display:inline-flex;gap:4px;margin-top:4px;background:#f0e6f2;border-radius:10px;padding:4px 5px;width:fit-content;">
                        @foreach(['accepted'=>'Accepted','pending'=>'Pending','refused'=>'Refused','deferred'=>'Deferred'] as $val => $lbl)
                        <button type="button" x-on:click="form.treatment_acceptance = '{{ $val }}'"
                            :style="form.treatment_acceptance === '{{ $val }}'
                                ? 'background:#ffffff;color:#6a0f70;box-shadow:0 1px 4px rgba(106,15,112,0.15),0 0 0 1px rgba(106,15,112,0.10);font-weight:600;'
                                : 'background:transparent;color:#6b7280;font-weight:500;'"
                            style="padding:4px 12px;font-size:11px;border:none;border-radius:7px;cursor:pointer;transition:all .15s;">
                            {{ $lbl }}
                        </button>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- Past consultations reference --}}
        @if(isset($pastConsultations) && $pastConsultations->count() > 0)
        <div style="background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <div style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;">
                Previous Visits
            </div>
            <div style="padding:8px 0;">
                @foreach($pastConsultations->take(3) as $pc)
                <div style="padding:6px 12px;border-bottom:1px solid #f9fafb;cursor:pointer;"
                     onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background='transparent'">
                    <div style="font-size:11px;font-weight:600;color:#374151;">
                        {{ $pc->consultation_date?->format('d M Y') ?? $pc->created_at->format('d M Y') }}
                        @if($pc->visit_type)
                            · <span style="color:#6b7280;">{{ ucfirst($pc->visit_type) }}</span>
                        @endif
                    </div>
                    @if($pc->chief_complaint)
                    <div style="font-size:11px;color:#9ca3af;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $pc->chief_complaint }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    {{-- /right --}}

</div>
{{-- /main workspace --}}

</div>
{{-- /x-data --}}

@push('scripts')
<script>
function consultBrain() {
    return {
        viewMode: 'brain',
        extracting: false,
        extracted: false,
        saving: false,
        toothInput: '',

        confidence: {
            chief_complaint: 0,
            tooth_numbers: 0,
            primary_diagnosis: 0,
            treatment_done: 0,
            treatment_plan_note: 0,
            follow_up_note: 0,
            risks_discussed: 0,
        },

        form: {
            visit_type: '{{ old('visit_type', $consultation?->visit_type ?? 'routine') }}',
            consultation_date: '{{ old('consultation_date', $consultation?->consultation_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}',
            doctor_id: '{{ old('doctor_id', $consultation?->doctor_id ?? '') }}',
            raw_note: `{!! addslashes($consultation?->raw_note ?? '') !!}`,
            chief_complaint: `{!! addslashes($consultation?->chief_complaint ?? '') !!}`,
            tooth_numbers: @json($consultation?->tooth_numbers ?? []),
            primary_diagnosis: `{!! addslashes($consultation?->primary_diagnosis ?? '') !!}`,
            treatment_done: `{!! addslashes($consultation?->treatment_done ?? '') !!}`,
            treatment_plan_note: `{!! addslashes($consultation?->treatment_plan_note ?? '') !!}`,
            follow_up_note: `{!! addslashes($consultation?->follow_up_note ?? '') !!}`,
            follow_up_date: '{{ $consultation?->follow_up_date?->format('Y-m-d') ?? '' }}',
            follow_up_days: 0,
            risks_discussed: `{!! addslashes($consultation?->risks_discussed ?? '') !!}`,
            treatment_acceptance: '{{ $consultation?->treatment_acceptance ?? 'pending' }}',
            prescription_notes: `{!! addslashes($consultation?->prescription_notes ?? '') !!}`,
            examination_notes: `{!! addslashes($consultation?->examination_notes ?? '') !!}`,
            status: '{{ $consultation?->status ?? 'draft' }}',
        },

        init() {
            // If editing, mark as already extracted
            @if(isset($consultation) && $consultation->chief_complaint)
            this.extracted = true;
            @endif
        },

        // ── Confidence helpers ──────────────────────────────────────────────

        confClass(c) {
            if (c >= 0.8) return 'conf-high';
            if (c >= 0.5) return 'conf-medium';
            if (c > 0)    return 'conf-low';
            return 'conf-none';
        },
        confLabel(c) {
            if (c >= 0.8) return 'HIGH';
            if (c >= 0.5) return 'MED';
            if (c > 0)    return 'LOW';
            return '—';
        },

        // ── Tooth input ─────────────────────────────────────────────────────

        addTooth() {
            const t = this.toothInput.trim();
            if (t && !this.form.tooth_numbers.includes(t)) {
                this.form.tooth_numbers.push(t);
            }
            this.toothInput = '';
        },

        // ── Auto-extract (debounced from textarea) ──────────────────────────

        autoExtract() {
            if (this.extracted) return; // don't overwrite after manual verification
            this.extractFromNote();
        },

        // ── DENTFLUENCE BRAIN ───────────────────────────────────────────────
        // Pattern-based extraction — replaces with real AI when available.

        extractFromNote() {
            const note = this.form.raw_note;
            if (!note.trim()) return;

            this.extracting = true;
            // Simulate network delay
            setTimeout(() => {
                this._doExtract(note);
                this.extracting = false;
                this.extracted = true;
            }, 900);
        },

        _doExtract(note) {
            const lower = note.toLowerCase();

            // ── Tooth numbers (FDI: 11-18, 21-28, 31-38, 41-48) ──
            const toothMatches = [...note.matchAll(/\b([1-4][1-8])\b/g)]
                .map(m => m[1])
                .filter((v, i, a) => a.indexOf(v) === i);
            if (toothMatches.length > 0) {
                this.form.tooth_numbers = [...new Set([...this.form.tooth_numbers, ...toothMatches])];
                this.confidence.tooth_numbers = 0.9;
            }

            // ── Chief complaint ──
            const complaintPatterns = [
                /complain(?:s|ing|t)?\s+of\s+([^.]+)/i,
                /c\/o\s*[:\-]?\s*([^.]+)/i,
                /presented?\s+with\s+([^.]+)/i,
                /pain\s+in\s+([^.]+)/i,
                /sensitivity\s+in\s+([^.]+)/i,
                /swelling\s+(?:in|of|near)\s+([^.]+)/i,
            ];
            for (const p of complaintPatterns) {
                const m = note.match(p);
                if (m && m[1] && !this.form.chief_complaint) {
                    this.form.chief_complaint = m[1].trim().replace(/\.$/, '');
                    this.confidence.chief_complaint = 0.85;
                    break;
                }
            }
            if (!this.form.chief_complaint && (lower.includes('pain') || lower.includes('sensitivity') || lower.includes('swelling'))) {
                // Extract the first sentence containing a symptom
                const sents = note.split(/[.!?]/);
                const s = sents.find(s => /pain|sensitiv|swelling|bleed|loosen|broken|fractur/i.test(s));
                if (s) { this.form.chief_complaint = s.trim(); this.confidence.chief_complaint = 0.6; }
            }

            // ── Diagnosis ──
            const diagPatterns = [
                /diagno(?:sis|sed)\s*[:\-]?\s*([^.]+)/i,
                /deep\s+caries/i,
                /periapical\s+(?:pathology|abscess|infection)/i,
                /pulpitis/i,
                /gingivitis/i,
                /periodontitis/i,
                /fractur/i,
            ];
            const diagKeywords = [];
            if (/deep\s+caries/i.test(note)) diagKeywords.push('Deep Caries');
            if (/periapical/i.test(note)) diagKeywords.push('Periapical Pathology');
            if (/pulpitis/i.test(note)) diagKeywords.push('Pulpitis');
            if (/gingivitis/i.test(note)) diagKeywords.push('Gingivitis');
            if (/periodontitis/i.test(note)) diagKeywords.push('Periodontitis');
            if (/fractur/i.test(note)) diagKeywords.push('Fracture');
            if (/caries/i.test(note) && !diagKeywords.length) diagKeywords.push('Caries');
            const diagMatch = note.match(/diagno(?:sis|sed)\s*[:\-]?\s*([^.]+)/i);
            if (diagMatch) {
                this.form.primary_diagnosis = diagMatch[1].trim();
                this.confidence.primary_diagnosis = 0.9;
            } else if (diagKeywords.length) {
                this.form.primary_diagnosis = diagKeywords.join(', ');
                this.confidence.primary_diagnosis = 0.75;
            }

            // ── Treatment done ──
            const procKeywords = [];
            if (/RCT|root\s+canal/i.test(note)) {
                procKeywords.push('RCT');
                const wl = note.match(/working\s+length\s+(\d+)\s*mm/i);
                if (wl) procKeywords.push(`WL ${wl[1]}mm`);
            }
            if (/scal(?:ing|e)/i.test(note)) procKeywords.push('Scaling');
            if (/extract/i.test(note)) procKeywords.push('Extraction');
            if (/filling|restor(?:ation|ed)/i.test(note)) procKeywords.push('Restoration');
            if (/implant/i.test(note)) procKeywords.push('Implant');
            if (/crown/i.test(note) && /placed|cemented|fitted/i.test(note)) procKeywords.push('Crown cemented');
            if (/temporary|temp\s+restor/i.test(note)) procKeywords.push('Temporary restoration');
            if (/x[-\s]?ray|opg|iopa|cbct/i.test(note)) procKeywords.push('Radiograph advised');
            if (procKeywords.length) {
                this.form.treatment_done = procKeywords.join('. ');
                this.confidence.treatment_done = 0.8;
            }

            // ── Treatment advice ──
            const advicePatterns = [
                /advis(?:ed|e)\s+([^.]+)/i,
                /recommend(?:ed)?\s+([^.]+)/i,
                /planned?\s+(?:for\s+)?([^.]+)/i,
                /crown\s+(?:within|in)\s+([^.]+)/i,
            ];
            for (const p of advicePatterns) {
                const m = note.match(p);
                if (m && m[1]) {
                    this.form.treatment_plan_note = m[1].trim().replace(/\.$/, '');
                    this.confidence.treatment_plan_note = 0.75;
                    break;
                }
            }

            // ── Follow-up ──
            const fuPatterns = [
                /review\s+after\s+(\d+)\s*(day|week|month)/i,
                /follow[\s\-]?up\s+(?:after\s+)?(\d+)\s*(day|week|month)/i,
                /return\s+(?:in|after)\s+(\d+)\s*(day|week|month)/i,
                /recall\s+(?:in|after)\s+(\d+)\s*(day|week|month)/i,
            ];
            for (const p of fuPatterns) {
                const m = note.match(p);
                if (m) {
                    const num = parseInt(m[1]);
                    const unit = m[2].toLowerCase();
                    this.form.follow_up_note = `Review after ${num} ${unit}${num > 1 ? 's' : ''}`;
                    // Calculate date
                    const d = new Date();
                    if (unit.startsWith('day')) d.setDate(d.getDate() + num);
                    else if (unit.startsWith('week')) d.setDate(d.getDate() + num * 7);
                    else if (unit.startsWith('month')) d.setMonth(d.getMonth() + num);
                    this.form.follow_up_date = d.toISOString().split('T')[0];
                    this.form.follow_up_days = num;
                    this.confidence.follow_up_note = 0.9;
                    break;
                }
            }

            // ── Risks ──
            if (/risk|warn|complic|consequ/i.test(note)) {
                const rm = note.match(/risk[s]?\s+(?:of\s+)?([^.]+)/i);
                if (rm) { this.form.risks_discussed = rm[1].trim(); this.confidence.risks_discussed = 0.7; }
            }

            // ── Treatment acceptance ──
            if (/accept(?:ed|s)/i.test(note)) { this.form.treatment_acceptance = 'accepted'; }
            else if (/refus(?:ed|es)/i.test(note)) { this.form.treatment_acceptance = 'refused'; }
            else if (/defer(?:red)?|postpone/i.test(note)) { this.form.treatment_acceptance = 'deferred'; }
        },

        clearExtraction() {
            this.extracted = false;
            this.confidence = { chief_complaint:0, tooth_numbers:0, primary_diagnosis:0, treatment_done:0, treatment_plan_note:0, follow_up_note:0, risks_discussed:0 };
        },

        // ── Save ────────────────────────────────────────────────────────────

        async saveDraft() {
            this.form.status = 'draft';
            await this.saveConsultation();
        },

        async saveConsultation() {
            // If in brain mode with unsaved extraction, extract first
            if (this.viewMode === 'brain' && this.form.raw_note.trim() && !this.extracted) {
                this._doExtract(this.form.raw_note);
                this.extracted = true;
            }

            this.saving = true;
            try {
                const url = '{{ isset($consultation)
                    ? route('patients.consultations.update', [$patient, $consultation])
                    : route('patients.consultations.store', $patient) }}';
                const method = '{{ isset($consultation) ? 'PATCH' : 'POST' }}';

                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const data = await resp.json();
                if (resp.ok && data.success) {
                    window.location.href = data.redirect_url ?? '{{ route('patients.show', $patient) }}?tab=consultation';
                } else {
                    alert(data.message ?? 'Failed to save. Please try again.');
                }
            } catch (e) {
                console.error(e);
                alert('Network error. Please try again.');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
