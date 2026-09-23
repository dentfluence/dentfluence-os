<?php

namespace Tests\Feature\MyDay;

use App\Models\Finance\FinanceExpense;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MY DAY — the sequence, and why it is by KIND OF WORK and not by time.
 *
 * ── CEO RULING, 23 Sep ──────────────────────────────────────────────────────
 * "my day madhye timewise nako… pahile aajche calls de / mag task de / mag lab
 * de / mag unpaid expenses reminders if its overdue"
 *
 * V1 grouped the page into NOW / THIS MORNING / BEFORE CLOSE. Two things were
 * wrong with that, and both are worth not re-learning:
 *
 *   1. The clinic does not run to that clock. Between patients you do what the
 *      gap allows, not what a heading says belongs to 11am.
 *   2. It SPLIT ONE KIND OF WORK across the page — lab cases to send sat in
 *      NOW and lab cases to chase in THIS MORNING, so "what is the lab
 *      situation" meant scrolling past the calls to find the other half.
 *
 * The order is a clinic policy in config/my_day.php, not a product decision,
 * so this test guards the ORDER rather than any one section's contents.
 */
class MyDaySectionOrderTest extends TestCase
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

    private function patient(): \App\Models\Patient
    {
        return \App\Models\Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'MyDaySection',
            'name'       => 'Test MyDaySection',
            'gender'     => 'male',
            'phone'      => '9000000088',
            'branch_id'  => 1,
        ]);
    }

    private function bill(array $extra = []): FinanceExpense
    {
        return FinanceExpense::create(array_merge([
            'clinic_id'      => 1,
            'title'          => 'Katara Dental Lab — August',
            'expense_date'   => today()->subDays(40),
            'amount'         => 12000,
            'total_amount'   => 12000,
            'status'         => 'approved',
            'payment_status' => 'unpaid',
            'due_date'       => today()->subDays(10),
        ], $extra));
    }

    public function test_the_section_order_is_the_clinic_policy(): void
    {
        // Asserted against CONFIG, not the rendered page: a section with
        // nothing in it is not rendered at all, so a page-order test would
        // pass or fail on whatever happened to be in the database that day.
        // The order itself is the policy, and this is where it lives.
        $this->assertSame(
            ['calls', 'tasks', 'lab', 'payments', 'stock'],
            array_keys(config('my_day.bands')),
            'The day\'s sequence changed. It is a clinic policy: calls, tasks, '
            . 'lab, payments — then stock, which is not in the CEO\'s four and '
            . 'is kept last rather than dropped.',
        );
    }

    public function test_lab_send_and_lab_chase_belong_to_one_section(): void
    {
        // The V1 split — sends in NOW, chases in THIS MORNING — is what made
        // "what is the lab situation" unanswerable in one glance.
        $lab = config('my_day.bands.lab.sources');

        $this->assertContains('unsent_lab', $lab);
        $this->assertContains('lab_chase', $lab);

        foreach (config('my_day.bands') as $key => $band) {
            if ($key === 'lab') {
                continue;
            }
            $this->assertEmpty(
                array_intersect(['unsent_lab', 'lab_chase'], $band['sources'] ?? []),
                "Lab work is split across sections again — '{$key}' has some of it.",
            );
        }
    }

    public function test_the_time_of_day_bands_are_gone(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);

        $html = $this->get(route('my-day'))->getContent();

        foreach (['Before the first patient', 'Between patients', 'End of day'] as $clock) {
            $this->assertStringNotContainsString(
                $clock,
                $html,
                'The time-of-day bands are back. The clinic does not run to that '
                . 'clock — between patients you do what the gap allows.',
            );
        }
    }

    public function test_every_section_uses_the_same_table_as_calls(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);
        $this->bill();

        $html = $this->get(route('my-day'))->getContent();

        // 23 Sep: "keep all ui same like calls… UI is different for lab and
        // calls." One page had two visual languages — the board's dense table
        // for calls, My Day's own cards for everything else — so the eye
        // re-learned how to read halfway down. The summary rows reuse the
        // board's OWN classes; they are not styled to match, they are the
        // same styles.
        $this->assertStringContainsString('class="taw-wrap"', $html);
        $this->assertStringContainsString('class="taw-t"', $html);
        $this->assertStringContainsString('class="taw-tag"', $html,
            'The summary rows are not using the board\'s cell styles.');
    }

    public function test_an_empty_section_does_not_render(): void
    {
        $actor = $this->admin();

        // One lab case and nothing else: the Tasks section has no tasks and
        // must not render its board's "Nothing here" panel, which is a
        // screenful of nothing between the calls and the lab work.
        \App\Models\LabCase::create([
            'patient_id'    => $this->patient()->id,
            'work_category' => 'Crown & Bridge',
            'status'        => 'order_placed',
            'branch_id'     => 1,
        ]);

        $html = $this->get(route('my-day'))->getContent();

        $this->assertStringContainsString('Lab', $html);
        $this->assertStringNotContainsString('No tasks match this filter', $html,
            'The empty Tasks board is rendering its own empty state on My Day.');
    }

    public function test_an_overdue_bill_is_listed(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);
        $bill = $this->bill(['title' => 'DG Digital Lab — September']);

        $res = $this->get(route('my-day'));

        $res->assertSee('DG Digital Lab — September', false);
        $res->assertSee('10 days overdue');
    }

    public function test_a_bill_not_yet_due_is_not_listed(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);
        $this->bill([
            'title'    => 'Not due until next month',
            'due_date' => today()->addDays(20),
        ]);

        // An unpaid bill that is not late is not today's work. Listing it
        // teaches people to skim the section — and then the one that IS late
        // gets skimmed past too.
        $this->get(route('my-day'))->assertDontSee('Not due until next month');
    }

    public function test_a_paid_bill_is_not_listed(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);
        $this->bill([
            'title'          => 'Already settled',
            'payment_status' => 'paid',
        ]);

        $this->get(route('my-day'))->assertDontSee('Already settled');
    }

    public function test_a_cancelled_bill_is_not_chased(): void
    {
        $actor = $this->admin();
        $this->someWork($actor);
        $this->bill([
            'title'  => 'Cancelled order',
            'status' => 'cancelled',
        ]);

        $this->get(route('my-day'))->assertDontSee('Cancelled order');
    }
}
