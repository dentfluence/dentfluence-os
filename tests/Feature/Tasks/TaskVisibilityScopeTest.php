<?php

namespace Tests\Feature\Tasks;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHO SEES WHOSE TASKS.
 *
 * ── CEO ruling, 23 Sep 2026 ─────────────────────────────────────────────────
 * "all can see each other task… manager owner will see all the task but other
 * should see assigned to them only."
 *
 * ── THE BUG THIS CLOSES ─────────────────────────────────────────────────────
 * The old rule scoped DOWN an allow-list of three staff types — assistant,
 * front desk, accounts — and let EVERY OTHER ROLE see the whole branch. A
 * doctor, a visiting consultant, a hygienist or any custom role a clinic
 * creates in Settings read everybody's work, on the web board and in the phone
 * app, which had its own copy of the same inverted rule.
 *
 * A permission boundary denies by default. An allow-list of the people to
 * restrict leaks every role nobody remembered to add — and the list of roles a
 * clinic can invent is open-ended.
 *
 * It also asked the legacy `role` STRING, a staff-type label anyone with HR
 * edit can change. That is the hole closed on isAdminRole() on 17 Sep: a front
 * desk user could set her own staff type and walk through the gate. The
 * assigned ACCESS role decides here too.
 */
class TaskVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user on a real assigned role, with view access to the Tasks module.
     *
     * The module permission is not decoration: `module:tasks` denies a role
     * with no permission row, so without it every one of these tests would
     * pass on a 403 and prove nothing about the scope.
     *
     * $legacy is the old `role` STRING — a staff-type label, deliberately set
     * to something different from the access role so a test can prove it is
     * not what decides.
     */
    private function userWithRole(string $slug, string $legacy = 'staff'): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name'      => ucfirst($slug),
                'category'  => $slug === Role::DOCTOR ? Role::CATEGORY_DOCTOR : Role::CATEGORY_STAFF,
                'is_system' => true,
            ],
        );

        $module = Module::firstOrCreate(
            ['slug' => 'tasks'],
            ['name' => 'Tasks', 'section' => 'communication', 'sort_order' => 50],
        );

        RoleModulePermission::firstOrCreate(
            ['role_id' => $role->id, 'module_id' => $module->id],
            ['can_view' => true, 'can_edit' => true, 'can_delete' => false],
        );

        return User::factory()->create([
            'role'      => $legacy,
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    private function task(User $owner, string $title): Task
    {
        return Task::create([
            'title'       => $title,
            'category'    => 'admin',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $owner->id,
            'assigned_to' => $owner->id,
        ]);
    }

    // ── the boundary itself ──────────────────────────────────────────────

    public function test_the_owner_sees_the_whole_branch(): void
    {
        $owner = $this->userWithRole(Role::ADMIN, 'admin');
        $other = $this->userWithRole(Role::ASSISTANT);

        $this->task($owner, 'Owner own task');
        $this->task($other, 'Ashwini crown chase');

        $res = $this->actingAs($owner)->get(route('tasks.index'));

        $res->assertSee('Owner own task');
        $res->assertSee('Ashwini crown chase');
    }

    public function test_a_manager_sees_the_whole_branch(): void
    {
        $manager = $this->userWithRole(Role::MANAGER);
        $other   = $this->userWithRole(Role::ASSISTANT);

        $this->task($manager, 'Manager own task');
        $this->task($other, 'Ashwini crown chase');

        $res = $this->actingAs($manager)->get(route('tasks.index'));

        $res->assertSee('Manager own task');
        $res->assertSee('Ashwini crown chase');
    }

    public function test_an_assistant_sees_only_her_own(): void
    {
        $ashwini  = $this->userWithRole(Role::ASSISTANT);
        $somebody = $this->userWithRole(Role::FRONT_DESK);

        $this->task($ashwini, 'Ashwini crown chase');
        $this->task($somebody, 'Not hers');

        $res = $this->actingAs($ashwini)->get(route('tasks.index'));

        $res->assertSee('Ashwini crown chase');
        $res->assertDontSee('Not hers');
    }

    /**
     * THE ACTUAL DEFECT. A doctor was in none of the three restricted staff
     * types, so the old rule let the whole branch through.
     */
    public function test_a_doctor_sees_only_their_own(): void
    {
        $doctor  = $this->userWithRole(Role::DOCTOR, 'doctor');
        $ashwini = $this->userWithRole(Role::ASSISTANT);

        $this->task($doctor, 'Doctor own task');
        $this->task($ashwini, 'Ashwini crown chase');

        $res = $this->actingAs($doctor)->get(route('tasks.index'));

        $res->assertSee('Doctor own task');
        $res->assertDontSee('Ashwini crown chase');
    }

    public function test_a_role_a_clinic_invented_sees_only_their_own(): void
    {
        // Deny by default is the whole point: a role nobody wrote into a list
        // must still be restricted.
        $hygienist = $this->userWithRole('hygienist');
        $ashwini   = $this->userWithRole(Role::ASSISTANT);

        $this->task($hygienist, 'Hygienist own task');
        $this->task($ashwini, 'Ashwini crown chase');

        $res = $this->actingAs($hygienist)->get(route('tasks.index'));

        $res->assertSee('Hygienist own task');
        $res->assertDontSee('Ashwini crown chase');
    }

    public function test_the_legacy_staff_type_string_cannot_widen_the_scope(): void
    {
        // The HR edit screen can set this string. It must not be a permission.
        $ashwini = $this->userWithRole(Role::ASSISTANT, 'admin');
        $other   = $this->userWithRole(Role::FRONT_DESK);

        $this->task($other, 'Not hers');

        $this->actingAs($ashwini)
            ->get(route('tasks.index'))
            ->assertDontSee('Not hers');
    }

    // ── the counts, and the control ──────────────────────────────────────

    public function test_the_counts_do_not_leak_what_the_list_hides(): void
    {
        $ashwini = $this->userWithRole(Role::ASSISTANT);
        $other   = $this->userWithRole(Role::FRONT_DESK);

        $this->task($ashwini, 'Hers');
        $this->task($other, 'Not hers');
        $this->task($other, 'Also not hers');

        $board = app(\App\Services\Tasks\TaskBoardData::class)->build([], $ashwini);

        // A count built from a wider query than the list tells someone exactly
        // how much work they are not allowed to read.
        $this->assertSame(1, $board['counts']['open']);
    }

    public function test_the_staff_filter_is_hidden_from_someone_who_sees_only_their_own(): void
    {
        $ashwini = $this->userWithRole(Role::ASSISTANT);
        $this->task($ashwini, 'Hers');

        $res = $this->actingAs($ashwini)->get(route('tasks.index'));

        // Offering it would advertise the names of colleagues whose work she
        // cannot read.
        $res->assertDontSee('All staff');
    }

    // ── the phone, which had its own copy of the bug ─────────────────────

    public function test_the_phone_applies_the_same_boundary(): void
    {
        $doctor  = $this->userWithRole(Role::DOCTOR, 'doctor');
        $ashwini = $this->userWithRole(Role::ASSISTANT);

        $this->task($doctor, 'Doctor own task');
        $this->task($ashwini, 'Ashwini crown chase');

        $res = $this->actingAs($doctor, 'sanctum')->getJson('/api/v1/tasks');

        $res->assertOk();
        $body = $res->getContent();

        $this->assertStringContainsString('Doctor own task', $body);
        $this->assertStringNotContainsString('Ashwini crown chase', $body);
    }
}
