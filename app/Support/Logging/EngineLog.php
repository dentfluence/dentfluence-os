<?php

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EngineLog — Phase 0 (Safety & Foundations).
 *
 * A thin helper that gives every engine a CONSISTENT, structured log shape on
 * the dedicated 'structured' channel (storage/logs/engine.log, JSON).
 *
 * This is a *capability*, not a mandate — nothing is forced to use it in
 * Phase 0. It exists so that when engines are built/refactored in later
 * phases they can emit uniform, queryable logs instead of ad-hoc strings.
 *
 * Guidance ("do not flood logs"):
 *   - Use info() for meaningful engine events (a decision made, a projection
 *     rebuilt), NOT for per-iteration chatter.
 *   - Use debug() for detail that is off by default in production.
 *
 * Every entry carries: engine, event, correlation_id, relationship_id (opt),
 * plus any context you pass.
 */
final class EngineLog
{
    public function __construct(
        private readonly string $engine,
        private readonly ?string $correlationId = null,
    ) {
    }

    /**
     * Create a logger bound to a specific engine name (e.g. 'relationship').
     * An optional correlation id ties related entries together across a flow.
     */
    public static function for(string $engine, ?string $correlationId = null): self
    {
        return new self($engine, $correlationId ?? (string) Str::uuid());
    }

    public function info(string $event, array $context = [], ?int $relationshipId = null): void
    {
        $this->write('info', $event, $context, $relationshipId);
    }

    public function warning(string $event, array $context = [], ?int $relationshipId = null): void
    {
        $this->write('warning', $event, $context, $relationshipId);
    }

    public function debug(string $event, array $context = [], ?int $relationshipId = null): void
    {
        $this->write('debug', $event, $context, $relationshipId);
    }

    public function error(string $event, array $context = [], ?int $relationshipId = null): void
    {
        $this->write('error', $event, $context, $relationshipId);
    }

    private function write(string $level, string $event, array $context, ?int $relationshipId): void
    {
        Log::channel('structured')->{$level}($event, array_merge([
            'engine'         => $this->engine,
            'event'          => $event,
            'correlation_id' => $this->correlationId,
            'relationship_id'=> $relationshipId,
        ], $context));
    }
}
