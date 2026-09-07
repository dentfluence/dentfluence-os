<?php

namespace Tests\Feature\Relationship;

use App\Models\Task;
use App\Models\User;
use App\Services\Huddle\HuddleBoardApiService;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CEO rule, 2026-09-06: "PRE engine che task vegle aahet ani task manager che
 * vegle aahet." Automation output belongs on the PRE Today board, never on a
 * staff surface — and the Huddle is a staff surface.
 *
 * The Huddle reads the tasks table through DB::table() for speed, so
 * Task::scopeVisibleToReception() never runs there. Task::applyReceptionVisibility()
 * is the query-builder twin that carries the same rule, plus the soft-delete
 * guard a raw read does not get for free.
 *
 * @see \App\Models\Task::applyReceptionVisibility()
 */
class HuddleTasksHumanOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function task(User $actor, string $title, string $type, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title'      => $title,
            'category'   => 'call',
            'task_type'  => $type,
            'status'     => 'pending',
            'due_date'   => today(),
            'branch_id'  => 1,
            'created_by' => $actor->id,
        ], $extra));
    }

    public function test_huddle_task_list_carries_human_work_only(): void
    {
        $actor  = $this->actor();
        $human  = $this->task($actor, 'Order gloves', 'human');
        $system = $this->task($actor, 'Birthday greeting', 'system');

        $rows = app(HuddleBoardApiService::class)->tasks(1, Carbon::today());
        $ids  = $rows->pluck('id')->all();

        $this->assertContains($human->id, $ids);
        $this->assertNotContains($system->id, $ids, 'Automation tasks must not reach the Huddle board');
    }

    public function test_huddle_task_list_skips_soft_deleted_rows(): void
    {
        $actor = $this->actor();
        $gone  = $this->task($actor, 'Retired reminder call', 'human');
        $gone->delete();

        $ids = app(HuddleBoardApiService::class)->tasks(1, Carbon::today())->pluck('id')->all();

        $this->assertNotContains($gone->id, $ids);
    }

    public function test_apply_reception_visibility_respects_the_flag(): void
    {
        $actor = $this->actor();
        $this->task($actor, 'Human row', 'human');
        $this->task($actor, 'System row', 'system');

        $count = fn () => DB::table('tasks')
            ->tap(fn ($q) => Task::applyReceptionVisibility($q))
            ->count();

        // Flag on (the product default since 6 Sep): staff work only.
        $this->assertSame(1, $count());

        // Flag off: the legacy view, both rows — but soft-deleted rows stay out.
        Feature::set('tasks.human_system_split', false);
        $this->assertSame(2, $count());
    }
}
