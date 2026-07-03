<?php

namespace App\Services\Workflow;

use App\Models\Relationship;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepLog;
use App\Models\WorkflowTemplate;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * WorkflowEngine — Phase 5, "where are we in this sequence?" for any
 * multi-step, multi-visit process (target architecture doc §B3).
 *
 * WHAT IT OWNS (and nothing else): tracking which step a run of a template
 * is on, whether it's allowed to move to the next step (linear order only —
 * v1 deliberately has no branching), and whether enough time has passed
 * since the current step for the next one to be due.
 *
 * WHAT IT NEVER DOES: create Tasks, send messages, schedule timers, or
 * decide business policy. Those stay the job of the Task Engine,
 * Communication Engine, and Rules Engine — exactly like AutomationEngine
 * (Phase 2) only answers timing questions and never acts itself.
 *
 * SLICE 1 SCOPE (this file): the engine core — start/advance/status plus
 * the feature-flag gate. No callers are wired to it yet and the
 * `workflow.engine` flag stays OFF, so this class is dormant scaffolding —
 * it changes no production behaviour. Slice 2 wires it to a shadow-run
 * against TreatmentVisit::current_stage.
 *
 * See docs/phase-5/workflow-engine-proposal.md for the full design.
 */
class WorkflowEngine
{
    // ─────────────────────────────────────────────────────────────────────
    // Flag gate
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Is the Workflow Engine active? Everything that would change production
     * behaviour must be guarded by this. Default OFF (see config/features.php).
     */
    public function enabled(?int $branchId = null): bool
    {
        return Feature::enabled('workflow.engine', $branchId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // start() — begin a new run of a template
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Start a new run of a template at its first step.
     *
     * @param  string            $template      Template key, e.g. "rct_staging".
     * @param  Relationship|null $relationship  Soft link only — nullable because
     *                                           not every patient has one yet
     *                                           (identity.link_patient is still off).
     * @param  array             $context       Optional: 'subject_type', 'subject_id'
     *                                           (polymorphic pointer at the real
     *                                           record this run tracks), 'actor_id',
     *                                           plus anything else to store as-is
     *                                           in the instance's `context` json.
     *
     * @throws RuntimeException  if the template doesn't exist, is inactive,
     *                           or has no steps defined.
     */
    public function start(string $template, ?Relationship $relationship, array $context = []): WorkflowInstance
    {
        $tpl = WorkflowTemplate::where('key', $template)->where('active', true)->first();

        if (!$tpl) {
            throw new RuntimeException("Workflow template [{$template}] not found or inactive.");
        }

        $firstStep = $tpl->firstStepKey();

        if ($firstStep === null) {
            throw new RuntimeException("Workflow template [{$template}] has no steps defined.");
        }

        return DB::transaction(function () use ($tpl, $relationship, $context, $firstStep) {
            $instance = WorkflowInstance::create([
                'template_id'      => $tpl->id,
                'relationship_id'  => $relationship?->id,
                'subject_type'     => $context['subject_type'] ?? null,
                'subject_id'       => $context['subject_id'] ?? null,
                'current_step'     => $firstStep,
                'status'           => 'active',
                'started_at'       => now(),
                'context'          => Arr::except($context, ['subject_type', 'subject_id', 'actor_id']) ?: null,
            ]);

            WorkflowStepLog::create([
                'workflow_instance_id' => $instance->id,
                'step'                 => $firstStep,
                'entered_at'           => now(),
                'actor_id'             => $context['actor_id'] ?? null,
            ]);

            return $instance;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // advance() — move a run to its next step
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Move a run forward to $step. v1 is linear-only: $step MUST be the
     * immediate next step after the run's current one — no skipping, no
     * branching. Closes out the previous step's log row, opens a new one,
     * and marks the instance completed if $step is the template's last step.
     *
     * @throws RuntimeException  if the instance isn't active, $step isn't
     *                           part of the template, or $step isn't the
     *                           expected next step in sequence.
     */
    public function advance(WorkflowInstance $instance, string $step, array $context = []): WorkflowInstance
    {
        if (!$instance->isActive()) {
            throw new RuntimeException("Workflow instance [{$instance->id}] is not active (status={$instance->status}).");
        }

        $tpl     = $instance->template;
        $stepDef = $tpl->step($step);

        if ($stepDef === null) {
            throw new RuntimeException("Step [{$step}] is not part of template [{$tpl->key}].");
        }

        $expectedNext = $tpl->nextStepKey($instance->current_step);

        if ($step !== $expectedNext) {
            throw new RuntimeException(
                "Cannot advance workflow instance [{$instance->id}] from [{$instance->current_step}] to [{$step}] — expected next step is [" . ($expectedNext ?? 'none, already at last step') . '].'
            );
        }

        return DB::transaction(function () use ($instance, $tpl, $step, $context) {
            // Close out whichever log row is still open (the current step).
            WorkflowStepLog::where('workflow_instance_id', $instance->id)
                ->whereNull('exited_at')
                ->latest('entered_at')
                ->first()
                ?->update(['exited_at' => now()]);

            $isLast = $tpl->isLastStep($step);

            $instance->update([
                'current_step' => $step,
                'status'       => $isLast ? 'completed' : 'active',
                'completed_at' => $isLast ? now() : null,
            ]);

            WorkflowStepLog::create([
                'workflow_instance_id' => $instance->id,
                'step'                 => $step,
                'entered_at'           => now(),
                'actor_id'             => $context['actor_id'] ?? null,
                'notes'                => $context['notes'] ?? null,
            ]);

            return $instance->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // status() — read-only snapshot of where a run stands
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Read-only snapshot: current step, position in the sequence, what's
     * next, and whether the next step is due yet given
     * `min_gap_days_from_previous`. Never mutates anything — safe to call
     * from a view.
     */
    public function status(WorkflowInstance $instance, ?Carbon $now = null): array
    {
        $now   = $now ?? Carbon::now();
        $tpl   = $instance->template;
        $steps = $tpl->steps;

        $currentIndex = array_search($instance->current_step, array_column($steps, 'key'), true);

        $currentEnteredAt = optional(
            $instance->stepLogs()
                ->where('step', $instance->current_step)
                ->whereNull('exited_at')
                ->latest('entered_at')
                ->first()
        )->entered_at ?? $instance->started_at;

        $nextKey = $tpl->nextStepKey($instance->current_step);
        $nextDef = $nextKey ? $tpl->step($nextKey) : null;

        $nextEligibleAt = null;
        $nextDue        = null;

        if ($nextDef) {
            $gapDays        = (int) ($nextDef['min_gap_days_from_previous'] ?? 0);
            $nextEligibleAt = $currentEnteredAt?->copy()->addDays($gapDays);
            $nextDue        = $nextEligibleAt ? $now->greaterThanOrEqualTo($nextEligibleAt) : true;
        }

        return [
            'instance_id'         => $instance->id,
            'template'            => $tpl->key,
            'status'              => $instance->status,
            'current_step'        => $instance->current_step,
            'current_step_label'  => $tpl->step($instance->current_step)['label'] ?? $instance->current_step,
            'position'            => $currentIndex === false ? null : $currentIndex + 1,
            'total_steps'         => count($steps),
            'next_step'           => $nextKey,
            'next_step_label'     => $nextDef['label'] ?? null,
            'next_eligible_at'    => $nextEligibleAt,
            'next_due'            => $nextDue,
            'started_at'          => $instance->started_at,
            'completed_at'        => $instance->completed_at,
            'steps'               => $steps,
        ];
    }
}
