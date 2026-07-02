<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Communication module — Task assignment → Notification
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  When one staff member creates a task assigned to another, the assignee
 *  should automatically receive an in-app notification.
 *
 *  Disables only the module-permission gate (keeps route-model-binding etc.).
 */
class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_task_notifies_the_assignee(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $creator  = User::factory()->create(['branch_id' => 1]);
        $assignee = User::factory()->create(['branch_id' => 1]);

        $resp = $this->actingAs($creator)->post(route('tasks.store'), [
            'title'       => 'Dusk follow-up task',
            'assigned_to' => $assignee->id,
            'due_date'    => today()->toDateString(),
            'priority'    => 'medium',
            'category'    => 'admin',
        ]);
        $resp->assertSessionHasNoErrors();

        // The task was created…
        $this->assertDatabaseHas('tasks', [
            'title'       => 'Dusk follow-up task',
            'assigned_to' => $assignee->id,
        ]);

        // …and the assignee was notified.
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
        ]);
    }
}
