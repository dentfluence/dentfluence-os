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

## Slice 6 — Inventory/Vendors ◐ BACKEND + CLIENT COMPLETE, UI hooks pending
- **`InventoryService::adjustStock` now THE single implementation** — gained web's unit_price/cost handling (mobile adds no longer log zero-cost movements); web `adjustStock` delegates to it. API validation accepts `unit_price`.
- **`InventoryService::costPerUsage()`** — extracted from web `calculateCostPerUsage`; web delegates; API `storeProduct` now sets `cost_per_usage` (was always 0 for mobile-created products).
- **New endpoints**: `POST /inventory/vendors` (storeVendor + Finance sync), `PATCH /inventory/vendors/{v}/toggle` (never hard-delete), `DELETE /inventory/products/{item}` (soft archive, admin-only — same as web admin.only).
- **Client methods**: `createVendor/toggleVendor/archiveProduct`.
- REMAINING (next session): UI affordances — "New vendor" button + form (9 fields, reuse the existing edit form in `inventory_settings_screens.dart`), activate/deactivate toggle on the vendor row, "Archive" in the product ⋯ menu (admin only). GRN reversal endpoint also still pending.

## Slices 7-10 — NOT STARTED
7. Reports parity: shared `ReportMetricsService` (outstanding: web=draft+partial vs API=all-non-cancelled; collections: 3 different source tables); date-range params; dashboard KPIs.
8. Clinical gaps: consultation delete + inline Rx; clinical-file edit/delete; TP revert billing-guard+audit (extend `TreatmentPlanAcceptanceService`); TP store/update missing fields.
9. PRE/Notifications: notifications API+screen; select_all bulk-dismiss; consent-gated WhatsApp send endpoint for mobile; profile Communication tab; delete orphan bulk-whatsapp route.
10. Final pass: flutter analyze fixes, UI states audit, final report.

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
21. Create + deactivate a vendor via API (UI buttons come next session; test via existing edit screen path or curl).
