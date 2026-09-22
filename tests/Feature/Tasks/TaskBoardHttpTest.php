<?php

namespace Tests\Feature\Tasks;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task Manager V2 — the screen and its endpoints, over HTTP.
 *
 * Two classes of assertion here, and both exist because of a specific defect:
 *
 *  1. FILTERS ACTUALLY FILTER. The screen this replaced shipped counter cards,
 *     a search box, a staff dropdown and a Daily/Weekly/Monthly switch whose
 *     Alpine state nothing ever read — four dead controls that reception had
 *     been clicking for months. A filter with no test is a filter that can go
 *     quietly dead again.
 *
 *  2. ROUTE ORDER. /tasks/{task} is a wildcard declared in the same group as
 *     /tasks/settings, /tasks/my and /tasks/overdue. Put the wildcard first
 *     and "settings" is read as a task id — a 404 nobody would connect to a
 *     routing change weeks later.
 */
class TaskBoardHttpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user the module gate actually lets through.
     *
     * CheckModulePermission asks User::canAccess(), which resolves the
     * ASSIGNED role (role_id) — the legacy `role === 'admin'` string bypass
     * was retired on 25 Jul 2026. A user with the right string and no role_id
     * is denied everything, so the role row is not optional here.
     */
    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Clinic Owner', 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        $user = User::factory()->create([
            'role'      => 'admin',
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function task(User $actor, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title'      => 'Autoclave service',
            'category'   => 'maintenance',
            'task_type'  => 'human',
            'status'     => 'pending',
            'due_date'   => today(),
            'branch_id'  => 1,
            'created_by' => $actor->id,
            'assigned_to'=> $actor->id,
        ], $extra));
    }

    // ── filters ──────────────────────────────────────────────────────────

    public function test_the_overdue_view_shows_only_overdue_work(): void
    {
        $actor = $this->admin();

        $this->task($actor, ['title' => 'Late crown chase', 'due_date' => today()->subDays(3)]);
        $this->task($actor, ['title' => 'Today autoclave',  'due_date' => today()]);

        $res = $this->get(route('tasks.index', ['view' => 'overdue']));

        $res->assertOk();
        $res->assertSee('Late crown chase');
        $res->assertDontSee('Today autoclave');
    }

    public function test_the_counts_and_the_rows_come_from_the_same_scope(): void
    {
        $actor = $this->admin();

        $this->task($actor, ['due_date' => today()->subDays(2)]);
        $this->task($actor, ['due_date' => today()->subDays(1)]);
        $this->task($actor, ['due_date' => today()]);

        $res = $this->get(route('tasks.index'));
        $res->assertOk();

        $counts = $res->viewData('counts');

        $this->assertSame(3, $counts['open']);
        $this->assertSame(2, $counts['overdue']);
        $this->assertSame(1, $counts['today']);
    }

    public function test_a_closed_task_leaves_the_open_count(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        app(\App\Services\Tasks\TaskOutcomeService::class)->done($task, 'completed');

        $counts = $this->get(route('tasks.index'))->viewData('counts');

        $this->assertSame(0, $counts['open']);
        $this->assertSame(1, $counts['done']);
    }

    public function test_the_search_filter_is_applied_server_side(): void
    {
        $actor = $this->admin();

        $this->task($actor, ['title' => 'Pest control visit']);
        $this->task($actor, ['title' => 'Compressor oil change']);

        $res = $this->get(route('tasks.index', ['q' => 'Pest']));

        $res->assertSee('Pest control visit');
        $res->assertDontSee('Compressor oil change');
    }

    // ── endpoints ────────────────────────────────────────────────────────

    public function test_marking_done_with_a_non_contact_outcome_reports_that_it_stayed_open(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor, ['category' => 'call', 'title' => 'Ring Mr Deshmukh']);

        $res = $this->postJson(route('tasks.done', $task), [
            'outcome_key' => 'no_answer',
            'note'        => 'rang twice',
        ]);

        $res->assertOk();
        // The phone and the web both key their UI off this flag. Returning
        // ok:true alone would have the row struck through and then reappear.
        $res->assertJsonPath('closed', false);
        $this->assertSame('pending', $task->refresh()->status);
    }

    public function test_a_reschedule_without_a_reason_is_rejected(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.reschedule', $task), [
            'due_date' => today()->addDays(3)->toDateString(),
        ])->assertStatus(422);

        // A reschedule with no reason is indistinguishable from avoidance.
        $this->assertSame(0, (int) $task->refresh()->reschedule_count);
    }

    public function test_a_task_cannot_be_moved_into_the_past(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.reschedule', $task), [
            'due_date' => today()->subDay()->toDateString(),
            'note'     => 'backdating the promise',
        ])->assertStatus(422);
    }

    public function test_a_cancel_without_a_reason_is_rejected(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.cancel', $task), [])->assertStatus(422);

        $this->assertSame('pending', $task->refresh()->status);
    }

    public function test_the_drawer_is_served_its_own_category_vocabulary(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor, ['category' => 'lab']);

        $res = $this->getJson(route('tasks.show', $task));

        $res->assertOk();
        $res->assertJsonPath('options.awaiting_lab', 'Waiting on lab');
        // Lab words on a lab task; call words nowhere near it.
        $this->assertArrayNotHasKey('no_answer', $res->json('options'));
    }

    public function test_a_task_from_another_branch_is_not_reachable(): void
    {
        $actor = $this->admin();
        $other = $this->task($actor, ['branch_id' => 99]);

        $this->getJson(route('tasks.show', $other))->assertStatus(403);
    }

    // ── route order ──────────────────────────────────────────────────────

    public function test_settings_is_not_swallowed_by_the_task_wildcard(): void
    {
        $this->admin();

        // If /tasks/{task} were declared first, "settings" would be read as an
        // id and this would 404.
        $this->get(route('tasks.settings'))
            ->assertOk()
            ->assertSee('Task Settings');
    }

    public function test_an_admin_can_add_a_maintenance_type_and_it_reaches_the_form(): void
    {
        $this->admin();

        $this->post(route('tasks.settings.maintenance.add'), [
            'label' => 'Compressor service',
        ])->assertRedirect();

        // The column is a varchar since 22 Sep precisely so this can be saved;
        // while it was an enum the dropdown would have shown it and MySQL
        // would have refused the write.
        $this->assertArrayHasKey('compressor_service', Task::maintenanceTypeOptions());
    }
}
