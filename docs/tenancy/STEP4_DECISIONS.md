# Step 4 — Enforcement Decisions (November)

Status: **IN PROGRESS.** Only decision (g) is written. Decisions (a)–(f) are
recorded in File 9 at the end of the Step 2/3 run. Nothing here is implemented —
this file is the contract November inherits.

Written 2026-09-04, during the Step 2 schema run, while the schema was in front of us.

---

## (a) — pending (File 9)
## (b) — pending (File 9)
## (c) — pending (File 9)
## (d) — pending (File 9)
## (e) — pending (File 9)
## (f) — pending (File 9)

---

## (g) DROP EVERY `DEFAULT 1` ON `organization_id` AS PART OF STEP 4

**The decision.** Every `DEFAULT 1` on an `organization_id` column must be
dropped in the same migration that applies `BelongsToTenant`. Not before, not
later — the same migration. Once the trait exists, an unstamped insert MUST fail
loudly rather than silently land in organization 1.

**Why the default exists in V1.** Without the trait there is nothing to stamp
`organization_id` on insert. `DEFAULT 1` is what makes the column correct for
Tulip on day one, and it is what lets the foreign keys be created on tables that
already hold live rows. For V1 it is right.

**Why it becomes a defect the moment Step 4 lands.** After the trait exists,
`DEFAULT 1` silently rescues every insert that bypasses it — a raw
`DB::table()->insert()`, a queued job running without tenancy context, a seeder,
an artisan command, a webhook handler. Those rows land in Tulip Dental's
organization and look legitimate. There is no error, no log line, nothing to
find later.

This is the identical disease being removed from `BranchScope` one layer down.
`BranchScope` fails open on the read path — an unscoped reader sees everything.
`DEFAULT 1` fails open on the write path — an unstamped writer pollutes org 1.
Removing one while keeping the other leaves the hole open, just quieter.

**After the drop, the column stays `NOT NULL` with no default.** An insert that
does not supply `organization_id` then fails at the database, which is the point.

---

### Tables carrying `DEFAULT 1` — the list November must clear

**Group 1 — default ADDED by this retrofit.**
- `branches` (File 2, 2026_09_04_100002)
- the spine tables added in File 6 — *list to be appended when File 6 is written*

**Group 2 — default INHERITED through the File 4 rename.**
`RENAME COLUMN` preserves the column definition, so these 16 tables carry their
existing `clinic_id DEFAULT 1` straight into `organization_id`. They will not
look like this retrofit added a default, but they have one:

```
finance_audit_log          finance_membership_plans
finance_bank_accounts      finance_payroll
finance_bank_transactions  finance_settings
finance_cashbook           finance_staff_advances
finance_expense_categories finance_transactions
finance_expenses           finance_vendor_payments
finance_gst_records        finance_vendors
finance_income_entries     membership_benefit_logs
```

The remaining rename targets (`mkt_*`, `blog_*`, `inventory_locations`) were
declared as bare `unsignedBigInteger('clinic_id')` with **no** default, so after
the rename they carry none. **Do not assume the rename set is uniform.**

**Group 3 — explicitly OUT of this drop.**
The 32 hybrid master tables (File 7). They are `NULLABLE` with no default by
design: `NULL` means "Dentfluence platform default", which is a real value, not
a missing stamp. They must keep behaving that way.

> ⚠ The default is only ONE of two dimensions in which this schema is not
> uniform. See decision (h) for the second: foreign keys.

---

### Do not trust the list above — regenerate it

The lists here are correct as of 2026-09-04 and will drift as Files 6 and 7 are
written. Before starting Step 4, regenerate the authoritative set from the live
schema:

```sql
SELECT TABLE_NAME, COLUMN_DEFAULT, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME  = 'organization_id'
  AND COLUMN_DEFAULT IS NOT NULL
ORDER BY TABLE_NAME;
```

Every row that returns is a table to clear. **Zero rows = Step 4 done.**

---

## (h) THE 39 RENAMED COLUMNS CARRY NO FOREIGN KEY — DELIBERATELY

**The decision.** The 39 `organization_id` columns produced by the File 4 rename
carry **no foreign key** to `organizations`, now or before launch. The columns
added new in Files 6 and 7 **do**.

**Rationale — cost, not consistency.** Files 6 and 7 are already altering their
tables and every row lands on `DEFAULT 1`, so foreign-key validation is free. The
39 renamed columns sit on tables that already hold live rows, several of them
large finance tables; retro-adding 39 foreign keys means a full validation scan
across them ten days before launch, buying a guarantee November does not yet need.

**This is deliberate asymmetry, not an oversight. Do not "fix" it.**

**What November decides.** Whether to add the missing 39 — and it decides that
*after* the Step 3 backfill has already proven every value resolves to a real
organization. That proof is the cheap part; the constraint is the expensive part,
and it can wait for a maintenance window.

---

## ⚠ THE SCHEMA IS NOT UNIFORM IN TWO DIMENSIONS

After Files 4, 6 and 7, no single statement is true of every `organization_id`
column in this database. Anyone reading one table and generalising will be wrong.

**Dimension 1 — default (decision g):**

| state | which tables |
|---|---|
| `NOT NULL DEFAULT 1` | `branches` + all File 6 spine tables + the 16 finance/membership tables that inherited it through the rename |
| `NOT NULL`, no default | the `mkt_*`, `blog_*` and `inventory_locations` rename targets |
| `NULL`, no default | the 32 hybrid master tables (File 7) — `NULL` is a real value here, meaning "Dentfluence platform default" |

**Dimension 2 — foreign key (decision h):**

| state | which tables |
|---|---|
| FK to `organizations` | `branches` (File 2) + all File 6 and File 7 tables |
| no FK | all 39 renamed tables |

The two dimensions are independent. A table can have a default and no FK
(`finance_transactions`), an FK and no default (a File 7 hybrid master), both
(`branches`), or neither (`mkt_campaigns`). Check the live schema before
assuming — the regeneration query above answers dimension 1; this answers
dimension 2:

```sql
SELECT c.TABLE_NAME,
       CASE WHEN k.CONSTRAINT_NAME IS NULL THEN 'NO FK' ELSE 'FK' END AS fk_state
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
       ON k.TABLE_SCHEMA           = c.TABLE_SCHEMA
      AND k.TABLE_NAME             = c.TABLE_NAME
      AND k.COLUMN_NAME            = c.COLUMN_NAME
      AND k.REFERENCED_TABLE_NAME  = 'organizations'
WHERE c.TABLE_SCHEMA = DATABASE()
  AND c.COLUMN_NAME  = 'organization_id'
ORDER BY fk_state, c.TABLE_NAME;
```
