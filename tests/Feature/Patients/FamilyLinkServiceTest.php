<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\PatientLink;
use App\Models\User;
use App\Services\Patient\FamilyLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patients Phase 3 — Family / Guardian · Slice 2 (FamilyLinkService).
 *
 * Covers the canonical family-graph writer/reader only: addLink, removeLink,
 * linksFor, guardiansFor, wardsFor, attachGuardian, and the derived inverse
 * label map. No UI / API / controllers.
 */
class FamilyLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['branch_id' => 1]);
    }

    private function svc(): FamilyLinkService
    {
        return app(FamilyLinkService::class);
    }

    private function patient(string $name, ?string $gender = null, ?int $ageYears = null): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'gender'    => $gender,
            'age_years' => $ageYears,
        ]);
    }

    // ── addLink ─────────────────────────────────────────────────────────────

    public function test_add_link_creates_a_directed_row(): void
    {
        $kid = $this->patient('Kid', 'male', 10);
        $mom = $this->patient('Mom', 'female', 40);

        $link = $this->svc()->addLink($kid, $mom, 'mother', [], $this->actor);

        $this->assertInstanceOf(PatientLink::class, $link);
        $this->assertSame($kid->id, $link->patient_id);
        $this->assertSame($mom->id, $link->linked_patient_id);
        $this->assertSame('mother', $link->relationship_type);
        $this->assertFalse($link->is_guardian);
        $this->assertSame($this->actor->id, $link->added_by);
        $this->assertSame(1, PatientLink::count());
    }

    public function test_add_link_rejects_self_link(): void
    {
        $a = $this->patient('Solo');
        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->addLink($a, $a, 'sibling', [], $this->actor);
    }

    public function test_add_link_rejects_invalid_type(): void
    {
        $a = $this->patient('A');
        $b = $this->patient('B');
        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->addLink($a, $b, 'cousin', [], $this->actor);
    }

    public function test_add_link_is_bidirectional_and_never_duplicates(): void
    {
        $a = $this->patient('A', 'male', 30);
        $b = $this->patient('B', 'female', 28);

        $this->svc()->addLink($a, $b, 'sibling', [], $this->actor);
        // Same pair, other direction — must update the single row, not create a second.
        $this->svc()->addLink($b, $a, 'sibling', [], $this->actor);

        $this->assertSame(1, PatientLink::count());
    }

    // ── linksFor + inverse label map ─────────────────────────────────────────

    public function test_links_for_derives_gender_refined_labels_both_ways(): void
    {
        $kid = $this->patient('Kid', 'male', 10);
        $mom = $this->patient('Mom', 'female', 40);

        // "Mom is the mother of Kid".
        $this->svc()->addLink($kid, $mom, 'mother', [], $this->actor);

        $fromKid = $this->svc()->linksFor($kid);
        $this->assertCount(1, $fromKid);
        $this->assertSame($mom->id, $fromKid->first()['counterpart']->id);
        $this->assertSame('mother', $fromKid->first()['label']);   // Kid sees Mom as "mother"
        $this->assertFalse($fromKid->first()['is_guardian']);
        $this->assertFalse($fromKid->first()['is_ward']);

        $fromMom = $this->svc()->linksFor($mom);
        $this->assertCount(1, $fromMom);
        $this->assertSame($kid->id, $fromMom->first()['counterpart']->id);
        $this->assertSame('son', $fromMom->first()['label']);      // Mom sees Kid as "son" (child → son by gender)
    }

    public function test_links_for_spouse_is_symmetric_and_gendered(): void
    {
        $husband = $this->patient('H', 'male', 35);
        $wife    = $this->patient('W', 'female', 33);

        $this->svc()->addLink($husband, $wife, 'spouse', [], $this->actor);

        $this->assertSame('wife', $this->svc()->linksFor($husband)->first()['label']);
        $this->assertSame('husband', $this->svc()->linksFor($wife)->first()['label']);
    }

    // ── attachGuardian + guardiansFor / wardsFor ─────────────────────────────

    public function test_attach_guardian_with_existing_patient(): void
    {
        $minor    = $this->patient('Minor', 'male', 8);
        $guardian = $this->patient('Guardian', 'female', 40);

        $link = $this->svc()->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->actor);

        $this->assertTrue($link->is_guardian);
        $this->assertSame($minor->id, $link->patient_id);
        $this->assertSame($guardian->id, $link->linked_patient_id);

        $this->assertTrue($this->svc()->guardiansFor($minor)->contains('id', $guardian->id));
        $this->assertTrue($this->svc()->wardsFor($guardian)->contains('id', $minor->id));

        // Direction shows correctly from each side.
        $this->assertTrue($this->svc()->linksFor($minor)->first()['is_guardian']);
        $this->assertTrue($this->svc()->linksFor($guardian)->first()['is_ward']);
    }

    public function test_attach_guardian_mints_new_patient_via_register(): void
    {
        $minor = $this->patient('Minor', 'female', 6);

        $before = Patient::count();
        $link = $this->svc()->attachGuardian(
            $minor,
            ['name' => 'New Guardian', 'phone' => '9123456780', 'gender' => 'male', 'age_years' => 45],
            ['relationship_type' => 'father'],
            $this->actor,
        );

        $this->assertSame($before + 1, Patient::count());
        $guardian = Patient::where('name', 'New Guardian')->firstOrFail();
        $this->assertNotNull($guardian->patient_id, 'New guardian minted through register() gets a Patient ID.');
        $this->assertSame($guardian->id, $link->linked_patient_id);
        $this->assertTrue($this->svc()->guardiansFor($minor)->contains('id', $guardian->id));
    }

    public function test_attach_guardian_rejects_minor_guardian_and_rolls_back(): void
    {
        $minor  = $this->patient('Minor', 'male', 7);
        $before = Patient::count();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->svc()->attachGuardian(
                $minor,
                ['name' => 'Too Young', 'phone' => '9123456781', 'age_years' => 10],
                [],
                $this->actor,
            );
        } finally {
            // Transaction rolled back — no orphan guardian patient, no link.
            $this->assertSame($before, Patient::count());
            $this->assertSame(0, Patient::where('name', 'Too Young')->count());
            $this->assertSame(0, PatientLink::count());
        }
    }

    // ── updateLink (F1 — explicit edit may demote a guardian) ────────────────

    public function test_update_link_demotes_guardian_when_unchecked(): void
    {
        $minor    = $this->patient('Minor', 'male', 8);
        $guardian = $this->patient('Guardian', 'female', 40);
        $this->svc()->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->actor);
        $this->assertTrue($this->svc()->guardiansFor($minor)->contains('id', $guardian->id));

        // Explicit edit with as_guardian=false → guardianship is REMOVED.
        $link = $this->svc()->updateLink($minor, $guardian, 'mother', ['as_guardian' => false], $this->actor);

        $this->assertFalse($link->fresh()->is_guardian);
        $this->assertFalse($this->svc()->guardiansFor($minor)->contains('id', $guardian->id));
        // Relationship itself survives the demotion.
        $this->assertSame('mother', $link->fresh()->relationship_type);
    }

    public function test_update_link_can_also_promote_and_add_link_still_never_demotes(): void
    {
        $a = $this->patient('A', 'male', 30);
        $b = $this->patient('B', 'female', 55);

        $this->svc()->addLink($a, $b, 'mother', [], $this->actor);

        // Promote via explicit edit.
        $this->svc()->updateLink($a, $b, 'mother', ['as_guardian' => true], $this->actor);
        $this->assertTrue($this->svc()->guardiansFor($a)->contains('id', $b->id));

        // addLink (the non-edit path) keeps its OR-union: it can never demote.
        $this->svc()->addLink($a, $b, 'mother', ['as_guardian' => false], $this->actor);
        $this->assertTrue($this->svc()->guardiansFor($a)->contains('id', $b->id), 'addLink must never silently drop guardianship.');
    }

    public function test_update_link_requires_an_existing_link(): void
    {
        $a = $this->patient('A');
        $b = $this->patient('B');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->updateLink($a, $b, 'spouse', [], $this->actor);
    }

    // ── removeLink ────────────────────────────────────────────────────────────

    public function test_remove_link_works_in_either_direction(): void
    {
        $a = $this->patient('A');
        $b = $this->patient('B');

        $this->svc()->addLink($a, $b, 'spouse', [], $this->actor);
        $this->assertSame(1, PatientLink::count());

        // Remove using the reverse direction — still finds and deletes the pair.
        $this->assertTrue($this->svc()->removeLink($b, $a, $this->actor));
        $this->assertSame(0, PatientLink::count());

        // Nothing left to remove.
        $this->assertFalse($this->svc()->removeLink($a, $b, $this->actor));
    }
}
