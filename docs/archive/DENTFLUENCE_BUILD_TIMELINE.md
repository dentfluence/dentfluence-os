# Dentfluence — Master Build Timeline
> Generated: May 29, 2026
> Rule: 1 session/day · ~200K tokens per session

---

## App Audit: What's Built vs What's Broken

### ✅ COMPLETE (don't touch)
| Module | Status |
|--------|--------|
| Auth (login/logout) | Done |
| Dashboard (real stats) | Done |
| Patients (full CRUD, tags, notes, print) | Done |
| Appointments (full CRUD, queue, status, conflict check) | Done |
| Consultations (full form with photos, scans, diagnosis, treatment-advised, print) | Done |
| Treatment Plans (store, items, destroy) | Done |
| Treatment Visits (store, update, destroy, print) | Done |
| Lab Cases (full CRUD) | Done |
| Inventory (items, products, stock-in/out, POs, vendors, implants, expiry) | Done |
| Huddle (boards, cards, tasks, comments, settings — full module) | Done |
| Settings (clinic, staff, roles/permissions, tags, masters) | Done |
| Communication — Manager Queue | Done |
| Communication — Follow-up Queue + Overdue | Done |
| Communication — Timeline (patient timeline) | Done |

---

### 🟡 HALF-BUILT (stubs / tabs placed / views exist but no backend)
| Module | What's Missing |
|--------|----------------|
| Communication — PRM | add-lead form & PRM settings are stub/coming-soon |
| Communication — Follow-up Calendar | Rendered as "coming soon" |
| Communication — Follow-up Recalls | Rendered as "coming soon" |
| Communication — Opportunities Board | Board view is "coming soon" |
| Communication — Message Templates | Both index and editor are "coming soon" |
| Communication — Tasks (My Tasks, Overdue) | Partial stubs, no real data |
| Content Management — Education | Partial — upload works, manage is incomplete |
| Content Management — Marketing tab | Tab placed, nothing inside |
| Reports | Only appointment reports; no revenue/patient/treatment analytics |

---

### ❌ MISSING / BROKEN ROUTES (app will crash if clicked)
| Module | Problem |
|--------|---------|
| Finance (Dashboard, Income, Expenses, Payroll, GST, Banking, Cashbook, CA Export) | 15 DB migrations created, views exist — but NO routes registered at all. Finance controller exists. Just needs wiring. |
| Billing | Route returns plain string "Coming soon" — view exists at `billing/index.blade.php` |
| Treatments (standalone) | Route returns "Coming soon" |
| CRM | Sidebar links to `crm.index` route — doesn't exist. View exists. |
| Analytics | Sidebar links to `analytics.index` route — doesn't exist anywhere |
| Marketing | Named `marketing` in content-management routes, sidebar expects `marketing.index` — name mismatch → 500 error |

---

### 🐛 LEAKAGES / CODE MESS TO FIX
| Issue | Location |
|-------|----------|
| Duplicate TreatmentVisitController at app root | `app/TreatmentVisitController.php` (should only be in Controllers/) |
| .bak files | `sidebar.blade.php.bak`, `tasks/index.blade.php.bak` |
| Double extension file | `components/prm/stage-badge.blade.php.php` |
| Filename with space | `resources/js/communication/prm-board .js` |
| Duplicate PRM views | `/prm/` folder AND `/communication/prm/` — same views in two places |
| Duplicate services | `/app/Services/Cms/` AND `/app/Services/ContentManagement/` — same services doubled |
| Non-Laravel dirs at root | `rules/`, `services/`, `workflows/` — leftover planning files, not used by Laravel |
| Orphan route file | `routes/communication-s7-s8.php` — loaded in bootstrap, overlaps with `communication.php` |
| `tags-routes.php` | File exists but NOT loaded in `bootstrap/app.php` |

---

## Token Estimate Per Session

One focused session (read files → build feature → test) uses ~150–180K of 200K tokens.
Complex sessions (Finance module with 8 views) may need to split.

**Estimated total sessions: 15**
**At 1 session/day → ~3 weeks to a shippable v1**

---

## Session-by-Session Timeline

---

### SESSION 1 — Codebase Cleanup ✅ DONE
**Goal:** Fix all leakages so app doesn't have random crashes or confusing duplicates.
**Tokens needed:** ~80K (light session, mostly deletes and small edits)

Tasks:
- [x] Delete `app/TreatmentVisitController.php` (root-level dupe)
- [x] Delete `.bak` files (sidebar, tasks/index)
- [x] Delete `stage-badge.blade.php.php` double-extension file
- [x] Rename `prm-board .js` → `prm-board.js` (deleted space version, clean copy kept)
- [x] Delete duplicate `/resources/views/prm/` folder (keep `/communication/prm/`)
- [x] Delete duplicate `/app/Services/Cms/` folder (keep `ContentManagement/`)
- [x] Delete `rules/`, `services/`, `workflows/` root dirs
- [x] Fix route name mismatch: added `marketing.index` redirect → `cms.marketing`
- [x] Add `tags-routes.php` to bootstrap (with proper imports + auth middleware)
- [x] Removed `communication-s7-s8.php` from bootstrap (full duplicate of `communication.php`)
- [x] Fix billing route (wired to BillingController, stub view created)
- [x] Add stub routes for `analytics.index`, `crm.index` so sidebar doesn't crash

**End state:** App loads all sidebar links without 500 errors.

---

### SESSION 2 — Finance Module: Wire Up + Dashboard + Income
**Goal:** Finance module becomes accessible with real data on dashboard and income tab.
**Tokens needed:** ~160K

Tasks:
- [ ] Create `routes/finance.php` and register in `bootstrap/app.php`
- [ ] Wire all Finance views to `FinanceController` methods
- [ ] Finance Dashboard — real stats: total income this month, total expenses, profit, pending payments
- [ ] Finance Income — list all billing entries from `finance_transactions` table
- [ ] Add "Record Payment" modal on Income view (store to finance_transactions)
- [ ] Connect income entries to patient invoices (appointment → payment flow)

**End state:** Finance sidebar works, dashboard shows real numbers, income records visible.

---

### SESSION 3 — Finance Module: Expenses + Payroll
**Goal:** Expense tracking and staff payroll fully functional.
**Tokens needed:** ~150K

Tasks:
- [ ] Expenses view — CRUD for expenses with categories
- [ ] Expense categories management (add/edit in-line)
- [ ] Vendor management (add vendor, link to expenses)
- [ ] Payroll view — list staff, add monthly salary entry, mark paid
- [ ] Staff advance tracking (link to payroll)

**End state:** Clinic can record all outgoing money.

---

### SESSION 4 — Finance Module: GST + Cashbook + Banking + CA Export
**Goal:** Complete the finance module.
**Tokens needed:** ~160K

Tasks:
- [ ] GST view — auto-aggregate from transactions (CGST/SGST breakdowns)
- [ ] Cashbook — daily cash in/out ledger view
- [ ] Banking — bank accounts list, transactions log, reconciliation flag
- [ ] CA Export — date-range filtered PDF/CSV dump of all transactions
- [ ] Finance settings (tax %, clinic GST number, financial year)

**End state:** Finance module is 100% complete.

---

### SESSION 5 — Billing Module
**Goal:** Patient-facing invoices and payment collection.
**Tokens needed:** ~170K

Tasks:
- [ ] Billing index — list all invoices (pending, paid, partial)
- [ ] Create invoice — from treatment plan or manual line items
- [ ] Invoice show/print view (with clinic letterhead from settings)
- [ ] Record payment against invoice (cash, card, UPI, EMI)
- [ ] Mark invoice status (paid/partial/waived)
- [ ] Link invoice to appointment and patient record
- [ ] Remove "Coming soon" stub route

**End state:** Full billing workflow: create → collect → print receipt.

---

### SESSION 6 — CRM + Analytics
**Goal:** Wire CRM view and build basic analytics dashboard.
**Tokens needed:** ~150K

Tasks:
- [ ] CRM index — patient source breakdown, conversion funnel, new vs returning
- [ ] Analytics dashboard route + view (currently crashes sidebar)
- [ ] Key metrics: appointments by type, revenue by treatment, patient acquisition trend
- [ ] Charts using Chart.js (already loaded in reports view)
- [ ] Date range filter (same pattern as reports)

**End state:** CRM and Analytics accessible with real data.

---

### SESSION 7 — Communication: PRM Complete
**Goal:** PRM (lead pipeline) is fully functional end to end.
**Tokens needed:** ~150K

Tasks:
- [ ] Fix PRM add-lead form (remove "coming soon", wire to database)
- [ ] Lead detail drawer — show full lead history, notes, stage
- [ ] Stage move (drag or button) → save to DB
- [ ] PRM settings — configure stages, sources, auto-assign rules
- [ ] Lead → Patient conversion flow (button in lead detail)

**End state:** Full lead pipeline: capture → nurture → convert.

---

### SESSION 8 — Communication: Follow-up Calendar + Recalls
**Goal:** Follow-up calendar view and recalls reminder system functional.
**Tokens needed:** ~140K

Tasks:
- [ ] Follow-up Calendar — show follow-ups on a weekly/monthly calendar grid (replace "coming soon")
- [ ] Calendar week partial already exists — wire it to real data
- [ ] Recalls — patients due for periodic checkup (6-month, annual)
- [ ] Recall rules: configure interval per treatment type
- [ ] Send recall reminder (WhatsApp link / note log)

**End state:** Clinic can see all follow-ups on calendar and track recalls.

---

### SESSION 9 — Communication: Opportunities Board
**Goal:** Treatment opportunity kanban board functional.
**Tokens needed:** ~130K

Tasks:
- [ ] Opportunities Board — replace "coming soon" with real kanban columns
- [ ] Column stages: Identified → Quoted → Accepted → Completed → Lost
- [ ] Cards show patient name, treatment, value
- [ ] Drag-to-move or button-move between stages
- [ ] Opportunity detail (notes, follow-up date, linked patient)

**End state:** Team can track treatment opportunities visually.

---

### SESSION 10 — Communication: Message Templates
**Goal:** WhatsApp/SMS template library is usable.
**Tokens needed:** ~120K

Tasks:
- [ ] Templates index — list all templates (appointment reminder, follow-up, recall, birthday)
- [ ] Template editor — rich text with merge tags ({{patient_name}}, {{date}}, etc.)
- [ ] Template categories (Appointment / Follow-up / Recall / Marketing)
- [ ] Preview rendered template with sample data
- [ ] Link templates to follow-up and recall send actions

**End state:** Staff can pick a template and send via WhatsApp link.

---

### SESSION 11 — Communication: Tasks Complete
**Goal:** Task management fully working (my tasks, overdue, escalated).
**Tokens needed:** ~120K

Tasks:
- [ ] My Tasks view — show tasks assigned to current user (real data)
- [ ] Overdue Tasks view — tasks past due date (real data)
- [ ] Escalated Tasks view — tasks flagged as escalated
- [ ] Task create modal — assign to staff, set due date, link to patient
- [ ] Task status update (complete / snooze / reassign)
- [ ] Task count badge on sidebar

**End state:** Tasks module fully operational for all staff roles.

---

### SESSION 12 — Reports: Full Analytics Dashboard
**Goal:** Reports goes beyond appointments to cover the full clinic picture.
**Tokens needed:** ~160K

Tasks:
- [ ] Revenue report — by period, by treatment type, by doctor
- [ ] Patient report — new vs returning, source breakdown, age/gender distribution
- [ ] Appointment report — already exists, clean up and enhance
- [ ] Lab report — cases by lab, turnaround time, pending vs delivered
- [ ] Inventory report — consumption, low-stock items, expiry alerts
- [ ] Export to PDF/CSV for all report types

**End state:** Owner can see full clinic KPIs in one place.

---

### SESSION 13 — Content Management: Education Complete
**Goal:** Education content library fully usable.
**Tokens needed:** ~130K

Tasks:
- [ ] Education index — browse by category/treatment type
- [ ] Education manage — CRUD for educational items (videos, PDFs, images)
- [ ] Media upload (already partially wired) — complete and test
- [ ] Tag and categorize educational content
- [ ] Share to patient link (generate shareable URL)
- [ ] Link education content to treatment plan items

**End state:** Doctor can share educational content with patients from consultation.

---

### SESSION 14 — Content Management: Marketing + CMS Polish
**Goal:** Marketing tab functional, full CMS polished.
**Tokens needed:** ~130K

Tasks:
- [ ] Marketing tab — show all clinical media tagged as "marketing-ready"
- [ ] Download / export selected images for social media
- [ ] Batch tag media as marketing/clinical/education
- [ ] CMS global search working (search controller exists)
- [ ] Watermark settings working (watermark service exists)
- [ ] Case timeline view functional

**End state:** CMS is a complete internal content library.

---

### SESSION 15 — Final Polish + QA
**Goal:** Fix remaining edge cases, polish UX, ensure nothing crashes.
**Tokens needed:** ~150K

Tasks:
- [ ] Walk every sidebar link and confirm no 500 errors
- [ ] Fix any broken flash messages, missing success/error states
- [ ] Ensure all print views (patient, consultation, visit, invoice) render correctly
- [ ] Role-based access: confirm admin vs staff see the right things
- [ ] Fix any duplicate migration conflicts or missing columns
- [ ] Dashboard — add Finance summary card (today's revenue)
- [ ] Final sidebar cleanup (remove any links to non-existent routes)

**End state:** v1 of Dentfluence is shippable.

---

## Summary

| | Count |
|--|--|
| Sessions total | **15** |
| Calendar days (1/day) | **15 days (~3 weeks)** |
| Est. total tokens | **~2,100,000** |
| Tokens per session budget | **200,000** |
| Comfortable fit per session? | **Yes (most use 120–170K)** |

---

## Priority Order if You Want to Skip Ahead

If you want the most impactful features first (for actual clinic use):

1. Session 1 — Cleanup (must do first, prevents crashes)
2. Session 5 — Billing (money flow is critical)
3. Session 2–4 — Finance (complete financial picture)
4. Session 11 — Tasks (daily staff workflow)
5. Session 7 — PRM (lead tracking)
6. Everything else in order

---

*This file is your single source of truth. Update checkboxes as sessions complete.*
