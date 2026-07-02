# Testing (Phase 0)

Phase 0 establishes the safety net that guards every later refactor. **No workflow tests yet** (per the blueprint).

## What exists

| Suite | Location | Purpose |
|---|---|---|
| **Foundation** | `tests/Feature/Foundation/` | Prove the new infrastructure works. |
| **Characterization** | `tests/Feature/Characterization/` | Pin *current* behaviour so it can't change by accident. |
| Support | `tests/Support/Events/CanaryEvent.php` | A test-only domain event for the bus. |

### Foundation tests
- `FeatureFlagTest` — defaults off, unknown-flag safety, global + per-clinic overrides, removal.
- `DomainEventBusTest` — sync publish/subscribe, versioned envelope, `onceProcessed` idempotency, rollback-on-failure.
- `CommunicationGuardHardeningTest` — default path unchanged, fail-closed only when flagged, urgency relaxes frequency, **consent never overridden by urgency**, consent dormant by default.
- `SystemStatusTest` — default checks aggregate, status route registered, registry extensible.

### Characterization tests
- `CommunicationGuardCharacterizationTest` — locks today's default: allows with no history, fails open, never throws, 3-arg signature still works.

## How to run

```
php artisan test                                   # everything
php artisan test --filter=Foundation               # just the new foundation
php artisan test tests/Feature/Characterization     # the safety net
```

Tests use the `dentfluence_testing` database via `RefreshDatabase`.

## Regression framework rule

Before refactoring any legacy engagement behaviour in a later phase, **add a characterization test** under `tests/Feature/Characterization/` that captures the current behaviour first. The refactor is only safe once that test stays green through the change.
