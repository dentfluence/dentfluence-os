<?php

namespace Tests\Feature\MyDay;

use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MY DAY — two real boards on one page.
 *
 * These are the three ways this arrangement breaks, and none of them is
 * visible by reading the page in a browser on a quiet day:
 *
 *  1. DOUBLE INCLUDE. Both boards declare top-level JavaScript — CHECKLISTS,
 *     RESPONSE_OPTS, todayActions(), taWorklist(), taskList(). Include either
 *     board twice and the redeclaration throws, which kills EVERY script on
 *     the page, not just the second board. The page still renders, so it looks
 *     fine and nothing works. Hence the exact-count assertions.
 *
 *  2. THE OLD SUMMARY ROWS COMING BACK. 'calls' and 'tasks' left the band's
 *     sources when their boards arrived. Put either back in config and every
 *     call and task renders twice — the duplication bug this page already had
 *     once, in September.
 *
 *  3. THE BOARD'S OWN PAGES BREAKING ON THE WAY OUT. The markup moved into a
 *     partial; /relationship/today and /relationship/today/pending must render
 *     exactly as before, and their payload still comes from the same method.
 */
class MyDayCallsBoardTest extends TestCase
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

    /**
     * A REAL CALL DUE TODAY.
     *
     * Needed because a section with nothing in it no longer renders at all
     * (23 Sep) — so a test that seeds only a task has no Calls section to
     * assert against, and every "is the calls board here" assertion fails for
     * a reason that has nothing to do with the board.
     *
     * A pending FollowUp due today is the cheapest thing TodayActionsEngine
     * will turn into a call row; the other categories need recall, opportunity
     * or invoice fixtures.
     */
    private function aCallDueToday(): FollowUp
    {
        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'MyDayCall',
            'name'       => 'Test MyDayCall',
            'gender'     => 'male',
            'phone'      => '9000000099',
            'branch_id'  => 1,
        ]);

        return FollowUp::create([
            'patient_id' => $patient->id,
            'label'      => 'Follow-up call',
            'due_date'   => today()->toDateString(),
            'status'     => 'pending',
            'channel'    => 'call',
            'priority'   => 'medium',
        ]);
    }

    /**
     * ONE PIECE OF WORK, so the page has a day to show.
     *
     * My Day renders nothing at all when the total is zero — it says "You're
     * clear." and stops, which is the point of the page and not a bug to fix.
     * So a test that seeds nothing is testing the empty state, not the boards.
     * That mistake is what these tests failed on first time round.
     */
    private function someWork(User $owner): Task
    {
        return Task::create([
            'title'       => 'Autoclave service',
            'category'    => 'maintenance',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $owner->id,
            'assigned_to' => $owner->id,
        ]);
    }

    public function test_my_day_carries_both_boards(): void
    {
        $this->someWork($this->admin());
        $this->aCallDueToday();

        $html = $this->get(route('my-day'))->assertOk()->getContent();

        $this->assertStringContainsString('todayActions()', $html, 'The calls board is missing.');
        $this->assertStringContainsString('taskList()', $html, 'The tasks board is missing.');
    }

    public function test_no_board_is_included_twice(): void
    {
        $this->someWork($this->admin());
        $this->aCallDueToday();

        $html = $this->get(route('my-day'))->getContent();

        // Declared once in the script; a second copy is a redeclaration and
        // takes the whole page's JavaScript down with it.
        $this->assertSame(1, substr_count($html, 'const CHECKLISTS'),
            'The calls board is included more than once — every script on the page will throw.');
        $this->assertSame(1, substr_count($html, 'function taskList()'),
            'The tasks board is included more than once — every script on the page will throw.');
        $this->assertSame(1, substr_count($html, 'function todayActions()'),
            'The calls board is included more than once — every script on the page will throw.');
    }

    public function test_the_boards_stylesheet_is_pushed_once(): void
    {
        $this->someWork($this->admin());
        $this->aCallDueToday();

        $html = $this->get(route('my-day'))->getContent();

        // @once guards the push. Without it the 630-line stylesheet ships
        // twice and the page weight doubles for nothing.
        $this->assertSame(1, substr_count($html, '.taw-band-title'));
    }

    public function test_a_clear_day_shows_no_boards_at_all(): void
    {
        $this->admin();

        $res = $this->get(route('my-day'));

        // Deliberate. Nothing to do means nothing to show — an empty calls
        // board and an empty tasks board under "You're clear." would argue
        // with it, and reading a board to learn it is empty is work.
        $res->assertSee("You're clear.", false);
        $res->assertDontSee('todayActions()', false);
        $res->assertDontSee('taskList()', false);
    }

    public function test_the_embedded_board_brings_no_page_furniture(): void
    {
        $this->someWork($this->admin());
        $this->aCallDueToday();

        $html = $this->get(route('my-day'))->getContent();

        // 23 Sep, after seeing it on local: "far scattered aahe". The board
        // was rendering its whole page around one call — a search box, a date
        // toolbar, category chips, its own band headings and a footer of
        // bookkeeping. Compact is the ROWS and the DRAWER. Nothing else.
        // MATCH THE ATTRIBUTE, NOT THE CLASS NAME. Every one of these strings
        // also appears in the pushed stylesheet as a selector (.taw-bar { … }),
        // which ships on the page whether the markup renders or not. A bare
        // 'taw-bar' assertion can never pass — and its positive form can never
        // fail, which is how the companion test below was quietly testing the
        // CSS file instead of the board.
        $this->assertStringNotContainsString('class="taw-bar"', $html,       'The date toolbar is back.');
        $this->assertStringNotContainsString('class="taw-cats"', $html,      'The category chip strip is back.');
        $this->assertStringNotContainsString('class="taw-foot"', $html,      'The footer bookkeeping is back.');
        $this->assertStringNotContainsString('class="taw-search"', $html,    'The search box is back.');
        $this->assertStringNotContainsString('class="taw-band-head"', $html, 'Bands inside bands are back.');

        // What must survive: the drawer, or nothing can be logged from here.
        $this->assertStringContainsString('todayActions()', $html);
    }

    public function test_the_full_page_keeps_every_bit_of_it(): void
    {
        $this->admin();

        $html = $this->get(route('relationship.today'))->getContent();

        foreach (['class="taw-bar"', 'class="taw-foot"', 'class="taw-search"'] as $furniture) {
            $this->assertStringContainsString($furniture, $html,
                "Stripping compact mode took {$furniture} off the board's own page too.");
        }
    }

    public function test_todays_actions_still_renders_on_its_own_page(): void
    {
        $this->admin();

        $res = $this->get(route('relationship.today'));

        $res->assertOk();
        $res->assertSee('todayActions()', false);
        // The title block the compact copy drops — proof $compact defaults off.
        $res->assertSee("Today's Actions", false);
    }

    public function test_pending_calls_still_renders_on_its_own_page(): void
    {
        $this->admin();

        $res = $this->get(route('relationship.today.pending'));

        $res->assertOk();
        $res->assertSee('Pending Calls', false);
    }

    public function test_my_day_ignores_a_date_in_the_query_string(): void
    {
        $this->someWork($this->admin());

        // boardPayload() reads ?date= and would render a future preview.
        // My Day is today; the date picker belongs to the board's own page.
        $html = $this->get(route('my-day', ['date' => today()->addDays(9)->toDateString()]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Preview from today', $html);
    }
}
