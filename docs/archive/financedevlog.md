# Dentfluence — Finance & Billing Rebuild Dev Log
> Detailed planning and progress tracker for the full billing/finance module rebuild.
> Discussed and scoped: 2026-06-05

---

## 🧠 CONCEPT: How Billing Works in Dentfluence

### Role Separation (Critical)
- **Doctor** — clinical only. Records consultation, creates treatment plan, starts treatment visits, logs tooth number and material/option chosen. **Never touches prices, discounts, invoices, or payments.**
- **Front Desk** — billing only. Sees prompts triggered by doctor actions. Builds invoices, applies discounts, collects payments, issues receipts and final bills.
- **Finance Module** — mirror of all billing transactions. Every invoice/receipt/payment auto-duplicates here.

---

### Patient Billing Flow

```
1. Patient walks in → Doctor records Consultation + creates Treatment Plan (A/B/C options)
        ↓
2. Billing prompt appears in patient profile:
   "Bill patient for: Consultation + X-Ray"   OR   "Offer AOCP Membership"
        ↓
3a. Patient takes MEMBERSHIP → Front desk generates invoice
    → Patient auto-enrolled in AOCP tier
    → Membership benefits auto-applied to all future invoices
    (free consultation, free X-ray, free single scaling, 10% discount, etc.)

3b. No membership → standard billing continues
        ↓
4. Patient selects Treatment Plan (A, B, or C)
        ↓
5. Doctor starts treatment → selects treatment + options (e.g. RCT + Ceramic Crown)
   + logs tooth number in Treatment Visit
        ↓
6. Billing prompt auto-generated for front desk:
   "Bill for: [Treatment] + [Material Option] — Tooth #X"
        ↓
7. Front desk opens invoice builder:
   - Line items (mandatory + optional/selected by doctor)
   - Membership benefits auto-applied (with override option)
   - Coupon code (optional, one per invoice)
   - Wallet credit (optional, combine with coupon)
        ↓
8. Invoice generated (UNPAID)
        ↓
9. Patient pays in parts → Receipt issued per payment
        ↓
10. 100% paid → Final Bill auto-generated
        ↓
11. All transactions mirror to Accounts & Finance module
```

---

### Discount & Credit System

| Layer | Who Controls | Rules |
|---|---|---|
| Membership benefits | Auto (system) | Per tier, overridable by front desk |
| Coupon Code | Admin creates in Finance Settings | Single or multi-use per patient, configurable. Max 1 coupon per invoice. |
| Wallet Credit | Admin adds (promotional) or system adds (refund/top-up) | Can combine with coupon on same invoice |

**Wallet — Two credit types:**
- **Promotional** — has expiry date (set by admin when crediting)
- **Patient top-up / Refund** — no expiry, carries over forever
- System consumes expiring credits first (FIFO by expiry)
- Wallet expiry behaviour configurable in Finance Settings

---

### AOCP Membership
- Currently single tier; architecture supports multi-tier later (e.g. Family plan)
- Benefits stored per tier (not hardcoded)
- Enrollment triggered from billing, not from a separate module

---

### Invoice Lifecycle States
`pending` → `partial` → `paid` → `final_bill_generated`

- **Invoice** = unpaid or partially paid
- **Receipt** = issued per payment (can be multiple)
- **Final Bill** = auto-generated when 100% paid

---

## 📦 PHASES

### F1 — Database Foundation
**Migrations + Models + Relationships**
~15-18 tables covering the full billing ecosystem.

**Tables to create:**
- `treatment_plan_items` — line items per plan, with mandatory flag + options JSON
- `treatment_visits` (may already exist — audit first)
- `treatment_visit_items` — what doctor selected (procedure + material + tooth no.)
- `billing_prompts` — auto-generated triggers for front desk (type, status, patient_id, visit_id)
- `invoices` — header record (patient, status, total, discount, wallet_applied, coupon_id)
- `invoice_line_items` — per treatment item on invoice
- `payments` — partial payments linked to invoice
- `receipts` — one per payment
- `final_bills` — generated when invoice fully paid
- `memberships` — AOCP tiers definition + benefit rules
- `patient_memberships` — enrollment record per patient
- `wallets` — one per patient, total balance
- `wallet_transactions` — ledger of all credits/debits (type: promotional/permanent, expiry nullable)
- `coupon_codes` — admin-generated, rules stored as JSON
- `coupon_usage` — tracks per-patient usage
- `finance_transactions` — mirror of all billing events for accounts module

**Est: 700–900 lines | Split: one migration file per table**
**Status: ✅ Done — 2026-06-05**

---

### F2 — Doctor Side (Treatment Plan + Visit)
**What doctor sees and does — no billing UI**

- Treatment Plan builder: add items, mark mandatory, add material options
- Treatment Visit: select active plan → pick treatment items → log tooth number + material chosen → mark as started
- Auto-fires `billing_prompt` record on visit save

**Controllers:** `TreatmentPlanController` (extend existing), `TreatmentVisitController` (extend existing)
**Views:** treatment plan item form, visit item selector, tooth chart integration
**Est: 600–800 lines**
**Status: ✅ Done — 2026-06-05**

---

### F3a — Billing Prompts + Invoice Builder
**Front desk: seeing prompts and building invoices**

- Billing prompt list in patient profile dashboard
- Invoice builder: line items pulled from visit, mandatory locked, optional selectable
- Membership benefit auto-apply with override toggle
- Coupon code input + validation
- Wallet credit selector (shows balance, expiring first)
- Invoice save (status: pending)

**Est: 700–900 lines**
**Status: ✅ Done — 2026-06-05**

---

### F3b — Payments, Receipts, Final Bill
**Front desk: collecting money and closing invoices**

- Partial payment form (amount, method, date)
- Receipt auto-generation per payment
- Invoice status update logic (pending → partial → paid)
- Final bill auto-generation on 100% payment
- Print/PDF for invoice, receipt, final bill

**Est: 500–600 lines**
**Status: ✅ Done — 2026-06-05**

---

### F4a — AOCP Membership Module
**Enrollment, tiers, benefits auto-apply**

- Membership tier admin (create tier, define benefits)
- Patient enrollment from billing prompt
- `MembershipBenefitService` — auto-applies free items + % discounts to invoice
- Override flag stored on invoice line item level

**Est: 400–500 lines**
**Status: ✅ Done — 2026-06-05**

---

### F4b — Wallet + Coupon Engine
**Credit wallet system + coupon code system**

- Wallet ledger per patient (2 credit types, FIFO expiry)
- Admin wallet credit form (promotional with expiry / permanent)
- Wallet debit on invoice payment
- Coupon code admin panel in Finance Settings
- Coupon validation on invoice (single/multi-use check, expiry, treatment scope)
- `CouponService` + `WalletService`

**Est: 500–700 lines**
**Status: ✅ Done — 2026-06-05**

---

### F5 — Finance Mirror + Accounts Module
**Auto-duplication to accounts, ledger views, reporting**

- `FinanceTransactionObserver` — fires on invoice/payment/receipt events
- Finance module: income ledger (from billing), expenses, payroll wired up
- Basic revenue reports: daily/monthly collections, outstanding invoices
- GST-ready line item tagging

**Est: 500–700 lines**
**Status: ⬜ Not started**

---

## 📊 SIZE SUMMARY

| Phase | Description | Est. Lines | Risk |
|---|---|---|---|
| F1 | DB Foundation | 700–900 | Low (split per file) |
| F2 | Doctor Side | 600–800 | Medium |
| F3a | Invoice Builder | 700–900 | High |
| F3b | Payments + Receipts | 500–600 | Medium |
| F4a | Membership | 400–500 | Medium |
| F4b | Wallet + Coupons | 500–700 | Medium |
| F5 | Finance Mirror | 500–700 | Low-Medium |
| **Total** | | **~3900–5100 lines** | |

---

## 📋 SESSION LOG

| Date | Phase | What was done |
|---|---|---|
| 2026-06-05 | Planning | Full billing concept scoped. Roles defined (doctor vs front desk). Invoice lifecycle, discount layers, wallet types, AOCP membership architecture all confirmed. F1–F5 phases defined. |
| 2026-06-05 | F1 | DB Foundation complete. 9 migrations + 8 new models + updated Invoice/TreatmentVisit/TreatmentPlan. Tables: treatment_visit_items, billing_prompts, receipts, final_bills, wallets, wallet_transactions, coupon_codes, coupon_usage, + altered invoices with wallet/coupon/membership columns. |
| 2026-06-05 | F2 | Doctor side complete. 5 files changed. TreatmentPlanController: getItems() AJAX endpoint. TreatmentVisitController: visit_items validation, saveVisitItems() method, BillingPrompt auto-fire on store/update. PatientProfileService: eager load visitItems. Visit form: plan dropdown, procedure selector (plan items clickable + custom items), no billing fields for doctor. Summary bar: billing items pending/billed counts. |
| 2026-06-05 | F3b | Payments + Receipts + Final Bill complete. `recordPayment()` enhanced: auto-creates Receipt (with before/after balance snapshot) + auto-generates FinalBill on full payment. New routes: billing.receipt, billing.finalBill. New controller methods: showReceipt(), showFinalBill(). New views: receipt.blade.php (A5 printable), final-bill.blade.php (full settlement doc). show.blade.php updated with receipt links per payment row + Final Bill card. No migrations needed — all models/tables were from F1. |
| 2026-06-05 | F4a | AOCP Membership complete.
| 2026-06-05 | F4b | Wallet + Coupon engine complete. WalletService: credit(), debit() (FIFO promo→permanent), refund(), summary(). CouponService: validate(), apply(), resolveFromRequest(). Finance/CouponController + views (index/form). Finance/WalletController + views (index/show/credit). BillingController: wallet actually debited on invoice store. Routes: finance.coupons.* + finance.wallets.*. No new migrations. | New models: FinanceMembershipPlan, FinancePatientMembership. New service: MembershipBenefitService (free item matching by name + % discount on remainder). New controller: Finance/MembershipController (index/create/store/edit/update/toggle/destroy). New views: finance/membership/index.blade.php, finance/membership/form.blade.php. PatientProfileService: loads activeMembership + membershipPlans. show.blade.php (patient profile): membership panel in billing sidebar — active status, days remaining, enrollment modal. BillingController: membershipInfo passed to create/createFromPrompt; enrollMembership() POST; membershipBenefits() AJAX. billing/form.blade.php: auto-applied membership block replaces manual field; overrideMembership() JS. Routes: 7 finance.membership.* routes + billing.membership.enroll + billing.membership.benefits (before resource). Finance dashboard nav: Membership link added. No new migrations. |

