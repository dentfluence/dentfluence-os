# Dentfluence UI Consistency Audit — Create / Edit / View / Print

**Date:** 2026-07-31
**Scope:** Structural audit only (sections, field order, labels, grouping, navigation, print fidelity). No visual redesign proposed, per directive.
**Method:** Light full-sweep pass — every module below was read and compared directly against its controller to confirm which views are actually live in routing (several modules turned out to have dead/orphaned Blade files that *look* like part of the CRUD set but aren't wired to anything).

## Executive summary

| # | Module | Verdict | Headline issue |
|---|---|---|---|
| 1 | Patients | **FAIL** | Two competing edit UIs live (routed `edit.blade.php` is unreachable; the real edit is a modal with a different field set). Several editable fields never appear on the profile or print. |
| 2 | Consultations | **FAIL** | The new embedded Prescription section isn't surfaced distinctly on the View (show) screen — doctor has to leave the page to see what they just entered, even though Print already does it correctly. Section numbering has a duplicate and a sub-number. |
| 3 | Treatments (Clinic Treatment Catalog — not patient Treatment Plans) | **FAIL** | Two independent "Create Treatment" UIs (standalone page + index modal) with different field sets feed the same `store()` action; unclear which is actually reachable. Currency label inconsistent. |
| 4 | Prescriptions | **PASS** (minor gaps) | Field order/grouping consistent across create=edit/show/print. One dead `language` field, one label mismatch ("Patient Instructions" vs "General Instructions"). |
| 5 | Billing | **FAIL** | Three different labels for the same three discount lines across form/show/print. Print silently drops the invoice-level discount row that show displays. |
| 6 | Lab | **FAIL** | `priority`/`technician_name` settable only after creation, not at intake. Same fields carry three different names across drawer/show/print. One genuine data-source divergence (`impression_sent_date` vs `sent_date`). |
| 7 | Appointments | **FAIL** | Three parallel booking/edit UIs in production (create page, edit page, index-calendar modal), each with a different field set and even a different visual framework. No print/receipt exists anywhere (flagged N/A, not a fail contributor). |
| 8 | Inventory | **N/A / PASS** | No standalone create/edit pages — Add/Edit Product is one shared modal, which is internally consistent by construction. |
| 9 | HR — Staff | **FAIL (by design)** | Edit exposes ~2.6x the fields Create does (bank details, full compensation breakdown, advance/bonus). Deliberate progressive disclosure, but not field-parallel — flagged for awareness. |
| 10 | HR — Training | **PASS** (minor drift) | Nearly identical fields; Create offers an "Internal Trainer (Staff)" option Edit doesn't expose — worth a follow-up look. |
| 11 | Finance — Wallet Campaigns | **PASS** | No edit by design (campaigns immutable once created). Show is a usage report, not a form mirror — expected. |
| 12 | Communication — B2B | **PASS** (needs deeper look) | No edit by design (append-only contact log). Show's markup pattern wasn't fully parsed in this light pass — recommend a closer read if this module gets prioritized later. |
| 13 | Data Requests | **PASS** | Small, consistent, append-only module. |

---

## 1. Patients — FAIL

**Critical structural finding:** `resources/views/patients/create.blade.php` is not actually the patient-creation form — it's a leftover Consultation form (confirmed via `PatientController::create()`, which now redirects to the real create UI: `partials/add-patient-modal.blade.php`, a 5-tab modal used for **both** create and edit). Meanwhile `resources/views/patients/edit.blade.php` is still routed and renders, but nothing in the UI links to it — the "Edit Patient" button opens the modal instead. **Two structurally different edit forms exist; only one is reachable.**

**Missing fields** (captured in the live modal, never shown on the profile):
- Emergency Contact (name/relationship/number)
- Email
- Pincode (address row drops it)
- Tags shown in a separate block, not grouped with Personal Details

**Extra fields** (appear on print, no writer anywhere):
- Blood Group
- "Medical History" (distinct from the modal's `medical_conditions` array — no field feeds it)

**Order/grouping mismatches:** Modal tabs = Basic Info → Contact → Medical & Dental → Habits → Source & Notes. Dead `edit.blade.php` = Personal Information → Address → Clinical → Source & Referral (no Habits section at all). Print = Patient Profile → Medical History → Recent Consultations (matches neither).

**Label inconsistencies:**
- Name: modal splits Title/First/Middle/Last; dead edit.blade.php has one "Full Name" field.
- Medical Alert: modal has preset chips + custom text (3 distinct fields); dead edit.blade.php collapses to one free-text box.
- Referral: modal has structured referrer search/type/mobile/notes; dead edit.blade.php has one "Referred By" text input.
- "Medical Alerts" (show, plural) vs "Medical Alert" (dead edit, singular) vs no equivalent on print.

**UI inconsistencies:** Two edit surfaces use two different visual systems (Tailwind utilities in edit.blade.php vs. scoped `.df-input`/`.df-label` classes in the modal).

**Print inconsistencies:** Omits Occupation, Area, Dental Conditions, Habits, Source, Referred By, Tags entirely; adds Blood Group/Medical History that don't exist in create/edit.

**Suggested fixes (minimum change):**
1. Remove or redirect the dead `patients.edit` route/view — it's unreachable and diverges from the real modal (mirror the fix already applied to `create()`).
2. Add Emergency Contact, Email, Pincode rows to the profile's Personal Details display.
3. Either wire `medical_history`/`blood_group` into the modal, or drop them from print — they're currently unset dead fields.
4. Rename print's "Medical History" section to reuse the profile's field set/labels for terminology parity.
5. Add Occupation, Area, Source, Referred By, Tags, Habits to print so it's a true subset of the profile, not a separate schema.

---

## 2. Consultations — FAIL

Confirmed `create.blade.php` genuinely serves both Create and Edit (`ConsultationController::edit()` returns the same view).

**Create/Edit section order:** 1 Consultation Type → 2 Chief Complaint → 3 Specialty Modules Zone → 4 HOPI → **4 Tooth Chart (duplicate "4")** → 5 Investigations → **5b Findings Summary** → 6 Diagnosis → **7 Prescription** (new) → unnumbered Same Issue card → Save. Only 2 of ~15 files under `consultations/partials/` are actually included (`investigations`, `prescription`) — the rest (chief-complaint, diagnosis, treatment-plan, treatment-advised, finishing-section, etc.) are orphaned; their content is duplicated inline instead.

**show.blade.php order:** Chief Complaint → Specialty Findings → HOPI → Visit Update → Tooth Chart → Investigations Advised → Findings Summary → Diagnosis → Treatment Rendered & Advice → Clinical Intelligence → **"Previous Prescriptions" accordion (collapsed by default)**.

**print.blade.php order:** Medical History → Chief Complaint → HOPI → Examination Findings → Provisional Diagnosis → Investigations Advised → Treatment Advised → **Prescription (full drug table, prominent)** → Next Follow-up Visit.

**The specific gap this audit was asked to check:** `ConsultationController::show()` never queries the Prescription linked to *this* consultation — it only shows the patient's last 5 prescriptions generically, collapsed by default. The prescription a doctor just entered on Create/Edit is not distinguishable from old ones unless it happens to land in that list and the doctor expands the accordion. `ConsultationController::print()`, by contrast, already queries `Prescription::where('consultation_id', ...)` correctly and prints it as its own prominent section. **Doctors currently have no reliable way to confirm, on the View screen, that the Rx they just wrote actually saved** — they'd have to check Print or the separate Prescriptions tab.

**Missing fields:** show.blade.php has no "this visit's prescription" block.
**Order mismatches:** Create puts Prescription directly after Diagnosis (6→7); show buries prescription-adjacent content after Treatment Rendered & Clinical Intelligence, unrelated to Diagnosis; print keeps Create's adjacency (Treatment Advised → Prescription).
**UI inconsistencies:** duplicate "4" and non-integer "5b" section numbers signal repeated patching without renumbering.
**Print inconsistencies:** none — print's Prescription section is the best-implemented of the three.

**Suggested fixes (minimum change):**
1. In `ConsultationController::show()`, add the same `Prescription::where('consultation_id', $consultation->id)->with('items')->latest()->first()` query `print()` already uses, and pass it to the view.
2. Render it as an open-by-default "Prescription (this visit)" block near Diagnosis, reusing print's existing drug-table markup.
3. Renumber Tooth Chart to "5" and shift the rest down (or drop numbering entirely).
4. Point the header "Rx" button at the new inline block when one exists, instead of always routing to the separate Prescriptions tab.

---

## 3. Treatments (Clinic Treatment Catalog) — FAIL

**Naming clarification:** `resources/views/treatments/*` is the clinic's procedure/pricing **catalog**, not the patient-specific Treatment Plan flow. Patient Treatment Plans are API-only (`TreatmentPlanController` returns JSON), with UI embedded inline in the patient profile tabs and only two standalone Blade files: `treatment-plans/print.blade.php` and `treatment-consents/print.blade.php`. These are unrelated to `treatments/print.blade.php` (SOP/pre-op/post-op/consent text sheets for the catalog). Audited `treatments/*` as its own self-contained module.

**Missing fields:** The standalone `treatments/create.blade.php` lacks "Requires Lab Work" fields that Edit (inside `show.blade.php`'s Overview tab) has. The index-page "Add Treatment" **modal** — a second, independent Create UI feeding the same `store()` action — lacks both lab-work fields *and* Min/Max price fields that the standalone create page has.

**Order mismatches:** Standalone create: Category → Name/Code → Description → Duration/Colour → Pricing (3 fields) → GST/Unit/Sort → Active. Modal: Category → Name → Code/Duration/Price/GST (2-col grid) → Description. It's unclear from routing which is actually reachable in the live UI (no in-app link to the standalone create route was found) — possibly the standalone page is orphaned.

**Label inconsistencies:** Currency shown as "₹" on the standalone create page vs. "Rs." on both Edit and the modal.

**UI inconsistencies:** Edit is a 9-tab interface (Overview/Intelligence/SOP/Consent/Stages/Rules/Media/Patient Materials/Review/Usage); both Create variants only populate the Overview subset — reasonable as a wizard pattern, but only the standalone page's subtitle mentions "add SOP/rules afterwards"; the modal doesn't.

**Print inconsistencies:** N/A by design — `treatments/print.blade.php` prints SOP/consent text, not a data mirror of Create/Edit fields.

**Suggested fixes (minimum change):**
1. Decide which Create UI (standalone page vs. modal) is canonical; delete or redirect the other rather than maintaining two field sets for one `store()` action.
2. Add "Requires Lab Work" to whichever Create UI is kept.
3. Standardize currency label ("Rs. (₹)" or pick one) across all three surfaces.
4. Add Min/Max price to the modal if the modal is kept.
5. Add the same "configured after creation" note to both Create UIs if both survive.

---

## 4. Prescriptions — PASS (minor gaps)

Confirmed `create()`/`edit()` both render the same `quick-form.blade.php` partial (also reused inline in the patient's Prescriptions tab) — genuinely one implementation for both states.

**Missing/dead field:** `language` is displayed conditionally on show but has no input in the form; `create()` hardcodes it to `'en'` — the show-page conditional can never actually fire from the UI.
**Order mismatches:** Header field order (Chief Complaint, Diagnosis, Weight, Follow-up) is consistent across form/show/print; print moves Follow-up to the footer rather than the clinical-context block, a minor placement difference.
**Label inconsistency:** Form section header "Patient Instructions" vs. show/print's "General Instructions" — same underlying field, different label.
**UI inconsistency:** Drug table has 9 columns in the editable form (including an editable "Unit" column) vs. 7 in show and 8 in print — duration+unit are merged into one text cell in both read views, which is an acceptable, deliberate collapse rather than lost data.

**Suggested fixes:**
1. Add a language selector to the form, or remove the dead conditional from show.
2. Rename "Patient Instructions" to "General Instructions" in the panel component to match show/print.

---

## 5. Billing — FAIL

Confirmed single `billing.form` view serves both create and edit (same pattern as Prescriptions).

**Missing/dead field:** Line-item `Disc%` is displayed on show and print but is a hardcoded hidden `0` in the form — effectively unreachable/read-only via the UI despite looking editable.
**Order/structure mismatch:** The invoice-level "Discount (X%)" row shown on the staff-facing show page is entirely absent from the patient-facing print — a silently dropped line. Print also adds a `#` index column to the line-items table that show lacks.
**Label inconsistencies (three-way, same fields):**
- AOCP: "AOCP Membership Discount" (show) vs. "AOCP Discount" (print, form)
- Coupon: "Coupon Discount" (show) vs. "Additional Discount" (print, form)
- Wallet: "Wallet Credit Applied" (show) vs. "Wallet Credit" (print) vs. "Wallet" (form)

**Suggested fixes (minimum change):**
1. Standardize each of the three discount labels to one wording — cheapest fix is making show and print match the form's existing wording ("AOCP Discount" / "Additional Discount" / "Wallet").
2. Add the invoice-level discount row to print (mirroring show), or confirm it's intentionally patient-hidden and drop it from show too for consistency.
3. Either make `disc_pct` genuinely editable in the form or remove the column from show/print since it's currently dead weight.

---

## 6. Lab — FAIL

Confirmed create/edit are one Alpine drawer inside `lab/index.blade.php` (both controller methods just redirect to index). `reconciliation/` is a distinct, correctly out-of-scope sub-feature (invoice reconciliation, not case creation). Legacy `resources/views/labs/index.blade.php` is a dead 9-line stub with zero route/controller references — confirmed orphaned.

**Missing fields:** `priority` and `technician_name` are only editable from show's inline panel — never at creation. A new lab case can't be marked urgent at intake; it silently defaults and needs a second edit step.
**Label inconsistencies (three-way, same fields):**
- "Work Category" (drawer) vs. "Treatment" (show) vs. "Work Type" (print)
- "Subtype / Material" (drawer) vs. "Sub-type" (show)
- "Tooth Selection" (drawer) vs. "Teeth" (show) vs. "Tooth Number" (print)
- "Sent Date" (drawer, print) vs. "Sent to Lab" (show) — **and a genuine data-source divergence**: show prefers `impression_sent_date` (falling back to `sent_date`), while the drawer and print only ever read/write `sent_date`.

**Print inconsistencies:** None found — print correctly omits internal-only fields (Payment Status, Internal Notes) that shouldn't reach the lab vendor.

**Suggested fixes (minimum change):**
1. Add `priority` and `technician_name` to the create/edit drawer so they're settable at intake.
2. Standardize "Work Category"/"Sub-type"/"Teeth"/"Sent Date" wording to one term each across drawer/show/print.
3. Confirm whether show's `impression_sent_date` fallback is intentional; if not, align it to read `sent_date` like the drawer and print already do.

---

## 7. Appointments — FAIL

**Structural framework mismatch:** `create.blade.php` (Tailwind utilities) and `edit.blade.php` (inline styles) are built on two different styling systems for the same entity.
**Missing fields:** Create collects `type` (consultation/treatment) and `chief_complaint`; edit.blade.php has neither, though show.blade.php displays both — once booked, these become permanently uneditable through the edit page.
**Third parallel UI:** `appointments/index.blade.php` runs its own Alpine app with a global modal that POSTs/PATCHes appointments directly by ID, bypassing the edit page entirely. `appointments.create` is only linked from the "today" view, not the main calendar; `appointments.edit` isn't linked from anywhere. **Three loosely-consistent booking/edit UIs exist in production.**
**Print:** No `print.blade.php` and no print/pdf controller method exist anywhere for appointments — confirmed genuinely absent, not hidden elsewhere. Flagged as **N/A**, not counted as a fail contributor on its own.

**Suggested fixes (minimum change):**
1. Consolidate on one edit surface — most likely the index-calendar modal, since it's the one actually reachable in daily use — and either delete or redirect `edit.blade.php`.
2. Add `type`/`chief_complaint` to whichever edit surface is kept.
3. Rebuild the kept edit surface with the same utility-class system as create.blade.php.
4. If an appointment print/receipt is wanted, it doesn't exist yet anywhere — new build, not a consistency fix.

---

## 8. Inventory — N/A / effectively PASS

`resources/views/inventory/index.blade.php` is an empty, unused file — the controller actually renders `inventory.dashboard`, `inventory.items`, and `inventory.products` from separate methods. The real CRUD surface is `inventory/products.blade.php`: one shared modal used for both Add and Edit (title swapped via JS), which is internally consistent by construction since it's literally the same form. `inventory/items.blade.php` is a read-only stock table with inline adjusters, not a CRUD form — no create/edit comparison applies.

No action needed beyond removing the dead empty `index.blade.php` file for hygiene.

---

## 9. HR — Staff — FAIL (by design, flagged for awareness)

Create (292 lines) collects a lean onboarding set. Edit (772 lines — 2.6x larger) adds WhatsApp/alternate contact, full bank details, a full compensation breakdown (HRA/Conveyance/Medical Allowance/Special Allowance/Gross/OT/incentives), and advance-loan/bonus sub-forms that Create never exposes. Show displays a middle-ground subset roughly matching Edit's core fields but omitting the compensation-plan detail.

This reads as a deliberate progressive-disclosure pattern (lean intake now, rich detail later) rather than accidental drift — flagging so it's a documented decision rather than an assumption.

**Suggested fix:** if intentional, add a one-line note on Create ("Bank details, compensation breakdown, and advances are configured after onboarding, from the staff Edit screen") so the gap is signposted rather than silent.

---

## 10. HR — Training — PASS (minor drift)

Create and Edit share nearly identical fields (Title, Description, Type, Venue, Date, Start/End Time, Trainer, Notes). Edit adds `Status` (expected — applies post-creation only). Create offers "Internal Trainer (Staff)" as a selectable option that Edit doesn't expose — worth a follow-up look, not scored as a fail here since it may be intentional (internal trainer only assignable at creation).

---

## 11. Finance — Wallet Campaigns — PASS

No edit view by design (campaigns are immutable once created — reasonable for anything tied to a discount/promo run). Show is a per-patient redemption usage report, not a form mirror, which is the correct shape for that screen.

---

## 12. Communication — B2B — PASS (recommend deeper look later)

No edit view by design (append-only contact log — reasonable). Show's markup pattern wasn't fully parsed in this light pass (it doesn't use the `<dt>/<th>` structure the light-pass tooling searched for) — small file (261 lines), worth a direct read if this module is prioritized in a future deeper pass.

---

## 13. Data Requests — PASS

Small, consistent, append-only module (Patient, Request type, Details, Received via, Raised by). No edit by design.

---

## Cross-cutting pattern worth flagging separately

Five of the thirteen modules audited (Patients, Treatments/Catalog, Appointments, and to a lesser extent Lab/Billing's form-vs-display drift) share the same root cause: **more than one UI implementation feeds the same save action**, and the implementations have drifted apart independently. That's a bigger structural risk than any single label mismatch — worth deciding, module by module, which UI is canonical and retiring the other, before investing further in field-level polish.
