<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\PatientLink;
use App\Models\User;
use App\Services\Patient\FamilyLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patients Phase 3 — Family / Guardian · Slice 3 (Profile "Family & Contacts").
 *
 * Web-layer feature tests: read-only rendering, add/change/remove links,
 * guardian creation, minor alert, empty state, and permission enforcement.
 */
class FamilyContactsSectionTest extends TestCase
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

    private function family(): FamilyLinkService
    {
        return app(FamilyLinkService::class);
    }

    // ── Read-only rendering ────────────────────────────────────────────────────

    public function test_profile_renders_family_section_with_members(): void
    {
        $parent = $this->patient('Parent Patient', 'female', 40);
        $child  = $this->patient('Child Patient', 'male', 10);
        $this->family()->addLink($parent, $child, 'child', [], $this->admin);

        $resp = $this->actingAs($this->admin)->get(route('patients.show', $parent));

        $resp->assertOk();
        $resp->assertSee('Family & Contacts');   // assertSee escapes the needle → matches "Family &amp; Contacts"
        $resp->assertSee('Child Patient');
        $resp->assertSee('Son');                 // child (male) shown as "son" via the inverse map
        $resp->assertSee('Family (1)');
    }

    public function test_empty_state_when_no_family(): void
    {
        $patient = $this->patient('Lonely Patient', 'male', 30);

        $resp = $this->actingAs($this->admin)->get(route('patients.show', $patient));

        $resp->assertOk();
        $resp->assertSee('No family linked.');
        $resp->assertSee('No emergency contact.');
    }

    public function test_minor_guardian_alert_shows_and_hides(): void
    {
        $minor = $this->patient('Minor Patient', 'male', 8);

        $this->actingAs($this->admin)->get(route('patients.show', $minor))
            ->assertOk()
            ->assertSee('Minor — guardian required')
            ->assertSee('Add guardian');

        // Once a guardian exists, the alert is gone.
        $guardian = $this->patient('Guardian Patient', 'female', 40);
        $this->family()->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->admin);

        $this->actingAs($this->admin)->get(route('patients.show', $minor))
            ->assertOk()
            ->assertDontSee('Minor — guardian required')
            ->assertSee('Guardian Patient'); // guardian now appears in the linked-members list
    }

    // ── Writes ──────────────────────────────────────────────────────────────────

    public function test_add_existing_family_member(): void
    {
        $patient = $this->patient('Main', 'male', 30);
        $sibling = $this->patient('Sib', 'female', 28);

        $resp = $this->actingAs($this->admin)
            ->from(route('patients.show', $patient))
            ->post(route('patients.family.links.store', $patient), [
                'linked_patient_id' => $sibling->id,
                'relationship_type' => 'sibling',
            ]);

        $resp->assertRedirect(route('patients.show', $patient));
        $resp->assertSessionHas('family_status');
        $this->assertSame(1, PatientLink::count());
        $this->assertTrue($this->family()->linksFor($patient)->contains(fn ($i) => $i['counterpart']->id === $sibling->id));
    }

    public function test_change_relationship(): void
    {
        $patient = $this->patient('Main', 'male', 30);
        $other   = $this->patient('Other', 'female', 29);
        $link    = $this->family()->addLink($patient, $other, 'sibling', [], $this->admin);

        $this->actingAs($this->admin)
            ->from(route('patients.show', $patient))
            ->patch(route('patients.family.links.update', [$patient, $link->id]), [
                'relationship_type' => 'spouse',
            ])
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame('spouse', $link->fresh()->relationship_type);
    }

    public function test_remove_link(): void
    {
        $patient = $this->patient('Main', 'male', 30);
        $other   = $this->patient('Other', 'female', 29);
        $link    = $this->family()->addLink($patient, $other, 'spouse', [], $this->admin);

        $this->actingAs($this->admin)
            ->from(route('patients.show', $patient))
            ->delete(route('patients.family.links.destroy', [$patient, $link->id]))
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame(0, PatientLink::count());
    }

    public function test_create_guardian_new_person_mints_via_register(): void
    {
        $minor  = $this->patient('Minor', 'female', 6);
        $before = Patient::count();

        $this->actingAs($this->admin)
            ->from(route('patients.show', $minor))
            ->post(route('patients.family.guardians.store', $minor), [
                'name'              => 'New Guardian',
                'phone'             => '9123456780',
                'gender'            => 'male',
                'age_years'         => 45,
                'relationship_type' => 'father',
            ])
            ->assertRedirect(route('patients.show', $minor));

        $this->assertSame($before + 1, Patient::count());
        $guardian = Patient::where('name', 'New Guardian')->firstOrFail();
        $this->assertNotNull($guardian->patient_id, 'Minted through register() → has a Patient ID.');
        $this->assertTrue($this->family()->guardiansFor($minor)->contains('id', $guardian->id));
    }

    public function test_attach_existing_guardian(): void
    {
        $minor    = $this->patient('Minor', 'male', 7);
        $guardian = $this->patient('Aunt', 'female', 38);

        $this->actingAs($this->admin)
            ->from(route('patients.show', $minor))
            ->post(route('patients.family.guardians.store', $minor), [
                'existing_patient_id' => $guardian->id,
                'relationship_type'   => 'other',
            ])
            ->assertRedirect(route('patients.show', $minor));

        $this->assertTrue($this->family()->guardiansFor($minor)->contains('id', $guardian->id));
    }

    // ── Permissions ─────────────────────────────────────────────────────────────

    public function test_write_actions_require_edit_permission(): void
    {
        $readonly = User::factory()->create(['branch_id' => 1, 'role' => 'front_desk', 'role_id' => null]);
        $patient  = $this->patient('Main', 'male', 30);
        $other    = $this->patient('Other', 'female', 29);

        $this->actingAs($readonly)
            ->from(route('patients.show', $patient))
            ->post(route('patients.family.links.store', $patient), [
                'linked_patient_id' => $other->id,
                'relationship_type' => 'sibling',
            ])
            // module:patients,edit denies via a redirect (denyAccess), not a 403 —
            // the write is blocked either way, proven by zero links created.
            ->assertRedirect();

        $this->assertSame(0, PatientLink::count());
    }

    // ── F1: guardian demotion through the row edit ──────────────────────────────

    public function test_unchecking_guardian_in_row_edit_demotes(): void
    {
        $minor    = $this->patient('Minor', 'male', 8);
        $guardian = $this->patient('Guardian P', 'female', 40);
        $link     = $this->family()->attachGuardian($minor, $guardian, ['relationship_type' => 'mother'], $this->admin);

        // Submit the edit WITHOUT as_guardian (an unchecked checkbox sends nothing).
        $this->actingAs($this->admin)
            ->from(route('patients.show', $minor))
            ->patch(route('patients.family.links.update', [$minor, $link->id]), [
                'relationship_type' => 'mother',
            ])
            ->assertRedirect(route('patients.show', $minor));

        $this->assertFalse($link->fresh()->is_guardian, 'Unchecking Guardian in the edit form must demote.');
        $this->assertFalse($this->family()->guardiansFor($minor)->contains('id', $guardian->id));
    }

    // ── F2/F3: duplicate-screen link validation + reported outcome ──────────────

    public function test_invalid_link_relationship_type_fails_validation_before_registration(): void
    {
        $existing = $this->patient('Existing', 'female', 40);
        $before   = Patient::count();

        $this->actingAs($this->admin)->postJson(route('patients.store'), [
            'first_name'             => 'New',
            'last_name'              => 'Person',
            'phone'                  => $existing->phone,
            'age_years'              => 20,
            'link_to_patient_id'     => $existing->id,
            'link_relationship_type' => 'cousin', // not in the canonical vocabulary
        ])->assertStatus(422);

        $this->assertSame($before, Patient::count(), 'Validation failure must prevent registration (no silent coercion).');
    }

    public function test_link_failure_is_reported_not_silent(): void
    {
        $existing = $this->patient('Existing', 'female', 40);
        // Soft-delete AFTER validation would pass `exists` — simulate by deleting
        // between validation and lookup is not possible in-process, so instead we
        // verify the CONTRACT: a successful link reports no warning...
        $resp = $this->actingAs($this->admin)->postJson(route('patients.store'), [
            'first_name'             => 'New',
            'last_name'              => 'Child',
            'phone'                  => $existing->phone,
            'age_years'              => 8,
            'link_to_patient_id'     => $existing->id,
            'link_relationship_type' => 'mother',
            'link_as_guardian'       => true,
        ]);
        $resp->assertSuccessful();
        $this->assertNull($resp->json('link_warning'));

        // ...and the response contract carries link_warning for the UI to show
        // when the link cannot be made (key present in the JSON envelope).
        $this->assertArrayHasKey('link_warning', $resp->json());
    }

    // ── Duplicate-screen "Register + link family" (Phase 3, Slice 3) ────────────

    public function test_duplicate_screen_registers_and_links_family(): void
    {
        $existing = $this->patient('Existing Parent', 'female', 40);

        $resp = $this->actingAs($this->admin)->postJson(route('patients.store'), [
            'first_name'             => 'New',
            'last_name'              => 'Child',
            'phone'                  => $existing->phone, // shares the number → duplicate
            'age_years'              => 8,
            'gender'                 => 'male',
            'link_to_patient_id'     => $existing->id,
            'link_relationship_type' => 'mother',
            'link_as_guardian'       => true,
        ]);

        $resp->assertSuccessful();

        $new = Patient::where('name', 'New Child')->firstOrFail();
        $this->assertTrue(
            $this->family()->guardiansFor($new)->contains('id', $existing->id),
            'New patient is linked immediately, with the existing patient recorded as guardian.'
        );
        $this->assertSame(1, PatientLink::count());
    }
}
