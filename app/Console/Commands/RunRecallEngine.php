<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\RecallEngineService;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Console\Command;

/**
 * RunRecallEngine — Phase 2 Communication OS.
 *
 * Runs the Recall Engine service and outputs a summary.
 * Scheduled daily at 7am in routes/console.php.
 *
 * Usage:
 *   php artisan recall:run           — standard run
 *   php artisan recall:run --dry-run — shows what would be queued (no DB writes)
 */
class RunRecallEngine extends Command
{
    protected $signature   = 'recall:run {--dry-run : Preview only, no records created}';
    protected $description = 'Run the Recall Engine — auto-creates communication_queue items for patient follow-up';

    public function handle(RecallEngineService $engine): int
    {
        $isDryRun = $this->option('dry-run');

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>🔔 Dentfluence Recall Engine</> — ' . now()->format('D d M Y, H:i'));
        $this->newLine();

        if ($isDryRun) {
            $this->warn('  ⚠  DRY RUN — no records will be created.');
            $this->newLine();
            // TODO: extend service to support dry-run mode
            return self::SUCCESS;
        }

        $this->line('  Running 6 recall triggers...');
        $this->newLine();

        $summary = $engine->runAll();

        $labels = [
            'no_visit_6months'    => '6-month no-visit recall',
            'approved_plan_no_appt' => 'Approved plan, no appointment',
            'post_op_followup'    => 'Post-op follow-up (14 days)',
            'lab_received_no_appt' => 'Lab received, no appointment',
            'recent_tx_followup'  => '7-day treatment follow-up',
            'birthday'            => 'Birthday',
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            $count  = $summary[$key] ?? 0;
            $status = $count > 0 ? "<fg=green>{$count} queued</>" : '<fg=gray>0</>';
            $rows[] = [$label, $status];
        }

        $this->table(['Trigger', 'Items Queued'], $rows);

        $total = $summary['total'] ?? 0;
        $this->newLine();
        $this->line("  <fg=green;options=bold>✓ Total items queued: {$total}</>");
        $this->newLine();

        // Phase 4 — log engine run summary to ActivityEngine.
        // Uses a dummy Patient record as the subject (system event, no specific patient).
        // Never blocks the command if logging fails.
        try {
            $anyPatient = Patient::first();
            if ($anyPatient) {
                app(ActivityEngine::class)->log(
                    $anyPatient,
                    'recall.engine_run',
                    null,  // system action
                    array_merge($summary, [
                        'ran_at'   => now()->toIso8601String(),
                        'dry_run'  => false,
                    ]),
                );
            }
        } catch (\Throwable) {
            // Logging failure must never block the recall run
        }

        return self::SUCCESS;
    }
}
