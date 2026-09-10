<?php

namespace Tests\Feature\Notifications;

use App\Models\AppNotification;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\NotificationRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationDispatcher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-1 — the notification engine's contract, pinned.
 *
 * Each case sits on a way the design could quietly be undone later:
 *  - a saved consultation reaches the FRONT DESK as a POPUP, and never the
 *    doctor who saved it (the actor is never told about their own action);
 *  - the same event for the same record can never produce a second row;
 *  - an admin rule of 'off' silences an event without touching code;
 *  - "Done" on a popup clears it for EVERY receptionist, not just the clicker;
 *  - "Later" drops only the clicker's copy to the bell;
 *  - an invoice announces itself once it has a total — not on the empty header;
 *  - a payment reaches admin; a backfilled historical payment does not.
 */
class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private User $deskA;
    private User $deskB;
    private User $admin;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->doctor = $this->userWithRole(Role::DOCTOR, 'doctor');
        $this->deskA  = $this->userWithRole(Role::FRONT_DESK, 'front_desk');
        $this->deskB  = $this->userWithRole(Role::FRONT_DESK, 'front_desk');
        $this->admin  = $this->userWithRole(Role::ADMIN, 'admin');

        $this->patient = Patient::create([
            'name'      => 'N1 Patient',
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

    private function saveConsultation(): Consultation
    {
        return Consultation::create([
            'patient_id'            => $this->patient->id,
            'doctor_id'             => $this->doctor->id,
            'branch_id'             => 1,
            'consultation_date'     => now()->toDateString(),
            'chief_complaint'       => 'Pain upper left',
            'provisional_diagnosis' => 'Irreversible pulpitis 26',
        ]);
    }

    public function test_consultation_save_pops_up_at_the_front_desk_and_not_for_the_doctor(): void
    {
        $this->actingAs($this->doctor);
        $consultation = $this->saveConsultation();

        $rows = AppNotification::where('event_key', 'consultation.saved')->get();

        $this->assertCount(2, $rows, 'one row per front-desk user');
        $this->assertEqualsCanonicalizing(
            [$this->deskA->id, $this->deskB->id],
            $rows->pluck('user_id')->all()
        );
        $this->assertTrue($rows->every(fn ($r) => $r->priority === AppNotification::PRIORITY_POPUP));
        $this->assertTrue($rows->every(fn ($r) => $r->target_role === Role::FRONT_DESK));
        $this->assertTrue($rows->every(fn ($r) => $r->source_id === $consultation->id));
        $this->assertStringContainsString('N1 Patient', $rows->first()->title);
        $this->assertStringContainsString('Pain upper left', $rows->first()->message);

        $this->assertSame(1, AppNotification::pendingPopups($this->deskA->id)->count());
        $this->assertSame(0, AppNotification::where('user_id', $this->doctor->id)->count(), 'the actor is never notified');
    }

    public function test_the_same_event_for_the_same_record_never_writes_twice(): void
    {
        $this->actingAs($this->doctor);
        $consultation = $this->saveConsultation();

        $created = app(NotificationDispatcher::class)->fire('consultation.saved', [
            'title'     => 'again',
            'source'    => $consultation,
            'branch_id' => 1,
        ]);

        $this->assertSame(0, $created);
        $this->assertSame(2, AppNotification::where('event_key', 'consultation.saved')->count());
    }

    public function test_an_off_rule_silences_the_event_without_code(): void
    {
        NotificationRule::create([
            'event_key' => 'consultation.saved',
            'role'      => Role::FRONT_DESK,
            'level'     => NotificationCatalog::LEVEL_OFF,
            'push'      => false,
        ]);

        $this->actingAs($this->doctor);
        $this->saveConsultation();

        $this->assertSame(0, AppNotification::where('event_key', 'consultation.saved')->count());
    }

    public function test_a_bell_rule_demotes_the_popup(): void
    {
        NotificationRule::create([
            'event_key' => 'consultation.saved',
            'role'      => Role::FRONT_DESK,
            'level'     => NotificationCatalog::LEVEL_BELL,
            'push'      => true,
        ]);

        $this->actingAs($this->doctor);
        $this->saveConsultation();

        $row = AppNotification::where('event_key', 'consultation.saved')->firstOrFail();
        $this->assertSame(AppNotification::PRIORITY_BELL, $row->priority);
        $this->assertFalse($row->push, 'push is popup-only whatever the matrix says');
        $this->assertSame(0, AppNotification::pendingPopups($this->deskA->id)->count());
    }

    public function test_done_clears_the_popup_for_every_receptionist_and_later_only_for_one(): void
    {
        $this->actingAs($this->doctor);
        $this->saveConsultation();

        // Later — deskB only
        $b = AppNotification::where('user_id', $this->deskB->id)->firstOrFail();
        $this->actingAs($this->deskB)
            ->postJson(route('notifications.later', $b->id))
            ->assertOk();

        $this->assertSame(0, AppNotification::pendingPopups($this->deskB->id)->count());
        $this->assertSame(1, AppNotification::pendingPopups($this->deskA->id)->count(), 'deskA still sees it');
        $this->assertFalse($b->fresh()->is_read, 'Later keeps it unread in the bell');

        // Done — deskA answers it for the whole desk
        $a = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        $this->actingAs($this->deskA)
            ->postJson(route('notifications.acknowledge', $a->id))
            ->assertOk()
            ->assertJsonPath('cleared', 2);

        $this->assertNotNull($a->fresh()->acknowledged_at);
        $this->assertNotNull($b->fresh()->acknowledged_at, 'the group is one piece of work');
        $this->assertSame($this->deskA->id, $b->fresh()->acknowledged_by);
        $this->assertTrue($b->fresh()->is_read);
    }

    public function test_popups_endpoint_returns_only_pending_popups_for_the_caller(): void
    {
        $this->actingAs($this->doctor);
        $this->saveConsultation();

        $this->actingAs($this->deskA)
            ->getJson(route('notifications.popups'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.priority', 'popup')
            ->assertJsonPath('items.0.event_key', 'consultation.saved');

        $this->actingAs($this->doctor)
            ->getJson(route('notifications.popups'))
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_a_payment_reaches_admin_but_a_backfilled_one_does_not(): void
    {
        $this->actingAs($this->deskA);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $this->patient->id,
            'invoice_date'   => now()->toDateString(),
            'status'         => 'draft',
        ]);

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $this->patient->id,
            'amount'       => 1500,
            'payment_mode' => 'upi',
            'payment_date' => now()->toDateString(),
        ]);

        $row = AppNotification::where('event_key', 'payment.received')->firstOrFail();
        $this->assertSame($this->admin->id, $row->user_id);
        $this->assertSame(AppNotification::PRIORITY_BELL, $row->priority);
        $this->assertStringContainsString('1,500', $row->title);
        $this->assertStringContainsString('N1 Patient', $row->title);

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $this->patient->id,
            'amount'       => 500,
            'payment_mode' => 'cash',
            'payment_date' => now()->subMonths(3)->toDateString(),
        ]);

        $this->assertSame(1, AppNotification::where('event_key', 'payment.received')->count(), 'historical rows are silent');
    }

    public function test_invoice_cancel_pops_up_for_admin(): void
    {
        $this->actingAs($this->deskA);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $this->patient->id,
            'invoice_date'   => now()->toDateString(),
            'status'         => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'RCT 26',
            'unit_price'  => 6000,
            'qty'         => 1,
            'net_amount'  => 6000,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => 6000,
        ]);
        $invoice->recalculate();

        // No visit item links this invoice to a doctor → invoice.created has no owner, no row.
        $this->assertSame(0, AppNotification::where('event_key', 'invoice.created')->count());

        $invoice->update(['status' => 'cancelled', 'cancelled_reason' => 'Duplicate bill']);

        $row = AppNotification::where('event_key', 'invoice.cancelled')->firstOrFail();
        $this->assertSame($this->admin->id, $row->user_id);
        $this->assertSame(AppNotification::PRIORITY_POPUP, $row->priority);
        $this->assertStringContainsString('Duplicate bill', $row->message);
    }

    public function test_the_doctors_handover_leads_the_popup_message(): void
    {
        $this->actingAs($this->doctor);

        $consultation = Consultation::create([
            'patient_id'        => $this->patient->id,
            'doctor_id'         => $this->doctor->id,
            'branch_id'         => 1,
            'consultation_date' => now()->toDateString(),
            'chief_complaint'   => 'Sensitivity 16',
            // Exactly what the form posts: strings, an unticked box absent, a blank note.
            'handover'          => ['collect_amount' => '400', 'xray' => '1', 'book_in_days' => '7', 'note' => '  '],
        ]);

        // assertEquals, not assertSame: MySQL re-orders JSON object keys on
        // storage (by length, then alphabetically) — the VALUES are the contract.
        $this->assertEquals(
            ['collect_amount' => 400, 'xray' => true, 'book_in_days' => 7],
            $consultation->fresh()->handover,
            'stored normalised — no blanks, no strings'
        );

        $row = AppNotification::where('event_key', 'consultation.saved')->firstOrFail();
        $this->assertStringStartsWith('Collect ₹400 · X-ray · Book in 7 days', $row->message);
        $this->assertStringContainsString('Sensitivity 16', $row->message);
    }

    public function test_an_untouched_handover_is_stored_as_null(): void
    {
        $this->actingAs($this->doctor);

        $consultation = Consultation::create([
            'patient_id'        => $this->patient->id,
            'doctor_id'         => $this->doctor->id,
            'branch_id'         => 1,
            'consultation_date' => now()->toDateString(),
            'handover'          => ['collect_amount' => '', 'book_in_days' => '', 'note' => ''],
        ]);

        $this->assertNull($consultation->fresh()->handover);
        $this->assertSame('', \App\Support\Handover::summary(null));
    }

    public function test_catalogue_defaults_have_exactly_six_popup_events(): void
    {
        $popupEvents = collect(NotificationCatalog::defaultRules())
            ->where('level', NotificationCatalog::LEVEL_POPUP)
            ->pluck('event_key')
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'consultation.saved', 'visit.saved', 'appointment.lab_missing',
            'invoice.cancelled', 'review.negative', 'system.failure',
        ], $popupEvents, 'popup is for a human who must act while the patient is at the desk — six events, by CEO ruling 9 Sep');
    }
}
