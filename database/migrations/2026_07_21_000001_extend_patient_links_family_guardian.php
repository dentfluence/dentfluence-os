<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Patients Phase 3 — Family / Guardian · Slice 1 (foundation).
 *
 * Additive, production-safe extension of `patient_links`:
 *   1. relationship_type — biological/social relationship ONLY
 *      (mother, father, spouse, child, sibling, other). Guardianship is NOT a
 *      relationship type; `ward` is derived, never stored.
 *   2. is_guardian — the single representation of legal/consent guardian
 *      authority (a capacity flag, orthogonal to relationship_type).
 *   3. notes — optional operational note (e.g. "primary guardian").
 *
 * The legacy free-text `relationship` column is BACKFILLED into
 * `relationship_type` (+ is_guardian where the text says "guardian") and then
 * left in place, READ-ONLY, until a future removal — no dual-write.
 *
 * Finally a MySQL 8 functional UNIQUE index on the ordered pair
 * (LEAST(patient_id, linked_patient_id), GREATEST(...)) enforces one link per
 * patient pair at the database level, closing the reverse-direction
 * (A→B / B→A) gap the existing UNIQUE(patient_id, linked_patient_id) cannot.
 * Any pre-existing reverse-duplicate rows are collapsed first so the index can
 * be created without violating production data.
 *
 * Reversible: down() drops the index and the three columns. (The one-time
 * reverse-duplicate collapse is not restorable — it removes only redundant
 * rows, never unique family data.)
 */
return new class extends Migration
{
    private const PAIR_INDEX = 'patient_links_pair_unique';

    public function up(): void
    {
        if (! Schema::hasTable('patient_links')) {
            return;
        }

        // 1. Additive columns (guarded so the migration is safe to re-run).
        Schema::table('patient_links', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_links', 'relationship_type')) {
                $table->string('relationship_type', 30)->nullable()->default('other')
                    ->after('relationship')
                    ->comment('Biological/social relationship only: mother, father, spouse, child, sibling, other. Guardianship = is_guardian; ward is derived.');
            }
            if (! Schema::hasColumn('patient_links', 'is_guardian')) {
                $table->boolean('is_guardian')->default(false)
                    ->after('relationship_type')
                    ->comment('Single representation of legal/consent guardian authority (DPDP anchor). Ward is the derived inverse, never stored.');
            }
            if (! Schema::hasColumn('patient_links', 'notes')) {
                $table->string('notes', 150)->nullable()
                    ->after('is_guardian')
                    ->comment('Optional operational note, e.g. "primary guardian", "non-custodial". Non-clinical.');
            }
        });

        // 2. Backfill legacy `relationship` free text → relationship_type (+ is_guardian).
        if (Schema::hasColumn('patient_links', 'relationship')) {
            DB::table('patient_links')
                ->select('id', 'relationship')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        [$type, $isGuardian] = $this->mapLegacyRelationship($row->relationship);
                        DB::table('patient_links')->where('id', $row->id)->update([
                            'relationship_type' => $type,
                            'is_guardian'       => $isGuardian,
                        ]);
                    }
                });
        }

        // 3. Collapse any pre-existing reverse-duplicate pairs (A→B and B→A) so
        //    the functional unique index can be created safely. Guardian
        //    authority is unioned; the more specific relationship_type wins.
        $this->collapseReversePairs();

        // 4. Functional UNIQUE index on the ordered pair (MySQL 8 only).
        if (DB::getDriverName() === 'mysql' && ! $this->pairIndexExists()) {
            DB::statement(
                'ALTER TABLE patient_links ADD UNIQUE INDEX ' . self::PAIR_INDEX .
                ' ((LEAST(patient_id, linked_patient_id)), (GREATEST(patient_id, linked_patient_id)))'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('patient_links')) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && $this->pairIndexExists()) {
            DB::statement('ALTER TABLE patient_links DROP INDEX ' . self::PAIR_INDEX);
        }

        Schema::table('patient_links', function (Blueprint $table) {
            foreach (['notes', 'is_guardian', 'relationship_type'] as $col) {
                if (Schema::hasColumn('patient_links', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Coarse best-effort map of legacy free text to the canonical vocabulary.
     * Unrecognised text → 'other'. Any mention of "guardian" sets is_guardian.
     *
     * @return array{0:string,1:bool}  [relationship_type, is_guardian]
     */
    private function mapLegacyRelationship(?string $relationship): array
    {
        $r = strtolower(trim((string) $relationship));

        if ($r === '') {
            return ['other', false];
        }

        // "grandmother/grandfather/grandson…" must not coarse-map to mother/father/child.
        if (str_contains($r, 'grand')) {
            return ['other', str_contains($r, 'guardian')];
        }

        $isGuardian = str_contains($r, 'guardian');

        if (str_contains($r, 'husband') || str_contains($r, 'wife')
            || str_contains($r, 'spouse') || str_contains($r, 'partner')) {
            return ['spouse', $isGuardian];
        }
        if (str_contains($r, 'mother') || $r === 'mom' || $r === 'mummy' || $r === 'maa') {
            return ['mother', $isGuardian];
        }
        if (str_contains($r, 'father') || $r === 'dad' || $r === 'papa') {
            return ['father', $isGuardian];
        }
        if (str_contains($r, 'son') || str_contains($r, 'daughter')
            || str_contains($r, 'child') || str_contains($r, 'kid')) {
            return ['child', $isGuardian];
        }
        if (str_contains($r, 'brother') || str_contains($r, 'sister') || str_contains($r, 'sibling')) {
            return ['sibling', $isGuardian];
        }

        // Generic "parent"/"guardian"/anything else — cannot resolve to a specific
        // kinship; keep 'other' but preserve any guardian signal.
        return ['other', $isGuardian];
    }

    /**
     * Merge reverse-duplicate rows (A→B and B→A) into a single kept row so the
     * ordered-pair unique index does not fail on existing data. Never removes a
     * unique pair — only the redundant second row of the same pair.
     */
    private function collapseReversePairs(): void
    {
        $seen = []; // "min-max" => kept row id

        DB::table('patient_links')
            ->select('id', 'patient_id', 'linked_patient_id', 'relationship_type', 'is_guardian', 'notes')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $a = min($row->patient_id, $row->linked_patient_id);
                    $b = max($row->patient_id, $row->linked_patient_id);
                    $key = $a . '-' . $b;

                    if (! isset($seen[$key])) {
                        $seen[$key] = $row->id;
                        continue;
                    }

                    // Reverse/duplicate of an already-kept pair — reconcile onto the kept row.
                    $keptId = $seen[$key];
                    $kept   = DB::table('patient_links')->where('id', $keptId)->first();
                    if ($kept) {
                        $update = [];

                        // Guardian authority is never lost.
                        if ((int) $row->is_guardian === 1 && (int) $kept->is_guardian === 0) {
                            $update['is_guardian'] = true;
                        }
                        // Prefer a specific relationship_type over 'other'.
                        if (in_array($kept->relationship_type, [null, 'other'], true)
                            && ! in_array($row->relationship_type, [null, 'other'], true)) {
                            $update['relationship_type'] = $row->relationship_type;
                        }
                        // Backfill notes if the kept row has none.
                        if (empty($kept->notes) && ! empty($row->notes)) {
                            $update['notes'] = $row->notes;
                        }

                        if ($update) {
                            DB::table('patient_links')->where('id', $keptId)->update($update);
                        }
                    }

                    DB::table('patient_links')->where('id', $row->id)->delete();
                }
            });
    }

    private function pairIndexExists(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            ['patient_links', self::PAIR_INDEX]
        );

        return $row && (int) $row->c > 0;
    }
};
