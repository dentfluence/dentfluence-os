<?php

namespace App\Console\Commands\ClinicalLibrary;

use App\Models\ClinicalFile;
use App\Services\ClinicalLibrary\MarketingEligibilityDetector;
use Illuminate\Console\Command;

/**
 * Clinical Library Slice 4 — backfill marketing eligibility for existing
 * before/after pairs that were uploaded before this automation existed.
 *
 * Usage:
 *   php artisan clinical-library:backfill-marketing-eligibility --dry-run
 *   php artisan clinical-library:backfill-marketing-eligibility
 *
 * Idempotent and non-destructive: re-runs the same detector every new upload
 * already goes through. Only ever moves a file into the review queue
 * (marketing_status: null → 'pending') — never touches consent_status, and
 * never re-opens a file already approved or rejected by a human.
 */
class BackfillMarketingEligibility extends Command
{
    protected $signature = 'clinical-library:backfill-marketing-eligibility
                            {--dry-run : Preview how many files would enter the review queue without writing anything}';

    protected $description = 'Clinical Library — flag existing before/after pairs as marketing-eligible';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('');
            $this->warn('  ⚡  DRY RUN — no data will be written  ⚡');
            $this->warn('');
        }

        $before = ClinicalFile::where('marketing_status', 'pending')
            ->where('is_marketing_eligible', true)
            ->count();

        if (! $isDryRun) {
            ClinicalFile::whereIn('stage', ['before', 'after', 'followup'])
                ->whereNotNull('patient_id')
                ->orderBy('id')
                ->chunkById(200, function ($files) {
                    foreach ($files as $file) {
                        MarketingEligibilityDetector::checkAndFlag($file);
                    }
                });
        } else {
            // Dry run: run the same grouping query the detector uses, just to
            // count candidate pairs, without writing anything.
            $candidates = ClinicalFile::whereIn('stage', ['before', 'after', 'followup'])
                ->whereNotNull('patient_id')
                ->whereNull('marketing_status')
                ->get(['id', 'patient_id', 'treatment_category', 'procedure', 'stage']);

            $pairedIds = [];
            foreach ($candidates as $file) {
                $column   = $file->treatment_category ? 'treatment_category' : 'procedure';
                $groupKey = $file->treatment_category ?? $file->procedure;
                if (blank($groupKey)) continue;

                $oppositeStages = $file->stage === 'before' ? ['after', 'followup'] : ['before'];
                $hasOpposite = $candidates->contains(function ($other) use ($file, $column, $groupKey, $oppositeStages) {
                    return $other->patient_id === $file->patient_id
                        && $other->$column === $groupKey
                        && in_array($other->stage, $oppositeStages, true);
                });

                if ($hasOpposite) {
                    $pairedIds[] = $file->id;
                }
            }

            $this->info('Would newly flag ' . count($pairedIds) . ' file(s) as marketing-eligible (pending review).');
            $this->warn('Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        $after = ClinicalFile::where('marketing_status', 'pending')
            ->where('is_marketing_eligible', true)
            ->count();

        $this->info("Newly flagged as marketing-eligible (pending review): " . ($after - $before));

        return self::SUCCESS;
    }
}
