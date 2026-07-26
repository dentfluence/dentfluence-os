<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.3 — prescriptions access ENFORCEMENT (flipped from the
 * 1.1 characterization, which recorded that the whole file was auth-only).
 */
class PrescriptionsAccessCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_zero_permission_user_is_denied_prescription_settings(): void
    {
        $response = $this->actingAs($this->zeroPermUser())->get(route('rx.settings.index'));

        $this->assertContains($response->getStatusCode(), [302, 403]);
    }

    public function test_view_grant_opens_prescription_settings(): void
    {
        $user = $this->userWithModulePerm('prescriptions', true, false, false, 'Rx Reader ' . uniqid());

        $this->actingAs($user)->get(route('rx.settings.index'))->assertOk();
    }

    public function test_view_only_user_cannot_write_prescription_masters(): void
    {
        $user = $this->userWithModulePerm('prescriptions', true, false, false);

        $this->actingAs($user)
            ->postJson(route('rx.settings.categories.store'), ['name' => 'Blocked Category'])
            ->assertForbidden();
    }

    public function test_edit_grant_allows_the_same_write(): void
    {
        $user = $this->userWithModulePerm('prescriptions', true, true, false);

        $this->assertNotSame(403, $this->actingAs($user)
            ->postJson(route('rx.settings.categories.store'), ['name' => 'Allowed Category'])
            ->getStatusCode());
    }
}
