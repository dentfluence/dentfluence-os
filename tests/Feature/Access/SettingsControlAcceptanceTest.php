<?php

namespace Tests\Feature\Access;

use App\Models\Module;
use App\Models\RoleModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 CORE ACCEPTANCE TEST — the Clinic Owner is the authorization truth.
 *
 * One arbitrarily-named custom role walks the whole lifecycle:
 *   no access → View only → View+Edit → access removed
 * and at every step BOTH the web and the API agree, with no code change, no
 * deployment, and no dependence on the role's name.
 */
class SettingsControlAcceptanceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function setPre(int $roleId, ?array $triple): void
    {
        $moduleId = Module::where('slug', 'relationship')->firstOrFail()->id;
        $row      = RoleModulePermission::where('role_id', $roleId)->where('module_id', $moduleId);

        if ($triple === null) {
            $row->delete(); // owner removes the module from the role entirely
            return;
        }

        [$v, $e, $d] = $triple;
        RoleModulePermission::updateOrCreate(
            ['role_id' => $roleId, 'module_id' => $moduleId],
            ['can_view' => $v, 'can_edit' => $e, 'can_delete' => $d]
        );
    }

    private function webRead(int $userId): int
    {
        return $this->actingAs($this->fresh(\App\Models\User::find($userId)))
            ->get(route('relationship.today'))->getStatusCode();
    }

    private function webMutate(int $userId): int
    {
        return $this->actingAs($this->fresh(\App\Models\User::find($userId)))
            ->postJson(route('relationship.today.close'), [
                'category' => 'recall_calls', 'notes' => 'acceptance',
            ])->getStatusCode();
    }

    private function apiRead(int $userId): int
    {
        Sanctum::actingAs($this->fresh(\App\Models\User::find($userId)), ['*']);

        return $this->getJson('/api/v1/relationships/today')->getStatusCode();
    }

    private function apiMutate(int $userId): int
    {
        Sanctum::actingAs($this->fresh(\App\Models\User::find($userId)), ['*']);

        return $this->postJson('/api/v1/relationships/today/close', [
            'category' => 'recall_calls', 'notes' => 'acceptance',
        ])->getStatusCode();
    }

    public function test_owner_settings_govern_web_and_api_together_for_any_role_name(): void
    {
        // The owner invents a role name Dentfluence has never heard of.
        $user   = $this->userWithModulePerm('relationship', false, false, false, 'Chai Break Squad ' . uniqid());
        $roleId = $user->role_id;
        $id     = $user->id;

        // ── Step 1: no access ────────────────────────────────────────────────
        $this->setPre($roleId, null);
        $this->assertContains($this->webRead($id), [302, 403], 'web read allowed with no grant');
        $this->assertSame(403, $this->apiRead($id), 'api read allowed with no grant');
        $this->assertContains($this->webMutate($id), [302, 403]);
        $this->assertSame(403, $this->apiMutate($id));

        // ── Step 2: owner enables View only ──────────────────────────────────
        $this->setPre($roleId, [1, 0, 0]);
        $this->assertSame(200, $this->webRead($id), 'web read denied despite View grant');
        $this->assertSame(200, $this->apiRead($id), 'api read denied despite View grant');
        $this->assertContains($this->webMutate($id), [302, 403], 'web mutation allowed on View only');
        $this->assertSame(403, $this->apiMutate($id), 'api mutation allowed on View only');

        // ── Step 3: owner enables Edit ───────────────────────────────────────
        $this->setPre($roleId, [1, 1, 0]);
        $this->assertSame(200, $this->webRead($id));
        $this->assertSame(200, $this->apiRead($id));
        $this->assertSame(200, $this->webMutate($id), 'web mutation denied despite Edit grant');
        $this->assertNotSame(403, $this->apiMutate($id), 'api mutation denied despite Edit grant');

        // ── Step 4: owner removes access again ───────────────────────────────
        $this->setPre($roleId, null);
        $this->assertContains($this->webRead($id), [302, 403], 'access survived removal on web');
        $this->assertSame(403, $this->apiRead($id), 'access survived removal on api');
    }
}
