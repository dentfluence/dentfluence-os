# Treatment Visit V1 — Phase 6: Create / Edit / View / Print Parity Audit

Method: full trace of the four surfaces through their actual code paths — `TreatmentVisitController` (create/edit/print), `TreatmentVisitService` (rules/create/update/format), the `treatmentVisits()` Alpine factory (`_blank()`/`openAddForm`/`openEditForm`/`saveVisit`), the `TreatmentVisit` model (`$fillable`/`$casts`), and all three Blade surfaces (`treatment-visit-form-fields.blade.php`, `treatment-visits-tab.blade.php`, `visits/print.blade.php`). No live browser session was available, so this is a code-traced audit, not a live click-through — every finding below is grounded in an exact file/line comparison, not inference.

**Surface map** (worth stating up front — there is no separate "View" page):
- **Create** and **Edit** both render the *same* Blade file (`patients.treatment-visit-form` → `treatment-visit-form-fields.blade.php`), driven by `TreatmentVisitController::create()`/`edit()`. There is structurally one form, not two.
- **View** is the visit card in the Treatment Visits tab (`treatment-visits-tab.blade.php`) — the only read-only surface a saved visit renders on.
- **Print** is `visits/print.blade.php`, served by `TreatmentVisitController::print()`.

## 1. Parity Matrix

| Feature | Create | Edit | View | Print |
|---|---|---|---|---|
| Visit Information (date/type/status/doctor/appointment) | ✅ | ✅ | ✅ | ✅ (Status added this sprint) |
| Chief Complaint | ✅ | ✅ | ✅ (added this sprint) | ✅ |
| Clinical Notes | ✅ | ✅ | ✅ | ✅ |
| Today's Procedures (visit items) | ✅ | ✅ | ✅ (count badge) | ✅ (itemised table) |
| Procedure Worksheet (RCT/Implant/Filling/Scaling/Extraction/Crown) | ✅ | ✅ | Summary only¹ | ✅ (added this sprint) |
| Treatment Progress (stage tracker) | ✅ | ✅ | ✅ | ✅ (labels fixed this sprint) |
| Billing Preview / Summary | ✅ | ✅ | ✅ | ✅ (added this sprint) |
| Lab Case | ✅ | ✅ | — ² | ✅ |
| Vitals | ✅ | ✅ | ✅ | ✅ (added this sprint) |
| Next Visit | ✅ | ✅ | ✅ | ✅ |
| Prescription | ✅ | ✅ | Summary only¹ | ✅ (itemised table) |
| Summary / footer | ✅ | ✅ | ✅ | ✅ |

¹ Deliberately scoped, not a defect — see "Reviewed, not fixed" below.
² The Treatment Visits tab card never surfaced lab-case info even before this sprint (Lab work is tracked in its own Lab tab); out of scope as a *new* feature, not a parity regression — flagging for your awareness rather than fixing silently.

## 2. Files Modified

1. `resources/views/visits/print.blade.php`
2. `resources/views/patients/partials/treatment-visits-tab.blade.php`

No Controllers, Services, Models, database, validation, or business logic were touched. One existing read-only Model method was *called* (`TreatmentVisit::allStagesFromDb()`, already used identically elsewhere) — nothing about the Model changed.

## 3. Parity Defects Found and Fixed

**3.1 — Print: Treatment Status missing entirely.**
Root cause: `visits/print.blade.php`'s "Visit Details" section never rendered `$visit->status`, even though Status is a core field on Create/Edit and shown on the View card's badge. Fix: added a Status row, label-cased (`Completed`, `In Chair`, etc.) to match the on-screen badge wording.

**3.2 — Print: stage names printed as raw machine keys, not labels.**
Root cause: the "Work Done" section printed `$visit->current_stage` / `completed_stages` directly — raw keys like `access_opening` — while the View card already resolves the same data through `TreatmentVisit::allStagesFromDb()` to show human labels (`Access Opening`). Fix: print now resolves through the same method, so a stage reads identically on screen and on paper. Falls back to the raw key only if a stage was recorded under a treatment name no longer configured with stages — same graceful-degradation behavior the card already has.

**3.3 — Print: Vitals recorded during the visit never appeared.**
Root cause: `visits/print.blade.php` had no Vitals section at all, despite Vitals being fully captured in Create/Edit and shown on the View card. Fix: added a Vitals section (BP, pulse, SpO₂, temperature, blood sugar, weight, vitals note), shown only when at least one value was recorded — matching this document's existing "no empty rows" convention.

**3.4 — Print: no Billing Summary.**
Root cause: the case sheet showed no financial information at all, even though the View card already shows cost / amount paid / balance due for every visit with billed items. Fix: added a Billing Summary section (Visit Cost, Amount Paid, Balance Due — balance only shown if > 0), visible only when the visit has a recorded cost. Kept deliberately to a one-line summary — itemised billing and receipts remain the invoice's job, not the case sheet's.

**3.5 — Print: Procedure Worksheet clinical detail never reached the case sheet.**
Root cause: none of the RCT/Implant/Filling/Scaling/Extraction/Crown Prep worksheet fields — captured in Create/Edit and persisted to the visit row — were ever rendered in print. Fix: added six treatment-specific worksheet sections (only the one matching the visit's `treatment_name` renders, and only for fields actually filled in). Implant fixture name is resolved through the existing `ImplantPlacement → ImplantCatalog::getFullName()` relation when a catalog fixture was used, falling back to the free-text brand/size fields otherwise — same fallback logic the Service already uses.

**3.6 — View card: Chief Complaint never shown.**
Root cause: `treatment-visits-tab.blade.php` renders a one-line Notes preview on every card but had no equivalent for Chief Complaint, a same-tier text field captured on every visit. Fix: added a labeled one-line preview directly above Notes, same `line-clamp-1` treatment, shown only when a complaint was recorded.

## 4. Reviewed and deliberately not changed

- **`mark_treatment_complete` not restored on Edit.** Traced this carefully before deciding: it is not a stored visit attribute — it is a one-time action flag that, when checked, marks the *linked treatment plan* complete and queues a 6-month recall task as a side effect. Re-populating it as "checked" every time an already-completed visit is reopened would misrepresent it as pending state and risk implying the completion action will fire again. Defaulting to unchecked on every Edit open is the correct, safe behavior, not a gap — confirmed via a full field-by-field diff between `_blank()` and `openEditForm()`, which is otherwise complete field-for-field.
- **Procedure Worksheet detail on the View card.** Print now shows RCT/Implant/etc. specifics (fix 3.5), but the View card deliberately stays a summary — consistent with how the card already treats visit items and prescriptions (count badges, not line-by-line detail) rather than a full clinical chart. Flagging this as a considered scope boundary, not an oversight.
- **`TreatmentVisitService::format()` (the AJAX save-response payload) omits vitals, cost/payment, and linked-Rx.** This is real, but it's a Service file — off-limits under this sprint's hard constraints — and it's not user-visible on desktop: every save on the dedicated Create/Edit page triggers an immediate full-page redirect (`window.location.href`) straight after, so the incomplete in-memory payload is never actually rendered before the page reloads with the complete server-rendered data. It does matter for the mobile API, which reuses the same `format()` — worth a note for whenever Mobile Parity is scoped, but out of bounds here per the Stop Condition.

## 5. Verification

Static verification (tag balance, `@if`/`@endif`, `@foreach`/`@endforeach`, `@php`/`@endphp` counts) on all three files touched or reviewed this sprint — all matched, zero mismatches:

- `visits/print.blade.php`: div 101/101, table/tr/td/th/thead/tbody all matched, span 132/132, `@if`/`@endif` 65/65, `@foreach`/`@endforeach` 5/5, `@php`/`@endphp` 3/3.
- `treatment-visits-tab.blade.php`: div 34/34, span 40/40, p 5/5, template 10/10, a 4/4, button 1/1.
- `treatment-visit-form-fields.blade.php` (unmodified this sprint, re-verified as a baseline sanity check): div 183/183, button 42/42, template 21/21, select 22/22, textarea 3/3.

I can't execute `php artisan view:clear` / `php artisan test` myself — no PHP runtime or database exists in this environment, and the project's standing Terminal Policy is that I list Artisan commands for you to run rather than execute them. Please run:

```
php artisan view:clear
php artisan test
```

Everything this sprint touched is Blade-only (two view files, no PHP logic, no new queries beyond calling an existing public Model method and reading an existing lazy-loaded relation) — I'd expect this to stay at the last confirmed green run, but want that confirmed before signing off.

---

Once you confirm the test run is green: **Treatment Visit V1 Desktop Parity Complete.**
