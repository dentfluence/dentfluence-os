<?php

namespace Tests\Feature\Relationship;

use App\Models\Patient;
use App\Models\Relationship;
use App\Services\Relationship\RelationshipSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One-time repair for patients that were merged onto the same Relationship
 * purely because they shared a phone number (household). Never touches
 * clinical data — only the relationship_id link and the new Relationship
 * rows it creates.
 */
class RelationshipSplitServiceTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(string $name, string $phone): Relationship
    {
        return Relationship::create([
            'name' => $name, 'phone' => $phone, 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    private function patient(string $name, string $phone, int $relationshipId, ?string $createdAt = null): Patient
    {
        $p = new Patient(['name' => $name, 'phone' => $phone]);
        $p->relationship_id = $relationshipId;
        $p->save();

        if ($createdAt) {
            $p->created_at = $createdAt;
            $p->saveQuietly();
        }

        return $p;
    }

    public function test_analyze_is_read_only(): void
    {
        $rel = $this->relationship('Sharma Family', '9990001111');
        $this->patient('Dad Sharma', '9990001111', $rel->id, '2026-01-01');
        $this->patient('Kid Sharma', '9990001111', $rel->id, '2026-02-01');

        $report = app(RelationshipSplitService::class)->analyze();

        $this->assertSame(1, $report['shared_relationships']);
        $this->assertSame(1, $report['patients_to_split_off']);
        $this->assertSame(1, Relationship::count(), 'Dry run must not create relationships.');
    }

    public function test_apply_splits_extra_patients_off_keeping_the_earliest(): void
    {
        $rel = $this->relationship('Sharma Family', '9990001111');
        $dad = $this->patient('Dad Sharma', '9990001111', $rel->id, '2026-01-01');
        $kid = $this->patient('Kid Sharma', '9990001111', $rel->id, '2026-02-01');

        $result = app(RelationshipSplitService::class)->apply();

        $this->assertSame(1, $result['patients_split']);
        $this->assertSame(0, $result['failed']);

        $dad->refresh();
        $kid->refresh();

        $this->assertSame($rel->id, $dad->relationship_id, 'Earliest-registered patient stays on the original relationship.');
        $this->assertNotSame($rel->id, $kid->relationship_id, 'Later patient gets a fresh, dedicated relationship.');
        $this->assertSame(2, Relationship::count());
    }

    public function test_three_patients_sharing_one_relationship_each_get_split_separately(): void
    {
        $rel = $this->relationship('Big Family', '9990004444');
        $this->patient('A', '9990004444', $rel->id, '2026-01-01');
        $b = $this->patient('B', '9990004444', $rel->id, '2026-02-01');
        $c = $this->patient('C', '9990004444', $rel->id, '2026-03-01');

        app(RelationshipSplitService::class)->apply();

        $b->refresh();
        $c->refresh();

        $this->assertNotSame($rel->id, $b->relationship_id);
        $this->assertNotSame($rel->id, $c->relationship_id);
        $this->assertNotSame($b->relationship_id, $c->relationship_id, 'B and C must not be merged into each other either — one relationship per patient.');
        $this->assertSame(3, Relationship::count());
    }

    public function test_apply_is_idempotent(): void
    {
        $rel = $this->relationship('Sharma Family', '9990001111');
        $this->patient('Dad Sharma', '9990001111', $rel->id, '2026-01-01');
        $this->patient('Kid Sharma', '9990001111', $rel->id, '2026-02-01');

        $svc = app(RelationshipSplitService::class);
        $svc->apply();
        $after = Relationship::count();
        $result = $svc->apply(); // re-run

        $this->assertSame($after, Relationship::count(), 'Re-running must not create more relationships.');
        $this->assertSame(0, $result['patients_split']);
    }

    public function test_single_patient_relationship_is_left_alone(): void
    {
        $rel = $this->relationship('Solo Patient', '9990002222');
        $this->patient('Solo Patient', '9990002222', $rel->id);

        $report = app(RelationshipSplitService::class)->analyze();

        $this->assertSame(0, $report['shared_relationships']);
        $this->assertSame(0, $report['patients_to_split_off']);
    }
}
