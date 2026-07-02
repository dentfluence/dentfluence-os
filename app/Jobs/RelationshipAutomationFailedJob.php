<?php

namespace App\Jobs;

use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\TaskEngine;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RelationshipAutomationFailedJob — Phase 5 FailSafe
 *
 * Dispatched whenever RulesEngine::evaluate() catches an action failure.
 *
 * Responsibilities:
 *  1. Log the failure to ActivityEngine: 'automation.failed'
 *  2. Count how many times the same rule has failed for the same relationship
 *     within the configured escalation window
 *  3. If failures >= escalation_count → create a manual admin review task
 *
 * Design principles:
 *  - NEVER re-throws. All errors caught and logged internally.
 *  - Can run on queue or sync — safe either way.
 *  - Admin task created only once per escalation threshold crossing (not on every failure).
 */
class RelationshipAutomationFailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $ruleName,
        public readonly ?int   $relationshipId,
        public readonly string $subjectType,
        public readonly mixed  $subjectId,
        public readonly string $errorMessage,
        public readonly array  $context = [],
    ) {}

    public function handle(): void
    {
        try {
            // 1. Log failure to ActivityEngine via a lightweight subject proxy
            $this->logFailureToActivityEngine();

            // 2. Count recent failures for this rule + relationship
            if ($this->relationshipId !== null) {
                $this->maybeEscalate();
            }

        } catch (\Throwable $e) {
            // Absolute last resort — write to Laravel log only, never re-throw
            Log::error("RelationshipAutomationFailedJob itself failed", [
                'rule_name'  => $this->ruleName,
                'error'      => $e->getMessage(),
                'original'   => $this->errorMessage,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Write a raw row to the activities table via DB (bypasses Eloquent model
     * so we don't need a real $subject model — the original may be unresolvable
     * when the failure happened).
     */
    protected function logFailureToActivityEngine(): void
    {
        try {
            DB::table('activities')->insert([
                'relationship_id' => $this->relationshipId,
                'subject_type'    => $this->subjectType,
                'subject_id'      => $this->subjectId,
                'actor_type'      => null,
                'actor_id'        => null,
                'event'           => 'automation.failed',
                'description'     => "Automation rule [{$this->ruleName}] failed: {$this->errorMessage}",
                'metadata'        => json_encode([
                    'rule_name'     => $this->ruleName,
                    'error_message' => $this->errorMessage,
                    'context'       => $this->context,
                ]),
                'occurred_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("RelationshipAutomationFailedJob: could not write to activities", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Count recent failures and create an admin review task if threshold is crossed.
     * Uses a raw DB count on the activities table (no Eloquent dependency).
     */
    protected function maybeEscalate(): void
    {
        $escalationCount = (int) config('relationship_rules.failsafe.escalation_count', 3);
        $windowHours     = (int) config('relationship_rules.failsafe.escalation_window_hours', 24);
        $since           = Carbon::now()->subHours($windowHours);

        $failureCount = DB::table('activities')
            ->where('event', 'automation.failed')
            ->where('relationship_id', $this->relationshipId)
            ->whereJsonContains('metadata->rule_name', $this->ruleName)
            ->where('occurred_at', '>=', $since)
            ->count();

        if ($failureCount < $escalationCount) {
            return; // threshold not yet crossed
        }

        // Check if an escalation task was already created this window (prevent duplicate admin tasks)
        $alreadyEscalated = DB::table('tasks')
            ->where('relationship_id', $this->relationshipId)
            ->where('title', 'LIKE', "[FailSafe] Rule [{$this->ruleName}]%")
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($alreadyEscalated) {
            return;
        }

        // Create admin review task
        $this->createEscalationTask($failureCount);

        Log::warning("RelationshipAutomationFailedJob: escalated rule [{$this->ruleName}] for relationship [{$this->relationshipId}]", [
            'failures_in_window' => $failureCount,
        ]);
    }

    /**
     * Create a manual admin task so the failure doesn't go unnoticed.
     * Uses raw DB insert to avoid any dependency chain that could also fail.
     */
    protected function createEscalationTask(int $failureCount): void
    {
        try {
            DB::table('tasks')->insert([
                'title'           => "[FailSafe] Rule [{$this->ruleName}] failed {$failureCount}× — review required",
                'description'     => implode("\n", [
                    "Automation rule [{$this->ruleName}] has failed {$failureCount} times in the last " . config('relationship_rules.failsafe.escalation_window_hours', 24) . " hours.",
                    "Relationship ID: {$this->relationshipId}",
                    "Last error: {$this->errorMessage}",
                    "Check the activities table (event='automation.failed') for details.",
                ]),
                'category'        => 'admin',
                'priority'        => 'urgent',
                'status'          => 'pending',
                'due_date'        => now()->toDateString(),
                'assigned_to'     => null,  // assign to admin pool — no specific user
                'created_by'      => 1,     // system user
                'branch_id'       => $this->context['branch_id'] ?? 1,
                'patient_id'      => null,
                'relationship_id' => $this->relationshipId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("RelationshipAutomationFailedJob: could not create escalation task", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
