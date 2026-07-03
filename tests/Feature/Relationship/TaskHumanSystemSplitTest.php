<?php

namespace Tests\Feature\Relationship;

use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\TaskEngine;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — Task Engine Human/System split.
 *
 * 'system' = a record created by TaskEngine::autoCreate() (Automation /
 * RulesEngine-driven). 'human' = everything else — manual, Practice Protocol,
 * Lab, PO, TreatmentVisit, AppointmentReminderEngine — a person still has to
 * act on those even though they're auto-generated.
 *
 * While tasks.human_system_split is OFF (default), reception lists are
 * unchanged — System tasks still show, exactly like before the split existed.
 * Once flipped ON, System tasks disappear from staff "my work" lists.
 *
 * NOTE: created_by / branch_id are real FKs (tasks.created_by → users.id).
 * Every test creates a real User first and uses its id — never a hard-coded
 * 1 — and acts as that user before calling TaskEngine::autoCreate() so
 * TaskEngine's own created_by-resolution (Auth::id() ?? system user) points
 * at a row that actually exists.
 */
class TaskHumanSystemSplitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function relationship(): Relationship
    {
        return Relationship::create([
            'name'  => 'Split Test Person',
            'phone' => '900' . random_int(1000, 9999),
        ]);
    }

    public function test_task_engine_autocreate_tags_task_type_system(): void
    {
        $user         = $this->admin();
        $relationship = $this->relationship();

        $this->actingAs($user);

        $task = app(TaskEngine::class)->autoCreate(
            category:       'follow_up',
            taskData:       ['title' => 'Automation follow-up', 'due_date' => today()],
            relationshipId: $relationship->id,
        );

        $this->assertSame('system', $task->fresh()->task_type);
        $this->assertTrue($task->fresh()->isSystemTask());
    }

    public function test_manually_created_task_stays_human(): void
    {
        $user = $this->admin();

        $task = Task::create([
            'title'      => 'Order more gloves',
            'category'   => 'admin',
            'status'     => 'pending',
            'due_date'   => today(),
            'branch_id'  => $user->branch_id,
            'created_by' => $user->id,
        ]);

        $this->assertSame('human', $task->fresh()->task_type);
        $this->assertTrue($task->fresh()->isHumanTask());
    }

    public function test_reception_task_list_shows_system_tasks_when_flag_off(): void
    {
        $user         = $this->admin();
        $relationship = $this->relationship();

        $this->actingAs($user);

        app(TaskEngine::class)->autoCreate(
            category:       'follow_up',
            taskData:       ['title' => 'System Record Task', 'due_date' => today()],
            relationshipId: $relationship->id,
        );

        $response = $this->get(route('tasks.index'));

        $response->assertOk();
        $response->assertSee('System Record Task');
    }

    public function test_reception_task_list_hides_system_tasks_when_flag_on(): void
    {
        $user         = $this->admin();
        $relationship = $this->relationship();

        $this->actingAs($user);

        app(TaskEngine::class)->autoCreate(
            category:       'follow_up',
            taskData:       ['title' => 'Hidden System Task', 'due_date' => today()],
            relationshipId: $relationship->id,
        );
        Task::create([
            'title' => 'Visible Human Task', 'category' => 'admin', 'status' => 'pending',
            'due_date' => today(), 'branch_id' => $user->branch_id, 'created_by' => $user->id,
        ]);

        Feature::set('tasks.human_system_split', true);

        $response = $this->get(route('tasks.index'));

        $response->assertOk();
        $response->assertDontSee('Hidden System Task');
        $response->assertSee('Visible Human Task');
    }

    public function test_my_tasks_page_hides_system_tasks_when_flag_on(): void
    {
        $user         = $this->admin();
        $relationship = $this->relationship();

        $this->actingAs($user);

        app(TaskEngine::class)->autoCreate(
            category:       'follow_up',
            taskData:       ['title' => 'My Hidden System Task', 'due_date' => today(), 'assigned_to' => $user->id],
            relationshipId: $relationship->id,
        );
        Task::create([
            'title' => 'My Visible Human Task', 'category' => 'admin', 'status' => 'pending',
            'due_date' => today(), 'branch_id' => $user->branch_id, 'created_by' => $user->id, 'assigned_to' => $user->id,
        ]);

        Feature::set('tasks.human_system_split', true);

        $response = $this->get(route('tasks.mine'));

        $response->assertOk();
        $response->assertDontSee('My Hidden System Task');
        $response->assertSee('My Visible Human Task');
    }

    public function test_scope_visible_to_reception_respects_flag(): void
    {
        $user = $this->admin();

        Task::create([
            'title' => 'H', 'category' => 'admin', 'status' => 'pending',
            'due_date' => today(), 'branch_id' => $user->branch_id, 'created_by' => $user->id, 'task_type' => 'human',
        ]);
        Task::create([
            'title' => 'S', 'category' => 'admin', 'status' => 'pending',
            'due_date' => today(), 'branch_id' => $user->branch_id, 'created_by' => $user->id, 'task_type' => 'system',
        ]);

        // Flag off: both visible.
        $this->assertSame(2, Task::visibleToReception()->count());

        // Flag on: only human visible.
        Feature::set('tasks.human_system_split', true);
        $this->assertSame(1, Task::visibleToReception()->count());
        $this->assertSame('H', Task::visibleToReception()->first()->title);
    }
}
