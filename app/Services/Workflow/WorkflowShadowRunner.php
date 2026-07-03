<?php

namespace App\Services\Workflow;

use App\Models\Relationship;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisit;
use App\Models\WorkflowInstance;
use App\Models\WorkflowShadowLog;
use App\Models\WorkflowStepLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * WorkflowShadowRunner — Phase 5, Slice 2.
 *
 * When a doctor saves a TreatmentVisit with a `current_stage` for a treatment
 * this session knows how to model (see TEMPLATE_MAP), this ALSO drives a
 * shadow WorkflowInstance behind the same `workflow.engine` flag and records
 * whether the engine's view agrees with what the doctor typed manually. This
 * is the parity-proof step — nothing here is authoritative and nothing here
 * can change what actually got saved on the visit.
 *
 * HARD GUARDRAIL: `run()` must never throw past its own boundary. Every
 * failure is caught and logged as a 'error' row instead — a shadow-run bug
 * must never block or corrupt a real treatment visit save. Callers should
 * still invoke this AFTER the visit's own DB transaction has committed, as
 * an extra belt-and-braces layer on top of the internal try/catch.
 *
 * Currently only visits linked to a TreatmentPlan are shadow-run (one
 * WorkflowInstance per plan). Visits without a plan are skipped — a known
 * scope boundary for this slice, not a bug; see docs/phase-5/
 * workflow-engine-proposal.md status notes.
 */
class WorkflowShadowRunner
{
    /**
     * Which template a visit's `treatment_name` maps to. Keyed by the exact
     * string stored in `treatments.name` (== TreatmentVisit::treatment_name)
     * so this lines up with TreatmentVisit::allStagesFromDb().
     */
    private const TEMPLATE_MAP = [
        'rct_staging'      => ['Root Canal Treatment'],
        'implant_staging'  => ['Single Dental Implant'],
    ];

    public function __construct(private WorkflowEngine $engine)
    {
    }

    public function enabled(?int $branchId = null): bool
    {
        return $this->engine->enabled($branchId);
    }

    /**
     * Shadow-run entry point. Safe to call unconditionally after any visit
     * save — it no-ops immediately if the flag is off, the visit has no
     * stage, or the treatment isn't one of the modelled templates.
     */
    public function run(TreatmentVisit $visit): void
    {
        try {
            $this->attempt($visit);
        } catch (Throwable $e) {
            // Absolute last line of defence — a shadow-run bug must never
            // surface to the doctor saving a visit. Log it and move on.
            report($e);
            try {
                WorkflowShadowLog::create([
                    'treatment_visit_id' => $visit->id,
                    'patient_id'         => $visit->patient_id,
                    'template_key'       => 'unknown',
                    'doctor_stage'       => $visit->current_stage,
                    'action'             => 'error',
                    'agreed'             => false,
                    'notes'              => substr($e->getMessage(), 0, 500),
                ]);
            } catch (Throwable $inner) {
                report($inner); // even the error log failed — give up silently
            }
        }
    }

    private function attempt(TreatmentVisit $visit): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (empty($visit->current_stage) || empty($visit->treatment_plan_id) || empty($visit->treatment_name)) {
            return;
        }

        $templateKey = $this->resolveTemplateKey($visit->treatment_name);

        if ($templateKey === null) {
            return; // not a treatment this session models yet
        }

        $this->syncVisit($visit, $templateKey);
    }

    private function resolveTemplateKey(string $treatmentName): ?string
    {
        foreach (self::TEMPLATE_MAP as $key => $names) {
            if (in_array($treatmentName, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    private function syncVisit(TreatmentVisit $visit, string $templateKey): void
    {
        $doctorStage = $visit->current_stage;

        $instance = WorkflowInstance::where('subject_type', TreatmentPlan::class)
            ->where('subject_id', $visit->treatment_plan_id)
            ->whereHas('template', fn ($q) => $q->where('key', $templateKey))
            ->first();

        // ── No shadow instance yet: start one ──────────────────────────
        if (!$instance) {
            $relationship = $visit->patient?->relationship_id
                ? Relationship::find($visit->patient->relationship_id)
                : null;

            $instance = $this->engine->start($templateKey, $relationship, [
                'subject_type' => TreatmentPlan::class,
                'subject_id'   => $visit->treatment_plan_id,
                'actor_id'     => $visit->doctor_id,
            ]);

            if ($instance->current_step === $doctorStage) {
                $this->log($visit, $templateKey, $instance, 'started', true, null);
                return;
            }

            // Doctor's first-logged stage isn't the template's first step
            // (e.g. they only started documenting mid-course). Resync so
            // the shadow tracks reality instead of drifting forever.
            $expectedFirst = $instance->current_step;
            $this->resync($instance, $doctorStage);
            $this->log($visit, $templateKey, $instance->fresh(), 'resynced', false, "template starts at [{$expectedFirst}], doctor's first logged stage was [{$doctorStage}]");
            return;
        }

        // ── Existing instance: try to move it forward in step ──────────
        if ($instance->current_step === $doctorStage) {
            $this->log($visit, $templateKey, $instance, 'noop', true, null);
            return;
        }

        try {
            $updated = $this->engine->advance($instance, $doctorStage, ['actor_id' => $visit->doctor_id]);
            $this->log($visit, $templateKey, $updated, 'advanced', true, null);
        } catch (RuntimeException $e) {
            $expected = $instance->template->nextStepKey($instance->current_step);
            $this->resync($instance, $doctorStage);
            $this->log($visit, $templateKey, $instance->fresh(), 'diverged', false, "expected next step [" . ($expected ?? 'none') . "], doctor typed [{$doctorStage}]");
        }
    }

    /**
     * Shadow-only resync: force the instance's current_step to match what
     * the doctor actually typed, bypassing WorkflowEngine::advance()'s
     * strict linear-order guard. This is deliberately NOT a method on
     * WorkflowEngine itself — real future callers (Slice 4+) must go
     * through the strict advance() so the guard rail stays meaningful. The
     * shadow's only job is to keep observing, even when reality doesn't
     * match the linear model — that mismatch IS the data Slice 4 needs.
     *
     * No-ops silently if $step isn't a key on the template at all (e.g. a
     * stale/free-text value) — the instance is left wherever it was rather
     * than being corrupted by an unrecognised key.
     */
    private function resync(WorkflowInstance $instance, string $step): void
    {
        $tpl = $instance->template;

        if ($tpl->step($step) === null) {
            return;
        }

        DB::transaction(function () use ($instance, $tpl, $step) {
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
                'notes'                => 'shadow resync (parity divergence)',
            ]);
        });
    }

    private function log(TreatmentVisit $visit, string $templateKey, ?WorkflowInstance $instance, string $action, bool $agreed, ?string $notes): void
    {
        WorkflowShadowLog::create([
            'workflow_instance_id' => $instance?->id,
            'treatment_visit_id'   => $visit->id,
            'patient_id'           => $visit->patient_id,
            'template_key'         => $templateKey,
            'doctor_stage'         => $visit->current_stage,
            'action'               => $action,
            'agreed'               => $agreed,
            'notes'                => $notes,
        ]);
    }
}
