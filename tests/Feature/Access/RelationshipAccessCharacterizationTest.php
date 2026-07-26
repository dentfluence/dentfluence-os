<?php

namespace Tests\Feature\Access;

use App\Models\Lead;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.3 — PRE access ENFORCEMENT (flipped from the 1.1
 * characterization, which recorded that all of this was wide open).
 *
 * Everything here flows through the owner-configured `relationship` module the
 * Clinic Owner manages in Settings. Role NAMES are deliberately arbitrary.
 */
class RelationshipAccessCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private const PRE_SURFACES = [
        'relationship.today',
        'relationship.reception',
        'relationship.pipeline',
        'relationship.list',
        'relationship.recalls',
        'relationship.opportunities',
    ];

    public function test_zero_permission_user_is_denied_every_pre_surface(): void
    {
        $user = $this->zeroPermUser();

        foreach (self::PRE_SURFACES as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName));

            $this->assertContains($response->getStatusCode(), [302, 403],
                "PRE surface [{$routeName}] is still reachable without a relationship grant");
        }
    }

    public function test_view_grant_opens_every_pre_surface(): void
    {
        $user = $this->userWithModulePerm('relationship', view: true, edit: false, delete: false,
            roleName: 'Purple Desk ' . uniqid()); // arbitrary name — must not matter

        foreach (self::PRE_SURFACES as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    public function test_view_only_user_cannot_execute_a_board_mutation(): void
    {
        $user = $this->userWithModulePerm('relationship', true, false, false);

        $this->actingAs($user)
            ->postJson(route('relationship.today.close'), [
                'category' => 'recall_calls',
                'notes'    => 'view-only attempt',
            ])
            ->assertForbidden();
    }

    public function test_edit_grant_allows_the_same_board_mutation(): void
    {
        $user = $this->userWithModulePerm('relationship', true, true, false);

        $this->actingAs($user)
            ->postJson(route('relationship.today.close'), [
                'category' => 'recall_calls',
                'notes'    => 'edit grant attempt',
            ])
            ->assertOk();
    }

    public function test_bulk_dismiss_requires_the_delete_grant(): void
    {
        $editOnly = $this->userWithModulePerm('relationship', true, true, false);
        $this->actingAs($editOnly)
            ->postJson(route('relationship.recalls.bulk-dismiss'), ['ids' => []])
            ->assertForbidden();

        $withDelete = $this->userWithModulePerm('relationship', true, true, true);
        $this->assertNotSame(403, $this->actingAs($withDelete)
            ->postJson(route('relationship.recalls.bulk-dismiss'), ['ids' => []])
            ->getStatusCode());
    }

    public function test_lead_conversion_requires_both_relationship_edit_and_patients_edit(): void
    {
        // CEO-approved semantics: converting mints a Patient, so it needs the
        // PRE edit grant AND the patients edit grant. Neither alone is enough.
        $lead = fn () => Lead::create([
            'name'  => 'Convert Gate Lead',
            'phone' => '9' . random_int(100000000, 999999999),
            'stage' => 'new_lead',
        ]);

        $preOnly = $this->userWithModulePerm('relationship', true, true, false);
        $before  = Patient::count();
        $this->actingAs($preOnly)
            ->postJson(route('relationship.pipeline.convert', $lead()->id))
            ->assertForbidden();
        $this->assertSame($before, Patient::count(), 'a patient was minted without patients,edit');

        // Both grants on one arbitrarily-named role → allowed.
        $both = $this->userWithTwoModulePerms(
            'relationship', [true, true, false],
            'patients',     [true, true, false],
            'Blue Coordinator ' . uniqid()
        );

        $this->actingAs($both)
            ->postJson(route('relationship.pipeline.convert', $lead()->id))
            ->assertOk();
        $this->assertGreaterThan($before, Patient::count());
    }
}
