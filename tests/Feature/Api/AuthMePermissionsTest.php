<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * M-6 (Android V1.1) — /auth/me carries the module permission map the phone
 * gates its UI with, from the same rows the web's module: middleware reads.
 *
 * Pinned: every registered module is present (no guessing on missing keys),
 * a role's row maps 1:1 onto view/edit/delete/settings, a zero-permission
 * role gets all-false, and the owner role reads all-true.
 */
class AuthMePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_a_role_row_maps_onto_the_permission_map_and_every_module_is_present(): void
    {
        $user = $this->userWithModulePerm('patients', view: true, edit: true, delete: false);

        Sanctum::actingAs($user, ['*']);
        $perms = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.permissions');

        $this->assertSame(['view' => true, 'edit' => true, 'delete' => false, 'settings' => false], $perms['patients']);
        $this->assertSame(['view' => false, 'edit' => false, 'delete' => false, 'settings' => false], $perms['finance']);

        $this->assertEqualsCanonicalizing(
            Module::pluck('slug')->all(),
            array_keys($perms),
            'every registered module must be present so a missing key never has to mean "unknown"'
        );
    }

    public function test_a_zero_permission_role_reads_all_false(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $perms = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.permissions');

        $this->assertNotEmpty($perms);
        foreach ($perms as $slug => $p) {
            $this->assertSame([false, false, false, false], array_values($p), "$slug must be all-false");
        }
    }

    public function test_the_owner_role_reads_all_true(): void
    {
        Sanctum::actingAs($this->legacyAdminUser(), ['*']);
        $res = $this->getJson('/api/v1/auth/me')->assertOk();

        $res->assertJsonPath('data.is_admin', true);
        foreach ($res->json('data.permissions') as $slug => $p) {
            $this->assertSame([true, true, true, true], array_values($p), "$slug must be all-true for the owner");
        }
    }
}
