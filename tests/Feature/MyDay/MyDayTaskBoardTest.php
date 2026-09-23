<?php

namespace Tests\Feature\MyDay;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MY DAY — the embedded Tasks board.
 *
 * Every assertion here exists because of a specific way this page can rot:
 *
 *  1. THE BOARD IS THE BOARD. My Day renders tasks/_board.blade.php, not a
 *     summary of it. If someone "simplifies" My Day back to flat rows, the
 *     outcome drawer disappears and nothing can be closed from this page —
 *     the exact failure the page was built to fix.
 *
 *  2. ONE TASK, ONE PLACE. The morning band gave up its 'tasks' SOURCE when
 *     it gained the 'tasks' BOARD. Put both back and every task renders twice.
 *     That is the same duplication bug that made My Day untrustworthy on the
 *     day it shipped, and it will not be caught by eye on a busy list.
 *
 *  3. OVERDUE IS TODAY'S PROBLEM. The board is asked for 'due_now', not
 *     'today'. A window that quietly excluded overdue work would leave a
 *     week-old promise sitting behind a clean-looking screen.
 *
 *  4. MY DAY IS ONE PERSON'S SHIFT. assigned_to is forced even for an admin
 *     who sees the whole branch on /tasks. A colleague's work appearing here
 *     turns a queue you can finish into a list you never can.
 */
class MyDayTaskBoardTest extends TestCase
{
    use RefreshDatabase;

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

    private function task(User $owner, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title'       => 'Autoclave service',
            'category'    => 'maintenance',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $owner->id,
            'assigned_to' => $owner->id,
        ], $extra));
    }

    public function test_my_day_renders_the_real_tasks_board_not_a_summary(): void
    {
        $actor = $this->admin();
        $this->task($actor, ['title' => 'Crown chase Sujeet']);

        $res = $this->get(route('my-day'));

        $res->assertOk();
        $res->assertSee('Crown chase Sujeet');
        // The board's own machinery. A flat list has none of it.
        $res->assertSee('taskList()', false);
    }

    public function test_a_task_appears_once_not_once_per_surface(): void
    {
        $actor = $this->admin();
        $this->task($actor, ['title' => 'Unmistakable crown chase']);

        $html = $this->get(route('my-day'))->getContent();

        // strip_tags first: cells carry their text in a title= tooltip as
        // well as in the text itself, so raw HTML counts 2 for one row and
        // says nothing about duplication.
        $this->assertSame(
            1,
            substr_count(strip_tags($html), 'Unmistakable crown chase'),
            'The task rendered more than once — the morning band is probably '
            . 'carrying both the tasks SOURCE and the tasks BOARD.',
        );
    }

    public function test_overdue_work_is_on_the_board_not_hidden_behind_today(): void
    {
        $actor = $this->admin();
        $this->task($actor, ['title' => 'Late crown chase', 'due_date' => today()->subDays(4)]);

        $this->get(route('my-day'))->assertSee('Late crown chase');
    }

    public function test_tomorrows_work_is_not_on_todays_list(): void
    {
        $actor = $this->admin();
        $this->task($actor, ['title' => 'Next week denture', 'due_date' => today()->addDays(6)]);

        $this->get(route('my-day'))->assertDontSee('Next week denture');
    }

    /**
     * REVERSED ON 23 SEP, deliberately.
     *
     * The first version forced assigned_to even for the owner, on the
     * reasoning that My Day is one person's shift. CEO ruling the same day:
     * "let owner and manager see all task and calls." For an owner or a
     * manager that reasoning was wrong — their shift IS the clinic, and a
     * page that hid the team's work from the person accountable for it sent
     * them to /tasks every morning to find out what was happening.
     */
    public function test_an_owner_sees_the_whole_clinic_on_my_day(): void
    {
        $actor = $this->admin();

        $colleague = User::factory()->create([
            'role'      => 'front_desk',
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $this->task($actor, ['title' => 'Mine to do']);
        $this->task($colleague, ['title' => 'Ankita crown chase']);

        $res = $this->get(route('my-day'));

        $res->assertSee('Mine to do');
        $res->assertSee('Ankita crown chase');
        // And the heading must say so — "Assigned to you" above the whole
        // clinic's work is how a number stops being trusted.
        $res->assertSee('Across the clinic');
    }

    /**
     * The boundary is the SAME one /tasks uses (User::taskScope()), so My Day
     * cannot become a way around it. An assistant still sees only her own.
     */
    public function test_everyone_else_still_sees_only_their_own(): void
    {
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => \App\Models\Role::ASSISTANT],
            ['name' => 'Assistant', 'category' => \App\Models\Role::CATEGORY_STAFF, 'is_system' => true],
        );

        $ashwini = User::factory()->create([
            'role'      => 'assistant',
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $colleague = User::factory()->create(['branch_id' => 1, 'is_active' => true]);

        $this->task($ashwini, ['title' => 'Hers to do']);
        $this->task($colleague, ['title' => 'Not hers']);

        $res = $this->actingAs($ashwini)->get(route('my-day'));

        $res->assertSee('Hers to do');
        $res->assertDontSee('Not hers');
        $res->assertSee('Assigned to you');
    }

    public function test_the_tasks_page_still_renders_after_the_board_moved_out(): void
    {
        $actor = $this->admin();
        $this->task($actor, ['title' => 'Still on the tasks page']);

        $res = $this->get(route('tasks.index'));

        $res->assertOk();
        $res->assertSee('Still on the tasks page');
        // The furniture that only the full page carries.
        $res->assertSee('Assign Task');
        $res->assertSee('All staff');
    }
}
