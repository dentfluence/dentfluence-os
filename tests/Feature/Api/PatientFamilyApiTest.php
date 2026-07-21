<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use App\Services\Patient\FamilyLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Patients Phase 3 — Slice 5: read-only family graph on the patient DETAIL API.
 *
 * Contract: linked_members / is_minor / guardian_required appear ONLY on
 * GET /api/v1/patients/{id}; never on the list; guardians are the
 * linked_members rows where is_guardian=true (no separate guardians[] array);
 * no write endpoints exist.
 */
class PatientFamilyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['branch_id' => 1, 'role' => 'admin', 'role_id' => null]);
        Sanctum::actingAs($this->admin, ['*']);
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

    public function test_detail_exposes_linked_members_and_guardian_state(): void
    {
        $minor    = $this->patient('Minor P', 'male', 8);
        $guardian = $this->patient('Priya Sharma', 'female', 40);
        app(FamilyLinkService::class)->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->admin);

        $resp = $this->getJson("/api/v1/patients/{$minor->id}");

        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertTrue($data['is_minor']);
        $this->assertFalse($data['guardian_required'], 'Guardian linked → not required.');
        $this->assertCount(1, $data['linked_members']);
        $this->assertSame('Priya Sharma', $data['linked_members'][0]['name']);
        $this->assertSame('mother', $data['linked_members'][0]['relationship']);
        $this->assertTrue($data['linked_members'][0]['is_guardian']);
        // Single representation: no separate guardians[] array.
        $this->assertArrayNotHasKey('guardians', $data);
    }

    public function test_guardian_required_true_for_minor_without_guardian(): void
    {
        $minor = $this->patient('Minor P', 'female', 6);

        $data = $this->getJson("/api/v1/patients/{$minor->id}")->assertOk()->json('data');

        $this->assertTrue($data['is_minor']);
        $this->assertTrue($data['guardian_required']);
        $this->assertSame([], $data['linked_members']);
    }

    public function test_adult_detail_reports_not_minor_not_required(): void
    {
        $adult = $this->patient('Adult P', 'male', 35);

        $data = $this->getJson("/api/v1/patients/{$adult->id}")->assertOk()->json('data');

        $this->assertFalse($data['is_minor']);
        $this->assertFalse($data['guardian_required']);
    }

    public function test_list_endpoint_never_carries_family_fields(): void
    {
        $a = $this->patient('List A', 'male', 30);
        $b = $this->patient('List B', 'female', 8);
        app(FamilyLinkService::class)->addLink($b, $a, 'father', [], $this->admin);

        $resp = $this->getJson('/api/v1/patients?limit=50');

        $resp->assertOk();
        foreach ($resp->json('data') as $row) {
            $this->assertArrayNotHasKey('linked_members', $row);
            $this->assertArrayNotHasKey('is_minor', $row);
            $this->assertArrayNotHasKey('guardian_required', $row);
        }
    }

    public function test_no_family_write_endpoints_exist(): void
    {
        $a = $this->patient('A', 'male', 30);

        $this->postJson("/api/v1/patients/{$a->id}/family/links", [])->assertNotFound();
        $this->postJson("/api/v1/patients/{$a->id}/family/guardians", [])->assertNotFound();
    }
}
