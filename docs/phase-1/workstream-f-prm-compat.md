# Phase 1 · Workstream F — Backward compatibility (PRM stays working, PRE becomes primary)

Keep the legacy PRM board fully functional and reachable while PRE becomes the primary surface. No PRM workflow is lost; `/api/v1` stays frozen for the Flutter app.

## Slice F1 — PRM → relationship spine adapter (built)

Every PRM write now also reflects into the relationship spine.

| Piece | File |
|---|---|
| Service | `app/Services/Prm/PrmRelationshipAdapter.php` |
| Wiring | `app/Http/Controllers/Communication/PrmController.php` → `moveStage`, `logActivity`, `convertToPatient` |
| Tests | `tests/Feature/Relationship/PrmAdapterTest.php` |

**What it does:** on each PRM write the adapter (1) shadow-syncs the lead's `RelationshipJourney` via `JourneyService::syncLeadJourney` — using the correct stage→state mapping, and (2) records a relationship `Activity` via `ActivityEngine` so the PRM action appears on the unified timeline.

**Bug fixed:** the old inline `moveStage` code compared the raw lead stage (e.g. `appointment`) against journey states (e.g. `appointment_booked`) via `canTransitionTo()`, so the journey sync silently no-op'd for most stages. The adapter routes through `JourneyService`'s mapping, so journeys now track PRM moves correctly (still in shadow).

**Safety:** additive and fault-tolerant — journey/activity failures never block the PRM action; a safe no-op for leads with no `relationship_id`. No read behavior changes; journeys remain shadow.

### Verify (you)

```
php artisan test --filter=PrmAdapterTest
```

Then on the legacy PRM board, drag a lead between stages — it behaves exactly as before, and (for a relationship-linked lead) the move now shows on that person's PRE timeline and updates their shadow lead journey.

## Slice F2 — `/api/v1` contract tests (built)

Locks the public API contract the Flutter app depends on, so a future refactor fails a test before it breaks the app in the field.

| Piece | File |
|---|---|
| Tests | `tests/Feature/Api/ApiV1ContractTest.php` |

**Frozen:** the success envelope `{success, message, data, (meta)}` and error envelope `{success, message, errors}`; `GET /api/v1/ping` is public; protected routes return `401` without a token; `POST /api/v1/auth/login` returns `{token, token_type: "Bearer", user{...}}`; bad credentials return the error envelope with `401`; `GET /api/v1/auth/me` returns the user payload; and the list pagination `meta` keys `{current_page, per_page, total, last_page}` (via `/api/v1/patients`). No app code changed — tests only.

### Run (you)

```
php artisan test --filter=ApiV1ContractTest
```

## Slice F3 — PRM → secondary (built)

Behind a new flag the legacy PRM board steps aside for PRE, while staying fully reachable.

| Piece | File |
|---|---|
| Flag | `prm.secondary` (default **off**) in `config/features.php` |
| Redirect | `Communication\PrmController::index/board` → `secondaryRedirect()` |
| Link back | PRE lead pipeline shows a "Legacy PRM board" link when the flag is on (`LeadPipelineController` + `relationship/pipeline/index.blade.php`) |
| Tests | `tests/Feature/Relationship/PrmSecondaryTest.php` |

**Behaviour:** with `prm.secondary` OFF (default) PRM is unchanged and primary. With it ON, `/communication/prm` and `/communication/prm/board` redirect to the PRE lead pipeline — but PRM stays fully reachable by appending `?legacy=1`, and the PRE pipeline surfaces a "Legacy PRM board" link (→ `?legacy=1`) so the legacy view is always one click away. No PRM logic, routes, or `/api/v1` touched; instant rollback by flipping the flag off.

### Run (you)

```
php artisan test --filter=PrmSecondaryTest
```

To try it live: `\App\Support\Features\Feature::set('prm.secondary', true)` → visiting `/communication/prm/board` lands on `/relationship/pipeline`; the "Legacy PRM board" link there reopens PRM. Flip back with `false`.

## Status

Workstream F complete (F1 adapter · F2 API contract · F3 PRM secondary). Legacy PRM keeps working and reachable; PRM writes flow into the relationship spine; the mobile API contract is locked; PRM can be flipped to secondary per clinic via the flag.
