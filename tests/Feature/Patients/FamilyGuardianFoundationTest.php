<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\PatientLink;
use App\Services\Patient\PatientMergeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Patients Phase 3 — Family / Guardian · Slice 1 (foundation).
 *
 * Covers ONLY the foundation: schema/columns, legacy backfill, the ordered-pair
 * functional unique constraint, the PatientLink model, and the merge precedence
 * rules (guardian preservation, duplicate-pair reconciliation, guardian↔ward
 * block). No UI / service / API — those are later slices.
 */
class FamilyGuardianFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(string $name): Patient
    {
        return Patient::create(['name' => $name, 'phone' => '9' . random_int(100000000, 999999999)]);
    }

    private function link(int $a, int $b, string $type = 'other', bool $guardian = false, ?string $notes = null): int
    {
        return DB::table('patient_links')->insertGetId([
            'patient_id'        => $a,
            'linked_patient_id' => $b,
            'relationship_type' => $type,
            'is_guardian'       => $guardian,
            'notes'             => $notes,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function linksBetween(int $a, int $b)
    {
        // Explicit nested closures: the associative-array form of orWhere() joins
        // its keys with OR, not AND — which would over-match.
        return DB::table('patient_links')
            ->where(function ($q) use ($a, $b) {
                $q->where(function ($w) use ($a, $b) {
                    $w->where('patient_id', $a)->where('linked_patient_id', $b);
                })->orWhere(function ($w) use ($a, $b) {
                    $w->where('patient_id', $b)->where('linked_patient_id', $a);
                });
            })
            ->get();
    }

    // ── Migration: columns exist ────────────────────────────────────────────────

    public function test_migration_adds_the_family_guardian_columns(): void
    {
        $this->assertTrue(\Schema::hasColumn('patient_links', 'relationship_type'));
        $this->assertTrue(\Schema::hasColumn('patient_links', 'is_guardian'));
        $this->assertTrue(\Schema::hasColumn('patient_links', 'notes'));
        // Legacy column is retained (read-only), never dropped.
        $this->assertTrue(\Schema::hasColumn('patient_links', 'relationship'));
    }

    // ── Migration: legacy backfill mapping ──────────────────────────────────────

    public function test_backfill_maps_legacy_relationship_text_to_the_canonical_vocabulary(): void
    {
        $p = [];
        for ($i = 0; $i < 8; $i++) {
            $p[$i] = $this->makePatient("Backfill {$i}")->id;
        }

        // Insert rows carrying only legacy free text (relationship_type left at the 'other' default).
        $husband  = $this->link($p[0], $p[1]); DB::table('patient_links')->where('id', $husband)->update(['relationship' => 'Husband']);
        $mother   = $this->link($p[2], $p[3]); DB::table('patient_links')->where('id', $mother)->update(['relationship' => 'Mother']);
        $guardian = $this->link($p[4], $p[5]); DB::table('patient_links')->where('id', $guardian)->update(['relationship' => 'Legal Guardian']);
        $grandma  = $this->link($p[6], $p[7]); DB::table('patient_links')->where('id', $grandma)->update(['relationship' => 'Grandmother']);

        // Re-run the migration up() — `require` re-executes the file and returns a fresh instance.
        $migration = require database_path('migrations/2026_07_21_000001_extend_patient_links_family_guardian.php');
        $migration->up();

        $this->assertSame('spouse', DB::table('patient_links')->where('id', $husband)->value('relationship_type'));
        $this->assertSame('mother', DB::table('patient_links')->where('id', $mother)->value('relationship_type'));

        // "Guardian" text → is_guardian flag, relationship_type stays 'other' (not a kinship).
        $g = DB::table('patient_links')->where('id', $guardian)->first();
        $this->assertSame('other', $g->relationship_type);
        $this->assertSame(1, (int) $g->is_guardian);

        // "Grandmother" must NOT coarse-map to 'mother'.
        $this->assertSame('other', DB::table('patient_links')->where('id', $grandma)->value('relationship_type'));
    }

    // ── Functional unique constraint (ordered pair) ─────────────────────────────

    public function test_functional_unique_index_blocks_reverse_direction_duplicate(): void
    {
        $a = $this->makePatient('Pair A')->id;
        $b = $this->makePatient('Pair B')->id;

        $this->link($a, $b, 'sibling');

        // The reverse (B→A) is the SAME pair — the ordered-pair index must reject it.
        $this->expectException(QueryException::class);
        $this->link($b, $a, 'sibling');
    }

    public function test_functional_unique_index_allows_distinct_pairs(): void
    {
        $a = $this->makePatient('Pair A')->id;
        $b = $this->makePatient('Pair B')->id;
        $c = $this->makePatient('Pair C')->id;

        $this->link($a, $b, 'sibling');
        $this->link($a, $c, 'child'); // different counterpart — allowed

        $this->assertSame(2, DB::table('patient_links')->count());
    }

    // ── PatientLink model ───────────────────────────────────────────────────────

    public function test_patient_link_model_casts_relations_and_legacy_read_only(): void
    {
        $a = $this->makePatient('Model A');
        $b = $this->makePatient('Model B');

        $link = PatientLink::create([
            'patient_id'        => $a->id,
            'linked_patient_id' => $b->id,
            'relationship_type' => 'mother',
            'is_guardian'       => true,
            'notes'             => 'primary guardian',
            'relationship'      => 'SHOULD-NOT-PERSIST', // legacy, not fillable
        ]);

        $this->assertIsBool($link->is_guardian);
        $this->assertTrue($link->is_guardian);
        $this->assertSame($a->id, $link->patient->id);
        $this->assertSame($b->id, $link->linkedPatient->id);

        // Legacy `relationship` is read-only (omitted from fillable).
        $this->assertNull(DB::table('patient_links')->where('id', $link->id)->value('relationship'));
    }

    // ── Merge: guardian preservation + duplicate-pair reconciliation ────────────

    public function test_merge_reconciles_duplicate_pair_preserving_guardian_and_specific_type(): void
    {
        $master = $this->makePatient('Master');
        $loser  = $this->makePatient('Loser');
        $x      = $this->makePatient('Shared Relative');

        // Master links to X weakly; loser links to X as a guardian mother.
        $this->link($master->id, $x->id, 'other', false);
        $this->link($loser->id, $x->id, 'mother', true);

        app(PatientMergeService::class)->merge($master, $loser);

        $links = $this->linksBetween($master->id, $x->id);
        $this->assertCount(1, $links, 'Duplicate pair collapses to a single link.');
        $this->assertSame(1, (int) $links->first()->is_guardian, 'Guardian authority is never dropped (OR).');
        $this->assertSame('mother', $links->first()->relationship_type, 'The more specific type wins over "other".');

        // Loser retains no links.
        $this->assertSame(0, DB::table('patient_links')
            ->where('patient_id', $loser->id)->orWhere('linked_patient_id', $loser->id)->count());
    }

    public function test_merge_repoints_link_when_master_has_none(): void
    {
        $master = $this->makePatient('Master');
        $loser  = $this->makePatient('Loser');
        $x      = $this->makePatient('Relative');

        $this->link($loser->id, $x->id, 'sibling', false);

        app(PatientMergeService::class)->merge($master, $loser);

        $links = $this->linksBetween($master->id, $x->id);
        $this->assertCount(1, $links);
        $this->assertSame('sibling', $links->first()->relationship_type);
    }

    // ── Merge: guardian↔ward collapse is blocked (never silent) ─────────────────

    public function test_merge_is_blocked_when_records_are_guardian_and_ward(): void
    {
        $master = $this->makePatient('Guardian');
        $loser  = $this->makePatient('Ward');

        // Direct guardianship link between the two records.
        $this->link($master->id, $loser->id, 'other', true);

        $this->expectException(\RuntimeException::class);
        try {
            app(PatientMergeService::class)->merge($master, $loser);
        } finally {
            // Nothing was merged — the guardian link is untouched (transaction rolled back).
            $this->assertNull($loser->fresh()->merged_into_id);
            $this->assertSame(1, $this->linksBetween($master->id, $loser->id)->count());
        }
    }
}
