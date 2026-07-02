/**
 * consultation.js
 * Alpine.js component for the 12-section Dentfluence consultation form.
 *
 * Usage:
 *   <div x-data="consultationForm()">...</div>
 *
 * Export: default export + window.consultationForm for non-module scripts.
 */

function consultationForm(initialData = {}) {
    return {
        // ── Navigation ────────────────────────────────────────────────────────
        currentSection: 1,
        totalSections: 12,

        nextSection() {
            if (this.currentSection < this.totalSections) this.currentSection++;
        },
        prevSection() {
            if (this.currentSection > 1) this.currentSection--;
        },
        goToSection(n) {
            if (n >= 1 && n <= this.totalSections) this.currentSection = n;
        },

        // ── Status ────────────────────────────────────────────────────────────
        isDraft: initialData.status === 'draft' || true,

        saveDraft() {
            this.isDraft = true;
            this.$refs.statusInput.value = 'draft';
            this.$refs.form.submit();
        },
        submitFinal() {
            this.isDraft = false;
            this.$refs.statusInput.value = 'completed';
            this.$refs.form.submit();
        },

        // ── Section 1 — Chief Complaint ───────────────────────────────────────
        chiefComplaint: initialData.chief_complaint || '',
        complaintDuration: initialData.complaint_duration || '',
        severity: initialData.severity || '',
        toothArea: initialData.tooth_area || '',
        location: initialData.location || '',
        complaintNotes: initialData.complaint_notes || '',
        visitType: initialData.visit_type || '',

        // ── Section 2 — Photographs ───────────────────────────────────────────
        photographs: {
            slots: {},          // { slotName: { file, preview } }

            setSlot(name, file) {
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.slots[name] = { file, preview: e.target.result };
                };
                reader.readAsDataURL(file);
            },

            removeSlot(name) {
                delete this.slots[name];
            },

            hasSlot(name) {
                return !!this.slots[name];
            },

            get count() {
                return Object.keys(this.slots).length;
            },
        },

        // ── Section 3 — Scans ─────────────────────────────────────────────────
        scanDate: initialData.scan_date || '',
        scanFiles: initialData.scan_files || [],

        // ── Section 4 — Investigations ────────────────────────────────────────
        investigations: initialData.investigations || [],
        investigationDetails: initialData.investigation_details || [],

        // ── Section 5 — Clinical Findings ─────────────────────────────────────
        clinicalData: initialData.clinical_data || [],

        // ── Section 6 — Tooth Chart ───────────────────────────────────────────
        toothChart: {
            selected: initialData.chart_data || [],

            toggle(tooth) {
                const idx = this.selected.indexOf(tooth);
                if (idx === -1) {
                    this.selected.push(tooth);
                } else {
                    this.selected.splice(idx, 1);
                }
            },

            isSelected(tooth) {
                return this.selected.includes(tooth);
            },

            clear() {
                this.selected = [];
            },
        },

        // ── Section 7 — Radiographic ──────────────────────────────────────────
        radioData: initialData.radio_data || [],

        // ── Section 8 — DBM ───────────────────────────────────────────────────
        dbm: {
            checklist: initialData.dbm_checklist || {},
            score: initialData.dbm_score || 0,
            toothShade: initialData.dbm_tooth_shade || '',
            whitening: initialData.dbm_whitening || '',
            toothMonitored: initialData.dbm_tooth_monitored || '',

            // Each checked item = ~10 points; cap at 100
            computeScore() {
                const checkedCount = Object.values(this.checklist).filter(Boolean).length;
                this.score = Math.min(checkedCount * 10, 100);
            },
        },

        // ── Section 9 — Prescriptions & Instructions ──────────────────────────
        prescriptions: initialData.prescriptions || [],
        instructions: initialData.instructions || [],

        // ── Section 10 — Diagnosis ────────────────────────────────────────────
        primaryDiagnosis: initialData.primary_diagnosis || '',
        secondaryDiagnosis: initialData.secondary_diagnosis || '',
        riskAssessment: initialData.risk_assessment || '',
        diagnosisNotes: initialData.diagnosis_notes || '',

        // ── Section 11 — Treatment Options ────────────────────────────────────
        txEmergency: initialData.tx_emergency || [],
        txProtective: initialData.tx_protective || [],
        txTransformative: initialData.tx_transformative || [],
        txTeeth: initialData.tx_teeth || [],

        // ── Section 12 — Treatment Plans & Finishing ──────────────────────────
        treatmentPlanBest: initialData.treatment_plan_best || [],
        treatmentPlanBestTotal: initialData.treatment_plan_best_total || 0,
        treatmentPlanAcceptable: initialData.treatment_plan_acceptable || [],
        treatmentPlanAccTotal: initialData.treatment_plan_acc_total || 0,
        aocpBest: initialData.aocp_best || false,
        aocpBestPlan: initialData.aocp_best_plan || '',
        aocpAcceptable: initialData.aocp_acceptable || false,
        aocpAcceptablePlan: initialData.aocp_acceptable_plan || '',
        finishingNotes: initialData.finishing_notes || '',
        nextVisitType: initialData.next_visit_type || '',
        nextVisitDate: initialData.next_visit_date || '',
        recallInterval: initialData.recall_interval || '',
        recallCustom: initialData.recall_custom || '',
        responsibleUserId: initialData.responsible_user_id || '',
        attachments: initialData.attachments || [],

        // ── Section Completion Indicators ─────────────────────────────────────
        /**
         * Returns true when the key fields for the given section are filled in.
         * Deliberately lenient — just checks that at least one primary field is set.
         */
        sectionComplete(n) {
            switch (n) {
                case 1:  return !!this.chiefComplaint;
                case 2:  return this.photographs.count > 0;
                case 3:  return !!this.scanDate || this.scanFiles.length > 0;
                case 4:  return this.investigations.length > 0;
                case 5:  return this.clinicalData.length > 0;
                case 6:  return this.toothChart.selected.length > 0;
                case 7:  return this.radioData.length > 0;
                case 8:  return Object.keys(this.dbm.checklist).length > 0;
                case 9:  return this.prescriptions.length > 0 || this.instructions.length > 0;
                case 10: return !!this.primaryDiagnosis;
                case 11: return (
                             this.txEmergency.length > 0 ||
                             this.txProtective.length > 0 ||
                             this.txTransformative.length > 0
                         );
                case 12: return this.treatmentPlanBest.length > 0 || !!this.finishingNotes;
                default: return false;
            }
        },

        // ── Lifecycle ─────────────────────────────────────────────────────────
        init() {
            // Re-compute DBM score whenever checklist changes
            this.$watch('dbm.checklist', () => this.dbm.computeScore());
        },
    };
}

// Register globally for non-module script tags
window.consultationForm = consultationForm;

export default consultationForm;
