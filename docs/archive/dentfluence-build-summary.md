# Dentfluence — Build Summary
**Project:** Dental CRM (Laravel 13 + Alpine.js + Tailwind CSS)  
**Module in progress:** Lab Module (Session 7 complete)  
**Last updated:** 2026-05-28

---

## Stack
- **Backend:** Laravel 13, PHP
- **Frontend:** Blade templates, Alpine.js, Tailwind CSS
- **DB:** MySQL
- **Architecture:** Service layer (`PatientProfileService`), resource controllers, AJAX endpoints

---

## Database Tables

### Existing / Completed
| Table | Key Columns | Notes |
|---|---|---|
| `patients` | id, name, phone, city, dob, gender, source, last_visit_date, recall_status, medical alerts, allergies, habits | Core patient record |
| `appointments` | patient_id, treatment_id, treatment_category_id, type, date, doctor_id, status | Visit log / appointment history |
| `consultations` | patient_id, doctor_id, date, chief_complaint, clinical_findings, diagnosis, notes | Full consultation record |
| `treatment_plans` | patient_id, consultation_id, plan_name, plan_type (best/acceptable), status, rows (JSON legacy), total, overall_disc_pct, aocp, created_by | Upgraded in Session 6 — added `patient_id`, `plan_name`, `status`, `overall_disc_pct`, `created_by` |
| `treatment_plan_items` | treatment_plan_id, tooth_number, treatment_name, unit_price, units, disc_pct, disc_amount, net_amount, gst_pct, gst_amount, total, aocp_applied, option_rank, status, notes, sort_order | **New in Session 6** — replaces JSON blob rows |
| `treatment_visits` | patient_id, consultation_id, doctor_id, visit_date, visit_type, status (started/ongoing/completed), procedure, tooth_number, treatment_name, current_stage, completed_stages (JSON) | Core visit record, built Session 5 |
| `treatment_visits` (clinical ext.) | rct_num_canals, rct_canal_lengths, rct_file_type, rct_irrigant, rct_obturation_method, impl_brand, impl_size, fill_shade, scaling_quadrants, extraction_type, crown_type | Smart clinical fields added Session 5 |
| `relationship_notes` | patient_id, author_id, note, tag | Rapport building notes |
| `opportunities` | patient_id, author_id, title, status, priority, follow_up_date, estimated_value, notes, created_by | Treatment opportunities / pipeline |
| `alerts` | patient_id, message, severity | Medical alert banners |
| `treatments` | id, name | Treatment master list |
| `users` | id, name, role (doctor/admin/etc.) | Staff / doctors |

---

## Key Decisions

| Decision | Rationale |
|---|---|
| **Follow-up tab removed** | Merged into Treatment Visits — follow-up is just a visit type, not a separate module |
| **Payment fields removed from Treatment Visits** | Payment is a billing concern, not a clinical one; doctors shouldn't see billing data |
| **`treatment_plan_items` table (not JSON blob)** | Needed per-row `status`, `tooth_number`, `disc_pct` tracking — JSON blob couldn't support that |
| **`patient_id` added to `treatment_plans`** | Plans should be queryable directly from a patient without going through a consultation |
| **`PatientProfileService`** | Extracts eager-loading + data prep logic out of `PatientController@show` to keep it thin |
| **Alpine.js for all tab/drawer interactions** | No full page reloads — tabs, drawers, and forms all use Alpine + fetch/AJAX |
| **Smart clinical fields per treatment** | RCT, Implant, Filling, Scaling, Extraction, Crown each show only their own relevant fields |
| **Stage tracker in visit sidebar** | Each treatment type auto-populates a stage checklist (e.g. RCT: Access → Biomech → Obturation) |

---

## Routes

### Patient Routes
```
GET    /patients                     → PatientController@index
GET    /patients/{patient}           → PatientController@show
PUT    /patients/{patient}           → PatientController@update
DELETE /patients/{patient}           → PatientController@destroy
```

### Consultation Routes
```
GET    /patients/{patient}/consultations/create   → ConsultationController@create
POST   /patients/{patient}/consultations          → ConsultationController@store
GET    /consultations/{consultation}              → ConsultationController@show
```

### Treatment Visit Routes
```
POST   /patients/{patient}/treatment-visits              → TreatmentVisitController@store
PUT    /patients/{patient}/treatment-visits/{visit}      → TreatmentVisitController@update
DELETE /patients/{patient}/treatment-visits/{visit}      → TreatmentVisitController@destroy
```

### Treatment Plan Routes (added Session 6)
```
GET    /patients/{patient}/treatment-plans                       → TreatmentPlanController@index
POST   /patients/{patient}/treatment-plans                       → TreatmentPlanController@store
PUT    /patients/{patient}/treatment-plans/{plan}                → TreatmentPlanController@update
DELETE /patients/{patient}/treatment-plans/{plan}                → TreatmentPlanController@destroy
POST   /patients/{patient}/treatment-plans/{plan}/items          → TreatmentPlanController@storeItem
PUT    /patients/{patient}/treatment-plans/{plan}/items/{item}   → TreatmentPlanController@updateItem
DELETE /patients/{patient}/treatment-plans/{plan}/items/{item}   → TreatmentPlanController@destroyItem
```

---

## Modules / Files Completed

### Models
- `Patient.php` — fillable, casts, relationships (appointments, consultations, treatmentVisits, treatmentPlans, relationshipNotes, opportunities, alerts, creator, branch, tags)
- `Consultation.php` — full model
- `TreatmentVisit.php` — fillable includes all clinical + stage fields
- `TreatmentPlan.php` — upgraded with `patient_id`, `hasMany items()`, accessors
- `TreatmentPlanItem.php` — **new** — fillable, belongs to TreatmentPlan

### Controllers
- `PatientController.php` — index, show (via service), update, destroy
- `ConsultationController.php` — create, store, show
- `TreatmentVisitController.php` — store, update, destroy (payment fields removed)
- `TreatmentPlanController.php` — upgraded with index, update, storeItem, updateItem, destroyItem

### Services
- `PatientProfileService.php` — loads patient with: appointments, treatmentCategory, relationshipNotes, opportunities, alerts, treatmentVisits (with doctor), treatmentPlans (with items + creator), consultations

### Views / Blade Partials
```
resources/views/patients/
├── index.blade.php                          ✅ patient list
├── show.blade.php                           ✅ profile page + tab bar
└── partials/
    ├── profile-tab.blade.php                ✅ personal details, rapport notes, opportunities, visit log
    ├── consultation-tab.blade.php           ✅ consultation list + new consultation button
    ├── treatment-visits-tab.blade.php       ✅ visit list + smart clinical sidebar (no payment)
    └── treatment-plan-tab.blade.php         ✅ NEW — plan list + item table per plan
```

### Consultation Partials (all 12 complete)
Located at `resources/views/consultations/partials/`:
`chief-complaint`, `clinical-findings`, `tooth-chart`, `diagnosis`, `treatment-plan`, `prescription`, `lab-orders`, `referral`, `patient-instructions`, `follow-up`, `attachments`, `summary`

### Migrations (in order)
1. `create_patients_table`
2. `create_appointments_table`
3. `create_consultations_table`
4. `create_treatment_plans_table`
5. `create_relationship_notes_table`
6. `create_opportunities_table`
7. `create_alerts_table`
8. `create_treatments_table`
9. `create_treatment_visits_table` (Session 5)
10. `add_clinical_details_to_treatment_visits_table` (Session 5)
11. `upgrade_treatment_plans_table` (Session 6) — adds patient_id, plan_name, status, overall_disc_pct, created_by
12. `create_treatment_plan_items_table` (Session 6)

---

## What's Pending

### ✅ Session 7 — Lab Module (COMPLETE)
- `lab_cases` table upgraded: patient_id, doctor_id, work_type, work_subtype, tooth_number, shade, lab_vendor, lab_cost, sent_date, expected_return_date, received_date, status, instructions, notes
- `LabCase` model: fillable, date casts, `patient()`, `doctor()` relationships, `workTypeLabel()`, `statusColor()`, `subtypesFor()` helpers
- `LabController`: index (all cases + status filter + search), store, update, destroy, patientCases (patient tab)
- Routes: `/lab` (CRUD), `/patients/{patient}/lab-cases` (patient-nested)
- View: `/lab` index page — filter tabs (All/Sent/In Progress/Received/Rejected), search, table with overdue detection, slide-over drawer for create/edit
- View: `patients/partials/lab-tab.blade.php` — patient profile Lab Cases tab with AJAX quick-status, delete, inline drawer
- Patient `show.blade.php`: Lab Cases tab added to tab bar + panel
- `Patient` model: `labCases()` relationship added

### Session 8 — Billing / Financials
- Invoice list per patient
- Payment entries against invoices
- Outstanding balance breakdown
- Wire up the dead "View Details" link on the Outstanding Balance stat card

### Session 8 — Recall & Reminders
- Trigger recall reminder action
- Update recall date
- Recall history log
- SMS / WhatsApp hook stub

### Session 9 — Patient Index Polish
- Search by name / phone
- Filters: source, doctor, recall status, outstanding balance
- Export CSV
- Pagination improvements

### Session 10 — Consultation Show Page
- Full read-only view of a single consultation
- Currently only the create form is built; there is no `consultations/{id}` detail page

### Session 11 — Delete / Archive / Merge
- Soft delete patient
- Archive toggle
- Merge duplicate patient records

### Session 12 — Final QA & Glue
- Dead links audit
- Stat card wiring (Total Billed, Collection %, etc. pulling real numbers)
- Mobile responsiveness pass
- Empty states everywhere
- `php artisan migrate:fresh --seed` clean run

---

## Quick Reference — Run After Each Session
```bash
php artisan migrate
php artisan view:clear
php artisan route:clear
php artisan cache:clear
```
