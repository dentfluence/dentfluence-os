<?php

namespace App\Console\Commands;

use App\Services\Patient\PatientMergeManifest;
use App\Services\Relationship\MergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * patients:merge-coverage
 *
 * Proves — against the LIVE database — that the patient-merge manifest accounts
 * for every table that references a patient, on BOTH axes:
 *   • patient_id        (direct children, money, special, or deliberately skipped)
 *   • relationship_id   (the PRE cascade delegated to Relationship\MergeService)
 *
 * Fails (non-zero exit) if any real patient_id / relationship_id table is left
 * unclassified, so a merge can never silently fragment a record. Also surfaces
 * the gap between what Relationship\MergeService actually moves and what the
 * manifest expects it to. Read-only — moves nothing.
 */
class PatientMergeCoverage extends Command
{
    protected $signature = 'patients:merge-coverage';

    protected $description = 'Verify the patient-merge manifest covers every patient/relationship table in the live schema (read-only).';

    public function handle(): int
    {
        $db = DB::getDatabaseName();

        $tablesWith = function (string $column) use ($db): array {
            // Alias TABLE_NAME explicitly — some MySQL builds return
            // information_schema columns upper-cased, which breaks pluck('table_name').
            return collect(DB::table('information_schema.columns')
                ->where('table_schema', $db)
                ->where('column_name', $column)
                ->selectRaw('TABLE_NAME as name')
                ->pluck('name'))
                ->map(fn ($t) => strtolower($t))
                ->unique()
                ->sort()
                ->values()
                ->all();
        };

        $patientIdTables = $tablesWith('patient_id');
        $relIdTables     = $tablesWith('relationship_id');

        $coveredPatient = array_map('strtolower', PatientMergeManifest::patientIdCovered());
        $coveredRel     = array_map('strtolower', PatientMergeManifest::relationshipIdCovered());

        $uncoveredPatient = array_values(array_diff($patientIdTables, $coveredPatient));
        $uncoveredRel     = array_values(array_diff($relIdTables, $coveredRel));

        // Declared-but-absent (stale manifest entries) — informational only.
        $stalePatient = array_values(array_diff(
            array_map('strtolower', array_merge(
                PatientMergeManifest::CHILD_TABLES,
                PatientMergeManifest::MONEY_TABLES,
                PatientMergeManifest::SPECIAL_TABLES,
            )),
            $patientIdTables
        ));

        $this->info("Database: {$db}");
        $this->line('');
        $this->info("patient_id tables in schema: " . count($patientIdTables));
        $this->info("relationship_id tables in schema: " . count($relIdTables));
        $this->line('');

        // ── patient_id axis ────────────────────────────────────────────────
        if ($uncoveredPatient) {
            $this->error('UNCLASSIFIED patient_id tables (add each to a manifest bucket):');
            foreach ($uncoveredPatient as $t) {
                $this->line("   • {$t}");
            }
        } else {
            $this->info('patient_id axis: every table is classified. ✓');
        }
        $this->line('');

        // ── relationship_id axis ───────────────────────────────────────────
        if ($uncoveredRel) {
            $this->error('UNCLASSIFIED relationship_id tables (add to RELATIONSHIP_TABLES or SKIP_TABLES):');
            foreach ($uncoveredRel as $t) {
                $this->line("   • {$t}");
            }
        } else {
            $this->info('relationship_id axis: every table is classified. ✓');
        }
        $this->line('');

        // ── Gap between the delegate engine and our expectation ────────────
        $engineTables = array_map('strtolower',
            (array) (new \ReflectionClass(MergeService::class))->getConstant('TARGET_TABLES'));
        $expectedRel  = array_map('strtolower', PatientMergeManifest::RELATIONSHIP_TABLES);
        $engineMisses = array_values(array_intersect(
            array_diff($expectedRel, $engineTables),  // we expect moved
            $relIdTables                              // and it really exists
        ));
        if ($engineMisses) {
            $this->warn('Relationship\\MergeService does NOT currently move these relationship_id tables:');
            foreach ($engineMisses as $t) {
                $this->line("   • {$t}");
            }
            $this->warn('  → decide (slice 2): extend the delegate engine, or reclassify as SKIP.');
            $this->line('');
        }

        if ($stalePatient) {
            $this->comment('Manifest entries not present in this schema (stale/optional):');
            $this->line('   ' . implode(', ', $stalePatient));
            $this->line('');
        }

        if ($uncoveredPatient || $uncoveredRel) {
            $this->error('COVERAGE INCOMPLETE — classify the tables above before building re-parenting (slice 2).');
            return self::FAILURE;
        }

        $this->info('Coverage complete — manifest accounts for every patient/relationship table.');
        return self::SUCCESS;
    }
}
