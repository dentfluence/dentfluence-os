<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * CEO rule 24 Sep 2026: a deactivated staff member or doctor never looks
 * deleted. They stay in the HR list with an Inactive badge and can be
 * reactivated; reactivation lets them sign in again.
 */
class InactiveStaffStaysVisibleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_a_deactivated_member_stays_in_the_hr_list_marked_inactive(): void
    {
        $admin = $this->legacyAdminUser();
        $user  = User::factory()->create(['name' => 'Inactive Tester', 'role' => 'receptionist', 'is_active' => false, 'branch_id' => 1]);

        $this->actingAs($admin)->get(route('hr.staff.index', ['view' => 'staff']))
            ->assertOk()
            ->assertSee('Inactive Tester')
            ->assertSee('Inactive');
    }

    public function test_reactivate_turns_the_member_back_on(): void
    {
        $admin = $this->legacyAdminUser();
        $user  = User::factory()->create(['role' => 'receptionist', 'is_active' => false, 'branch_id' => 1]);

        $this->actingAs($admin)->post(route('hr.staff.reactivate', $user))
            ->assertRedirect(route('hr.staff.show', $user));

        $this->assertTrue(User::findOrFail($user->id)->is_active);
    }
}
