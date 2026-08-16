@push('scripts')
<script>
const TV_STAGES        = {{ Js::from($stagesJson) }};
const TV_LAB_TREATMENTS = {{ Js::from($labTreatmentsMap) }};
const TV_LAB_CATEGORIES = {{ Js::from(\App\Models\LabCase::WORK_CATEGORIES) }};
const TV_APPOINTMENTS  = {{ Js::from($appointmentsJson) }};
const TV_TREATMENTS    = {{ Js::from($treatmentsList) }};

function treatmentVisits() {
    return {
        visits: {{ Js::from($visitsJson) }},
        stages: TV_STAGES,

        // UX-01: filter chips removed — search is the single narrowing control.
        search: '',

        formOpen: false,
        saving: false,
        errorMsg: '',
        editingVisit: null,
        // Treatment typeahead
        txSearch: '',
        txSuggestions: [],
        txSuggestOpen: false,
        // Tooth chart state
        toothChartOpen: false,
        selectedTeeth: [],
        // Vitals collapsible (collapsed by default — optional section)
        vitalsOpen: false,
        // Redesign (2026-08-05): meta-line editor + notes/vitals drawer toggles
        metaOpen: false,
        drawerOpen: false,
        // Treatment plans for the dropdown — seeded from server, refreshed on form open
        // so a plan created/accepted in the Plan tab shows up WITHOUT a page reload.
        treatmentPlans: @json($treatmentPlansJson),
        // Plan items + visit items state
        planItems: [],
        planItemsLoading: false,
        otherActive: false, // "Other / Not in plan" typeahead toggled on
        visitItems: [],   // billing line items (from plan + add-ons + custom)
        addonItems: [],   // layer-3 add-on procedures (lightweight, merged into visitItems on save)
        form: {},

        // Repeat-work detection
        repeatWarnings: [],   // [{treatment_name, tooth, date, originalItemId}]
        repeatReason: '',     // staff-entered reason (required when repeats exist)

        // Visit → Next Action state. Kept OUT of `form` on purpose: `form` maps
        // 1:1 onto treatment_visits columns, these rows become follow_ups.
        // Same separation the lab case and visit items already use.
        // Starts EMPTY — the doctor opts in with one tap, so a visit that needs
        // no reception action is not one field longer than it was before.
        nextActions: [],
        // Stable repeater keys. Index keys make Alpine reuse DOM nodes across
        // removals, which strands a <select> on the wrong row's value.
        _nextActionUid: 0,

        addNextAction() {
            this.nextActions.push({
                _uid:        ++this._nextActionUid,
                id:          null,
                action_type: 'wellness_call',
                due_mode:    'tomorrow',
                due_in_days: 3,
                due_date:    '',
                instruction: '',
            });
        },

        removeNextAction(idx) { this.nextActions.splice(idx, 1); },

        /** Human echo of when this action lands, so the doctor can see the
         *  real date before saving. Anchored on visit_date (not today) — a
         *  back-dated visit schedules from the day the patient was seen. */
        nextActionDueLabel(row) {
            const anchor = this.form.visit_date
                ? new Date(this.form.visit_date + 'T00:00:00')
                : new Date();
            let due = new Date(anchor);
            if (row.due_mode === 'tomorrow')      due.setDate(anchor.getDate() + 1);
            else if (row.due_mode === 'in_days')  due.setDate(anchor.getDate() + (parseInt(row.due_in_days) || 1));
            else if (row.due_mode === 'on_date') {
                if (!row.due_date) return '—';
                due = new Date(row.due_date + 'T00:00:00');
            }
            return due.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        },

        // Lab case state — populated from the lab prompt section
        labCase: {
            enabled:              false,
            lab_vendor_id:        '',
            work_category:        '',
            work_subtype:         '',
            priority:             'routine',
            expected_return_date: '',
            instructions:         '',
        },

        init() { this.form = this._blank(); },

        get filteredVisits() {
            let out = this.visits;
            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                out = out.filter(v =>
                    (v.treatment_name||'').toLowerCase().includes(q) ||
                    (v.tooth_number||'').toLowerCase().includes(q) ||
                    (v.notes||'').toLowerCase().includes(q) ||
                    (v.doctor_name||'').toLowerCase().includes(q)
                );
            }
            // Always show newest visits first
            return [...out].sort((a, b) => new Date(b.visit_date || 0) - new Date(a.visit_date || 0));
        },

        get currentStages() {
            return this.stages[this.form.treatment_name] || {};
        },

        // F2: total of suggested prices on selected visit items
        get visitItemsTotal() {
            return this.visitItems.reduce((s, i) => s + (parseFloat(i.suggested_price)||0), 0);
        },

        // True when the selected treatment needs lab work
        get labNeeded() {
            return !!(this.form.treatment_name && TV_LAB_TREATMENTS[this.form.treatment_name]);
        },

        // ── Redesign getters (2026-08-05) — presentation only, derived only ──

        /** Meta line: "Today, 05 Aug 2026" or the picked date. */
        get metaDateLabel() {
            const t = new Date().toISOString().slice(0,10);
            return this.form.visit_date === t
                ? 'Today, ' + this._fmtDate(t)
                : this._fmtDate(this.form.visit_date);
        },

        /** Meta line: linked appointment label, or a quiet placeholder. */
        get metaApptLabel() {
            if (!this.form.appointment_id) return 'no appointment linked';
            const a = TV_APPOINTMENTS.find(x => String(x.id) === String(this.form.appointment_id));
            return a ? a.label : 'appointment #' + this.form.appointment_id;
        },

        /** Zone 0 line 1 — last visit, compiled from structured facts only. */
        get zone0LastLine() {
            if (this.editingVisit || !this.visits.length) return '';
            const v = [...this.visits].sort((a,b) => new Date(b.visit_date||0) - new Date(a.visit_date||0))[0];
            if (!v) return '';
            const items = (v.visit_items || []).map(i =>
                i.treatment_name
                + (i.tooth_number ? ' T' + i.tooth_number : '')
                + (i.work_outcome ? ' — ' + (this.workOutcomes[i.work_outcome] || i.work_outcome) : '')
            ).join(', ');
            const what = items || v.treatment_name || v.procedure || 'visit recorded';
            const st = (v.completed_stages || []).length;
            return 'Last visit ' + this._fmtDate(v.visit_date) + ': ' + what
                + (st ? ' · ' + st + ' stage' + (st > 1 ? 's' : '') + ' done' : '');
        },

        /** Zone 0 course lines — plan name + DerivedProgressService label. */
        get zone0PlanLines() {
            if (this.editingVisit) return [];
            return this.treatmentPlans.slice(0, 2).map(p =>
                'Plan "' + p.plan_name + '" — ' + (p.progress || 'no work recorded yet'));
        },

        /** Completion suggestion — every planned item picked today is
         *  'Completed Today'. Styling only; never auto-checks the box. */
        get allPlannedCompletedToday() {
            const planned = this.visitItems.filter(i => i.treatment_plan_item_id);
            return planned.length > 0 && planned.every(i => i.work_outcome === 'completed_today');
        },

        /** Delta 2 — footer micro-summary. Derived from existing state only:
         *  no duplicate calculations (reuses visitItemsTotal / labCase /
         *  mark_treatment_complete). */
        get footerSummary() {
            const n = this.visitItems.length;
            if (n === 0) return '0 items · review only';
            const parts = [n + (n === 1 ? ' item' : ' items'), 'Rs. ' + this.fmt(this.visitItemsTotal)];
            if (this.labCase.enabled)               parts.push('lab draft');
            if (this.form.mark_treatment_complete)  parts.push('plan completes');
            return parts.join(' · ');
        },

        /** Delta 3 (R-1) — dense picker layout for complex cases (FMR,
         *  implant rehab). Pure layout switch; same rows, same handlers. */
        get densePicker() {
            return this.planItems.length > 6;
        },

        /** Delta 4 (R-2) — bulk outcome for dense mode. Loops the EXISTING
         *  per-item setter, so every item carries its own work_outcome in the
         *  payload exactly as if tapped individually. */
        bulkOutcome(key) {
            this.planItems.forEach(pi => {
                const row = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
                if (row) row.work_outcome = key;
            });
        },

        /** Chip active-state: is next_visit_date exactly N days from today? */
        isNextVisitIn(days) {
            const d = new Date(); d.setDate(d.getDate() + days);
            return this.form.next_visit_date === d.toISOString().slice(0, 10);
        },

        /** Next-visit chips — compute the EXISTING next_visit_date field
         *  client-side; suggest a purpose from the primary treatment when the
         *  field is still empty. Presentation over existing payload keys. */
        setNextVisitIn(days) {
            const d = new Date(); d.setDate(d.getDate() + days);
            this.form.next_visit_date = d.toISOString().slice(0, 10);
            if (!this.form.next_visit_type) {
                const map = { 'RCT': 'RCT Next Step', 'Extraction': 'Suture Removal',
                              'Crown Prep': 'Crown Try-in', 'Implant': 'Implant Review' };
                this.form.next_visit_type = map[this.form.treatment_name] || 'Review';
            }
        },

        // Work subtypes for the selected lab category
        get labSubtypes() {
            return TV_LAB_CATEGORIES[this.labCase.work_category] || [];
        },

        _blank() {
            return {
                appointment_id: '',
                visit_date: new Date().toISOString().slice(0,10),
                visit_type: 'treatment',
                // Redesign: a chairside save documents work that HAPPENED —
                // 'completed' is the honest default (approved spec decision).
                // Editable in the meta-line editor for the exceptional case.
                status: 'completed',
                doctor_id: '{{ auth()->id() }}',
                treatment_plan_id: '',
                plan_item_id: '',        // selected plan item (layer 2)
                treatment_name: '',
                current_stage: '',
                completed_stages: [],
                tooth_number: '',
                notes: '',
                chief_complaint: '',
                next_visit_date: '', next_visit_type: '',
                mark_treatment_complete: false,
                rct_num_canals: '', rct_canal_lengths: [], rct_file_type: '', rct_irrigant: '', rct_obturation_method: '',
                impl_brand: '', impl_size: '', impl_torque: '', impl_graft_used: '', impl_graft_brand: '', impl_membrane: '', impl_healing_collar: '',
                implant_fixture_catalog_id: '', implant_components_used: [], implant_lot_number: '',
                fill_material: '', fill_shade: '',
                scale_quadrants: '', scale_method: '',
                ext_type: '', ext_socket: '', ext_suture: false,
                crown_type: '', crown_shade: '', crown_impression: false, crown_temp_placed: '',
                prescription_drugs: [], prescription_instructions: [], prescription_custom_notes: '',
                // Vitals (optional)
                bp_systolic: '', bp_diastolic: '', pulse_rate: '', spo2: '', temperature: '',
                blood_sugar: '', blood_sugar_type: '', weight: '', vitals_notes: '',
            };
        },

        // When an appointment is selected, auto-fill visit_date, doctor and
        // (redesign) the visit_type default from the appointment's type.
        // A default only — the meta-line editor can still override it.
        onAppointmentChange() {
            if (!this.form.appointment_id) return;
            const appt = TV_APPOINTMENTS.find(a => String(a.id) === String(this.form.appointment_id));
            if (!appt) return;
            if (appt.date)      this.form.visit_date = appt.date;
            if (appt.doctor_id) this.form.doctor_id  = appt.doctor_id;
            const typeMap = { 'treatment': 'treatment', 'follow-up': 'followup' };
            if (appt.type && typeMap[appt.type]) this.form.visit_type = typeMap[appt.type];
        },

        onTreatmentChange() {
            this.form.completed_stages = [];
            this.form.current_stage = '';
            if (this.form.treatment_name === 'RCT') this.form.rct_canal_lengths = [];
            this._carryForwardStages();
            // Auto-configure lab case prompt when treatment needs lab
            this._syncLabCase();
            this._checkRepeatWork();
        },

        // ── Treatment typeahead helpers ──
        filterTx() {
            const q = this.txSearch.toLowerCase().trim();
            this.txSuggestions = q
                ? TV_TREATMENTS.filter(t => t.toLowerCase().includes(q)).slice(0, 12)
                : TV_TREATMENTS.slice(0, 12);
            this.txSuggestOpen = this.txSuggestions.length > 0;
        },
        selectTx(name) {
            this.form.treatment_name = name;
            this.txSearch            = name;
            this.txSuggestOpen       = false;
            if (this.otherActive) {
                // Keep a single "other" billing line in sync with the typeahead pick,
                // and make it the primary treatment (matches the old single-pick behavior).
                this.visitItems = this.visitItems.filter(i => i._isOther !== true);
                this.visitItems.push({
                    _isOther:                true,
                    treatment_plan_item_id:  null,
                    treatment_name:          name,
                    material_option:         '',
                    tooth_number:            '',
                    suggested_price:         '',
                    notes:                   '',
                });
                this.form.plan_item_id = '__other__';
            }
            this.onTreatmentChange();
        },
        clearTx() {
            this.form.treatment_name = '';
            this.txSearch            = '';
            this.txSuggestions       = [];
            this.txSuggestOpen       = false;
            if (this.otherActive) {
                this.visitItems = this.visitItems.filter(i => i._isOther !== true);
                if (this.form.plan_item_id === '__other__') {
                    this._promoteNextPrimary();
                    return;
                }
            }
            this.onTreatmentChange();
        },

        _syncLabCase() {
            const info = TV_LAB_TREATMENTS[this.form.treatment_name];
            if (info) {
                // Pre-fill work category from treatment's default; keep enabled state user chose
                if (info.work_category && !this.labCase.work_category) {
                    this.labCase.work_category = info.work_category;
                }
                // Auto-enable the toggle when treatment changes to a lab-needing one
                this.labCase.enabled = true;
            } else {
                // Reset if treatment changed to one without lab
                this.labCase.enabled        = false;
                this.labCase.work_category  = '';
                this.labCase.work_subtype   = '';
            }
        },

        // When treatment+tooth combo matches an existing in-progress visit,
        // pre-load that visit's completed stages so the doctor continues from where they left off.
        _carryForwardStages() {
            if (!this.form.treatment_name || !this.form.tooth_number) return;
            if (this.editingVisit) return; // editing an existing visit — don't override
            const match = this.visits.find(v =>
                v.treatment_name === this.form.treatment_name &&
                v.tooth_number   === this.form.tooth_number   &&
                v.status         !== 'completed'              &&
                (!this.editingVisit || v.id !== this.editingVisit.id)
            );
            if (match && match.completed_stages && match.completed_stages.length > 0) {
                this.form.completed_stages = [...match.completed_stages];
                this.form.current_stage    = match.current_stage || '';
                // Show a subtle hint (won't block saving)
                this.errorMsg = '↩ Stages carried forward from last visit on Tooth ' + this.form.tooth_number + '. Mark new stages done.';
                setTimeout(() => { if (this.errorMsg.startsWith('↩')) this.errorMsg = ''; }, 4000);
            }
        },

        // Tooth chart helpers
        toggleTooth(n) {
            const idx = this.selectedTeeth.indexOf(n);
            if (idx >= 0) this.selectedTeeth.splice(idx, 1);
            else this.selectedTeeth.push(n);
            this.form.tooth_number = this.selectedTeeth.slice().sort((a,b)=>a-b).join(', ');
            this._carryForwardStages();
            this._checkRepeatWork();
        },

        // ── Repeat-work detection ────────────────────────────────────────────
        // Split a "45, 46" style string into a clean array of tooth tokens.
        _teethSet(str) {
            if (!str) return [];
            return String(str).split(/[,\s]+/).map(s => s.trim()).filter(Boolean);
        },

        // Look for any PAST visit where the same treatment was done on the same
        // tooth for this patient. Runs entirely on already-loaded history, so it
        // is instant. Populates this.repeatWarnings.
        _checkRepeatWork() {
            this.repeatWarnings = [];
            if (this.editingVisit) return; // editing an existing visit — don't warn

            // What is being recorded right now (treatment + effective tooth)
            const current = [];
            const push = (name, tooth) => { if (name) current.push({ name: String(name).trim(), tooth }); };
            this.visitItems.forEach(i => push(i.treatment_name, i.tooth_number || this.form.tooth_number));
            this.addonItems.forEach(a => push(a.treatment_name, a.tooth_number || this.form.tooth_number));
            if (this.form.treatment_name) push(this.form.treatment_name, this.form.tooth_number);

            const found = {}; // key "name|tooth" -> warning (most recent date wins)

            current.forEach(cur => {
                const teeth = this._teethSet(cur.tooth);
                if (teeth.length === 0) return;
                const curName = cur.name.toLowerCase();

                this.visits.forEach(v => {
                    if (this.editingVisit && v.id === this.editingVisit.id) return;

                    // Past records to compare against: each visit item, plus the
                    // visit-level treatment (older visits may have no line items).
                    const past = [];
                    (v.visit_items || []).forEach(pi => past.push({ name: pi.treatment_name, tooth: pi.tooth_number, id: pi.id }));
                    if (v.treatment_name) past.push({ name: v.treatment_name, tooth: v.tooth_number, id: null });

                    past.forEach(p => {
                        if (!p.name || String(p.name).toLowerCase() !== curName) return;
                        const overlap = this._teethSet(p.tooth).filter(t => teeth.includes(t));
                        overlap.forEach(t => {
                            const key = curName + '|' + t;
                            const existing = found[key];
                            if (!existing || (v.visit_date && v.visit_date > existing.date)) {
                                found[key] = {
                                    treatment_name: cur.name,
                                    tooth: t,
                                    date: v.visit_date || '',
                                    originalItemId: p.id,
                                };
                            }
                        });
                    });
                });
            });

            this.repeatWarnings = Object.values(found);
            // Clear a stale reason if there are no longer any repeats
            if (this.repeatWarnings.length === 0) this.repeatReason = '';
        },

        // Pretty date for the warning banner (YYYY-MM-DD -> DD Mon YYYY)
        _fmtDate(d) {
            if (!d) return 'a previous visit';
            const dt = new Date(d + 'T00:00:00');
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        // Timeline card date-badge split — consumed by the visit cards in
        // treatment-visits-tab.blade.php (fmtMonth/fmtDay/fmtYear render the
        // 3-line date stamp; fmtFull renders the "Next: …" line). These were
        // referenced by the markup but never defined — closure-sprint fix.
        fmtMonth(d) {
            if (!d) return '';
            const dt = new Date(d + 'T00:00:00');
            if (isNaN(dt)) return '';
            return dt.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase();
        },
        fmtDay(d) {
            if (!d) return '';
            const dt = new Date(d + 'T00:00:00');
            if (isNaN(dt)) return '';
            return String(dt.getDate()).padStart(2, '0');
        },
        fmtYear(d) {
            if (!d) return '';
            const dt = new Date(d + 'T00:00:00');
            if (isNaN(dt)) return '';
            return String(dt.getFullYear());
        },
        fmtFull(d) {
            return this._fmtDate(d);
        },

        // Timeline badge classes — visit_type / status pill colours.
        // Purple = active work, blue = scheduled/followup, amber = in-chair/
        // emergency, green = completed, red/grey = cancelled/no-show —
        // matches the colour system used across the rest of the page.
        typeBadge(type) {
            const map = {
                treatment: 'bg-purple-50 text-[#6a0f70] border border-purple-100',
                followup:  'bg-blue-50 text-blue-700 border border-blue-100',
                emergency: 'bg-amber-50 text-amber-700 border border-amber-200',
                recall:    'bg-gray-100 text-gray-600 border border-gray-200',
            };
            return map[type] || 'bg-gray-100 text-gray-600 border border-gray-200';
        },
        statusBadge(status) {
            const map = {
                completed: 'bg-green-100 text-green-700',
                in_chair:  'bg-amber-100 text-amber-700',
                scheduled: 'bg-blue-100 text-blue-700',
                cancelled: 'bg-gray-200 text-gray-500 line-through',
                no_show:   'bg-red-100 text-red-700',
            };
            return map[status] || 'bg-gray-100 text-gray-600';
        },

        _syncTeethFromString() {
            // Parse comma/space separated tooth numbers into selectedTeeth array
            if (!this.form.tooth_number) { this.selectedTeeth = []; return; }
            this.selectedTeeth = this.form.tooth_number.split(/[,\s]+/)
                .map(s => parseInt(s.trim()))
                .filter(n => !isNaN(n));
        },

        toggleStage(key) {
            const idx = this.form.completed_stages.indexOf(key);
            if (idx >= 0) {
                this.form.completed_stages.splice(idx, 1);
                this.form.current_stage = key;
            } else {
                this.form.completed_stages.push(key);
                this.form.current_stage = key;
                const stageKeys = Object.keys(this.currentStages);
                const lastDone = stageKeys.filter(k => this.form.completed_stages.includes(k)).pop();
                const nextIdx  = stageKeys.indexOf(lastDone) + 1;
                if (nextIdx < stageKeys.length) this.form.current_stage = stageKeys[nextIdx];
            }
        },

        syncCanalRows() {
            const n = parseInt(this.form.rct_num_canals) || 0;
            const names = ['MB','DB','P','MB2','DL'];
            this.form.rct_canal_lengths = Array.from({length: n}, (_, i) => ({
                name: this.form.rct_canal_lengths[i]?.name || names[i] || 'C'+(i+1),
                length: this.form.rct_canal_lengths[i]?.length || '',
            }));
        },

        toggleQuadrant(q) {
            const parts = this.form.scale_quadrants ? this.form.scale_quadrants.split(',').map(s=>s.trim()).filter(Boolean) : [];
            if (q === 'Full Mouth') { this.form.scale_quadrants = parts.includes('Full Mouth') ? '' : 'Full Mouth'; return; }
            const idx = parts.indexOf(q);
            idx >= 0 ? parts.splice(idx,1) : parts.push(q);
            this.form.scale_quadrants = parts.join(',');
        },

        quadrantSelected(q) {
            if (!this.form.scale_quadrants) return false;
            return this.form.scale_quadrants.split(',').map(s=>s.trim()).includes(q);
        },

        // UX-06 (Freeze Spec): the embedded per-visit Rx entry UI was removed in
        // an earlier slice; its orphaned helpers (rxOpen/rxAddDrug/rxCalcTotal/
        // rxToggleInstr) are now deleted too. Prescriptions are written ONLY
        // through the shared prescription-panel Blade component (Consultation,
        // Prescriptions tab, and the per-visit "Write Prescription" link below).
        // NB: never spell that component as a literal tag in this file — Blade
        // compiles component tags even inside JS comments.

        // ── F2: Visit items + plan item helpers ──────────────────────────────

        onPlanChange() {
            // Reset layer-2 selection and plan items; reload
            this.form.plan_item_id = '';
            this.form.treatment_name = '';
            this.form.completed_stages = [];
            this.form.current_stage = '';
            this.visitItems = [];
            this.planItems  = [];
            this.otherActive = false;
            if (this.form.treatment_plan_id) this.loadPlanItems();
        },

        isPlanItemSelected(planItemId) {
            return this.visitItems.some(i => i.treatment_plan_item_id == planItemId);
        },

        // ── Slice 2.4b — today's clinical work ──────────────────────────────
        // Three words, no enums, no lifecycle. "Completed Today" means this
        // treatment finished at THIS visit — it does not close the plan.
        workOutcomes: @js(\App\Models\TreatmentVisitItem::WORK_OUTCOMES),

        workOutcomeFor(pi) {
            const row = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
            return row ? (row.work_outcome || null) : null;
        },

        setWorkOutcome(pi, key) {
            const row = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
            if (!row) return;
            row.work_outcome = (row.work_outcome === key) ? null : key;   // click again to clear
        },

        isPlanItemPrimary(planItemId) {
            return this.form.plan_item_id == planItemId;
        },

        // Layer-2: toggle a plan item on/off as one of today's billing lines.
        // Multiple items may be selected at once — the first one selected
        // automatically becomes "primary" (drives the stage-tracker + smart
        // clinical fields below); staff can re-pick primary via the star icon.
        togglePlanItem(pi) {
            const idx = this.visitItems.findIndex(i => i.treatment_plan_item_id == pi.id);
            if (idx >= 0) {
                const wasPrimary = this.isPlanItemPrimary(pi.id);
                this.visitItems.splice(idx, 1);
                if (wasPrimary) this._promoteNextPrimary();
            } else {
                this.visitItems.push({
                    treatment_plan_item_id: pi.id,
                    work_outcome:    null,   // Slice 2.4b — set by the dentist, never guessed
                    treatment_name:  pi.treatment_name,
                    material_option: '',
                    tooth_number:    pi.tooth_number || '',
                    suggested_price: pi.unit_price   || '',
                    notes:           '',
                });
                if (!this.form.plan_item_id) this.setPrimaryPlanItem(pi);
            }
            this._checkRepeatWork();
        },

        // After the primary item is removed, hand primary to whatever billing
        // line is left (another plan item, or the "other" typeahead pick).
        // Clears primary entirely when nothing is left selected.
        _promoteNextPrimary() {
            const next = this.visitItems.find(i => i.treatment_plan_item_id) || this.visitItems.find(i => i._isOther);
            if (!next) {
                this.form.plan_item_id   = '';
                this.form.treatment_name = '';
                this.onTreatmentChange();
                return;
            }
            if (next.treatment_plan_item_id) {
                this.setPrimaryPlanItem({ id: next.treatment_plan_item_id, treatment_name: next.treatment_name });
            } else {
                this.form.plan_item_id   = '__other__';
                this.form.treatment_name = next.treatment_name;
                this.form.tooth_number   = next.tooth_number || '';
                this._syncTeethFromString();
                this.onTreatmentChange();
            }
        },

        // Mark a selected plan item as the visit's primary treatment — the one
        // whose stage-tracker, RCT/Implant/Crown fields and lab-case prompt show.
        setPrimaryPlanItem(pi) {
            const item = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
            this.form.plan_item_id   = pi.id;
            this.form.treatment_name = pi.treatment_name;
            this.form.tooth_number   = item ? (item.tooth_number || '') : '';
            this._syncTeethFromString();
            this.onTreatmentChange();
        },

        // Teeth listed on a plan item itself (e.g. "42, 46" -> ['42','46'])
        planItemTeeth(pi) {
            return this._teethSet(pi.tooth_number);
        },

        itemToothSelected(pi, tooth) {
            const item = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
            if (!item) return false;
            return this._teethSet(item.tooth_number).includes(tooth);
        },

        // Narrow a multi-tooth plan item down to just the tooth/teeth actually
        // done today — keeps the billing line's tooth_number in sync, and if
        // it's the primary item, keeps the visit-level Tooth No. in sync too.
        toggleItemTooth(pi, tooth) {
            const item = this.visitItems.find(i => i.treatment_plan_item_id == pi.id);
            if (!item) return;
            const order = this.planItemTeeth(pi);
            let teeth = this._teethSet(item.tooth_number);
            const idx = teeth.indexOf(tooth);
            if (idx >= 0) teeth.splice(idx, 1); else teeth.push(tooth);
            item.tooth_number = order.filter(t => teeth.includes(t)).join(', ');
            if (this.isPlanItemPrimary(pi.id)) {
                this.form.tooth_number = item.tooth_number;
                this._syncTeethFromString();
            }
            this._checkRepeatWork();
        },

        // Layer-2 fallback: a treatment not listed in the accepted plan.
        toggleOtherTreatment() {
            this.otherActive = !this.otherActive;
            if (!this.otherActive) {
                this.txSearch = ''; this.txSuggestions = []; this.txSuggestOpen = false;
                const wasPrimary = this.form.plan_item_id === '__other__';
                this.visitItems = this.visitItems.filter(i => i._isOther !== true);
                if (wasPrimary) this._promoteNextPrimary();
            }
        },

        // Layer-3: add an add-on procedure row
        addCustomItem() {
            this.addonItems.push({
                treatment_name: '',
                tooth_number:   '',
            });
        },

        // Add a blank custom line — starts EXPANDED (Delta 1): a blank row
        // has nothing to show collapsed. Plan-picked items start collapsed
        // because they arrive complete.
        addBillingItem() {
            this.visitItems.push({
                treatment_plan_item_id: null,
                treatment_name:  '',
                material_option: '',
                tooth_number:    '',
                suggested_price: '',
                notes:           '',
                _open:           true,
            });
        },

        openAddForm(prefill = {}) {
            this.editingVisit = null;
            this.form = this._blank();
            this.visitItems    = [];
            this.addonItems    = [];
            this.planItems     = [];
            this.otherActive   = false;
            this.selectedTeeth = [];
            this.toothChartOpen = false;
            this.errorMsg = '';
            this.repeatWarnings = []; this.repeatReason = '';
            this.txSearch = ''; this.txSuggestions = []; this.txSuggestOpen = false;
            this.labCase = { enabled: false, lab_vendor_id: '', work_category: '', work_subtype: '', priority: 'routine', expected_return_date: '', instructions: '' };
            this.nextActions = [];
            this.metaOpen = false; this.drawerOpen = false; this.vitalsOpen = false;
            this.loadTreatmentPlans();   // refresh plan list so new/accepted plans show without reload

            // UX-05 (Freeze Spec) — prefilled handoff from the consultation gate.
            // Appointment link auto-fills date + doctor via onAppointmentChange().
            // Plan is prefilled ONLY when the caller resolved exactly one accepted
            // plan — never guess between multiple (frozen guardrail).
            if (prefill.appointment_id) {
                this.form.appointment_id = String(prefill.appointment_id);
                this.onAppointmentChange();
            }
            if (prefill.treatment_plan_id) {
                this.form.treatment_plan_id = String(prefill.treatment_plan_id);
                this.onPlanChange();
            }

            this.formOpen = true;
        },

        openEditForm(visit) {
            this.editingVisit = visit;
            this.form = {
                appointment_id:    visit.appointment_id || '',
                visit_date:        visit.visit_date || '',
                visit_type:        visit.visit_type || 'treatment',
                status:            visit.status || 'scheduled',
                doctor_id:         visit.doctor_id || '',
                treatment_plan_id: visit.treatment_plan_id || '',
                plan_item_id:      '',  // restored below after planItems load
                treatment_name:    visit.treatment_name || '',
                current_stage:     visit.current_stage || '',
                completed_stages:  [...(visit.completed_stages||[])],
                tooth_number:      visit.tooth_number || '',
                notes:             visit.notes || '',
                chief_complaint:   visit.chief_complaint || '',
                next_visit_date:   visit.next_visit_date || '',
                next_visit_type:   visit.next_visit_type || '',
                rct_num_canals:        visit.rct_num_canals || '',
                rct_canal_lengths:     [...(visit.rct_canal_lengths||[])],
                rct_file_type:         visit.rct_file_type || '',
                rct_irrigant:          visit.rct_irrigant || '',
                rct_obturation_method: visit.rct_obturation_method || '',
                impl_brand:            visit.impl_brand || '',
                impl_size:             visit.impl_size || '',
                impl_torque:           visit.impl_torque || '',
                impl_graft_used:       visit.impl_graft_used || '',
                impl_graft_brand:      visit.impl_graft_brand || '',
                impl_membrane:         visit.impl_membrane || '',
                impl_healing_collar:   visit.impl_healing_collar || '',
                implant_fixture_catalog_id: visit.implant_fixture_catalog_id || '',
                implant_components_used:    [...(visit.implant_components_used || [])],
                implant_lot_number:         visit.implant_lot_number || '',
                fill_material:         visit.fill_material || '',
                fill_shade:            visit.fill_shade || '',
                scale_quadrants:       visit.scale_quadrants || '',
                scale_method:          visit.scale_method || '',
                ext_type:              visit.ext_type || '',
                ext_socket:            visit.ext_socket || '',
                ext_suture:            visit.ext_suture || false,
                crown_type:            visit.crown_type || '',
                crown_shade:           visit.crown_shade || '',
                crown_impression:      visit.crown_impression || false,
                crown_temp_placed:     visit.crown_temp_placed || '',
                prescription_drugs:        [...(visit.prescription_drugs||[])],
                prescription_instructions: [...(visit.prescription_instructions||[])],
                prescription_custom_notes: visit.prescription_custom_notes || '',
                // Vitals
                bp_systolic:       visit.bp_systolic ?? '',
                bp_diastolic:      visit.bp_diastolic ?? '',
                pulse_rate:        visit.pulse_rate ?? '',
                spo2:              visit.spo2 ?? '',
                temperature:       visit.temperature ?? '',
                blood_sugar:       visit.blood_sugar ?? '',
                blood_sugar_type:  visit.blood_sugar_type || '',
                weight:            visit.weight ?? '',
                vitals_notes:      visit.vitals_notes || '',
            };
            // Sync typeahead with existing treatment name
            this.txSearch = visit.treatment_name || '';
            this.txSuggestions = []; this.txSuggestOpen = false;
            // populate visit items, addon items, plan items, and tooth chart state
            this.visitItems = (visit.visit_items || []).map(i => ({...i}));
            this.addonItems = [];
            this.planItems  = [];
            // Restore which billing line was the visit's primary treatment, matched
            // by name against visit.treatment_name (the field that drove this visit's
            // stage-tracker / clinical fields when it was saved).
            const primaryItem = this.visitItems.find(i =>
                (i.treatment_name || '').trim().toLowerCase() === (visit.treatment_name || '').trim().toLowerCase()
            );
            if (primaryItem && primaryItem.treatment_plan_item_id) {
                this.form.plan_item_id = primaryItem.treatment_plan_item_id;
                this.otherActive = false;
            } else if (primaryItem) {
                primaryItem._isOther = true;
                this.form.plan_item_id = '__other__';
                this.otherActive = true;
            } else {
                this.form.plan_item_id = '';
                this.otherActive = false;
            }
            if (this.form.treatment_plan_id) this.loadPlanItems();
            this._syncTeethFromString();
            this.toothChartOpen = false;
            this.errorMsg = '';
            // Reset lab case (no editing of existing lab cases from here — use Lab module)
            this.labCase = { enabled: false, lab_vendor_id: '', work_category: '', work_subtype: '', priority: 'routine', expected_return_date: '', instructions: '' };
            // Visit → Next Action — re-populate the PENDING actions this visit
            // already scheduled, ids included. That id round-trip is what makes
            // a re-save reconcile the same follow_ups rows instead of creating
            // duplicate calls for the front desk (CEO Test Case 3). Actions the
            // call team already completed are not returned by the bootstrap and
            // so can never be edited away from here.
            this.nextActions = (visit.next_actions || []).map(a => ({
                _uid:        ++this._nextActionUid,
                id:          a.id,
                action_type: a.action_type || 'wellness_call',
                due_mode:    'on_date',
                due_in_days: 3,
                due_date:    a.due_date || '',
                instruction: a.instruction || '',
            }));
            // Redesign: auto-open the drawer when the visit already carries a
            // note or vitals, so existing content is never hidden on edit.
            this.drawerOpen = !!(visit.notes || visit.vitals_notes || visit.bp_systolic || visit.pulse_rate || visit.temperature || visit.weight);
            this.vitalsOpen = !!(visit.bp_systolic || visit.pulse_rate || visit.temperature || visit.weight || visit.vitals_notes);
            this.metaOpen = false;
            this.loadTreatmentPlans();   // refresh plan list so new/accepted plans show without reload
            this.formOpen = true;
        },

        closeForm() {
            this.formOpen = false;
            this.editingVisit = null;
            this.errorMsg = '';
            this.form = this._blank();
            this.visitItems = [];
            this.addonItems = [];
            this.planItems  = [];
            this.otherActive = false;
            this.selectedTeeth = [];
            this.nextActions = [];
            this.metaOpen = false; this.drawerOpen = false; this.vitalsOpen = false;
            this.repeatWarnings = []; this.repeatReason = '';
            this.txSearch = ''; this.txSuggestions = []; this.txSuggestOpen = false;
        },

        async saveVisit() {
            // Delta 1 — auto-expand any recorded item missing its treatment
            // name so the invalid field is visible, never hidden in a
            // collapsed row.
            let hasUnnamed = false;
            this.visitItems.forEach(i => {
                if (!(i.treatment_name || '').trim()) { i._open = true; hasUnnamed = true; }
            });
            if (hasUnnamed) {
                this.errorMsg = 'Every recorded item needs a treatment name.';
                return;
            }

            // Repeat work flagged but no reason given — block and prompt for it.
            this._checkRepeatWork();
            if (this.repeatWarnings.length > 0 && !this.repeatReason.trim()) {
                this.errorMsg = 'This looks like repeat work. Please add a reason before saving.';
                return;
            }

            this.saving = true;
            this.errorMsg = '';
                    try {
                const patientId = {{ $patient->id }};
                const isEdit    = !!this.editingVisit;
                const url       = isEdit
                    ? `{{ url('/visits') }}/${this.editingVisit.id}`
                    : `{{ url('/patients') }}/${patientId}/visits`;
                const method = isEdit ? 'PUT' : 'POST';

                // Helper: does this (treatment, tooth) match a detected repeat?
                // When editing an existing visit we skip detection, so preserve
                // whatever repeat flags the item already had.
                const reason = this.repeatReason.trim();
                const tagRepeat = (item, name, tooth) => {
                    if (this.editingVisit) {
                        return {
                            is_repeat:               !!item.is_repeat,
                            repeat_reason:           item.repeat_reason ?? null,
                            repeat_of_visit_item_id: item.repeat_of_visit_item_id ?? null,
                        };
                    }
                    const teeth = this._teethSet(tooth || this.form.tooth_number);
                    const w = this.repeatWarnings.find(rw =>
                        rw.treatment_name.toLowerCase() === String(name || '').toLowerCase() &&
                        teeth.includes(rw.tooth)
                    );
                    return w
                        ? { is_repeat: true, repeat_reason: reason, repeat_of_visit_item_id: w.originalItemId }
                        : { is_repeat: false, repeat_reason: null, repeat_of_visit_item_id: null };
                };

                const allVisitItems = [
                    ...this.visitItems.map(i => ({ ...i, ...tagRepeat(i, i.treatment_name, i.tooth_number) })),
                    ...this.addonItems.filter(a => a.treatment_name).map(a => ({
                        treatment_plan_item_id: null,
                        treatment_name:         a.treatment_name,
                        material_option:        '',
                        tooth_number:           a.tooth_number || this.form.tooth_number || '',
                        suggested_price:        '',
                        notes:                  '',
                        ...tagRepeat(a, a.treatment_name, a.tooth_number),
                    })),
                ];

                const payload = {
                    ...this.form,
                    tooth_number: this.form.tooth_number,
                    visit_items:  allVisitItems,
                    lab_case:     this.labCase.enabled ? this.labCase : null,
                    // Visit → Next Action. ALWAYS sent (even empty) so the
                    // server can tell "doctor removed the action" from "this
                    // client doesn't know about next actions" — the service
                    // treats a missing key as leave-alone, an empty array as
                    // clear. Rows with a blank action type are dropped here.
                    next_actions: this.nextActions
                        .filter(a => a.action_type)
                        .map(a => ({
                            id:          a.id || null,
                            action_type: a.action_type,
                            due_mode:    a.due_mode,
                            due_in_days: a.due_mode === 'in_days' ? (parseInt(a.due_in_days) || 1) : null,
                            due_date:    a.due_mode === 'on_date' ? (a.due_date || null) : null,
                            instruction: (a.instruction || '').trim() || null,
                        })),
                };

                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed.');

                if (isEdit) {
                    const idx = this.visits.findIndex(v => v.id === this.editingVisit.id);
                    if (idx >= 0) this.visits.splice(idx, 1, data.visit);
                } else {
                    this.visits.unshift(data.visit);
                }
                this.closeForm();
                // Dedicated-page navigation (08-05, presentation-only, additive):
                // on the modal (tab fragment) this global is never set, so this
                // is a no-op there. On the new visits.create/visits.edit page it
                // is set to the Treatment Visits tab URL — after a successful
                // save, the page-shaped form navigates back to the timeline the
                // same way Consultation's own page already does. Fires only on
                // the success path above; validation/network errors never reach
                // here (they're caught below, form stays open, error banner shows).
                if (window.TV_AFTER_SAVE_REDIRECT) { window.location.href = window.TV_AFTER_SAVE_REDIRECT; return; }
            } catch (e) {
                this.errorMsg = e.message;
            } finally {
                this.saving = false;
            }
        },

        async deleteVisit(id) {
            if (!confirm('Delete this visit?')) return;
            try {
                const resp = await fetch(`{{ url('/visits') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Delete failed.');
                this.visits = this.visits.filter(v => v.id !== id);
            } catch (e) {
                alert(e.message);
            }
        },

        // Re-fetch this patient's treatment plans (accepted only — same rule the
        // server uses to seed the dropdown). Keeps the dropdown fresh so a plan
        // accepted in the Plan tab appears here without reloading the page.
        loadTreatmentPlans() {
            fetch(`{{ url('/patients/'.$patient->id.'/treatment-plans') }}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => r.json())
            .then(d => {
                const plans = (d.plans || []).filter(p => p.is_accepted);
                // Slice 2.4e — canonical progress, not the legacy status column.
                this.treatmentPlans = plans.map(p => ({
                    id: p.id, plan_name: p.plan_name, progress: p.progress,
                }));
            })
            .catch(() => { /* keep the server-seeded list on failure */ });
        },

        loadPlanItems() {
            if (!this.form.treatment_plan_id) { this.planItems = []; return; }
            this.planItemsLoading = true;
            fetch(`{{ url('/treatment-plans') }}/${this.form.treatment_plan_id}/items`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(d => { this.planItems = d.items || []; })
            .catch(() => { this.planItems = []; })
            .finally(() => { this.planItemsLoading = false; });
        },

        fmt(n)    { return Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fmtInt(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 }); },
    };
}
</script>
@endpush
