<?php

namespace Tests\Feature\Tasks;

use App\Models\AppNotification;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks joined the notification engine on 22 Sep.
 *
 * Two things are locked here.
 *
 * ONE — Tasks go THROUGH the engine. TaskController::store() used to call
 * AppNotification::notify() directly, which skipped notification_rules, the
 * Settings matrix and dedupe, and wrote a row with no push intent. Nobody had
 * ever been pushed a task.
 *
 * TWO — a BELL-level rule with push ticked actually pushes. The dispatcher
 * used to write `push && level === popup`, which silently discarded every
 * push the catalogue asked for at bell level. The catalogue's own BP constant
 * is [bell, true] and eight shipped events use it, so this was not a Tasks
 * problem — payment.received, lab.received, lead.new, membership.sold,
 * leave.requested and login.new_device were all affected. This test would
 * have caught it, which is why it exists.
 *
 * @see \App\Services\Notifications\NotificationDispatcher
 */
class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        return User::factory()->create([
            'role'      => $slug,
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    private function seedRules(): void
    {
        $this->seed(\Database\Seeders\NotificationRuleSeeder::class);
    }

    public function test_the_catalogue_asks_for_a_push_on_a_bell_level_task_event(): void
    {
        // Guard on the shape itself: if someone quietly demotes these, the
        // assertions below would still pass while nothing reached a phone.
        foreach (['task.assigned', 'task.overdue'] as $key) {
            $def = NotificationCatalog::get($key);
            $this->assertSame(
                [NotificationCatalog::LEVEL_BELL, true],
                $def['owner'],
                "{$key} should reach the assignee's bell AND phone",
            );
        }
    }

    public function test_assigning_a_task_notifies_the_assignee_with_push_intent(): void
    {
        $this->seedRules();

        $manager  = $this->userWithRole(Role::MANAGER);
        $assignee = $this->userWithRole('assistant');
        $actor    = $this->userWithRole(Role::ADMIN);

        $this->actingAs($actor);

        $task = Task::create([
            'title'       => 'Pest control visit',
            'category'    => 'maintenance',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $actor->id,
            'assigned_to' => $assignee->id,
        ]);

        app(NotificationDispatcher::class)->fire('task.assigned', [
            'title'     => 'New task assigned to you',
            'message'   => 'Pest control visit',
            'source'    => $task,
            'branch_id' => 1,
            'owner'     => $assignee->id,
        ]);

        $row = AppNotification::where('user_id', $assignee->id)->first();

        $this->assertNotNull($row, 'the assignee was never told');
        $this->assertTrue(
            (bool) $row->push,
            'a bell rule with push ticked must still reach the phone',
        );

        // The manager hears about it too, but on the bell only.
        $this->assertNotNull(AppNotification::where('user_id', $manager->id)->first());
    }

    public function test_the_actor_is_not_told_about_their_own_action(): void
    {
        $this->seedRules();

        $actor = $this->userWithRole(Role::ADMIN);
        $this->actingAs($actor);

        $task = Task::create([
            'title'       => 'Self-assigned job',
            'category'    => 'admin',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $actor->id,
            'assigned_to' => $actor->id,
        ]);

        app(NotificationDispatcher::class)->fire('task.assigned', [
            'title'     => 'New task assigned to you',
            'source'    => $task,
            'branch_id' => 1,
            'owner'     => $actor->id,
        ]);

        $this->assertNull(
            AppNotification::where('user_id', $actor->id)->first(),
            'a person does not need telling about a thing they just did',
        );
    }

    public function test_the_overdue_digest_fires_once_a_day_not_once_per_run(): void
    {
        $this->seedRules();

        $staff = $this->userWithRole('assistant');
        $actor = $this->userWithRole(Role::ADMIN);
        $this->actingAs($actor);

        $ctx = [
            'title'        => '3 task(s) still pending',
            'branch_id'    => 1,
            'owner'        => $staff->id,
            'actor_id'     => null,
            'source_type'  => User::class,
            'source_id'    => $staff->id,
            'dedupe_scope' => today()->toDateString(),
        ];

        // The command runs every two hours; only the first run of the day may
        // write a row. Six buzzes a day is how staff learn to swipe alerts
        // away unread.
        app(NotificationDispatcher::class)->fire('task.overdue', $ctx);
        app(NotificationDispatcher::class)->fire('task.overdue', $ctx);
        app(NotificationDispatcher::class)->fire('task.overdue', $ctx);

        $this->assertSame(
            1,
            AppNotification::where('user_id', $staff->id)->count(),
            'the every-two-hours sweep wrote more than one row for one day',
        );
    }
}
