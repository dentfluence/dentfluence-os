# Mobile Completion Sprint — Progress Log
Started 2026-07-14 · Reference audit: `docs/web-mobile-parity-audit-2026-07-14.md`

Product decisions locked with Sumit:
- Backend money/security fixes FIRST, then Flutter.
- Mobile WhatsApp → consent-gated API send through `OutboundMessageService` (no more bare deep-link).
- Invoice EDIT stays web-only (mobile keeps create/cancel/void).

## Slice 1 — Backend money/security ✅ CODE-COMPLETE (untested)

**New shared services**
- `app/Services/Procurement/VendorInvoiceService.php` — single brain for vendor-invoice create/cancel with the DOUBLE-AP GUARD. `rules()` shared by both controllers. Guard matches ALL historical `source_type` forms ('PurchaseOrder', FQCN, GRN class via GRN ids of the PO).
- `app/Services/LabCaseTransitionService.php` — web's canonical lab transition (correct date columns, task chain close/create, notifications, lab expense on final_received). API previously stamped non-fillable columns and skipped tasks + AP expense.
- `WalletService::reverseInvoiceDebit()` — extracted from web BillingController; now called by BOTH web destroy/cancel and API cancelInvoice (was silently losing patient wallet credit on mobile cancels).
- `WalletService::receiveAdvance()` — deposit + FinanceTransaction income mirror + BillingAuditLog in one transaction; web `Finance\WalletController::receiveAdvance` and API `addWalletCredit` both delegate.

**Controllers refactored onto services**
- `VendorInvoiceController` (web + Api/V1) store/destroy → `VendorInvoiceService`.
- `LabController` (web + Api/V1) transition → `LabCaseTransitionService`. API transition no longer accepts notes/date params (web is canonical).
- `Api/V1/BillingController`: `cancelInvoice` now reverses wallet debits; `recordPayment` rules now include `wallet_used` (service already supported it); `addWalletCredit` now gated by `ADVANCE_ADJUSTMENT` (admin bypass), validates like web (payment_date required, payment_mode enum), posts the finance chain.
- `InventoryService::receivePurchaseOrder` — GRN expense now written canonical (`GoodsReceiptNote::class` + grn id, matching web receivePO).

**Security**
- `Api/V1/AuthController::updateMe` — password change now requires `current_password` + `Password::defaults()`; field caps aligned to web (name:100, email:150); phone/designation supported; `userPayload` now returns phone/designation.
- `routes/api.php` — previously ungated groups now use the permission table via `api.role:module:*,view` (same action web checks): patient notes/comms writes → module:patients; lab reads+writes → module:lab; reports → module:reports.

## Slice 2 — Flutter auth & resilience ✅ CODE-COMPLETE (untested)
- `api_client.dart`: `TwoFactorRequiredException`; `login(..., code:)`; `_guard()` converts SocketException/Timeout/TLS/ClientException into friendly retryable ApiExceptions (all verbs incl. multipart); `_handle()` upgrades — 401+two_factor_required → typed exception, 401 with token → clear token + `ApiClient.onSessionExpired` + friendly message, 422 → first Laravel validation message; `updateProfile` gains phone/designation/currentPassword; new `logoutAll()`.
- `login_screen.dart`: two-step 2FA challenge (code field appears when server demands it; recovery codes accepted).
- `main.dart`: `rootNavigatorKey` + `onSessionExpired` → pushAndRemoveUntil LoginScreen (expired 30-day Sanctum token no longer strands the user).
- `profile_screen.dart`: edit sheet now has phone, designation, current-password (required to change password).
- `app_user.dart`: phone/designation fields.

## Slice 3 — Appointments ✅ CODE-COMPLETE (untested)
- `allow_overlap` end-to-end: `createAppointment`/`createWalkIn` accept `allowOverlap`; `ApiClient.isOverlapConflict()` detects the server's overridable-overlap 422 (via the 'allow_overlap' token in the message — commented server-side in `AppointmentService::assertSlotIsBookable`); `add_appointment_screen` + `walk_in_screen` show "Book anyway?" / "Check in anyway?" confirms and retry with the override. `WalkInRequest` now validates `allow_overlap` (was impossible to override walk-ins).
- Reschedule + delete: new `AppointmentService::reschedule()` (same guards as create, excludes self, allow_overlap override) + `delete()`; API `PATCH /appointments/{id}/reschedule` + `DELETE /appointments/{id}` (admin/front_desk); Flutter action-sheet entries with date/time pickers, "Reschedule anyway?" overlap confirm, and a delete confirm that steers reasoned cancellations to Cancel.
- Status `cancelled` now logged by `AppointmentService::updateStatus` (was invisible on timeline when mobile cancelled via the status route — audit A2).
- Doctor + status filters: filter sheet in `appointments_screen.dart` (ChoiceChips, badge on the filter icon); server params were already accepted, never passed.
- DEFERRED (documented technical debt): full appointment EDIT (change patient/doctor/type) — reschedule covers date/time/duration; cancel+rebook covers the rest. Revisit only if front desk asks.

## Slice 4 — Money completion ✅ CODE-COMPLETE (untested)
- **Pay from wallet**: `paymentOptions` API now returns `wallet` (WalletService::summary); `record_payment_screen` shows a "Pay from wallet" card when credit exists (Max button caps at min(balance, due)), sends `wallet_used`; server caps at min(requested, balance, balance_due) via the shared service.
- **Payment-date edit**: API `PATCH /invoices/{invoice}/payments/{payment}/date` (mirrors web updatePayment guards: ownership, cancelled-invoice block, before_or_equal:today) → shared `InvoicePaymentService::updatePaymentDate` (cascades receipt + FinanceTransaction). Flutter: edit-calendar icon on each payment row in `invoice_detail_screen`.
- **Wallet ledger fixed**: `PatientProfileController::wallet` transaction mapping read non-existent columns (blank rows on mobile) — now returns direction/credit_type/source/invoice_number/payment_mode/notes (+legacy keys). Profile wallet tab renders +/− with source labels.
- **Top-up dialog enum fix**: mode list had 'neft' which the hardened advance validation would 422; now matches the server enum.

## Slice 5 — Lab writes on mobile ✅ CODE-COMPLETE (untested)
- New API `GET /lab/form-options` (work_categories from `LabCase::WORK_CATEGORIES` — single source of truth, vendors, doctors, priorities).
- Client: `getLabFormOptions/createLabCase/transitionLabCase`.
- `lab_screen.dart`: "Move case forward" card on the detail (buttons from `next_statuses`, confirm dialogs — final_received explains the auto task + finance expense), list refreshes after transitions; FAB → new `LabCaseCreateScreen` (patient picker, category→subtype cascade keyed to rebuild, vendor/doctor, priority segmented, expected return, shade, est. cost, notes, draft-vs-order toggle).
- DEFERRED: lab attachments UI — clinical photo capture already exists on the patient profile (per the existing-upload-paths rule); lab prescriptionSave UI (specialist workflow, low mobile frequency).

## Slice 6 — Inventory/Vendors ✅ CODE-COMPLETE (untested)
- **`InventoryService::adjustStock` now THE single implementation** — gained web's unit_price/cost handling (mobile adds no longer log zero-cost movements); web `adjustStock` delegates to it. API validation accepts `unit_price`.
- **`InventoryService::costPerUsage()`** — extracted from web `calculateCostPerUsage`; web delegates; API `storeProduct` now sets `cost_per_usage` (was always 0 for mobile-created products).
- **New endpoints**: `POST /inventory/vendors` (storeVendor + Finance sync), `PATCH /inventory/vendors/{v}/toggle` (never hard-delete), `DELETE /inventory/products/{item}` (soft archive, admin-only — same as web admin.only).
- **Client methods**: `createVendor/toggleVendor/archiveProduct`.
- **UI hooks done (2nd session)**: `VendorsEditScreen` gained a New-vendor FAB (form is now create+edit dual-mode), per-row activate/deactivate toggle with strikethrough on inactive; item detail gained an Archive action (confirm dialog, admin-gated server-side).
- **GRN reversal consolidated + exposed**: extracted web `reverseLastGrn` into `InventoryService::reverseLastGrn()` (window guard throws; web delegates). BUG FIXED while extracting: expense-void matched only `grn_number`, which web-created GRN expenses never set — now matches both historical source forms, and skips expenses already taken over by a vendor invoice. New API `DELETE /inventory/purchase-orders/{po}/grn/last` (admin); "Undo last GRN" action on the mobile PO detail.

## Slice 7 — Reports parity ✅ CODE-COMPLETE (untested)
- **New `app/Services/Analytics/ReportMetricsService.php`** — ONE definition of collected (InvoicePayment), outstanding (draft+partial), appointmentsDone ('done'), collectionsSeries, resolveRange (7|30|90|365|custom). Optional branch scoping.
- Web `ReportsController::buildRevenueData` collected/outstanding → service. API `ReportController::overview` → service (was Receipt-summed + all-non-cancelled outstanding — web and mobile now show the SAME totals), gained `?period=` support + a `range` block (collected/appointments/done/new patients) + range-driven series (capped 90 points).
- Flutter Reports screen: 7/30/90/365 chips, "Selected range" KPI cards, chart follows the range.
- **Dashboard parity**: API dashboard now returns finance (today_revenue/outstanding_balance/count), lab (pending/overdue), and the web's 3 alert rules (with `key` for client routing). Flutter Home gained an alert strip (only when something needs attention) + one dense pulse line (₹in / ₹due / lab) — deliberately NOT a stat-card wall (Sumit removed those before; simple-not-busier).
- DEBT (documented): huddle period report still computes from FinanceTransaction with its own status set — web-internal divergence, migrate to ReportMetricsService in a later pass.

## Slice 8 — Clinical gaps ✅ CODE-COMPLETE (untested)
- **TP revert consolidated**: `TreatmentPlanAcceptanceService::revert()` (accepted-check + billing guard + StaffActivityLog with reason); web + API delegate. API previously had NO billing guard and NO audit.
- **TP items**: API store/update now accept + persist `items.*.treatment_id` and `items.*.consent_required` (web parity; were silently dropped).
- **Consultation delete**: API `DELETE /consultations/{id}` (soft, branch-scoped, module:patients gate) + Delete entry in the mobile consultation menu with confirm.
- **Inline Rx/instructions**: same-issue/minor/emergency API workflows accept `prescriptions`/`instructions` arrays (same encrypted-array columns web writes).
- **Clinical files**: API PUT/DELETE `/patients/{p}/clinical-files/{f}` mirroring web ClinicalFileController update/destroy (full metadata field set). Client methods ready (`updateClinicalFile`/`deleteClinicalFile`); library-tab edit UI = next session.

## Slice 9 — PRE/WhatsApp ◐ MOSTLY COMPLETE
- **Consent-gated WhatsApp send (Sumit's decision)**: new `Api/V1/WhatsappController::send` → `OutboundMessageService::sendText` (consent gate runs inside) + Timeline log; route under module:patients. Flutter `_composeWhatsapp` sheet replaces the deep-link wherever a patient_id exists (Today's Actions, opportunities, recalls, missed calls); leads (no patient record → no consent to check) keep the deep-link fallback, documented in code.
- **select_all bulk-dismiss**: API mirrors web (chunkById over the same filters); Flutter "Dismiss ALL matching" app-bar action with confirm — the 1,810-item backlog is now clearable from mobile.
- **Orphan removed**: `bulk-whatsapp` route + method deleted (no web equivalent since 07-06, no caller).
- **Notifications (4th session)**: new `Api/V1/NotificationsController` (index auto-marks read like web, unread, markRead, markAllRead) + `notifications_screen.dart` + bell-with-badge on Home.
- **Communication tab (4th session)**: new `GET /patients/{p}/whatsapp/thread` (wa_threads + messages + consentGate verdict, zeroes unread like web); new `whatsapp_chat_screen.dart` (bubbles, gate banner, consent-gated reply box); "Communication → Open chat" card on the relationship profile.

## Device-testing feedback (2026-07-15, Sumit's phone) — ✅ ALL THREE FIXED (untested)
1. **Reports chart** — `_BarChart` rewritten: >10 points switches to compact mode (no per-bar labels, 1px gaps, zero-days as flat gray stubs, 4-5 sparse horizontal "15 Jul"-style date labels); summary line "Total ₹X · Best <date> ₹Y" replaces the per-bar amounts; ≤7 points keeps the old per-bar day/value labels.
2. **Prescriptions list-first** — new `GET /api/v1/prescriptions` (clinic-wide, branch-scoped, ?search= on patient name/phone/code/Rx number, paginated) + `PrescriptionsHomeScreen` (search bar, infinite list, status pills, tap→detail, "New Rx" FAB → old pick-patient→write-pad flow). Home tile now opens the list instead of a patient picker.
3. **Bottom sheets hidden behind system nav bar** — new `sheetInsets(context)` helper in ui/widgets.dart (viewInsets.bottom + viewPadding.bottom); ALL ~29 sheet paddings across 14 screen files switched from bare `viewInsets.bottom` to it (add_appointment, create_invoice, huddle ×4, inventory_implant, inventory ×3, inventory_settings, invoice_detail, patient_list, patient_picker, profile, relationship ×10, treatment_plan ×2, prescription). Save/Cancel rows now clear gesture-nav and 3-button nav bars.
4. **Calendar overlap (2nd round)** — `_DayColumn` appointment blocks were all full-width (`left:3,right:3`), so concurrent appointments painted on top of each other. Implemented web-calendar column-split: `_ApptLayout` + `_layoutAppointments()` (sort → transitive-overlap clusters → greedy first-free-column; ≥24-min visual window so tiny slots still split), `LayoutBuilder`-derived widths, slimmer padding/font when sharing width. Covers day AND 3-day views (same widget).
5. **Prescriptions "unable to load" (open)** — no server exception logged → suspected 404 = phone pointed at a server without the new /prescriptions route (VPS?). Diagnostic sent: check app Server URL + open `<url>/prescriptions` in phone browser (Unauthenticated = route ok / 404 = wrong server). SIDE FINDING: laravel.log shows recurring `MissingAppKeyException` pairs (production channel, every few minutes — cron/scheduled artisan runs without .env/APP_KEY; e.g. cron/ scripts). Investigate separately — scheduled jobs are silently dying.

## Slice 10 — ✅ Final report written
`docs/mobile-completion-sprint-final-report.md` — completed modules, screens, APIs, backend changes, services, debt, web-only justifications, build verification, migration steps. flutter analyze re-run on Sumit's machine is the last gate.

## Verification needed (Sumit's machine — nothing run yet)
```bash
# Laravel (no migrations required — all code-only so far)
cd "E:\Dentfluence\Dentfluence_OS\Dentfluence Web"
php -l app/Services/Procurement/VendorInvoiceService.php
php -l app/Services/LabCaseTransitionService.php
php -l app/Services/WalletService.php
php -l app/Http/Controllers/VendorInvoiceController.php
php -l app/Http/Controllers/Api/V1/VendorInvoiceController.php
php -l app/Http/Controllers/LabController.php
php -l app/Http/Controllers/Api/V1/LabController.php
php -l app/Http/Controllers/Api/V1/BillingController.php
php -l app/Http/Controllers/Api/V1/AuthController.php
php -l app/Http/Controllers/Finance/WalletController.php
php -l app/Http/Controllers/BillingController.php
php artisan route:clear && php artisan config:clear
php artisan app:crawl-routes   # optional page smoke-test

# Flutter
cd "E:\Dentfluence\Dentfluence_OS\dentfluence_mobile"
flutter pub get
flutter analyze
```

## Additional php -l files (Slices 3-6)
```bash
php -l app/Services/AppointmentService.php
php -l app/Http/Controllers/Api/V1/AppointmentController.php
php -l app/Http/Controllers/Api/V1/PatientProfileController.php
php -l app/Http/Requests/Api/V1/WalkInRequest.php
php -l app/Services/Inventory/InventoryService.php
php -l app/Http/Controllers/InventoryController.php
php -l app/Http/Controllers/Api/V1/InventoryController.php
php -l app/Http/Controllers/Api/V1/InventorySettingsController.php
php -l app/Http/Controllers/Api/V1/LabController.php
php -l app/Services/Analytics/ReportMetricsService.php
php -l app/Services/TreatmentPlan/TreatmentPlanAcceptanceService.php
php -l app/Http/Controllers/Api/V1/WhatsappController.php
php -l app/Http/Controllers/Api/V1/ReportController.php
php -l app/Http/Controllers/Api/V1/DashboardController.php
php -l app/Http/Controllers/Api/V1/ConsultationController.php
php -l app/Http/Controllers/Api/V1/TreatmentPlanController.php
php -l app/Http/Controllers/Api/V1/RelationshipMissedCallsController.php
php -l app/Http/Controllers/ReportsController.php
php -l app/Http/Controllers/TreatmentPlanController.php
php -l routes/api.php
```

## Behavioural changes to test manually
1. Mobile vendor invoice against a received PO → ONE Finance expense (taken over), vendor outstanding delta-adjusted.
2. Cancel a wallet-part-paid invoice from mobile → wallet credit returns.
3. Mobile advance → appears in Finance cashbook + audit log; non-permitted role gets 403.
4. Mobile record payment can send `wallet_used` (UI field comes in Slice 4).
5. Lab transition from API → task chain + expense on final_received.
6. 2FA account can log in on mobile (code prompt appears).
7. Expired/revoked token → any API call returns user to login screen.
8. Password change on mobile requires current password.
9. Overlapping booking on mobile → "Book anyway?" flow works.
10. Web regression: vendor-invoice create/delete, lab transitions, advance receive, invoice cancel/delete still behave identically (they now run through the shared services).
11. Reschedule from mobile: pick date+time → moves; onto a clash → "Reschedule anyway?" works; onto doctor leave → hard-blocked.
12. Delete appointment from mobile (soft delete, matches web calendar menu).
13. Doctor/status filter on the mobile schedule.
14. Record payment partly from wallet on mobile → wallet debited, receipt correct, web patient ledger agrees.
15. Edit a payment date on mobile → receipt date + finance ledger date follow.
16. Wallet tab on patient profile shows real ledger rows (was blank descriptions before).
17. Create a lab case from mobile (draft + order-placed variants) → appears on web board; order_placed creates the dispatch task.
18. Transition a lab case from mobile through to final_received → tasks roll, notifications fire, lab expense appears in Finance.
19. Quick stock-add with a unit price from mobile → item purchase price updates, movement has cost (web + mobile identical now).
20. Create a product from mobile → cost_per_usage computed (was 0).
21. Create + deactivate a vendor from the mobile Vendors screen (FAB + toggle); archive a product from item detail.
22. Undo last GRN from the mobile PO detail (inside the correction window; outside → clear 422 message).
23. Reports: switch 7/30/90/365 on mobile → "Selected range" totals MATCH the web Reports page for the same range (this is the headline parity test).
24. Mobile home shows the alert strip when lab is overdue / >5 outstanding invoices; pulse line matches web dashboard numbers.
25. Revert an accepted plan on mobile: blocked with clear message if billed; success writes the staff-activity audit row.
26. Delete a consultation from mobile → disappears on web too (soft delete).
27. WhatsApp a patient from Today's Actions on mobile → consent-gated; without consent → 422 reason shown; with consent → message in web inbox + patient timeline.
28. Missed calls "Dismiss ALL matching" clears the whole filtered backlog (not just the page).
