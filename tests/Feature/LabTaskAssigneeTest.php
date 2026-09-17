<?php

namespace Tests\Feature;

use App\Models\LabCase;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\LabCaseTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V.17 — the lab task chain goes to a front-desk user chosen by role_id and
 * branch, not to whoever has the lowest id and an old "receptionist" string.
 */
class LabTaskAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst(str_replace('_', ' ', $slug)), 'is_system' => true]);
    }

    private function user(array $attrs): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'branch_id' => 1], $attrs));
    }

    private function caseInBranch(int $branchId): LabCase
    {
        $patient = Patient::create([
            'first_name' => 'Lab', 'last_name' => 'Assignee', 'name' => 'Lab Assignee',
            'gender' => 'male', 'phone' => '9000000077', 'branch_id' => $branchId,
        ]);

        return LabCase::create([
            'patient_id' => $patient->id, 'work_category' => 'Crown & Bridge',
            'status' => 'order_placed', 'branch_id' => $branchId,
        ]);
    }

    public function test_front_desk_is_picked_by_role_id_active_and_branch(): void
    {
        $frontDesk = $this->role(Role::FRONT_DESK);
        $manager   = $this->role(Role::MANAGER);

        // Created first, so the old orderBy('id') rule would have picked each of these.
        $this->user(['role' => 'receptionist', 'role_id' => $manager->id]);            // legacy string lies
        $this->user(['role' => 'front_desk', 'role_id' => $frontDesk->id, 'is_active' => false]);
        $this->user(['role' => 'front_desk', 'role_id' => $frontDesk->id, 'branch_id' => 2]);
        $right = $this->user(['role' => 'staff', 'role_id' => $frontDesk->id]);
        $actor = $this->user(['role' => 'doctor']);

        $task = app(LabCaseTransitionService::class)->transition($this->caseInBranch(1), 'impression_sent', $actor);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame($right->id, (int) $task->assigned_to);
        $this->assertSame(1, (int) $task->branch_id);
    }

    public function test_falls_back_to_the_actor_when_branch_has_no_front_desk(): void
    {
        $this->role(Role::FRONT_DESK);
        $actor = $this->user(['role' => 'doctor', 'branch_id' => 3]);

        $task = app(LabCaseTransitionService::class)->transition($this->caseInBranch(3), 'impression_sent', $actor);

        $this->assertSame($actor->id, (int) $task->assigned_to);
    }
}
