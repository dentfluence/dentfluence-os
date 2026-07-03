# Phase 6 · Workstream D — Read-contracts (Slice 4)

De-god-reads the Insights Engine: the 3 signal calculators (Slice 1) no
longer call `DB::table(...)` directly — they depend on small read-contract
interfaces instead. This is the blueprint's deliverable #4 ("a thin
read-model/interface layer so Insights and Today's Actions stop touching raw
domain tables directly"), scoped to **Insights only** in this slice —
Today's Actions is owned by another workstream/thread and intentionally left
untouched here.

## Slice 4 — read-contracts (built)

| Piece | File |
|---|---|
| Interfaces | `app/Contracts/Insights/AppointmentReadContract.php`, `CommunicationReadContract.php`, `BillingReadContract.php` |
| Implementations | `app/Services/Insights/Reads/EloquentAppointmentReadContract.php`, `EloquentCommunicationReadContract.php`, `EloquentBillingReadContract.php` |
| Calculators updated | `HealthSignalCalculator.php`, `RiskSignalCalculator.php` (inject Appointment + Communication contracts), `LtvSignalCalculator.php` (injects Billing contract) |
| Bindings | `app/Providers/InsightsServiceProvider.php` — new `register()` method binds each interface to its Eloquent implementation |
| Tests | `tests/Feature/Insights/ReadContractsTest.php` |

**This is a pure extraction, not a rewrite.** Every query that used to live
inline in a calculator was moved verbatim into its matching
`Eloquent*ReadContract` — same table, same `where`/`whereIn` conditions, same
aggregate. The calculators call the injected interface instead of
`DB::table(...)`, but compute the exact same numbers. Proof: **Slice 1's
existing tests (`InsightsProjectorTest`, `InsightSignalEventListenerTest`)
were left completely unchanged** and must still pass — if the refactor had
altered any computed value, those tests would catch it (same fixtures, same
assertions on health/LTV/risk scores).

**Why this matters going forward:** any future signal (or a future consumer
that composes Insights differently) can reuse `AppointmentReadContract` /
`CommunicationReadContract` / `BillingReadContract` instead of writing a new
raw query — and if `appointments`/`communication_queue`/billing tables are
ever restructured, only the 3 Eloquent implementations need to change, not
every calculator that reads them.

**Safety:** 3 new interfaces + 3 new implementations + 1 test file, plus
edits to 3 files that are entirely mine from Slice 1 (no other engine's
files touched) and 1 line of new binding logic in a provider I already own.
No migration, no table, no flag — this slice is a code-organization change
only.

### Run (you)

```
php artisan test --filter=Insights
```

This re-runs everything Insights-related — Slice 1's `InsightsProjectorTest`
and `InsightSignalEventListenerTest` plus the new `ReadContractsTest` — all
should be green with **zero change to any previously-passing assertion**.
No migration needed for this slice.
