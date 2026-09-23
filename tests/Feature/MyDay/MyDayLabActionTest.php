<?php

namespace Tests\Feature\MyDay;

use App\Models\LabCase;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MY DAY — the one-click lab action, and the two places there deliberately
 * is not one.
 *
 * ── THE RULE THIS LOCKS ─────────────────────────────────────────────────────
 * A row gets a button only where the next step is not a judgement:
 *
 *   SEND a lab case   → one obvious next status. Button.
 *   CHASE a lab case  → a phone call. The case does not change status because
 *                       you rang them, and a button marking it "received"
 *                       would be a lie told to clear a row. No button.
 *   ORDER stock       → a quantity, a supplier and a price, none of which can
 *                       be guessed from a low-stock row. No button.
 *
 * Someone will eventually look at the lab-chase row, think the missing button
 * is an oversight, and add one. These tests are the argument against it.
 */
class MyDayLabActionTest extends TestCase
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

    private function patient(): Patient
    {
        // No Patient factory in this repo — built the way the other lab tests
        // build one, so a change to the model breaks them together.
        return Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'MyDayLab',
            'name'       => 'Test MyDayLab',
            'gender'     => 'male',
            'phone'      => '9000000077',
            'branch_id'  => 1,
        ]);
    }

    private function labCase(User $actor, array $extra = []): LabCase
    {
        return LabCase::create(array_merge([
            'patient_id'    => $this->patient()->id,
            'work_category' => 'Crown & Bridge',
            'status'        => 'order_placed',
            'branch_id'     => 1,
            'created_by'    => $actor->id,
        ], $extra));
    }

    // ── the model's single answer ────────────────────────────────────────

    public function test_next_action_is_the_same_answer_from_a_status_or_a_model(): void
    {
        $actor = $this->admin();
        $case  = $this->labCase($actor, ['status' => 'order_placed']);

        $this->assertSame(
            LabCase::nextActionFor('order_placed'),
            $case->nextAction(),
            'The static and instance forms disagreed — the lab page and My Day '
            . 'would then offer different buttons for the same case.',
        );
    }

    public function test_a_finished_case_offers_nothing(): void
    {
        $this->assertNull(LabCase::nextActionFor('complete'));
        $this->assertNull(LabCase::nextActionFor('rejected'));
    }

    public function test_every_offered_action_is_a_transition_the_case_allows(): void
    {
        // A button that posts a status the flow forbids is a 500 waiting to
        // happen, and it would only show up on whichever case type nobody
        // tested by hand.
        foreach (array_keys(LabCase::STATUS_FLOW) as $status) {
            $next = LabCase::nextActionFor($status);

            if ($next === null) {
                continue;
            }

            $this->assertContains(
                $next['to'],
                LabCase::STATUS_FLOW[$status],
                "nextActionFor('{$status}') offers '{$next['to']}', which STATUS_FLOW does not allow.",
            );
        }
    }

    // ── the page ─────────────────────────────────────────────────────────

    public function test_an_unsent_case_carries_its_send_button(): void
    {
        $actor = $this->admin();
        $case  = $this->labCase($actor, ['status' => 'order_placed']);

        $res = $this->get(route('my-day'));

        $res->assertOk();
        $res->assertSee(route('lab.transition', [$case->id, 'impression_sent']), false);
        $res->assertSee('Mark as Sent to Lab');
    }

    public function test_the_link_carries_a_way_back(): void
    {
        $actor = $this->admin();
        $case  = $this->labCase($actor, ['status' => 'order_placed']);

        $this->get(route('my-day'))->assertSee('from=my-day', false);

        // And the module page honours it.
        $this->get(route('lab.show', $case->id) . '?from=my-day')
            ->assertOk()
            ->assertSee('Back to My Day');
    }

    public function test_an_unsent_case_is_never_also_a_chase(): void
    {
        $actor = $this->admin();

        // Written, never sent, and long past when it was due back — the exact
        // shape that appeared twice on My Day on 23 Sep: "Send lab case
        // LAB-2026-0009" and "Chase Katara Dental Lab — LAB-2026-0009".
        $case = $this->labCase($actor, [
            'status'               => 'order_placed',
            'expected_return_date' => today()->subDays(40),
        ]);

        $html = $this->get(route('my-day'))->getContent();

        // COUNT WHAT A PERSON READS, NOT WHAT THE HTML CONTAINS. Every cell
        // carries its text twice — once in a title= tooltip and once as the
        // text itself — exactly as the calls board's cells do. Counting raw
        // HTML therefore counts 2 for a single row and says nothing about
        // duplication. strip_tags() drops the attributes and leaves the text.
        $this->assertSame(
            1,
            substr_count(strip_tags($html), $case->case_number),
            'One case, two rows — you cannot chase a lab for work it never received.',
        );
        $this->assertStringContainsString('Send lab case', $html);
        $this->assertStringNotContainsString('Chase ', $html);
    }

    public function test_the_lab_page_shows_no_way_back_when_you_did_not_come_from_my_day(): void
    {
        $actor = $this->admin();
        $case  = $this->labCase($actor, ['status' => 'order_placed']);

        $this->get(route('lab.show', $case->id))
            ->assertOk()
            ->assertDontSee('Back to My Day');
    }
}
