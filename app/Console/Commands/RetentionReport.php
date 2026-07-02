<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

/**
 * dpdp:retention-report (DPDP 5.4)
 * --------------------------------
 * READ-ONLY. Prints how many records are past their retention window for each
 * active policy. Deletes nothing. Safe to schedule (e.g. monthly) as a heads-up
 * report. Actual purging is intentionally a separate, sign-off-gated step.
 */
class RetentionReport extends Command
{
    protected $signature = 'dpdp:retention-report';

    protected $description = 'Dry-run: report records past their DPDP retention window (deletes nothing)';

    public function handle(RetentionService $retention): int
    {
        $rows = $retention->report();

        if ($rows->isEmpty()) {
            $this->warn('No active retention policies. Seed them: php artisan db:seed --class=RetentionPolicySeeder');
            return self::SUCCESS;
        }

        $this->info('DPDP retention dry-run — ' . now()->toDateTimeString());
        $this->table(
            ['Data type', 'Retain (days)', 'Cutoff', 'Action', 'Past-window count'],
            $rows->map(fn ($r) => [
                $r['policy']->data_type,
                $r['policy']->retain_days,
                $r['cutoff'],
                $r['policy']->action,
                $r['count'] ?? 'n/a',
            ])->all()
        );

        $this->line('Nothing was deleted — this is a report only.');
        return self::SUCCESS;
    }
}
