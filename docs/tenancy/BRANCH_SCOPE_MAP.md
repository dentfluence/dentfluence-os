# Branch Scope Map — which module lives at which level

Written 2026-09-07. Read `STEP1_DISCOVERY.md` first; this sits on top of it.
Nothing here is implemented. This is the contract the migrations and the
scope traits inherit.

> **Naming note.** This is deliberately *not* called `STEP2_SCOPE_MAP.md`.
> "Step 2" in this folder already means the schema run (Files 1–9). This
> document is a different layer: Step 1–4 answer *"does this table carry
> `organization_id`"*. This answers *"and does it also carry `branch_id`, and
> who decides"*. Numbering it Step 2 would collide.

---

## 1. There are THREE axes, not one. Never conflate them.

| Axis | Column | Question it answers | Configurable? |
|---|---|---|---|
| **1. Tenant** | `organization_id` | Does Vardhaman Dental see Tulip Dental's data? | **Never.** Hard wall, fails closed. |
| **2. Branch** | `branch_id` | Does Regency Estate see Tukaram Nagar's data? | **Per module.** This document. |
| **3. Data** | `role_module_permissions.data_scope` | Within Regency, does Dr. A see Dr. B's chair? | Per role. **Already built** (migration `2026_08_26_100001`). |

Worked example, Vardhaman Dental Care:

- Dr. Vishal must not see Tulip Dental → **axis 1**, non-negotiable, no setting.
- Regency receptionist must not see Tukaram Nagar's appointments or income → **axis 2**.
- Regency associate opens Appointments on his own chair by default → **axis 3**, already solved.

Every scoping conversation from here must name its axis. Most tenancy bugs
in products like this are one axis being used to do another axis's job.

---

## 2. The four scope labels

**TENANT (fixed)** — the record belongs to the organization. Every branch of
that org sees it. No branch filter is applied, ever. `branch_id` may exist on
the row as *provenance* (where it was created) but is never a read filter.

**BRANCH (fixed)** — the record belongs to one branch. A user sees it only if
that branch is in their permitted branch set. A multi-branch user's "All
branches" view is a **UNION of their permitted branches** — never a scope
bypass. There is no admin shortcut. This is the mistake `BranchScope` makes
today and the one thing this whole retrofit exists to kill.

**CONFIGURABLE** — the organization chooses at onboarding: *centralised*
(module behaves as TENANT) or *branch-wise* (module behaves as BRANCH). Stored
per organization per module. The clinic sets it; the code does not assume it.

**SPLIT** — the module's master data and its transactions sit at different
levels. Not a setting; a structural fact. These are the ones that get built
wrong if the map says only one word.

---

## 3. The map — all 20 catalogue modules

Slugs are the live `modules.slug` values from `RolePermissionSeeder`.

| # | Module | slug | Scope | Why |
|---|---|---|---|---|
| 1 | Dashboard | `dashboard` | **BRANCH** (roll-up) | Renders for one branch. Multi-branch users get an "All branches" toggle that unions only their permitted branches. |
| 2 | Daily Huddle | `daily_huddle` | **BRANCH** (fixed) | A huddle is one team, one address, one morning. It has no meaning combined. Never rolls up. |
| 3 | Patients | `patients` | **TENANT** (fixed) | The core rule. One patient file per brand. Tukaram Nagar's patient walks into Regency and the full history is there. |
| 4 | Appointments | `appointments` | **BRANCH** (fixed) | The hardest boundary below org. `operatory_id` is the chair — do not add `chair_id`. |
| 5 | Treatments | `treatments` | **SPLIT** | Master = platform hybrid (nullable org). `treatment_plans` = **TENANT** — the plan follows the patient across branches. `treatment_visits` = **BRANCH** — needs `branch_id` + `operatory_id` added. |
| 6 | Clinical Library | `cms` | **TENANT** (fixed) | Content the brand authors once. |
| 7 | Smart Presentation | `presentations` | **TENANT** (fixed) | Follows the plan, which follows the patient. |
| 8 | Prescriptions | `prescriptions` | **TENANT** record, **BRANCH** stamped | The Rx belongs to the patient's file; stamp the issuing branch for audit and for the printed footer. |
| 9 | Communication (PRE) | `communication` | **CONFIGURABLE** | The exact case: one central WhatsApp desk for the brand, or each branch answering its own. |
| 10 | Relationships (PRE) | `relationship` | **CONFIGURABLE** | Data is tenant-level (it follows the patient); the *work queue* — who owns the recall call — is what's configurable. |
| 11 | Marketing | `marketing` | **TENANT** (fixed) | Marketing is the brand. A branch is a *targeting attribute* on a campaign, not an access scope. Do not make this configurable — it invites branch-level brand drift. |
| 12 | Accounts & Finance | `finance` | **SPLIT + CONFIGURABLE** | `finance_cashbook`, receipts, `invoice_payments`, day-book = **BRANCH, fixed** — money is physically collected at a desk in a building. Vendors, payroll, GST, bank accounts = **CONFIGURABLE**. |
| 13 | Inventory | `inventory` | **CONFIGURABLE** | Central store issuing to branches, vs each branch holding its own stock. `inventory_locations` already exists and maps to branch. |
| 14 | Lab | `lab` | **CONFIGURABLE** | An in-house lab serving all branches, vs each branch using its own vendor. Both are real Indian setups. |
| 15 | Tasks | `tasks` | **CONFIGURABLE** | Org-wide task board vs per-branch. |
| 16 | Practice Protocols | `practice_protocols` | **TENANT** (fixed) | SOPs *are* the brand. A branch writing its own protocol is the failure this module exists to prevent. |
| 17 | HR | `hr` | **SPLIT** | Staff profile, salary, documents, incentive rules = **TENANT** (a person is employed by the brand). Attendance, shifts, roster, entry/exit = **BRANCH, fixed** (a person clocks in at one building). |
| 18 | Reports | `reports` | **FOLLOWS SOURCE** | No scope of its own. Each report inherits its source module's scope. |
| 19 | Analytics | `analytics` | **FOLLOWS SOURCE** | Same. |
| 20 | Settings | `settings` | **TWO-LEVEL** | Organization settings = TENANT. `branch_settings` (already exists) = BRANCH. Module scope choices live here. |

**Tally:** TENANT fixed 6 · BRANCH fixed 4 · CONFIGURABLE 5 · SPLIT 3 · follows-source 2.

---

## 4. What must NEVER become a setting

Refuse these even if a clinic asks:

1. **The organization boundary.** No cross-org sharing, no "partner clinic"
   toggle, no referral visibility. If two brands want shared data they are one
   organization with two branches.
2. **Patients as branch-wise.** A group owner will ask for it ("my Regency
   manager shouldn't see Tukaram Nagar patients"). Say no. Splitting the patient
   record breaks continuity of care, duplicates files, and destroys the one
   thing a group PMS is bought for. If they want to *restrict the list view*,
   that is axis 3, not axis 2 — default the list to the user's branch and let
   search reach the whole org.
3. **Appointments as tenant-wide.** A shared calendar across branches is
   noise on day one and a scheduling accident by month three.
4. **Huddle as combined.**
5. **Cashbook as centralised.** Cash exists in a drawer in a building.

---

## 5. Two things the schema does not yet have

### 5.1 `branch_user` pivot — `users.branch_id` will not survive V2

Today `users.branch_id` is a single column (`2026_05_12_114407`). It cannot
express any of the real cases:

- Dr. Vishal (owner) — both branches
- centralised comms team — all branches
- Regency receptionist — one branch
- visiting implantologist — two branches, two days a week

```php
Schema::create('branch_user', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
    $t->boolean('is_primary')->default(false);   // default branch on login
    $t->timestamps();
    $t->unique(['user_id', 'branch_id']);
});
```

Migration path: backfill one row per existing user from `users.branch_id`,
mark it `is_primary`, keep the old column read-only for one release, then drop
it. **Do not** leave both as sources of truth — that is how the "which branch
am I in" bug ships.

The user's **permitted branch set** = rows in `branch_user`. The branch scope
filters to that set. There is no `isAdminRole()` bypass. An org owner has all
branches because the onboarding writes all the rows, not because the code
makes an exception for him.

### 5.2 `organization_module_settings`

```php
Schema::create('organization_module_settings', function (Blueprint $t) {
    $t->id();
    $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $t->string('module_slug', 50);          // matches modules.slug
    $t->string('scope', 10);                // 'tenant' | 'branch'
    $t->timestamps();
    $t->unique(['organization_id', 'module_slug']);
});
```

Rows exist only for the 5 CONFIGURABLE modules plus `finance_ledger`. A
missing row resolves to the default below — so a single-branch clinic never
touches this screen and nothing breaks.

**Recommended defaults for a new multi-branch org:**

| module | default | reason |
|---|---|---|
| `communication` | centralised | one number, one brand voice; a group that wants split will say so |
| `relationship` | centralised | recalls are a brand asset, not a branch asset |
| `tasks` | branch | most tasks are "fix the chair in op 2" |
| `inventory` | branch | stock is physical and sits in a building |
| `lab` | branch | most groups start with a local vendor per branch |
| `finance_ledger` | centralised | one GSTIN, one accountant, one filing |

A single-branch clinic (most of your first 10) gets all of this for free —
one branch means both settings resolve identically.

---

## 6. Resolution order at query time

Every scoped query runs three gates in this order. No step may be skipped.

```
1. ORGANIZATION   always applied. No user context → throw, never "return all".
2. BRANCH         look up organization_module_settings for the module:
                    scope = 'tenant' → no branch filter
                    scope = 'branch' → whereIn(branch_id, user's branch_user set)
                  fixed-BRANCH modules skip the lookup and always filter.
3. DATA SCOPE     role_module_permissions.data_scope → all | own_default | own_only
```

Gate 1 is a global scope with **no bypass path in application code**. The only
escape is an explicit, greppable `withoutTenancy()` used in console commands,
seeders and HQ-side code — and every use of it is reviewed.

---

## 7. Vardhaman Dental Care — the acceptance test

Two branches: Tukaram Nagar, Regency Estate. Comms centralised, inventory
branch-wise.

| Actor | Sees | Does not see |
|---|---|---|
| Dr. Vishal (owner, both branches) | Both dashboards + combined roll-up; both incomes separately and together; both inventories; all patients | Anything belonging to Tulip Dental or any other organization |
| Regency receptionist (1 branch) | Regency appointments, Regency cash, Regency stock, **all Vardhaman patients** | Tukaram Nagar appointments, Tukaram Nagar income, Tukaram Nagar stock |
| Central comms staff (all branches) | Every WhatsApp thread and recall for the brand | Clinical notes and finance, if role denies them |
| Tukaram Nagar patient at Regency | — | — |
| ↳ Regency staff opening that patient | Full treatment history, plans, Rx, photos from Tukaram Nagar | Tukaram Nagar's *appointment book* and *cash book* |

That last row is the whole design in one line: **the patient crosses the
branch wall; the branch's operational data does not.**

---

## 8. Open decisions — need Sumit's ruling before Files 6–9

1. **Can an org change a configurable module's scope after go-live?**
   Recommendation: **yes for `communication`, `relationship`, `tasks`** (no data
   moves — only the filter changes); **no for `inventory`** (stock has a
   physical location; centralising it is a stock-transfer operation, not a
   settings toggle). Lock inventory scope at onboarding.

2. **Does a single-branch manager ever see org totals?**
   Recommendation: **no.** Roll-up is a function of holding multiple branches,
   not a permission that can be granted separately. Keeps axis 2 honest.

3. **`patients.branch_id`** — Step 1 left this open. This document answers it:
   **keep the column, rename its meaning to branch-of-registration, remove it
   from every read scope.** It stays useful for "which branch acquired this
   patient" marketing reporting.

4. **`treatment_visits`** needs `branch_id` + `operatory_id` added. Confirm
   before File 6.

---

## 9. What this unblocks

With this map settled, three things can be written without further decisions:

- **File 6** — which spine tables get `branch_id` (everything marked BRANCH or
  the branch half of a SPLIT).
- **The trait pair** — `BelongsToTenant` (unconditional) and `BelongsToBranch`
  (rewritten, fails closed, reads `organization_module_settings`).
- **The onboarding wizard** — 6 questions: org name, branches, and the 5
  configurable scopes. That screen is the whole difference between selling to
  one dentist and selling to a group.
