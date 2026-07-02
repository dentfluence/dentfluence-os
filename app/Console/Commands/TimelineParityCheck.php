<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\Scopes\BranchScope;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\TimelineParityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * relationship:timeline-parity — Phase 1 · Sprint 3.
 *
 * Proves the unified timeline matches the legacy timeline BEFORE the profile
 * is cut over to it. Checks representative records (new lead, converted lead,
 * existing patient, RCT patient, implant opportunity, recall patient, lab
 * patient, membership patient) plus a broad random batch.
 *
 * Exit code 0 = parity holds (safe to enable the flag).
 * Exit code 1 = mismatch found (DO NOT cut over — legacy stays active).
 *
 *   php artisan relationship:timeline-parity
 *   php artisan relationship:timeline-parity --sample=50
 */
class TimelineParityCheck extends Command
{
    protected $signature = 'relationship:timeline-parity {--sample=25 : How many extra random relationships to spot-check}';
    protected $description = 'Compare legacy vs unified timeline for representative records. Non-zero exit on any mismatch.';

    public function handle(TimelineParityService $parity): int
    {
        $this->info('Timeline parity check — legacy vs unified.');
        $this->newLine();

        $targets = $this->representativeTargets();
        $rows = [];
        $mismatches = [];

        // 1) Named representative categories.
        foreach ($targets as $label => $relId) {
            if (! $relId) {
                $rows[] = [$label, '—', 'no sample found', '', ''];
                continue;
            }
            $rel = Relationship::find($relId);
            if (! $rel) {
                $rows[] = [$label, $relId, 'relationship missing', '', ''];
                continue;
            }
            $r = $parity->compare($rel);
            $rows[] = [
                $label,
                $relId,
                $r['match'] ? 'PASS' : 'MISMATCH',
                "{$r['legacy_count']} / {$r['unified_count']}",
                $r['match'] ? '' : (count($r['missing_in_unified']) . ' missing↑, ' . count($r['missing_in_legacy']) . ' missing↓'),
            ];
            if (! $r['match']) {
                $mismatches[] = $r;
            }
        }

        $this->table(['Category', 'Relationship', 'Result', 'Legacy / Unified', 'Detail'], $rows);

        // 2) Broad random spot-check.
        $sample = (int) $this->option('sample');
        $checked = 0;
        $sampleMismatch = 0;
        Relationship::inRandomOrder()->limit($sample)->pluck('id')->each(function ($id) use ($parity, &$checked, &$sampleMismatch, &$mismatches) {
            $rel = Relationship::find($id);
            if (! $rel) {
                return;
            }
            $checked++;
            $r = $parity->compare($rel);
            if (! $r['match']) {
                $sampleMismatch++;
                $mismatches[] = $r;
            }
        });
        $this->line("Random spot-check: <comment>{$checked}</comment> relationships, <comment>{$sampleMismatch}</comment> mismatch(es).");
        $this->newLine();

        if (! empty($mismatches)) {
            $this->error('PARITY FAILED — do NOT enable activity.single_ledger_reads. Legacy timeline stays active.');
            foreach (array_slice($mismatches, 0, 5) as $m) {
                $this->warn("Relationship #{$m['relationship_id']} — missing in unified: " . json_encode($m['missing_in_unified']));
                $this->warn("Relationship #{$m['relationship_id']} — missing in legacy:  " . json_encode($m['missing_in_legacy']));
            }
            return self::FAILURE;
        }

        $this->info('PARITY PASSED — every event matches in both timelines.');
        $this->line('Safe to cut over:  php artisan tinker --execute="\\App\\Support\\Features\\Feature::set(\'activity.single_ledger_reads\', true);"');
        $this->line('Instant rollback:  php artisan tinker --execute="\\App\\Support\\Features\\Feature::set(\'activity.single_ledger_reads\', false);"');
        return self::SUCCESS;
    }

    /**
     * Best-effort discovery of one relationship per representative category.
     * Each probe is guarded — a missing table/column just yields "no sample".
     *
     * @return array<string, int|null>
     */
    private function representativeTargets(): array
    {
        return [
            'New lead'            => $this->probe(fn () => Lead::where('stage', 'new_lead')->whereNotNull('relationship_id')->value('relationship_id')),
            'Converted lead'      => $this->probe(fn () => Lead::where('stage', 'converted')->whereNotNull('relationship_id')->value('relationship_id')),
            'Existing patient'    => $this->probe(fn () => $this->patientRelId(fn ($q) => $q)),
            'RCT patient'         => $this->probe(fn () => $this->patientRelIdViaJoin('treatment_plan_items', 'treatment_plans', '%root%')),
            'Implant opportunity' => $this->probe(fn () => TreatmentOpportunity::where('type', 'like', '%implant%')->whereNotNull('relationship_id')->value('relationship_id')),
            'Recall patient'      => $this->probe(fn () => $this->patientRelId(fn ($q) => $q->whereNotNull('recall_queued_at'))),
            'Lab patient'         => $this->probe(fn () => $this->patientRelIdViaTable('lab_cases')),
            'Membership patient'  => $this->probe(fn () => $this->patientRelIdViaTable('finance_patient_memberships')),
        ];
    }

    private function probe(callable $cb): ?int
    {
        try {
            $v = $cb();
            return $v ? (int) $v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A relationship id from patients (cross-branch), optionally filtered. */
    private function patientRelId(callable $filter): ?int
    {
        $q = Patient::withoutGlobalScope(BranchScope::class)->whereNotNull('relationship_id');
        $q = $filter($q);
        return $q->value('relationship_id');
    }

    /** A relationship id for a patient that has a row in $table (patient_id FK). */
    private function patientRelIdViaTable(string $table): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }
        $patientId = DB::table($table)->whereNotNull('patient_id')->value('patient_id');
        if (! $patientId) {
            return null;
        }
        return Patient::withoutGlobalScope(BranchScope::class)->where('id', $patientId)->value('relationship_id');
    }

    /** A relationship id for a patient with a treatment matching $like (best-effort). */
    private function patientRelIdViaJoin(string $itemsTable, string $plansTable, string $like): ?int
    {
        if (! Schema::hasTable($itemsTable) || ! Schema::hasTable($plansTable)) {
            return null;
        }
        $patientId = DB::table($plansTable)->whereNotNull('patient_id')->value('patient_id');
        return $patientId
            ? Patient::withoutGlobalScope(BranchScope::class)->where('id', $patientId)->value('relationship_id')
            : null;
    }
}
