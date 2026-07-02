# Communication Guard — Phase 0 Hardening (foundation, dormant)

`App\Services\Relationship\CommunicationGuard` gained a hardening **foundation**. With every flag at its default (off) and `$isUrgent = false`, `decide()` reduces **exactly** to the original three rules + fail-open. **No behaviour changes in Phase 0.**

## New capabilities (all dormant by default)

| Capability | Gate | Default |
|---|---|---|
| **Fail-closed** — block (not fail open) when a guard check errors | flag `guard.fail_closed` | OFF → fails open (today's behaviour) |
| **Consent gate** — block when the patient has not consented | flag `guard.consent_required` | OFF → not enforced |
| **Quiet hours** — block during a window | `config('relationship_rules.communication_guard.quiet_hours.enabled')` | OFF |
| **Urgency** — relax frequency & quiet-hours | `$isUrgent` argument | `false` |

## The invariant (red-team condition #1)

**Consent is checked FIRST and is NEVER relaxed by urgency.** Urgency may relax *frequency* and *quiet-hours* only. This is enforced structurally: the consent check does not consult `$isUrgent`, and `config(...urgency.never_relaxes)` lists `consent` as a hard rule. The test `test_consent_is_never_overridden_by_urgency` locks it.

## API

```php
// Legacy boolean API — unchanged signature + default behaviour:
$guard->canContact($relationshipId, $channel, $type);              // 3-arg (backward compatible)
$guard->canContact($relationshipId, $channel, $type, isUrgent: true);

// New structured decision (for the Decision Log / explainability):
$decision = $guard->decide($relationshipId, $channel, $type, $isUrgent);
$decision->allowed();   // bool
$decision->reason();    // e.g. 'consent' | 'total_contacts_exceeded' | null
```

## What Phase 0 does NOT do

- It does **not** wire real consent (the `patientHasConsent()` seam returns `true`; Phase 4 wires DPDP).
- It does **not** flip any flag on. Enforcement arrives in later phases, in shadow mode first.
