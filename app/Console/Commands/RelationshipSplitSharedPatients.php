<?php

namespace App\Console\Commands;

use App\Services\Relationship\RelationshipSplitService;
use Illuminate\Console\Command;

/**
 * relationship:split-shared-patients — one-time repair.
 *
 * Un-merges patients that were incorrectly linked to the same Relationship
 * purely because they share a phone number (common when a household shares
 * one registered contact number). Clinic ID (the Patient record) is the only
 * unique identity for a patient — see
 * RelationshipEngine::findOrCreateForPatient(), which prevents this from
 * happening again for any patient linked from now on. This command only
 * repairs what already happened before that fix.
 *
 *   php artisan relationship:split-shared-patients                 # DRY RUN (read-only report)
 *   php artisan relationship:split-shared-patients --apply         # write the split (asks to confirm)
 *   php artisan relationship:split-shared-patients --apply --force # write, no prompt
 *
 * Dry-run is the default and safe. --apply never touches clinical data —
 * only the relationship_id link and the new Relationship rows it creates.
 */
class RelationshipSplitSharedPatients extends Command
{
    protected $signature = 'relationship:split-shared-patients
        {--apply : Write the split (default is a read-only dry run)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'One-time repair: give every patient sharing a household Relationship its own dedicated Relationship. Dry-run by default.';

    public function handle(RelationshipSplitService $svc): int
    {
        if (! $this->option('apply')) {
            $r = $svc->analyze();
            $this->line('<info>DRY RUN</info> — nothing was changed.');
            $this->table(
                ['Shared relationships found', 'Patients that would be split off'],
                [[$r['shared_relationships'], $r['patients_to_split_off']]]
            );
            $this->newLine();
            $this->line('Run with <comment>--apply</comment> to give each of those patients their own Relationship.');
            return self::SUCCESS;
        }

        $this->warn('This creates a new Relationship for every patient currently sharing one with another patient (the earliest-registered patient in each group stays on the original).');
        if (! ($this->option('force') || $this->confirm('Continue?', false))) {
            $this->warn('Aborted — no changes made.');
            return self::FAILURE;
        }

        $r = $svc->apply();
        $this->info('Split applied.');
        $this->table(
            ['Patients split off', 'Failed/Skipped'],
            [[$r['patients_split'], $r['failed']]]
        );
        return self::SUCCESS;
    }
}
