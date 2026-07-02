# Phase 0 — Safety & Foundations (Architecture Baseline v1.0)

**Status:** implemented (code), pending your `migrate` + `test` run.
**Governing docs:** Implementation Blueprint v1.0 §2 (Phase 0), §5 (flags), §6 (testing).

Phase 0 builds *only* the infrastructure needed to safely perform the later migration. **It changes no user-facing behaviour.** Every new capability is additive and dormant by default.

## What was built

| Area | Key files |
|---|---|
| **Feature flags** | `config/features.php`, `app/Support/Features/{FeatureFlagService,FeatureScope,Feature}.php`, `app/Models/FeatureFlag.php`, migration `…_create_feature_flags_table` |
| **Structured logging** | `config/logging.php` (new `structured` channel), `app/Support/Logging/EngineLog.php` |
| **Monitoring** | `app/Support/Monitoring/**`, `app/Http/Controllers/System/StatusController.php`, route `system.status` |
| **Domain events** | `app/Domain/Events/{DomainEvent,AbstractDomainEvent,DomainEventBus}.php`, `app/Models/ProcessedDomainEvent.php`, migration `…_create_processed_domain_events_table` |
| **Guard hardening** | `app/Services/Relationship/{CommunicationGuard,GuardDecision}.php`, `config/relationship_rules.php` (additive keys) |
| **Decision Log** | migration `…_add_decision_columns_to_relationship_rule_logs`, `app/Models/RelationshipRuleLog.php`, `app/Services/Relationship/DecisionLogRecorder.php` |
| **Wiring** | `app/Providers/FoundationServiceProvider.php`, `bootstrap/providers.php` (registered) |
| **Tests** | `tests/Feature/Foundation/**`, `tests/Feature/Characterization/**`, `tests/Support/Events/CanaryEvent.php` |
| **Docs** | this folder |

## What you need to run (Laragon terminal — you run these, not the assistant)

```
php artisan migrate            # adds: feature_flags, processed_domain_events, + decision columns
php artisan test               # runs the new Foundation + Characterization tests
```

The three migrations are **additive and nullable**; each `down()` drops only what it created. No existing data is touched.

## Definition of Done — Phase 0 checklist

- [ ] `php artisan migrate` succeeds (3 additive migrations).
- [ ] `php artisan test` green (existing + new).
- [ ] No user-visible behaviour change (all flags default OFF; Guard defaults to today's fail-open).
- [ ] `GET /system/status` (while logged in) returns health JSON; `/up` still works.
- [ ] Architecture Baseline unchanged (no new engines, no responsibility moves).

## Guardrails honoured

- No engine implemented or modified beyond the **Guard hardening foundation** explicitly scoped to Phase 0.
- RulesEngine is **not** touched (Decision Log writer exists but is unwired).
- No PRM/Journeys/Automation/Workflow/Insights/Search/Integration changes.
