# Step 1 — Multi-Tenancy Discovery (read-only)
Generated 2026-09-04 from `database/migrations` (436 files) and `app/Models` (178 models).
No code written. Nothing changed.

---

## 0. HEADLINE — six things that break the brief as written

**1. `clinics` already exists and means something else.**
`2026_07_16_000002_create_clinics_table` is the **Dentfluence HQ sales CRM** (prospect / trial /
active / churned, contact_name, onboarded_at). It lives under `app/Modules/Hq/Models/Clinic.php`
with `plans`, `subscriptions`, `tickets`. It is the *vendor* side — the list of clinics we sell to.
It is NOT a tenant table.

**2. But `clinic_id` is already used as a de-facto tenant column on ~35 tables.**
Every `finance_*` (15), every `mkt_*` (18), every `blog_*` (4), `inventory_locations`,
`membership_benefit_logs`. Defined as `unsignedBigInteger('clinic_id')->default(1)` — a loose
scoping column, **not** an FK to `clinics`. It already has composite indexes
(`clinic_id, transaction_date`), unique keys (`clinic_id, slug`).
→ Adding `organization_id` on top gives the schema **three** words for tenant. The backfill must
migrate `clinic_id` → `organization_id` and drop it, or November is worse than today.

**3. `branches` already exists** (`2026_05_12_200000`) with a seeded row `id=1 'Main Clinic'`,
code/phone/email/address/city/state/is_active, plus `branch_settings` and ABDM fields.
→ Do not create it. Add `organization_id` to it.

**4. `chairs` already exists, called `operatories`** (`2026_06_17_200001`):
`branch_id` FK cascade, `name`, `display_order`, `is_active`, index `(branch_id, is_active, display_order)`.
`appointments.operatory_id` is **live data**.
→ Creating `chairs` duplicates it. Use `operatories`, add `organization_id`, and do NOT add a
second `chair_id` column to `appointments` — `operatory_id` IS the chair.

**5. A tenant scope already exists and it fails OPEN.**
`app/Models/Scopes/BranchScope.php` + `app/Traits/BelongsToBranch.php`, applied to
**Patient, Appointment, Consultation, LabCase, Task**. It deliberately no-ops when: no auth user,
user `isAdminRole()`, or user has no `branch_id`. Today everyone is admin, so it is inert.
→ A tenant scope must fail CLOSED. These two coexisting is the highest-risk item in the whole job.

**6. Your rule "patients belong to ORGANIZATION, never to a branch" contradicts live data.**
`patients.branch_id` exists and is branch-scoped by `BranchScope` right now.
→ Decision needed: keep `branch_id` on patients as "branch of first registration" (data kept,
scope removed), or drop it. I recommend keeping the column, killing the scope.

---

## (a) Every existing table — 287 total

### A. Framework / infrastructure — NO organization_id (11)
cache · cache_locks · failed_jobs · job_batches · jobs · migrations · mobile_otps ·
password_reset_pins · password_reset_tokens · personal_access_tokens · processed_domain_events · sessions

### B. Dentfluence HQ — vendor side, ABOVE tenants — NO organization_id (4)
clinics · plans · subscriptions · tickets

### C. Global Dentfluence IP / platform content — NO organization_id (25)
kb_topics · kb_blocks · kb_block_media · kb_topic_relations · decision_trees · decision_tree_nodes ·
treatment_knowledge · education_categories · education_treatments · education_media ·
cms_edu_categories · cms_edu_items · terminology_maps ·
rx_drugs · rx_drug_categories · rx_generics · rx_routes_of_admin · rx_food_instructions ·
rx_dose_templates · rx_duration_templates · rx_allergy_rules · rx_drug_interaction_rules ·
rx_warning_rules · medical_conditions · dental_conditions

*(`kb_topics` header explicitly states: "Global, versioned education (Dentfluence IP). NEVER carries
prices, discounts, brands, clinic_id, or patient data" — enforced by `GuardsKnowledgeBankPurity`.)*

### D. RBAC / system definition — NO organization_id for V1 (5) ⚠ decision
modules · roles · role_module_permissions · role_billing_permissions · feature_flags
*(`feature_flags` already carries `branch_id`. In V2 an org will want its own roles. For V1 keep
global; the V2 move is `organization_id` nullable, NULL = platform default.)*

### E. HYBRID MASTERS — nullable organization_id, NULL = platform default (32) ⚠ your ruling needed
These are seeded by Dentfluence but editable by the clinic. Making them NOT NULL means every new
org re-seeds 3,000 rows. Making them global means a clinic cannot add its own.
treatments · treatment_types · treatment_categories · treatment_options · treatment_rules ·
treatment_sops · treatment_media · diagnosis_treatment_options ·
complaints · diagnosis_masters · investigation_masters · medicines · dental conditions list ·
materials · brands · patient_sources · message_templates · consent_purposes · retention_policies ·
action_option_lists · emi_providers · emi_schemes · rx_templates · rx_template_items ·
documentation_protocols · documentation_protocol_steps · practice_protocols ·
practice_protocol_materials · inventory_categories · inventory_sub_types · inventory_settings ·
mkt_festival_dates · workflow_templates

### F. TENANT-OWNED — organization_id NOT NULL (~210)
Everything else. By module:

- **Patient core (22)** patients · patient_alerts · patient_allergies · patient_communications ·
  patient_consents · patient_documents · patient_identifiers · patient_journeys · patient_links ·
  patient_merges · patient_notes · patient_relationship_notes · patient_tag · tags ·
  relationships · relationship_journeys · relationship_merges · relationship_notifications ·
  relationship_rule_logs · relationship_contact_log · dedup_candidates · activities
- **Scheduling (5)** appointments · doctor_blocked_slots · escalations · huddle_notes · today_actions
- **Huddle (6)** huddle_boards · huddle_cards · huddle_comments · huddle_settings · huddle_task_logs · today_action_dismissals
- **Clinical (24)** consultations · consultation_photographs · consultation_scans ·
  consultation_specialty_modules · consultation_coha_reports · clinical_findings ·
  clinical_files · clinical_media · diagnoses · investigations · treatment_plans ·
  treatment_plan_items · treatment_plan_item_teeth · treatment_visits · treatment_visit_items ·
  treatment_opportunities · treatment_consents · plan_decisions · plan_decision_items ·
  case_selections · case_consent_snapshots · journey_curations · journey_custom_options · journey_sent_snapshots
- **Prescriptions (4)** prescriptions · prescription_items · prescription_overrides · prescription_audit_logs
- **Billing (17)** invoices · invoice_items · invoice_payments · receipts · final_bills ·
  billing_prompts · billing_audit_logs · wallets · wallet_transactions · wallet_campaigns ·
  coupon_codes · coupon_usage · emi_schedules · referral_rewards ·
  finance_membership_plans · finance_patient_memberships · membership_benefit_logs
- **Finance (15, currently `clinic_id`)** finance_transactions · finance_income_entries ·
  finance_expenses · finance_expense_categories · finance_vendors · finance_vendor_payments ·
  finance_payroll · finance_cashbook · finance_bank_accounts · finance_bank_transactions ·
  finance_gst_records · finance_staff_advances · finance_vouchers · finance_audit_log · finance_settings
- **Lab (16)** lab_cases · lab_case_items · lab_case_attachments · lab_case_events ·
  lab_case_prescriptions · lab_case_ratings · lab_vendors · lab_vendor_contacts ·
  lab_vendor_services · lab_vendor_price_lists · lab_prescription_templates ·
  lab_monthly_reconciliations · lab_reconciliation_events · lab_reconciliation_items · implant_catalog · implant_placements
- **Inventory / procurement (14)** inventory_items · inventory_variants · inventory_stocks ·
  inventory_locations · inventory_vendors · stock_movements · stock_count_sessions ·
  stock_count_lines · purchase_orders · purchase_order_items · goods_receipt_notes · grn_items ·
  vendor_invoices · vendor_invoice_items · product_dealers · reusable_assets
- **HR (20)** hr_departments · hr_shifts · hr_staff_profiles · hr_staff_shifts · hr_attendance ·
  hr_entry_exit_logs · hr_staff_documents · hr_staff_advances · hr_salary_components ·
  hr_incentive_rules · hr_bonuses · hr_performance_memos · hr_training_sessions ·
  hr_training_enrollments · hr_periodic_training_requirements · hr_periodic_training_records
- **Marketing (18, currently `clinic_id`)** mkt_settings · mkt_brand_kits · mkt_campaigns ·
  mkt_campaign_goals · mkt_campaign_team · mkt_ideas · mkt_idea_assets · mkt_platform_connections ·
  mkt_posts · mkt_post_variants · mkt_post_media · mkt_post_schedules · mkt_assets ·
  mkt_asset_folders · mkt_asset_tags · mkt_asset_tag_map · mkt_activity_log
- **Blog / CMS (10, currently `clinic_id`)** blog_categories · blog_posts · blog_post_seo ·
  blog_post_tag · blog_post_versions · blog_publications · blog_tags · cms_media · cms_tags · cms_treatment_cases
- **Leads / comms (10)** leads · lead_activities · follow_ups · follow_up_notes ·
  communication_queue · comm_activity_logs · wa_threads · wa_messages · reviews · voice_notes
- **Presentations (5)** presentations · presentation_snapshots · presentation_media_items ·
  presentation_access_tokens · media_assets
- **Compliance / DPDP (8)** consent_logs · data_requests · data_breaches · fhir_documents ·
  facility_abdm_config · practitioner_identifiers · practitioner_qualifications · branch_settings
- **AI / workflow / ops (16)** ai_conversations · ai_messages · ai_action_logs · workflow_instances ·
  workflow_step_log · workflow_shadow_log · automation_shadow_log · integration_shadow_log ·
  insight_signals · analytics_snapshots · search_index · tasks · app_notifications ·
  staff_activity_logs · audit_logs · app_settings

---

## (b)/(c) summary
- **Must get organization_id (NOT NULL):** ~210 tables
- **Must get nullable organization_id (hybrid masters):** ~32 tables — needs your ruling
- **Must NOT get organization_id:** ~45 tables (framework 11 + HQ 4 + global IP 25 + RBAC 5)

## Tables that also need branch_id / chair
Per your rule "appointments, visits, treatments, payments":
- `appointments` — has `branch_id` ✅ and `operatory_id` ✅ (= chair). Nothing to add.
- `treatment_visits` — needs `branch_id` + `operatory_id`.
- `treatment_visit_items` — inherits from visit. Skip.
- `invoices` · `invoice_payments` · `receipts` · `final_bills` · `finance_cashbook` — need `branch_id`.
  **`chair_id` on payment tables is noise — money is not collected in a chair. Recommend branch only.**

## (d) Models — 178 files
- **Already tenant-aware (5):** Patient, Appointment, Consultation, LabCase, Task (via `BelongsToBranch`)
- **Never scope (14):** Modules/Hq/{Clinic,Plan,Subscription,Ticket}, KbTopic, KbBlock, KbBlockMedia,
  KbTopicRelation, DecisionTree, DecisionTreeNode, TreatmentKnowledge, EducationCategory,
  EducationTreatment, EducationMedia, TerminologyMap, Prescription/Rx* masters (13), Module, Role,
  RoleModulePermission, RoleBillingPermission
- **Need the trait (~140):** everything else in `app/Models`, plus the models under
  `app/Modules/{Appointment,Huddle,Lab,Patient,PracticeProtocols,Treatment}/Models`
- **Special:** `User` gets `organization_id` but NOT the global scope (login must resolve the user
  before tenancy exists — chicken/egg).

## Providers — the one genuinely new concept
There is no `providers` table today. Doctors are **users**: `doctor_id` FKs to `users.id` on
17 tables (appointments, treatment_plans, treatment_visits, prescriptions, invoices, …), plus
`doctor_blocked_slots`, `users.color`, `users.designation`.
Creating a separate `providers` table means either duplicating users or rewriting 17 FKs.
**Recommendation: `providers` is a 1:1 extension of `users` (`providers.user_id` unique FK),
`branch_provider` pivot as specced, and `doctor_id` keeps pointing at `users.id` for V1.**
