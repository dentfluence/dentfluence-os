<?php

namespace Tests\Feature\Relationship;

use App\Models\Patient;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 4 — household relationships on the profile.
 *
 * When several patients share one relationship (household), the profile surfaces
 * ALL of them, not just the hasOne. Single-patient profiles are unchanged.
 */
class HouseholdProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    private function relationship(string $name, string $phone): Relationship
    {
        return Relationship::create([
            'name' => $name, 'phone' => $phone, 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    private function patient(string $name, string $phone, int $relationshipId): Patient
    {
        $p = new Patient(['name' => $name, 'phone' => $phone]);
        $p->relationship_id = $relationshipId;
        $p->save();
        return $p;
    }

    public function test_household_relationship_lists_all_patients(): void
    {
        $rel = $this->relationship('Sharma Family', '9990001111');
        $this->patient('Dad Sharma', '9990001111', $rel->id);
        $this->patient('Kid Sharma', '9990001111', $rel->id);

        $response = $this->actingAs($this->user())->get(route('relationship.profile', $rel->id));

        $response->assertOk();
        $this->assertTrue($response->viewData('isHousehold'));
        $this->assertSame(2, $response->viewData('householdPatients')->count());
        $response->assertSee('Household');
        $response->assertSee('Dad Sharma');
        $response->assertSee('Kid Sharma');
    }

    public function test_single_patient_relationship_shows_no_household_panel(): void
    {
        $rel = $this->relationship('Solo Patient', '9990002222');
        $this->patient('Solo Patient', '9990002222', $rel->id);

        $response = $this->actingAs($this->user())->get(route('relationship.profile', $rel->id));

        $response->assertOk();
        $this->assertFalse($response->viewData('isHousehold'));
        $response->assertDontSee('Several people share this phone');
    }

    public function test_profile_requires_authentication(): void
    {
        $rel = $this->relationship('Auth Test', '9990003333');
        $this->get(route('relationship.profile', $rel->id))->assertRedirect();
    }
}
