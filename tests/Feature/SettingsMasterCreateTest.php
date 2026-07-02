<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Settings module — Masters (dropdown config) save
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  The "masters" screens feed every dropdown in the app (complaints,
 *  diagnoses, medicines, etc.). They all share one save path, so testing the
 *  Complaints master proves the whole masters mechanism saves correctly.
 */
class SettingsMasterCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_master_item_can_be_added(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);
        $name = 'DuskComplaint' . now()->format('His');

        $resp = $this->actingAs($user)->post(route('settings.masters.complaints.store'), [
            'name' => $name,
        ]);
        $resp->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaints', ['name' => $name]);
    }
}
