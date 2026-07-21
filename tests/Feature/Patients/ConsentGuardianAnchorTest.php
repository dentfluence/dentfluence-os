<?php

namespace Tests\Feature\Patients;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\User;
use App\Services\Patient\FamilyLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patients Phase 3 — Family / Guardian · Slice 4 (minor→guardian consent anchor).
 *
 * The consent capture screen prefills the consenting party from the canonical
 * family graph (FamilyLinkService). Persistence is untouched: patient_consents
 * still stores the free-text snapshot exactly as before.
 */
class ConsentGuardianAnchorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['branch_id' => 1, 'role' => 'admin', 'role_id' => null]);
    }

    private function patient(string $name, ?string $gender = null, ?int $ageYears = null): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'gender'    => $gender,
            'age_years' => $ageYears,
            'branch_id' => 1,
        ]);
    }

    public function test_minor_with_linked_guardian_prefills_consent_fields(): void
    {
        $minor    = $this->patient('Minor P', 'male', 8);
        $guardian = $this->patient('Priya Sharma', 'female', 40);
        app(FamilyLinkService::class)->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->admin);

        $resp = $this->actingAs($this->admin)->get(route('consent.patient', $minor));

        $resp->assertOk();
        $resp->assertSee('Consenting guardian:');
        $resp->assertSee('Priya Sharma');
        $resp->assertSee('value="Priya Sharma"', false);   // name input prefilled
        $resp->assertSee('value="Mother"', false);         // relationship input prefilled (gender label)
    }

    public function test_minor_with_multiple_guardians_shows_picker(): void
    {
        $minor = $this->patient('Minor P', 'female', 7);
        $mom   = $this->patient('Priya Sharma', 'female', 40);
        $dad   = $this->patient('Rahul Sharma', 'male', 42);
        $svc   = app(FamilyLinkService::class);
        $svc->attachGuardian($minor, $mom, ['relationship_type' => 'mother'], $this->admin);
        $svc->attachGuardian($minor, $dad, ['relationship_type' => 'father'], $this->admin);

        $resp = $this->actingAs($this->admin)->get(route('consent.patient', $minor));

        $resp->assertOk();
        $resp->assertSee('_guardian_pick', false);  // radio picker rendered
        $resp->assertSee('Priya Sharma');
        $resp->assertSee('Rahul Sharma');
    }

    public function test_minor_without_guardian_shows_nudge_and_blank_fields(): void
    {
        $minor = $this->patient('Minor P', 'male', 9);

        $resp = $this->actingAs($this->admin)->get(route('consent.patient', $minor));

        $resp->assertOk();
        $resp->assertSee('No guardian is linked to this minor.');
        $resp->assertSee('Family &amp; Contacts', false);
        $resp->assertDontSee('Consenting guardian:');
    }

    public function test_adult_has_no_guardian_block(): void
    {
        $adult = $this->patient('Adult P', 'female', 30);

        $resp = $this->actingAs($this->admin)->get(route('consent.patient', $adult));

        $resp->assertOk();
        $resp->assertDontSee('guardian_name', false);
        $resp->assertDontSee('is a minor');
    }

    public function test_persistence_snapshot_unchanged(): void
    {
        $minor    = $this->patient('Minor P', 'male', 8);
        $guardian = $this->patient('Priya Sharma', 'female', 40);
        app(FamilyLinkService::class)->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->admin);

        $purpose = ConsentPurpose::create([
            'key' => 'treatment', 'name' => 'Treatment', 'category' => 'clinical',
            'is_mandatory' => true, 'requires_explicit' => true,
            'version' => 1, 'active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('consent.patient.update', $minor), [
                'granted'               => [$purpose->id],
                'guardian_name'         => 'Priya Sharma',   // the prefilled values, submitted as free text
                'guardian_relationship' => 'Mother',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patient_consents', [
            'patient_id'            => $minor->id,
            'consent_purpose_id'    => $purpose->id,
            'on_behalf_of'          => 'guardian',
            'guardian_name'         => 'Priya Sharma',
            'guardian_relationship' => 'Mother',
        ]);
    }
}
