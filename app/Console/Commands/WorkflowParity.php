<?php

namespace App\Console\Commands;

use App\Models\WorkflowShadowLog;
use Illuminate\Console\Command;

/**
 * workflow:parity — Phase 5, Slice 4 (cutover decision tooling).
 *
 * Read-only report over `workflow_shadow_log`, the table Slice 2's
 * WorkflowShadowRunner writes to every time a doctor saves a stage on a
 * modelled treatment (currently RCT). This command does NOT re-run
 * anything and does NOT flip any flag — it just summarises what's
 * already accumulated so Sumit can decide whether the engine's linear
 * model is trustworthy enough to build on.
 *
 * The actual cutover decision (Slice 4 in the proposal doc) is a product
 * call, not a coding one — see docs/phase-5/workflow-engine-proposal.md.
 * This command only produces the evidence for that conversation.
 *
 * Usage:
 *   php artisan workflow:parity                 — rct_staging (default)
 *   php artisan workflow:parity implant_staging  — once Slice 5 has data
 */
class WorkflowParity extends Command
{
    protected $signature   = 'workflow:parity {template=rct_staging : Which workflow template to report on}';
    protected $description = 'Report agreement/divergence stats from the Slice 2 Workflow Engine shadow-run (read-only, no flags flipped)';

    public function handle(): int
    {
        $template = $this->argument('template');

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>🔍 Workflow Engine Parity</> — ' . now()->format('D d M Y, H:i'));
        $this->line("  Template: <fg=yellow>{$template}</>");
        $this->newLine();

        $rows = WorkflowShadowLog::where('template_key', $template)->get();

        if ($rows->isEmpty()) {
            $this->warn("  No shadow-run data yet for [{$template}].");
            $this->line('  Data accumulates automatically once the workflow.engine flag is ON');
            $this->line('  and doctors save current_stage on real visits for this treatment.');
            $this->newLine();
            $this->line('  Nothing to decide yet — there is no evidence either way.');
            $this->newLine();
            return self::SUCCESS;
        }

        $total    = $rows->count();
        $agreed   = $rows->where('agreed', true)->count();
        $agreeRate = round(($agreed / $total) * 100, 1);

        $byAction = $rows->groupBy('action')->map->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Shadow-run rows',      $total],
                ['Agreed (started/noop/advanced)', $agreed],
                ['Agreement rate',       $agreeRate . '%'],
                ['  started',            $byAction->get('started', 0)],
                ['  noop',               $byAction->get('noop', 0)],
                ['  advanced',           $byAction->get('advanced', 0)],
                ['  resynced (diverged)', $byAction->get('resynced', 0)],
                ['  diverged',           $byAction->get('diverged', 0)],
                ['  error',              $byAction->get('error', 0)],
            ]
        );

        $divergent = $rows->whereIn('action', ['resynced', 'diverged', 'error']);

        if ($divergent->isNotEmpty()) {
            $this->newLine();
            $this->warn("  ⚠ {$divergent->count()} divergent row(s) — most recent 20:");
            $this->table(
                ['Visit ID', 'Patient ID', 'Doctor Stage', 'Action', 'Notes'],
                $divergent->sortByDesc('id')->take(20)->map(fn ($r) => [
                    $r->treatment_visit_id,
                    $r->patient_id,
                    $r->doctor_stage,
                    $r->action,
                    $r->notes ? \Illuminate\Support\Str::limit($r->notes, 60) : '',
                ])->values()->all()
            );
        }

        $this->newLine();
        $this->line('  This report does not flip any flag. The cutover decision (whether');
        $this->line("  current_stage becomes engine-constrained, or the engine stays an");
        $this->line('  advisory sidebar) is Sumit\'s product call — review the divergence');
        $this->line('  rows above with him before proposing either option.');
        $this->newLine();

        return self::SUCCESS;
    }
}
