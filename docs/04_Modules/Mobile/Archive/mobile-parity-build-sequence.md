# Mobile Parity Build Sequence

Goal: bring `dentfluence_mobile` to full feature parity with the web app, module by module, with **no field omission** — every form on mobile carries the same fields as its web equivalent, just laid out for a phone. Sequence is ordered by daily-use dependency, not by module size.

Audit baseline: 2026-07-05 full code-level comparison of web (`Dentfluence Web`) vs mobile (`dentfluence_mobile`). See conversation for the full gap table; this doc tracks the build order and status only.

Status legend: `[ ]` not started · `[~]` in progress · `[x]` done, migrated, tested

---

## Phase 1 — Core Daily Clinical Loop
*Everything a receptionist/dentist touches every single day. Has to be complete and trustworthy before anything else matters.*

- [ ] **Patients** — add missing intake fields (dob_unknown, tags, emergency contact, medical/dental conditions, habits, referral/source, state/pincode), edit screen, deactivate/delete
- [ ] **Appointments** — edit/reschedule/delete (currently create-only; no API route even exists yet)
- [ ] **Appointment Calendar view** — web uses a full day/week/month FullCalendar grid (`appointments/index.blade.php`); mobile only has a flat list. A grid/day-view matters for managing chair-time across dentists/branches.
- [ ] **Daily Huddle** — board/tasks/comms exist; add Period Reports (Weekly/Monthly/Quarterly/Annual), Huddle Comments, Huddle Settings, Yesterday's Flow combo card
- [ ] **Consultation** — finish New-workflow gaps (photo/X-ray/scan uploads, inline Rx panel, previous-visit context, Save & Start Treatment Plan), add COHA (5th workflow, currently missing entirely). Same Issue / Minor Visit / Emergency are already at parity.
- [ ] **Treatment Plan** — FDI tooth-chart picker (currently free-text), delete plan/item, Bill-from-Plan hookup, material_variants
- [ ] **Treatment Visit** — procedure sub-forms (RCT/Implant/Filling/Scaling/Extraction/Crown — backend ready, UI omits them), lab/prescription tie-in, print/PDF
- [ ] **Prescriptions** — WhatsApp-send action (only real gap; PDF already at/above web parity)

## Phase 2 — Billing / Money
*Revenue-critical. Depends on the tooth-chart component built in Phase 1.*

- [ ] Manual discount (type/value/reason)
- [ ] Wallet apply-to-invoice (promo/permanent split, treatment-restricted credits) + wallet refund/withdraw
- [ ] EMI at the UI layer (backend already supports it; screen currently blocks it)
- [ ] Auto-qty from tooth count, FDI tooth chart in billing, partial billing from Treatment Plan
- [ ] Treatment-master linkage (treatment_id, GST — currently hardcoded 0)
- [ ] Coupon codes, membership auto-discount
- [ ] Invoice edit / void-receipt / payment-date edit

## Phase 3 — Lab + Inventory Completion

- [ ] Lab Vendor management (CRUD, contacts, rate cards, scoring)
- [ ] Lab Reconciliation (monthly bill-matching, dispute, auto-AP)
- [ ] Lab cases: ratings, templates, duplicate, edit/delete/restore, print, vendor picker at creation
- [ ] Inventory: Vendor Invoice processing (Invoice/AP leg)
- [ ] Inventory: Stock Count (15-day physical audit cycle)
- [ ] Inventory: product master + vendor CRUD (currently view-only on mobile)

## Phase 4 — Reports Hub
*Only meaningful once Phases 1–3 are producing real data.*

- [ ] Full parity with web's two reporting hubs (Reports: 6 tabs — appointments/revenue/patients/treatment/lab/inventory; Finance Reports: 11 tabs incl. Excel/PDF export). Mobile currently has one summary screen.

## Phase 5 — Settings (full admin console)

- [ ] Clinic/team/clinical masters, finance/EMI config, printing, banking, tags, inventory, feature-flags, import-export, PRE settings. Mobile is currently a thin device-prefs shell (profile, server URL, theme, 3 local toggles).

## Phase 6 — Security / 2FA
*Do this before opening the more sensitive modules below to mobile.*

- [ ] TOTP 2FA (QR + secret + recovery codes), login challenge, throttling

## Phase 7 — Relationship / CRM (PRE)
*API is already live (`Api/V1/RelationshipController`) — this is UI-only work.*

- [ ] Lead/Opportunity/Recall pipeline boards, convert-to-patient, in-app notifications, Referral Rewards, Analytics

## Phase 8 — Communication OS
*Natural follow-on to CRM.*

- [ ] WhatsApp inbox, Follow-up/Recall/Opportunity engine surfaces, B2B, KPI dashboard, templates

## Phase 9 — DPDP Compliance Tools
*Legal deadline is 13 May 2027 — there's runway, but don't let this slide to dead last.*

- [ ] Consent hash-chain trail UI, Data Rights/DSAR (erasure + correction requests), Breach register, Retention policy

## Phase 10 — ABDM Integration
*Blocked on web-side build — ABDM is currently design-only (docs/abdm/, no code yet). Slot mobile work in once web Phase 1 (identity tables + Branch model + app/Abdm skeleton) lands.*

- [ ] Mirror whatever web ships once it exists: ABHA linking, health ID lookup, FHIR record exchange

## Phase 11 — Marketing Engine, Clinical Library, Reviews, Tags
*Lowest daily urgency. Good candidates to frame as premium/add-on modules once the core app is bulletproof.*

- [ ] Marketing Engine (Publish/Calendar/Brainstorm/Ideas/Campaigns/Library/Assets/Brand Kit/Integrations/Analytics)
- [ ] Clinical Library (patient clinical-files engine, protocol steps)
- [ ] Reviews/Reputation (public rating page reference only — admin dashboard if useful on mobile)
- [ ] Tags (full color/group system — no backend route exists yet either)

---

## Working rule
One phase at a time, one module within a phase at a time. No field omission vs the web Blade form — when in doubt, the Blade view is ground truth. Flag before starting each module if scope looks like it'll exceed one response, per project pre-flight rule.
