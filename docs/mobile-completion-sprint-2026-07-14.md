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

## Slice 3 — Appointments ◐ PARTIAL
Done:
- `allow_overlap` end-to-end: `createAppointment`/`createWalkIn` accept `allowOverlap`; `ApiClient.isOverlapConflict()` detects the server's overridable-overlap 422 (via the 'allow_overlap' token in the message — commented server-side in `AppointmentService::assertSlotIsBookable`); `add_appointment_screen` + `walk_in_screen` show "Book anyway?" / "Check in anyway?" confirms and retry with the override. `WalkInRequest` now validates `allow_overlap` (was impossible to override walk-ins).
- `addWalletCredit` client updated for the new contract (sends `payment_date`, mode list already enum-valid).

REMAINING in Slice 3:
- Reschedule/edit/delete appointment: extract web `AppointmentController::reschedule()/update()/destroy()` (lines ~544-658) into `AppointmentService`, expose `PATCH /api/v1/appointments/{id}/reschedule` + `PUT .../{id}` + `DELETE .../{id}`, add long-press actions in `appointments_screen.dart`.
- Doctor/status filter UI in `appointments_screen.dart` (client params already exist, never passed).
- `AppointmentService::updateStatus` must log `cancelled` like web (audit finding A2) or API should reject `cancelled` via status route.

## Slices 4-10 — NOT STARTED
4. Money completion: wallet_used field in `record_payment_screen.dart` (server+service ready now); wallet ledger view; payment-date edit endpoint (`InvoicePaymentService::updatePaymentDate` exists, unrouted).
5. Lab writes UI: endpoints live + now correct; build create-case/transition/attachment sheets in `lab_screen.dart`.
6. Inventory/Vendors: vendor create/toggle endpoints; item archive; `cost_per_usage` on API product create; `unit_price` on API quick-adjust; GRN reversal.
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
