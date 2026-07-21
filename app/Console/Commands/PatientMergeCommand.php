<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\Patient\PatientMergeService;
use Illuminate\Console\Command;

/**
 * patients:merge {master} {loser} [--dry-run] [--reason=]
 *
 * Admin/testing entry point for the patient merge. Accepts numeric row ids.
 * --dry-run prints the "records to be moved" preview and changes nothing.
 * Without --dry-run it executes the merge inside one transaction.
 *
 * The web wizard (slice 3) drives the SAME PatientMergeService — this command
 * is the canonical way to test the engine before there is any UI.
 */
class PatientMergeCommand extends Command
{
    protected $signature = 'patients:merge
                            {master : Surviving patient row id}
                            {loser  : Duplicate patient row id to merge in}
                            {--dry-run : Preview what would move; make no changes}
                            {--reason=manual : Reason recorded on the merge}';

    protected $description = 'Merge a duplicate patient into a surviving one (use --dry-run to preview).';

    public function handle(PatientMergeService $service): int
    {
        $master = Patient::find((int) $this->argument('master'));
        $loser  = Patient::find((int) $this->argument('loser'));

        if (! $master || ! $loser) {
            $this->error('Master or loser patient not found.');
            return self::FAILURE;
        }
        if ($master->id === $loser->id) {
            $this->error('Master and loser are the same record.');
            return self::FAILURE;
        }

        $this->line("Master (surviving): #{$master->id}  {$master->name}  [{$master->patient_id}]");
        $this->line("Loser  (merged in): #{$loser->id}  {$loser->name}  [{$loser->patient_id}]");
        $this->line('');

        $preview = $service->preview($loser);

        $rows = [];
        foreach (['children' => 'child', 'money' => 'money', 'special' => 'special'] as $bucket => $label) {
            foreach ($preview[$bucket] as $table => $n) {
                $rows[] = [$label, $table, $n];
            }
        }
        if ($rows) {
            $this->table(['Bucket', 'Table', 'Rows to move'], $rows);
        } else {
            $this->comment('No patient_id child rows to move.');
        }
        $this->line("Relationship activity entries (delegated): {$preview['relationship']}");
        $this->line("Total patient_id rows to move: {$preview['total']}");
        $this->line('');

        if ($this->option('dry-run')) {
            $this->info('DRY RUN — nothing changed.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Merge #{$loser->id} into #{$master->id}? This archives the loser and is only reversible by an admin un-merge.")) {
            $this->comment('Aborted.');
            return self::SUCCESS;
        }

        try {
            $record = $service->merge($master, $loser, [], null, (string) $this->option('reason'));
        } catch (\Throwable $e) {
            $this->error('Merge failed (rolled back): '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Merged. PatientMerge #{$record->id} recorded"
            .($record->relationship_merge_id ? " (relationship merge #{$record->relationship_merge_id})" : '').'.');
        $this->line("Loser #{$loser->id} archived → redirects to master #{$master->id}.");
        return self::SUCCESS;
    }
}
