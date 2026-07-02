<?php

namespace App\Services\Prm;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\Communication\FollowUpRulesService;
use App\Services\Relationship\FollowUpRuleEngine;
use App\Support\Features\Feature;

/**
 * LeadFollowUpService — PRM Phase 2b (follow-up reminders).
 * ----------------------------------------------------------------------------
 * When a lead enters a pipeline stage, auto-create the right follow-up
 * reminder(s) in the EXISTING Follow-up Engine — linked to the lead via
 * follow_ups.lead_id. They then show up in the follow-up queue / overdue /
 * daily huddle just like patient follow-ups, with zero new UI.
 *
 * Rules live in config/followup_rules.php → 'prm_stage_changed' (single source
 * of truth). This class just resolves them for a lead and inserts, with
 * duplicate protection so re-entering a stage doesn't pile up reminders.
 */
class LeadFollowUpService
{
    public function __construct(
        protected FollowUpRulesService $rules = new FollowUpRulesService(),
    ) {}

    /**
     * Create follow-up reminders for a lead entering a given stage.
     *
     * @return int How many new follow-ups were created.
     */
    public function createForStage(Lead $lead, string $stage): int
    {
        if (! config('prm.followups.enabled')) {
            return 0;
        }

        $context = [
            'lead_id'     => $lead->id,
            'patient_id'  => null,
            'assigned_to' => $lead->assigned_to_id,   // real user id (Phase 2a)
            'base_date'   => now()->toDateString(),
        ];

        // Phase 2, Slice 6 — rules consolidation. When rules.single_engine is ON,
        // the Rules-Engine-owned FollowUpRuleEngine resolves the definitions
        // (identical output, proven by parity). OFF (default) = legacy service.
        $definitions = Feature::enabled('rules.single_engine')
            ? app(FollowUpRuleEngine::class)->resolve('prm_stage_changed', $stage, '', $context)
            : $this->rules->resolve('prm_stage_changed', $stage, '', $context);

        $created = 0;

        foreach ($definitions as $def) {
            // Duplicate guard: same lead + label + due date, still pending.
            $exists = FollowUp::where('lead_id', $lead->id)
                ->where('label', $def['label'])
                ->whereDate('due_date', $def['due_date'])
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            FollowUp::create(array_merge($def, [
                'status'   => 'pending',
                'due_time' => $def['due_time'] ?? '10:00',
            ]));

            $created++;
        }

        return $created;
    }
}
