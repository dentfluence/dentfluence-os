# Dentfluence — P2C Consultation Module Rebuild
## Complete Architecture Document
> **Status:** Pre-implementation. Awaiting approval before any code is written.
> **Date:** 2026-06-06
> **Author:** Claude (Cowork) + Sumit

---

## SECTION 1 — ARCHITECTURE UNDERSTANDING

### What the current module is
The existing `consultations` table and `create.blade.php` is a **flat, monolithic form** — 1,315 lines of Blade, everything serialised into JSON blobs (`clinical_data`, `dbm_checklist`, `chart_data`, etc.). It has two visit types (`emergency`, `routine`), one hardcoded specialty panel (DBM 35-point checklist), hardcoded treatment lists, and treatment plan data embedded inside the consultation record itself.

### What is fundamentally wrong
| Problem | Impact |
|---|---|
| Visit type enum only has `emergency` / `routine` | Blocks all 7 consultation types required |
| Treatment plan embedded inside consultation (`treatment_plan_best`, `treatment_plan_acceptable` columns) | Violates Core Philosophy — consultation should end at diagnosis |
| DBM checklist hardcoded into the Blade — not a specialty module | Cannot extend to orthodontics, perio, etc. without editing core form |
| No rules engine — specialty suggestions are zero | Doctor must know which fields to fill; no AI assist possible |
| No consultation_type — no context switching | Follow-up, recall, emergency all look identical |
| Treatment Advised section (section 9) feeds a treatment plan embedded IN the consultation | Double data — treatment plan module already exists separately |
| Clinical findings are 9 generic dropdowns — hardcoded | Not expandable without core form changes |

### What this rebuild must achieve
The consultation form must become a **context-aware, type-driven, rules-guided workflow engine** where:
- The doctor picks a consultation type first
- The form reshapes itself based on that type
- As the doctor types the chief complaint, the rules engine suggests relevant specialty modules
- The doctor accepts/rejects suggestions — modules load dynamically
- Each loaded module provides structured, specialty-specific findings
- The system auto-drafts HOPI and Findings Summary from structured input
- The consultation **ends at diagnosis** — no treatment plan, no pricing
- A Treatment Plan is created as a separate downstream step

---

## SECTION 2 — CORE PHILOSOPHY (RESTATED AS ARCHITECTURE RULES)

```
Consultation = Workflow Engine        → knows nothing about dentistry
Treatment Module = Knowledge Base     → owns all specialty intelligence
Treatment Plan = Execution Layer      → downstream of consultation
Billing = Financial Layer             → downstream of treatment plan
```

**Rule 1:** No specialty logic hardcoded in consultation Blade or controller.
**Rule 2:** Rules that drive specialty suggestions live in `treatment_knowledge` (new table).
**Rule 3:** Adding TMJ / Sleep Dentistry / Pediatrics = adding rows to `treatment_knowledge`, zero consultation code changes.
**Rule 4:** Consultation saves findings, HOPI, and diagnosis. Nothing else.

---

## SECTION 3 — CONSULTATION TYPE SYSTEM

Each consultation type changes the form's structure, required fields, and default modules loaded.

| Type Key | Label | Core Behaviour |
|---|---|---|
| `new` | New Consultation | Full diagnostic workflow. Rules engine active. All modules available. |
| `followup` | Follow-Up Consultation | Auto-loads previous consultation. Shows previous diagnosis + treatment. Condensed findings. |
| `same_issue` | Same Issue Follow-Up | Auto-loads previous consultation for same complaint. Flags unresolved issue. |
| `recall_6m` | 6 Month Recall | Preventive mode. Caries / restorations / perio / oral cancer screening modules auto-loaded. |
| `emergency` | Emergency Consultation | Minimal workflow. Pain / swelling / trauma fast-track. |
| `minor_visit` | Minor Clinical Visit | Ultra-light. No full workflow. Crown re-cement, suture removal etc. |
| `coha` | Comprehensive Oral Health Assessment | Full structured checklist. Generates printable PDF report. Not a treatment plan. |

---

## SECTION 4 — CONSULTATION WORKFLOW ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP 1: Select Consultation Type                                           │
│  [New] [Follow-Up] [Same Issue] [6M Recall] [Emergency] [Minor] [COHA]    │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
                    ┌───────────▼───────────┐
                    │   Context Loading     │
                    │ • Previous consult    │
                    │ • Previous diagnosis  │
                    │ • Active treatment    │
                    │   plan (if followup)  │
                    └───────────┬───────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 2: Header (Auto-populated — no manual entry)                          │
│  Doctor: [logged-in user]  Reg: [from profile]  Clinic: [settings]         │
│  Date/Time: [now]                                                           │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 3: Chief Complaint (Doctor types what patient says)                   │
│                                                                             │
│  "I want braces because my teeth are crooked"                               │
│                                          ┌──────────────────────────────┐  │
│  ← Doctor's input                        │  CONSULT ASSIST PANEL        │  │
│                                          │  Suggested specialties:      │  │
│                                          │  ✓ Orthodontics              │  │
│                                          │  ○ Smile Design              │  │
│                                          │  Accept / Reject             │  │
│                                          └──────────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │ (on accept)
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 4: Dynamic Specialty Modules Load                                     │
│  (From treatment_knowledge table — NOT hardcoded)                           │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  ORTHODONTIC MODULE                                                  │  │
│  │  Crowding │ Spacing │ Overjet │ Overbite │ Midline │ Skeletal │ ...  │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 5: HOPI Auto-Draft                                                    │
│  System generates narrative from chief complaint + duration + findings      │
│  Doctor edits → Doctor is final authority                                   │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 6: Findings Summary Auto-Draft                                        │
│  Structured findings → narrative. Editable.                                 │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────────┐
│  STEP 7: Diagnosis                                                          │
│  Provisional Diagnosis                                                      │
│  Differential Diagnosis (new — currently not in DB)                        │
│  Final Diagnosis                                                            │
│  ← CONSULTATION ENDS HERE                                                  │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │
              ┌─────────────────▼──────────────────────────┐
              │  "Create Treatment Plan" button             │
              │  Passes: Complaint + Findings + Diagnosis   │
              │  + Specialty tags to Treatment Plan module  │
              │  No duplicate entry required.               │
              └────────────────────────────────────────────┘
```

---

## SECTION 5 — RULES ENGINE ARCHITECTURE

### Flow
```
Chief Complaint text input
        │
        ▼
 KeywordMatcher::scan($text)
        │
        ▼
 treatment_knowledge table
 (keyword → specialty_tag mapping)
        │
        ▼
 Array of matched specialty tags
        │
        ▼
 ConsultAssistPanel renders suggestions
        │
        ▼ (doctor accepts)
 SpecialtyModule::load($tag)
        │
        ▼
 consultation_specialty_modules table
 (module config: fields, options, labels)
        │
        ▼
 Dynamic HTML panel rendered via Alpine/JS
```

### Keyword Matching Examples (stored in DB, not code)

| Keywords | Specialty Tag | Module Loaded |
|---|---|---|
| braces, aligners, crooked, crowding, spacing, overjet | `orthodontics` | Orthodontic Module |
| bleeding gums, gum disease, calculus, scaling | `periodontics` | Periodontic Module |
| tooth pain, sensitivity, hot cold, cavity, decay | `endodontics` | Endodontic Module |
| whiter teeth, staining, smile, cosmetic | `smile_design` | Smile Design Module |
| missing tooth, implant, bridge | `prosthodontics` | Prosthodontic Module |
| jaw pain, clicking jaw, TMJ | `tmj` | TMJ Module |
| child, kids teeth, milk teeth | `pediatric` | Pediatric Module |

---

## SECTION 6 — SPECIALTY MODULES (DYNAMIC)

Each module = a set of structured field definitions, stored as JSON in `consultation_specialty_modules`. The consultation form renders these dynamically. **No hardcoding in Blade.**

### Orthodontic Module
```
Crowding: None / Mild / Moderate / Severe
Spacing: None / Mild / Moderate / Severe
Overjet: Normal / Increased / Reduced / Negative
Overbite: Normal / Deep / Reduced / Open
Midline: Coincident / Shifted (U) / Shifted (L)
Skeletal Pattern: Class I / Class II / Class III
Profile: Straight / Convex / Concave
Facial Symmetry: Symmetric / Asymmetric
Molar Relation: Class I / Class II / Class III
Canine Relation: Class I / Class II / Class III
```

### Periodontic Module
```
BOP (Bleeding on Probing): Absent / Localized / Generalized
Pocket Depth: WNL / 4-5mm / 6mm+ / >7mm
Recession: None / Present (teeth list)
Mobility: None / Grade I / Grade II / Grade III
Furcation: None / Class I / Class II / Class III
Plaque Score: Good / Fair / Moderate / Poor
Calculus: None / Mild / Moderate / Heavy
```

### Smile Design Module
```
Shade: A1 / A2 / A3 / B1 / (custom)
Smile Line: Low / Average / High / Gummy
Buccal Corridor: Narrow / Average / Wide
Gingival Display: None / Mild / Moderate / Excessive
Tooth Proportions: Ideal / Short / Long / Wide
Midline: Coincident / Deviated
Existing Restorations: (multiselect)
```

### Endodontic Module
```
Pain Type: Spontaneous / Stimulated / Dull / Sharp / Throbbing
Thermal Response: Normal / Prolonged / Absent / Hypersensitive
Percussion: Negative / Positive
Palpation: Negative / Positive
Mobility: None / Present
Swelling: None / Present (hard/fluctuant)
Sinus Tract: Absent / Present
Pulp Status: Normal / Reversible Pulpitis / Irreversible Pulpitis / Necrotic
```

### Prosthodontic Module
```
Missing Teeth: (tooth number picker)
Bone Support: Adequate / Reduced
Existing Prosthesis: None / Partial Denture / Complete Denture / Bridge / Implant Crown
Occlusal Support: Adequate / Inadequate
Ridge: Well-formed / Resorbed / Irregular
```

---

## SECTION 7 — COHA (COMPREHENSIVE ORAL HEALTH ASSESSMENT) MODE

COHA is a **separate consultation type** that generates a patient-facing PDF awareness report. It does NOT create a treatment plan — it creates a `ConsultationCohaReport`.

### COHA Sections
1. **Extraoral** — TMJ, Muscles, Lymph nodes, Facial symmetry
2. **Soft Tissue** — Lips, Buccal mucosa, Tongue, Floor of mouth, Hard palate, Soft palate, Salivary glands, Oral cancer screening
3. **Tooth Assessment** — Per-tooth status (Missing / Decayed / Fractured / Root stump / Crown / Bridge / Implant / Denture / Wear / Restoration)
4. **Orthodontic Findings** — Crowding / Spacing / Midline / Overjet / Overbite / Crossbite
5. **Periodontal Findings** — BOP / Pocketing / Recession / Mobility / Calculus
6. **Esthetic Findings** — Shade / Discoloration / Smile line / Tooth proportions
7. **Risk Assessment** — Caries Risk / Perio Risk / Bruxism Risk / Oral Cancer Risk (Low/Med/High each)
8. **Monitoring Teeth** — Specific tooth numbers flagged for next visit
9. **Treatment Awareness** — Fillings / RCT / Crowns / Extractions / Implants / Ortho / Perio / Cosmetic (explained in plain language)

COHA prints as a **branded PDF** — not a treatment estimate — and is handed to the patient.

---

## SECTION 8 — DATABASE CHANGES REQUIRED

### 8A — `consultations` table — ALTER (Migration)

**Add columns:**
```sql
consultation_type ENUM(
  'new','followup','same_issue','recall_6m',
  'emergency','minor_visit','coha'
) NOT NULL DEFAULT 'new'

-- HOPI
hopi_auto TEXT NULL          -- system-generated draft
hopi_final TEXT NULL         -- doctor-edited final

-- Findings summary
findings_summary_auto TEXT NULL
findings_summary_final TEXT NULL

-- Structured specialty findings (JSON per loaded module)
specialty_findings JSON NULL
-- Example: {"orthodontics": {"crowding":"moderate","overjet":"increased"}, "periodontics": {...}}

-- Accepted specialty tags from rules engine
accepted_specialties JSON NULL
-- Example: ["orthodontics", "periodontics"]

-- Diagnosis (expand from 2 to 3)
provisional_diagnosis TEXT NULL
differential_diagnosis TEXT NULL
-- (primary_diagnosis = final diagnosis, already exists)

-- Previous consultation link (for followup/same_issue types)
previous_consultation_id BIGINT UNSIGNED NULL FK consultations.id

-- COHA report ID (for COHA type)
coha_report_id BIGINT UNSIGNED NULL FK consultation_coha_reports.id
```

**Remove from consultations (move to TreatmentPlan):**
```
treatment_plan_best         -- already in treatment_plans table
treatment_plan_best_total   -- already in treatment_plans table
treatment_plan_acceptable   -- already in treatment_plans table
treatment_plan_acc_total    -- already in treatment_plans table
aocp_best                   -- belongs in treatment plan
aocp_best_plan              -- belongs in treatment plan
aocp_acceptable             -- belongs in treatment plan
aocp_acceptable_plan        -- belongs in treatment plan
tx_emergency                -- this is treatment plan data
tx_protective               -- this is treatment plan data
tx_transformative           -- this is treatment plan data
```
> ⚠️ Do NOT drop these columns immediately — migrate data first, then drop in a later phase.

**Update visit_type enum → consultation_type enum:**
The existing `visit_type` column maps to `consultation_type`. We keep `visit_type` for backward compat and add `consultation_type` as the new authoritative column.

---

### 8B — `treatment_knowledge` table — NEW

The rules engine brain. Replaces all hardcoded specialty logic.

```sql
CREATE TABLE treatment_knowledge (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  specialty_tag    VARCHAR(50) NOT NULL,        -- 'orthodontics', 'periodontics', etc.
  trigger_keywords JSON NOT NULL,               -- ["braces","aligners","crooked"]
  patient_concerns JSON NULL,                   -- ["cosmetic","functional","pain"]
  suggested_questions JSON NULL,                -- ["Are your teeth crowding?", ...]
  suggested_findings JSON NULL,                 -- fields to look for
  suggested_investigations JSON NULL,           -- ["OPG","CBCT","Photos"]
  possible_diagnoses JSON NULL,                 -- ["Class II Div 1 Malocclusion", ...]
  module_config JSON NULL,                      -- full field definitions for dynamic module
  display_label VARCHAR(100) NOT NULL,          -- "Orthodontics"
  display_icon VARCHAR(50) NULL,                -- icon name
  sort_order TINYINT UNSIGNED DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

---

### 8C — `consultation_specialty_modules` table — NEW

Links a consultation to the specialty modules that were loaded and accepted.

```sql
CREATE TABLE consultation_specialty_modules (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  consultation_id   BIGINT UNSIGNED NOT NULL FK consultations.id,
  specialty_tag     VARCHAR(50) NOT NULL,
  findings          JSON NULL,     -- structured findings entered for this module
  accepted_at       TIMESTAMP NULL,
  rejected_at       TIMESTAMP NULL,
  created_at        TIMESTAMP,
  updated_at        TIMESTAMP
)
```

---

### 8D — `consultation_coha_reports` table — NEW

Stores COHA report data separately (large structured JSON).

```sql
CREATE TABLE consultation_coha_reports (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  consultation_id  BIGINT UNSIGNED NOT NULL FK consultations.id,
  patient_id       BIGINT UNSIGNED NOT NULL FK patients.id,
  doctor_id        BIGINT UNSIGNED NOT NULL FK users.id,
  
  -- Structured sections
  extraoral        JSON NULL,
  soft_tissue      JSON NULL,
  tooth_assessment JSON NULL,   -- per-tooth status keyed by FDI number
  ortho_findings   JSON NULL,
  perio_findings   JSON NULL,
  esthetic_findings JSON NULL,
  risk_assessment  JSON NULL,   -- {caries:'high', perio:'medium', bruxism:'low', oral_cancer:'low'}
  monitoring_teeth JSON NULL,   -- array of tooth numbers
  treatment_awareness JSON NULL,
  
  doctor_notes     TEXT NULL,
  report_date      DATE NULL,
  pdf_path         VARCHAR(255) NULL,   -- generated PDF path
  
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

---

### 8E — `treatments` table — ALTER (Treatment Knowledge Base)

Add intelligence fields to make Treatment the knowledge base.

```sql
-- Add to treatments table
trigger_keywords      JSON NULL,   -- ["braces","aligners","crooked","crowding"]
patient_concerns      JSON NULL,   -- ["cosmetic","alignment","confidence"]
suggested_questions   JSON NULL,   -- Q&A the consult engine can surface
suggested_findings    JSON NULL,   -- Findings to look for
suggested_investigations JSON NULL, -- ["OPG","CBCT","Photos","Study models"]
possible_diagnoses    JSON NULL,   -- ["Class II Div 1","Skeletal Bimaxillary Protrusion"]
specialty_tag         VARCHAR(50) NULL,  -- links to treatment_knowledge.specialty_tag
consent_template      TEXT NULL,   -- future use
patient_instructions  TEXT NULL,   -- post-procedure instructions
treatment_pathways    JSON NULL,   -- future use — sequencing logic
```

---

## SECTION 9 — NEW MODELS REQUIRED

| Model | Table | Purpose |
|---|---|---|
| `TreatmentKnowledge` | `treatment_knowledge` | Rules engine brain — specialty tags, keywords, module configs |
| `ConsultationSpecialtyModule` | `consultation_specialty_modules` | Per-consultation specialty module activation + findings |
| `ConsultationCohaReport` | `consultation_coha_reports` | COHA structured report data |

---

## SECTION 10 — TREATMENT MODULE CHANGES (KNOWLEDGE BASE UPGRADE)

### New Tab: "Intelligence" (between Overview and SOP)
Adds a new tab to the Treatment show page where the clinic can configure:
- **Trigger Keywords** — comma-tag input
- **Patient Concerns** — checkboxes (Cosmetic / Functional / Pain / Preventive)
- **Suggested Questions** — repeater rows
- **Suggested Findings** — repeater rows
- **Suggested Investigations** — checkbox group (IOPA / OPG / CBCT / Photos / Study models / CBCT / Blood tests)
- **Possible Diagnoses** — repeater rows
- **Specialty Tag** — dropdown linking to `treatment_knowledge`

### Existing Tabs — No Change
Overview, SOP, Stages, Rules, Media, Patient Materials, Review, Usage all stay exactly as they are.

---

## SECTION 11 — RISKS & EDGE CASES

| Risk | Mitigation |
|---|---|
| `consultations` table has treatment plan columns — dropping them breaks existing records | Phase: migrate data → add FK linking to TreatmentPlan → drop columns in a LATER migration only after verification |
| Existing `visit_type` enum only has `emergency/routine/followup` | Add `consultation_type` as new column; keep `visit_type` for backward compat; sync both in controller |
| `create.blade.php` is 1,315 lines — full rewrite risk | Do UI phases first; test each section independently; keep old form at `/consultations/legacy/create` during transition |
| Dynamic module loading in Blade/Alpine without page reload | Use Alpine's `x-show` + `fetch()` to load module HTML partials; each specialty module = its own Blade partial |
| Rules engine performance (scanning keywords on every keystroke) | Debounce 600ms; load all keyword→tag mappings as a JS array on page load (tiny — <50 rows); match client-side |
| COHA PDF generation | Use existing PDF skill (fpdf/mPDF); generate on-demand, store path in `consultation_coha_reports.pdf_path` |
| Treatment Plan removal from Consultation form breaks existing doctors' workflow | Transition: add a prominent "Start Treatment Plan →" button that pre-populates the Treatment Plan form with consultation data |
| Previous consultation linking (followup/same_issue types) | Eager-load previous consultation in controller; pass to view; render as read-only context panel |

---

## SECTION 12 — PHASE BREAKDOWN

### Convention
- **P2C** = Phase 2, Consultation module rebuild
- Phases are sequential. Each phase is independently testable before next begins.
- Order: **UI → DB Schema → Controller/Services → Rules Engine → Automation**

---

### P2C1 — Consultation Type System (UI Restructure)
**Goal:** Replace the flat form with a type-aware shell. Zero DB changes. Zero backend changes.

| Sub | Task | Deliverables |
|---|---|---|
| P2C1a | New `consultation/create.blade.php` — strip all treatment plan sections, add Consultation Type selector as Step 1 | New Blade (replaces old one) |
| P2C1b | Type-aware form shell: each type shows/hides sections via Alpine `x-show`. Header auto-population. Previous consultation context panel (followup/same_issue). | Modified Blade |
| P2C1c | Visit type enum → map 7 types through `visit_type` field (no migration yet — store as string in existing column) | Blade + Alpine JS only |

**Test:** Can select all 7 types. Form reshapes. Old data not broken.

---

### P2C2 — DB Schema Foundation
**Goal:** Run all required migrations.

| Sub | Task | Deliverables |
|---|---|---|
| P2C2a | Migration: alter `consultations` — add `consultation_type`, `hopi_auto`, `hopi_final`, `findings_summary_auto`, `findings_summary_final`, `specialty_findings`, `accepted_specialties`, `provisional_diagnosis`, `differential_diagnosis`, `previous_consultation_id` | Migration file |
| P2C2b | Migration: create `treatment_knowledge` table | Migration file |
| P2C2c | Migration: create `consultation_specialty_modules` table | Migration file |
| P2C2d | Migration: create `consultation_coha_reports` table | Migration file |
| P2C2e | Migration: alter `treatments` — add intelligence fields | Migration file |
| P2C2f | Update `Consultation` model — add new fillable/casts/relationships | Model file |
| P2C2g | Create `TreatmentKnowledge`, `ConsultationSpecialtyModule`, `ConsultationCohaReport` models | 3 model files |

**Test:** `php artisan migrate` runs clean. `php artisan tinker` can create records.

---

### P2C3 — Consult Assist Panel (Rules Engine - Phase 1)
**Goal:** Keyword → specialty suggestion working. No module loading yet.

| Sub | Task | Deliverables |
|---|---|---|
| P2C3a | Seed `treatment_knowledge` with 7 specialties + keywords | Seeder: `TreatmentKnowledgeSeeder` |
| P2C3b | `ConsultAssistController@suggest` — AJAX endpoint that accepts `text`, returns matched specialties from `treatment_knowledge` | Controller + route |
| P2C3c | Consult Assist panel UI (right sidebar) — debounced input watcher → fetch → render suggestions with Accept/Reject | Blade partial + Alpine JS |

**Test:** Type "I want braces" → Orthodontics suggestion appears. Type "bleeding gums" → Periodontics. Accept → tag saved to `accepted_specialties` JSON.

---

### P2C4 — Dynamic Specialty Modules
**Goal:** Accepted specialty → structured findings panel loads dynamically.

| Sub | Task | Deliverables |
|---|---|---|
| P2C4a | Create Blade partials for each specialty module: `_module-orthodontics.blade.php`, `_module-periodontics.blade.php`, `_module-smile-design.blade.php`, `_module-endodontics.blade.php`, `_module-prosthodontics.blade.php` | 5 Blade partials |
| P2C4b | `ConsultAssistController@loadModule` — returns module HTML partial for a given specialty tag | Controller method + route |
| P2C4c | Alpine: on accept → fetch module partial → inject into `#specialty-modules-container`. On reject → remove. | JS in create.blade |
| P2C4d | Save module findings: each module's inputs map to `consultation_specialty_modules.findings` JSON | Controller store/update |

**Test:** Accept Orthodontics → crowding/spacing/overjet fields appear. Fill them. Save. Check `consultation_specialty_modules` table.

---

### P2C5 — HOPI & Findings Summary Auto-Draft
**Goal:** Structured data → auto-drafted narrative. Doctor edits before saving.

| Sub | Task | Deliverables |
|---|---|---|
| P2C5a | `HopiDraftService::generate(Consultation $c)` — rules-based narrative builder from chief complaint + duration + severity + specialty findings | Service class |
| P2C5b | `FindingsSummaryService::generate(Consultation $c)` — converts structured module findings to readable paragraph | Service class |
| P2C5c | UI: HOPI section with auto/manual toggle. "Regenerate" button. Doctor-editable textarea overlaid on auto-draft. | Blade section |

**Test:** Fill chief complaint "bleeding gums for 3 months" + periodontic findings → click Regenerate → readable HOPI appears. Doctor edits it. Saves. DB stores `hopi_final`.

---

### P2C6 — Diagnosis Section Rebuild
**Goal:** 3-stage diagnosis with suggested diagnoses from rules engine.

| Sub | Task | Deliverables |
|---|---|---|
| P2C6a | Diagnosis section: Provisional → Differential → Final. Suggestions sourced from `treatment_knowledge.possible_diagnoses` for accepted specialties. | Blade section |
| P2C6b | Controller: save `provisional_diagnosis`, `differential_diagnosis`, `primary_diagnosis` (final) | Controller update |

**Test:** Accept Orthodontics → diagnosis suggestions show "Class II Div 1 Malocclusion", "Bimaxillary Protrusion". Select one. Saves to correct column.

---

### P2C7 — COHA Mode
**Goal:** Complete COHA consultation type with structured checklist and PDF output.

| Sub | Task | Deliverables |
|---|---|---|
| P2C7a | COHA-specific Blade view — all 9 sections (extraoral, soft tissue, tooth assessment, ortho, perio, esthetic, risk, monitoring, awareness) | `consultations/coha.blade.php` |
| P2C7b | `ConsultationCohaReport` store/update in controller | Controller |
| P2C7c | COHA PDF generation — patient-facing branded PDF report | `consultations/coha-print.blade.php` + PDF route |

**Test:** Select COHA type → see COHA form. Fill all sections. Save. View PDF — clean, printable, patient-appropriate.

---

### P2C8 — Treatment Module Intelligence Tab
**Goal:** Treatment module becomes the knowledge base.

| Sub | Task | Deliverables |
|---|---|---|
| P2C8a | Add "Intelligence" tab to treatment show page (between Overview and SOP) | `treatments/show.blade.php` update |
| P2C8b | `TreatmentController@saveIntelligence` — saves keyword/concern/question/finding/investigation/diagnosis fields | Controller + route |
| P2C8c | Blade partial for Intelligence tab: keyword tag input, concern checkboxes, repeater rows for questions/findings/diagnoses | `treatments/partials/_intelligence.blade.php` |

**Test:** Open Orthodontics treatment → Intelligence tab → add "braces, aligners" keywords → save → consult assist now matches.

---

### P2C9 — Follow-Up & Same Issue Context Loading
**Goal:** Follow-up types auto-load previous consultation as read-only context.

| Sub | Task | Deliverables |
|---|---|---|
| P2C9a | Controller: for followup/same_issue types, load most recent consultation (or same-complaint consultation) and pass to view | Controller update |
| P2C9b | Blade: "Previous Consultation" context panel — read-only, collapsible, shows previous diagnosis + treatment done + notes | Blade partial |
| P2C9c | `previous_consultation_id` saved on new consultation | Controller + model |

**Test:** Create Follow-Up for a patient who has a previous consultation → previous consultation context panel loads automatically.

---

### P2C10 — Consultation → Treatment Plan Handoff
**Goal:** Consultation ends at diagnosis. "Start Treatment Plan" button passes context forward.

| Sub | Task | Deliverables |
|---|---|---|
| P2C10a | Remove Treatment Advised (Section 9) and embedded treatment plan (Section 10) from consultation form | Blade update |
| P2C10b | Add "Start Treatment Plan →" button at end of consultation form | Blade update |
| P2C10c | `TreatmentPlanController@createFromConsultation` — pre-populates plan form with: patient, doctor, complaint, diagnosis, specialty tags | Controller + route |

**Test:** Complete a consultation. Click "Start Treatment Plan". Treatment Plan form opens pre-filled. No data re-entry needed.

---

### P2C11 — Verification & Migration Cleanup
**Goal:** Data integrity, legacy column removal, full smoke test.

| Sub | Task | Deliverables |
|---|---|---|
| P2C11a | Verify all existing consultations display correctly in new `show.blade.php` | Manual audit |
| P2C11b | Migration: drop legacy treatment plan columns from `consultations` (after confirming all data migrated) | Migration file |
| P2C11c | Update `DEVLOG.md` with full P2C completion | DevLog update |
| P2C11d | Update `Consultation` model fillable — remove dropped columns | Model update |

---

## SECTION 13 — DEVELOPMENT SEQUENCE SUMMARY

```
P2C1  → UI Shell (type selector, form reshape)            NO migrations
P2C2  → DB Schema (all migrations)                        RUN: php artisan migrate
P2C3  → Rules Engine (keyword → suggestions)              SEED: TreatmentKnowledgeSeeder
P2C4  → Dynamic Modules (specialty findings panels)
P2C5  → HOPI + Findings Summary auto-draft
P2C6  → Diagnosis section rebuild
P2C7  → COHA mode + PDF
P2C8  → Treatment Intelligence Tab
P2C9  → Follow-up context loading
P2C10 → Consultation → Treatment Plan handoff
P2C11 → Verification + cleanup
```

Each phase can be demoed and approved before the next begins.

---

## SECTION 14 — DEVLOG UPDATE (to be added to DEVLOG.md)

```markdown
---

## 🏥 P2C — Consultation Module Complete Rebuild

**Philosophy:** Consultation = Engine. Treatment = Knowledge Base. Plan = Execution. Billing = Financial.

### Architecture Decisions
- Consultation type system (7 types) replaces flat emergency/routine enum
- Rules engine: keyword matching against `treatment_knowledge` table (DB-driven, not hardcoded)
- Specialty modules are dynamic Blade partials loaded via AJAX on acceptance
- HOPI and Findings Summary auto-drafted from structured input (rules-based)
- Consultation ends at diagnosis — treatment plan is a separate downstream module
- COHA mode = separate type with dedicated checklist + printable PDF

### New Tables
- `treatment_knowledge` — specialty tag → keywords → module config
- `consultation_specialty_modules` — per-consultation module activations + findings
- `consultation_coha_reports` — COHA structured data

### Migrations Required
Run `php artisan migrate` after P2C2 is complete.
Run `php artisan db:seed --class=TreatmentKnowledgeSeeder` after P2C3a.

### Phase Status
| Phase | Description | Status |
|---|---|---|
| P2C1 | UI Shell — Consultation type selector | ⏳ Pending |
| P2C1a | New create.blade.php — type selector + form shell | ⏳ Pending |
| P2C1b | Type-aware sections (x-show per type) | ⏳ Pending |
| P2C1c | Visit type enum mapping | ⏳ Pending |
| P2C2 | DB Schema — all migrations | ⏳ Pending |
| P2C2a | Alter consultations table | ⏳ Pending |
| P2C2b | Create treatment_knowledge table | ⏳ Pending |
| P2C2c | Create consultation_specialty_modules table | ⏳ Pending |
| P2C2d | Create consultation_coha_reports table | ⏳ Pending |
| P2C2e | Alter treatments table (intelligence fields) | ⏳ Pending |
| P2C2f | Update Consultation model | ⏳ Pending |
| P2C2g | New models (3) | ⏳ Pending |
| P2C3 | Rules Engine — keyword suggestions | ⏳ Pending |
| P2C3a | TreatmentKnowledgeSeeder | ⏳ Pending |
| P2C3b | ConsultAssistController@suggest AJAX | ⏳ Pending |
| P2C3c | Consult Assist panel UI | ⏳ Pending |
| P2C4 | Dynamic Specialty Modules | ⏳ Pending |
| P2C4a | 5 specialty module Blade partials | ⏳ Pending |
| P2C4b | loadModule AJAX endpoint | ⏳ Pending |
| P2C4c | Alpine module injection | ⏳ Pending |
| P2C4d | Module findings save | ⏳ Pending |
| P2C5 | HOPI + Findings Summary | ⏳ Pending |
| P2C6 | Diagnosis section rebuild | ⏳ Pending |
| P2C7 | COHA mode + PDF | ⏳ Pending |
| P2C8 | Treatment Intelligence Tab | ⏳ Pending |
| P2C9 | Follow-up context loading | ⏳ Pending |
| P2C10 | Consultation → Treatment Plan handoff | ⏳ Pending |
| P2C11 | Verification + legacy cleanup | ⏳ Pending |
```

---

## APPENDIX — FILES AFFECTED

### Modified
- `resources/views/consultations/create.blade.php` — complete rewrite
- `resources/views/consultations/show.blade.php` — update for new fields
- `resources/views/consultations/print.blade.php` — update for new fields
- `resources/views/treatments/show.blade.php` — add Intelligence tab
- `app/Models/Consultation.php` — add new fillable/casts/relationships
- `app/Models/Treatment.php` — add intelligence fields
- `app/Http/Controllers/ConsultationController.php` — update store/update methods
- `app/Http/Controllers/TreatmentPlanController.php` — add createFromConsultation
- `app/Http/Requests/StoreConsultationRequest.php` — update validation rules
- `routes/web.php` — new routes

### New Files
- `app/Models/TreatmentKnowledge.php`
- `app/Models/ConsultationSpecialtyModule.php`
- `app/Models/ConsultationCohaReport.php`
- `app/Http/Controllers/ConsultAssistController.php`
- `app/Services/HopiDraftService.php`
- `app/Services/FindingsSummaryService.php`
- `database/seeders/TreatmentKnowledgeSeeder.php`
- `resources/views/consultations/coha.blade.php`
- `resources/views/consultations/coha-print.blade.php`
- `resources/views/consultations/partials/_module-orthodontics.blade.php`
- `resources/views/consultations/partials/_module-periodontics.blade.php`
- `resources/views/consultations/partials/_module-smile-design.blade.php`
- `resources/views/consultations/partials/_module-endodontics.blade.php`
- `resources/views/consultations/partials/_module-prosthodontics.blade.php`
- `resources/views/consultations/partials/_consult-assist-panel.blade.php`
- `resources/views/consultations/partials/_hopi-section.blade.php`
- `resources/views/consultations/partials/_coha-context-panel.blade.php`
- `resources/views/treatments/partials/_intelligence.blade.php`
- `database/migrations/P2C2a_alter_consultations_table.php`
- `database/migrations/P2C2b_create_treatment_knowledge_table.php`
- `database/migrations/P2C2c_create_consultation_specialty_modules_table.php`
- `database/migrations/P2C2d_create_consultation_coha_reports_table.php`
- `database/migrations/P2C2e_alter_treatments_intelligence_fields.php`

---

*Document version 1.0 — awaiting Sumit's approval before implementation begins.*
