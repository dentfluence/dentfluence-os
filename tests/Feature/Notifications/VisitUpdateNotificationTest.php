<?php

namespace Tests\Feature\Notifications;

use App\Models\AppNotification;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-7 — an edit must reach the desk, and must not become noise.
 *
 * The bug this pins: a treatment visit is routinely saved empty at the chair
 * and given its work and its handover minutes later. The desk was told the
 * visit existed and was NEVER told to collect the money, because the notifier
 * ran only from create() and a second fire() is swallowed by the UNIQUE
 * dedupe_key.
 *
 * Each case sits on a way the fix could quietly be undone:
 *  - a changed payload refreshes the SAME card and re-opens it, and never
 *    writes a second card for one piece of work;
 *  - an unchanged payload does nothing at all — a card the desk already
 *    answered does not come back because someone re-saved the record;
 *  - a card sent to the bell with "Later" pops again once what it says has
 *    materially changed;
 *  - plain fire() is untouched, so the other three dozen events keep their
 *    fire-once behaviour.
 */
class VisitUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private User $deskA;
    private User $deskB;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->doctor = $this->userWithRole(Role::DOCTOR, 'doctor');
        $this->deskA  = $this->userWithRole(Role::FRONT_DESK, 'front_desk');
        $this->deskB  = $this->userWithRole(Role::FRONT_DESK, 'front_desk');

        $this->patient = Patient::create([
            'name'      => 'N7 Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function userWithRole(string $slug, string $legacy): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();

        return User::factory()->create([
            'role'      => $legacy,
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /** The payload as the desk sees it. */
    private function ctx(string $title, ?string $message): array
    {
        return [
            'title'        => $title,
            'message'      => $message,
            'action_url'   => 'https://dentfluence.test/patients/' . $this->patient->id,
            'action_label' => 'Open patient',
            'source'       => $this->patient,
            'branch_id'    => 1,
            'owner'        => $this->doctor->id,
            // The doctor saved it, so the doctor is never told about it.
            'actor_id'     => $this->doctor->id,
        ];
    }

    private function deskRows(): \Illuminate\Database\Eloquent\Collection
    {
        return AppNotification::whereIn('user_id', [$this->deskA->id, $this->deskB->id])
            ->orderBy('id')
            ->get();
    }

    public function test_an_edit_refreshes_the_same_card_and_re_opens_it(): void
    {
        $d = app(NotificationDispatcher::class);

        // The visit is saved empty at the chair.
        $d->fire('consultation.saved', $this->ctx('N7 Patient — treatment visit done', null));

        $first = $this->deskRows();
        $this->assertCount(2, $first, 'both receptionists are told once');

        // One of them answers it.
        $first->first()->acknowledgeGroup($this->deskB->id);
        $this->assertNotNull($first->first()->fresh()->acknowledged_at);

        // Minutes later the work and the handover are added.
        $d->fireOrRefresh('consultation.saved', $this->ctx(
            'N7 Patient — Composite Filling (26)',
            'Collect ₹4,000 · Next visit 28 Sep'
        ));

        $after = $this->deskRows();

        // ONE card per piece of work — an edit must not stack a second one.
        $this->assertCount(2, $after, 'still one card each, not a second pair');

        foreach ($after as $row) {
            $this->assertSame('N7 Patient — Composite Filling (26)', $row->title);
            $this->assertSame('Collect ₹4,000 · Next visit 28 Sep', $row->message);
            $this->assertNull($row->acknowledged_at, 'the card comes back');
            $this->assertNull($row->acknowledged_by);
            $this->assertFalse((bool) $row->is_read);
            $this->assertNull($row->read_at);
        }
    }

    public function test_an_unchanged_payload_does_not_disturb_an_answered_card(): void
    {
        $d = app(NotificationDispatcher::class);

        $d->fire('consultation.saved', $this->ctx('N7 Patient — Composite Filling (26)', 'Collect ₹4,000'));
        $this->deskRows()->first()->acknowledgeGroup($this->deskA->id);

        // The doctor re-saves the record having changed only the clinical note,
        // which the desk never sees. Reception must not be interrupted.
        $refreshed = $d->fireOrRefresh('consultation.saved', $this->ctx('N7 Patient — Composite Filling (26)', 'Collect ₹4,000'));

        $this->assertSame(0, $refreshed, 'nothing the desk acts on changed');

        foreach ($this->deskRows() as $row) {
            $this->assertNotNull($row->acknowledged_at, 'the answered card stays answered');
            $this->assertTrue((bool) $row->is_read);
        }
    }

    public function test_a_card_sent_to_the_bell_pops_again_when_the_money_changes(): void
    {
        $d = app(NotificationDispatcher::class);

        $d->fire('consultation.saved', $this->ctx('N7 Patient — treatment visit done', null));

        $mine = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        $this->assertSame(AppNotification::PRIORITY_POPUP, $mine->priority);

        // "Later" — this user only.
        $mine->snoozeToBell();
        $this->assertSame(AppNotification::PRIORITY_BELL, $mine->fresh()->priority);

        $d->fireOrRefresh('consultation.saved', $this->ctx('N7 Patient — Composite Filling (26)', 'Collect ₹4,000'));

        $this->assertSame(
            AppNotification::PRIORITY_POPUP,
            $mine->fresh()->priority,
            'a snoozed card must pop again once it says something new'
        );
    }

    public function test_the_three_facts_travel_as_fields_and_a_changed_amount_re_opens_the_card(): void
    {
        $d = app(NotificationDispatcher::class);

        $ctx = $this->ctx('N7 Patient — Composite Filling (26)', 'Collect ₹4,000');
        $ctx['payload'] = ['collect' => 4000, 'action' => 'X-ray', 'appointment' => '28 Sep (Sun)', 'note' => null];
        $d->fire('consultation.saved', $ctx);

        $row = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        $this->assertSame(4000, $row->payload['collect'], 'the amount is a field, not a sentence');
        $this->assertSame('X-ray', $row->payload['action']);

        $row->acknowledgeGroup($this->deskA->id);

        // The doctor corrects the amount. The words around it never change —
        // only the payload does — and the desk must still be told.
        $ctx['payload']['collect'] = 6500;
        $refreshed = $d->fireOrRefresh('consultation.saved', $ctx);

        $this->assertGreaterThan(0, $refreshed, 'a changed amount is a changed card');
        $row->refresh();
        $this->assertSame(6500, $row->payload['collect']);
        $this->assertNull($row->acknowledged_at, 'the card comes back with the new figure');
    }

    public function test_a_card_with_nothing_to_do_waits_in_the_bell(): void
    {
        $d = app(NotificationDispatcher::class);

        $ctx = $this->ctx('N7 Patient — treatment visit done', null);
        $ctx['max_level'] = \App\Services\Notifications\NotificationCatalog::LEVEL_BELL;

        $d->fire('consultation.saved', $ctx);

        foreach ($this->deskRows() as $row) {
            $this->assertSame(
                AppNotification::PRIORITY_BELL,
                $row->priority,
                'an empty visit must never interrupt reception'
            );
            $this->assertFalse((bool) $row->push, 'and must never buzz a phone');
        }
    }

    public function test_plain_fire_still_never_writes_twice(): void
    {
        $d = app(NotificationDispatcher::class);

        $d->fire('consultation.saved', $this->ctx('N7 Patient — treatment visit done', null));
        $again = $d->fire('consultation.saved', $this->ctx('N7 Patient — changed', 'Collect ₹4,000'));

        $this->assertSame(0, $again, 'fire() is fire-once, for every other event');

        foreach ($this->deskRows() as $row) {
            $this->assertSame('N7 Patient — treatment visit done', $row->title);
            $this->assertNull($row->message);
        }
    }
}
