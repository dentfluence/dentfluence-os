# Phase A — Security & Hardening Audit

**Date:** 2026-06-29 · **Scope:** Read-only audit of the existing codebase (no code changed).
**Covers Phase A items:** 0.4 (security & audit hardening), 8.1 (encryption), 8.2 (MFA + session), 0.3 (API harden).
**Goal of this doc:** establish exactly what is built vs missing so we fix the right things, in the right order, before any PHI touches the cloud.

> **Read this first:** the app is functionally rich but was built local-first (Laragon, single trusted machine). Almost every gap below is a *cloud-exposure* gap — things that were fine on a private LAN but are not safe once the app is internet-facing with real patient data. That is the whole point of Phase A.

---

## 1. Executive summary

| Domain | State | One-line verdict |
|--------|-------|------------------|
| RBAC / authorization | 🟡 Partial | Solid DB-driven role model + web coverage; **API write endpoints are under-gated** and branch isolation is manual (leak-prone). |
| Encryption at rest | 🔴 Weak | Only marketing OAuth tokens are encrypted. **All patient PHI, clinical notes, and bank details are plaintext.** |
| Encryption in transit | 🟡 Partial | HTTPS set in prod `.env`, but **no app-level force-HTTPS, no HSTS, no CSP**, web routes get no security headers. |
| MFA | 🔴 Not started | No TOTP/2FA. SMS-OTP login exists but is an *alternative login*, not a second factor, and the gateway isn't wired. |
| Session security | 🟢 Mostly OK | Session regeneration on login/logout is correct; needs forced secure-cookie + absolute timeout + "log out all devices". |
| Audit logging | 🟡 Partial | Good coverage for consent/billing/Rx writes, but **logins, record-reads, and role changes aren't logged**. |
| Tamper-evidence | 🔴 Weak | Only `consent_logs` is hash-chained/append-only. **All other audit tables are ordinary editable rows.** |
| API hardening | 🟡 Partial | Good input validation; **tokens never expire, full `*` abilities, login not throttled.** |

**Bottom line:** four CRITICAL items must be closed before cloud launch — public-disk clinical files, plaintext PHI, non-expiring full-access tokens, and unguarded clinical-write API endpoints. None are large; most are 1–3 days each.

---

## 2. Findings ranked by severity

### 🔴 CRITICAL — fix before any cloud exposure

1. **Clinical files stored on the PUBLIC disk.** `ClinicalMediaService::register()` writes x-rays, intake-form scans, receipts and photos to the `public` disk, served at `/storage/clinical/{patient_id}/...`. Anyone who guesses/enumerates a patient id can download medical images with no auth. *(app/Services/Cms/ClinicalMediaService.php; config/filesystems.php)*
2. **All patient PHI/PII stored plaintext.** Only `PlatformConnection` OAuth tokens use `Crypt`. Plaintext includes: phone, email, address, ABHA number/address, medical conditions, current medications, chief complaint, clinical notes, and **bank account / IFSC / UPI** (finance + HR staff). DPDP treats this as sensitive personal data. *(app/Models/Patient.php, FinanceBankAccount, HrStaffProfile, Consultation)*
3. **Sanctum tokens never expire and carry full `*` abilities.** `config/sanctum.php` `expiration => null`; `createToken($deviceName)` is called with no abilities, so a stolen mobile token = permanent full API access with no forced re-auth and no scope limits. *(config/sanctum.php; Api/V1/AuthController::login)*
4. **Clinical-write API endpoints are not role-gated.** Any authenticated user (e.g. a receptionist account) can create/edit/cancel **prescriptions**, create/edit **consultations** (all 4 workflows), and create/update/**delete treatment visits**. Patients/billing/inventory writes *are* gated; clinical writes are the gap. *(routes/api.php:96–152)*
5. **General audit logs are not tamper-evident.** Only `consent_logs` is append-only + hash-chained (DPDP 5.6, blocks UPDATE/DELETE in the model). `AuditLog`, `BillingAuditLog`, `PrescriptionAuditLog` and `finance_audit_log` are ordinary rows an admin or a compromised DB user can silently edit or delete — the finance migration even *claims* "immutable trail" but nothing enforces it. *(app/Models/AuditLog.php etc.)*

### 🟠 HIGH

6. **No MFA (Phase A item 8.2).** No TOTP/authenticator support. `MobileOtpController` is a passwordless SMS-login alternative, not a second factor, and the SMS gateway is unconfigured (OTPs are written to the log file). *(app/Http/Controllers/Auth/MobileOtpController.php)*
7. **Login not throttled against brute force.** API login sits under the global `throttle:120,1` only (~7,200 guesses/hr) with no dedicated limiter; web login has no rate limiter at all. *(routes/api.php:40; app/Http/Controllers/AuthController.php)*
8. **Branch/clinic isolation is manual.** Multi-branch data is scoped by hand-written `where('branch_id', …)` in each controller. No global scope/trait enforces it, so any new or forgotten query leaks cross-branch patient data. *(e.g. Api/V1/PatientController::findInBranch)*
9. **No app-level HTTPS enforcement, no HSTS, no CSP.** No `URL::forceScheme('https')`, no redirect middleware, no HSTS. `SecureApiHeaders` runs on API routes only (and omits HSTS/CSP); **web routes get no security headers at all** → clickjacking / MIME-sniff / mixed-content exposure. *(bootstrap/app.php; SecureApiHeaders.php)*
10. **Security-relevant events aren't logged.** No audit entry for login, logout, **failed logins** (no brute-force signal), patient-record **views**, or user role/permission changes. API login only bumps `last_login_at`. *(Api/V1/AuthController)*

### 🟡 MEDIUM

11. **Weak, inconsistent password policy.** `ProfileController` uses `Password::min(8)`, but `HrStaffController` and `SettingsController` use bare `min:6`. No mixed-case/number/symbol/breached-password rules anywhere. *(noted controllers)*
12. **CORS defaults to `*`.** `config/cors.php` `allowed_origins` falls back to `*` if `CORS_ALLOWED_ORIGINS` isn't set. Fine today (no browser SPA, `supports_credentials=false`), but must be locked down before the Next.js web app ships.
13. **No model policies / authorize() calls.** Authorization is 100% middleware-based; `app/Policies` is empty. Any endpoint that forgets its middleware has no second line of defence.
14. **Empty config placeholders.** `config/security.php` and `config/permissions.php` are 0-byte files — permissions are entirely DB-driven, so a bad migration/seed silently disables permission checks with no config fallback.

### 🟢 LOW / already-good

- Session **fixation** is handled correctly (`session()->regenerate()` on login, `regenerateToken()` on logout).
- Input validation is **good**: FormRequests + `$request->validate()` are used widely; no `$request->all()` mass-assignment into create/update was found.
- `BCRYPT_ROUNDS=12`; `session.serialization=json` (no PHP-gadget risk); prod `.env` sets `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE`.
- The **DPDP consent hash-chain is genuinely well built** — use it as the template for #5.

---

## 3. Ordered remediation plan

Sequenced by *risk ÷ effort*. Each step is small, additive, and flag-safe. Nothing destructive. Suggested grouping into four short sprints — we tackle **one step per message** so nothing gets truncated.

### Sprint 1 — "Stop the bleeding" (the 4 CRITICALs) — maps to 8.1 + 0.3 + 0.4
1. **Private clinical files + authenticated download route.** Switch `ClinicalMediaService` to the private `local` disk; serve via an `auth`+`module`-gated controller that streams the file and writes an audit entry. Migrate existing files out of `public`. *(Finding #1)*
2. **Encrypt PHI/PII at rest.** Add Laravel `encrypted` casts to the sensitive columns on `Patient`, `PatientIdentifier`, `FinanceBankAccount`, `HrStaffProfile`, `Consultation`. Ship a one-off backfill command to encrypt existing rows. Note: encrypted columns can't be `WHERE`-matched — keep a separate hashed/blind-index column for phone/ABHA lookups. *(Finding #2)*
3. **Token expiry + scoped abilities + revoke-all.** Set `sanctum.expiration` (e.g. 30 days, configurable); issue tokens with role-appropriate abilities instead of `*`; add a "log out all devices" endpoint. *(Finding #3)*
4. **Role-gate clinical-write API endpoints.** Add `api.role:admin,doctor` (and pharmacist where relevant) to prescription, consultation, and treatment-visit write routes. *(Finding #4)*

### Sprint 2 — Tamper-evident audit + event logging — maps to 0.4
5. **Make all audit tables append-only + hash-chained**, reusing the proven `consent_logs` pattern (block UPDATE/DELETE in the model; `prev_hash`→`hash` chain; a `verifyChain()` per stream). Apply to `AuditLog`, `BillingAuditLog`, `PrescriptionAuditLog`, `finance_audit_log`. *(Finding #5)*
6. **Log security events:** login, logout, failed login (with IP/UA), patient-record view, and role/permission changes. *(Finding #10)*

### Sprint 3 — Authentication hardening — maps to 8.2 + session
7. **MFA (TOTP).** Add authenticator-app 2FA (enrol, verify, recovery codes), enforced for admin/clinical roles, optional for others. Wire the existing SMS-OTP path as a fallback factor once a gateway is configured. *(Finding #6)*
8. **Throttle logins** — dedicated rate limiter on web + API login (e.g. 5/min/IP + per-account lockout). *(Finding #7)*
9. **Session/password polish:** force `SESSION_SECURE_COOKIE=true` + `same_site` review in prod; add an absolute (not just idle) session timeout; standardise password rules on `Password::min(8)->mixedCase()->numbers()->uncompromised()` everywhere. *(Findings #9-partial, #11)*

### Sprint 4 — Defence-in-depth — maps to 0.4 + 0.3
10. **Force HTTPS + full security headers.** `URL::forceScheme('https')` in prod; a `SecureWebHeaders` middleware on the web group; add HSTS + a starter CSP to both web and API. *(Finding #9)*
11. **Central branch-isolation scope.** Add a `BelongsToBranch` global scope/trait on `Patient`, `Consultation`, `Prescription`, `TreatmentVisit`, etc., so isolation is automatic, not per-query. *(Finding #8)*
12. **Lock down CORS** to an explicit allow-list before the web SPA; populate `config/security.php` as the single source for these toggles. *(Findings #12, #14)*

---

## 4. What's already good (don't re-do)

Input validation, session-fixation handling, bcrypt cost, JSON session serialization, the DPDP consent hash-chain, and the web-side `module:` permission coverage are all in good shape. Phase A is about extending these patterns to the gaps above — not rebuilding.

---

## 5. Suggested starting point

**Sprint 1, Step 1 (private clinical files)** is the highest risk-reduction for the least code and is fully self-contained. Recommend we start there next.
