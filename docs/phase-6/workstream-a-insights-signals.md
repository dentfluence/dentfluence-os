# Phase 6 · Workstream A — Insights Engine (Slice 1: Health / LTV / Risk)

Replaces the single 0–100 `RelationshipScoreEngine` score with 3 independent,
event-fed signals — same "build → rebuild command → parity" discipline already
proven by `TodayActionsProjector` (Phase 1 · Workstream E). Behind the
existing `insights.signals` flag (default off).

## Slice 1 — signal projections (built, shadow)

| Piece | File |
|---|---|
| Migration | `database/migrations/2026_07_03_800001_create_insight_signals_table.php` |
| Model | `app/Models/InsightSignal.php` |
| Config | `config/insights.php` |
| Calculators | `app/Services/Insights/HealthSignalCalculator.php`, `LtvSignalCalculator.php`, `RiskSignalCalculator.php` (+ shared `Support/ResolvesPatientIds.php`) |
| Facade | `app/Services/Insights/InsightsEngine.php` |
| Projector | `app/Services/Insights/InsightsProjector.php` — `rebuildAll()`, `rebuildFor()`, `signalsFor()`, `parity()` |
| Command | `app/Console/Commands/RebuildInsightSignals.php` → `insights:rebuild-signals [--relationship=] [--check]` |
| Event wiring | `app/Listeners/Insights/RecalculateInsightSignalsListener.php`, `app/Jobs/RecalculateInsightSignalsJob.php`, `app/Providers/InsightsServiceProvider.php` (registered in `bootstrap/providers.php`) |
| Tests | `tests/Feature/Insights/InsightsProjectorTest.php`, `InsightSignalEventListenerTest.php` |

**How it works:** each calculator reads ONLY the raw tables its own signal
needs (Health: appointments + communication_queue; LTV: invoice_payments +
invoices + treatment_plans; Risk: appointments + communication_queue) —
independent of one another and of `RelationshipScoreEngine`, which keeps
running untouched. `InsightsProjector::rebuildFor()` persists all 3 signals
for one relationship into `insight_signals` (one table, discriminated by a
`signal` column — the same "one store, several classes" pattern already used
by the Task Engine). `rebuildAll()` does the same for every relationship.
`parity()` recomputes fresh values and diffs them against what's stored —
since Insights is net-new there's no legacy 3-signal system to diff against,
so this proves the stored projection isn't stale, the same thing
`TodayActionsProjector::parity()` proves for Today's Actions.

**Incremental refresh:** `RecalculateInsightSignalsListener` subscribes to
`ActivityRecorded` (the one universal fact-publisher fed by
`ActivityEngine::log()`). While `insights.signals` is OFF (default) the
listener does nothing — publishing the event is a no-op — so this slice ships
with **zero behaviour change**. Turning the flag on makes relevant events
(`recall.queued`, `journey.transitioned`, `appointment.completed`,
`payment.received`, …) queue a cheap per-relationship recompute.

**Safety:** one additive table, one line added to `bootstrap/providers.php`
(registers the new provider) — no existing engine, controller, or table
touched. `RelationshipScoreEngine` and `MarketingScoreService` are untouched
and keep running. Nothing in the live UI reads `insight_signals` yet — that
read-cutover (Today's Actions / profile header) is a later slice, same as
Today's Actions did its projection (E1) before its read cutover (E2).

### Run (you)

```
php artisan migrate                                   # creates insight_signals
php artisan insights:rebuild-signals                  # build the projection (shadow)
php artisan insights:rebuild-signals --check           # parity vs a fresh recompute
php artisan test --filter=Insights
```

Nothing user-visible changes in Slice 1. Flipping `insights.signals` only
turns on the incremental listener (background recompute) — still no UI reads
these signals.

## Known simplifications (documented, not hidden)

- **LTV projection heuristic:** `value_projected = value_realized +
  (accepted treatment plan value not yet matched by an invoice against that
  plan)`. Simple and conservative; refinable later without changing the table
  shape.
- **Event coverage:** only 7 call sites in the app currently call
  `ActivityEngine::log()` today (leads, recall/reminder runners, journey
  transitions) — appointment/payment/treatment domains don't publish formal
  events yet. `config('insights.recalculate_on_events')` already lists the
  target events (`appointment.completed`, `payment.received`,
  `treatment.completed`) so wiring those publishers later (a different,
  future slice) needs no change here — same pattern already used by
  `config/relationship_score.php`.
- **Read contracts (blueprint deliverable #4)** — deliberately deferred to a
  later slice, once these 3 signals are proven, exactly like Today's Actions
  staged its own read cutover after its shadow slice.

## Next (not started — hold for confirmation)

- Slice 2: Analytics Engine incremental aggregate projections.
- Slice 3: Search Engine index projection (behind `search.index`).
- Slice 4: Read-contracts — thin read-model interface so Insights/Today's
  Actions stop touching raw domain tables directly.
