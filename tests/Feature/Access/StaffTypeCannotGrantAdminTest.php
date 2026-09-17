<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * V.18 (17 Sep 2026, found on production) — a non-admin with HR edit could
 * open HR -> Staff, change a staff type to "admin", and isAdminRole() then
 * trusted that string and opened every admin.only gate.
 */
class StaffTypeCannotGrantAdminTest extends TestCase
{
    use RefreshDatabase, BuildsAccessPersonas;

    public function test_admin_staff_type_string_does_not_make_a_role_id_user_admin(): void
    {
        $desk = $this->userWithModulePerm('hr', true, true, false);
        $desk->forceFill(['role' => 'admin'])->saveQuietly();

        $this->assertFalse($desk->fresh()->isAdminRole());
        $this->actingAs($desk->fresh())->get('/data-rights')->assertSessionHas('access_denied');
    }

    public function test_hr_editor_cannot_change_a_staff_type(): void
    {
        $desk = $this->userWithModulePerm('hr', true, true, false);

        $this->actingAs($desk)->put(route('hr.staff.update', $desk), [
            'name' => $desk->name, 'email' => $desk->email, 'role' => 'admin',
        ])->assertStatus(403);

        $this->assertSame('assistant', $desk->fresh()->role);
    }

    public function test_hr_editor_cannot_edit_an_admin_record(): void
    {
        $this->seedAccessRoles();
        $owner = User::factory()->create([
            'role' => 'admin', 'role_id' => Role::where('slug', Role::ADMIN)->value('id'),
            'branch_id' => 1, 'is_active' => true,
        ]);
        $desk = $this->userWithModulePerm('hr', true, true, false);

        $this->actingAs($desk)->put(route('hr.staff.update', $owner), [
            'name' => 'Changed', 'email' => $owner->email, 'role' => 'admin',
        ])->assertStatus(403);

        $this->assertNotSame('Changed', $owner->fresh()->name);
    }

    public function test_hr_editor_can_still_update_a_non_admin_without_changing_type(): void
    {
        $desk  = $this->userWithModulePerm('hr', true, true, false);
        $staff = $this->zeroPermUser('assistant');

        $this->actingAs($desk)->put(route('hr.staff.update', $staff), [
            'name' => 'Renamed Staff', 'email' => $staff->email, 'role' => 'assistant',
        ])->assertRedirect();

        $this->assertSame('Renamed Staff', $staff->fresh()->name);
    }

    public function test_owner_can_still_change_a_staff_type(): void
    {
        $this->seedAccessRoles();
        $owner = User::factory()->create([
            'role' => 'admin', 'role_id' => Role::where('slug', Role::ADMIN)->value('id'),
            'branch_id' => 1, 'is_active' => true,
        ]);
        $staff = $this->zeroPermUser('assistant');

        $this->actingAs($owner)->put(route('hr.staff.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'role' => 'front_desk',
        ])->assertRedirect();

        $this->assertSame('front_desk', $staff->fresh()->role);
    }
}
