<?php

namespace App\Console\Commands\ClinicalLibrary;

use App\Models\ClinicalFile;
use App\Services\ClinicalLibrary\TreatmentCategoryDetector;
use Illuminate\Console\Command;

/**
 * Clinical Library Slice 2 — backfill treatment_category on existing records.
 *
 * Usage:
 *   php artisan clinical-library:backfill-treatment-category --dry-run   (preview only)
 *   php artisan clinical-library:backfill-treatment-category             (live)
 *
 * Idempotent and non-destructive: only touches rows where treatment_category
 * is currently null. Safe to run multiple times — already-categorized rows
 * (whether auto-detected or manually corrected) are never overwritten.
 *
 * Uses the same rule-based keyword match applied to new uploads
 * (TreatmentCategoryDetector) so historical and new records are categorized
 * consistently. Rows whose procedure text doesn't match any keyword are left
 * uncategorized (null) rather than guessed — correct them manually from the
 * Content Manager if needed.
 */
class BackfillTreatmentCategory extends Command
{
    protected $signature = 'clinical-library:backfill-treatment-category
                            {--dry-run : Preview what would be updated without writing anything}';

    protected $description = 'Clinical Library — auto-detect treatment_category for existing clinical_files rows that don\'t have one yet';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('');
            $this->warn('  ⚡  DRY RUN — no data will be written  ⚡');
            $this->warn('');
        } else {
            $this->info('');
            $this->info('  LIVE MODE — treatment_category WILL be updated on matching rows');
            $this->info('');
        }

        $counts   = array_fill_keys(array_keys(ClinicalFile::TREATMENT_CATEGORIES), 0);
        $noMatch  = 0;
        $updated  = 0;

        ClinicalFile::whereNull('treatment_category')
            ->whereNotNull('procedure')
            ->chunkById(200, function ($files) use (&$counts, &$noMatch, &$updated, $isDryRun) {
                foreach ($files as $file) {
                    $category = TreatmentCategoryDetector::detect($file->procedure);

                    if ($category === null) {
                        $noMatch++;
                        continue;
                    }

                    $counts[$category]++;
                    $updated++;

                    if (! $isDryRun) {
                        $file->updateQuietly(['treatment_category' => $category]);
                    }
                }
            });

        $this->table(
            ['Category', 'Matched'],
            collect($counts)
                ->filter(fn($count) => $count > 0)
                ->map(fn($count, $key) => [ClinicalFile::TREATMENT_CATEGORIES[$key], $count])
                ->values()
        );

        $this->info("Matched and " . ($isDryRun ? 'would update' : 'updated') . ": {$updated}");
        $this->info("No keyword match (left uncategorized): {$noMatch}");

        if ($isDryRun) {
            $this->warn('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
