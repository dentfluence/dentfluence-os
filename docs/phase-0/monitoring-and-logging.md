# Monitoring & Structured Logging

## Structured logging

A dedicated, rotating JSON channel `structured` (in `config/logging.php`) writes to `storage/logs/engine.log`. It is **kept out of the default `stack`** so it never floods the main log.

Engines write to it via a helper (opt-in — nothing is forced to use it in Phase 0):

```php
use App\Support\Logging\EngineLog;

$log = EngineLog::for('relationship', $correlationId);
$log->info('journey.transitioned', ['from' => 'lead', 'to' => 'patient'], relationshipId: 42);
```

Every entry carries `engine`, `event`, `correlation_id`, `relationship_id`, plus your context. Guidance: log meaningful engine events, **not** per-iteration chatter.

## Monitoring foundation

An extensible health registry:

- `App\Support\Monitoring\HealthCheck` — interface (`key()`, `run()`). Checks never throw.
- `App\Support\Monitoring\SystemStatusService` — registry; `run()` aggregates all checks into one payload with an overall status (worst-of).
- Default checks: `DatabaseCheck`, `CacheCheck`, `QueueCheck` (pending/failed jobs), `SchedulerCheck` (heartbeat), `CommunicationCheck` (config presence).

Later phases register more checks (engine status, etc.) via `SystemStatusService::register()` — no edit to core.

### Status endpoint

`GET /system/status` (route name `system.status`) — **authenticated, internal**. Returns the aggregated checks plus resolved feature-flag state. Returns HTTP 503 if any check is `fail`. Laravel's own `/up` health route is left untouched.
