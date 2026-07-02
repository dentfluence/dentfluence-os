<?php

namespace App\Services\Relationship;

use App\Models\RelationshipRuleLog;
use Illuminate\Support\Facades\Log;

/**
 * DecisionLogRecorder — Phase 0 foundation (owned by the Rules Engine).
 *
 * The single writer for the Decision Log. Phase 0 provides the writer and
 * schema ONLY — it is intentionally NOT wired into RulesEngine yet ("do not
 * migrate rules yet"). Phase 2 will have the Rules Engine call record() on
 * every evaluation.
 *
 * Never throws: logging a decision must never break the decision itself.
 */
class DecisionLogRecorder
{
    /**
     * Record one automation decision.
     *
     * @param  array<string,mixed>  $inputs      The event/data the rule saw.
     * @param  array<string,mixed>  $conditions  Each condition and how it resolved.
     */
    public function record(
        string $ruleName,
        string $result,           // e.g. 'matched' | 'not_matched'
        string $decision,         // e.g. 'task_requested' | 'suppressed:frequency'
        string $requestingEngine, // e.g. 'rules' | 'automation' | 'workflow'
        ?int $relationshipId = null,
        array $inputs = [],
        array $conditions = [],
        ?int $userId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        try {
            RelationshipRuleLog::create([
                'rule_name'         => $ruleName,
                'relationship_id'   => $relationshipId,
                'subject_type'      => $subjectType,
                'subject_id'        => $subjectId,
                'fired_at'          => now(),
                'inputs'            => $inputs,
                'conditions'        => $conditions,
                'result'            => $result,
                'decision'          => $decision,
                'requesting_engine' => $requestingEngine,
                'user_id'           => $userId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DecisionLogRecorder::record failed', [
                'rule_name' => $ruleName,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
