<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\Consultation;
use App\Models\DeviceToken;
use App\Models\LabCase;
use App\Models\NotificationRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\LabNotificationService;
use App\Services\Notifications\FcmSender;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * N-5 — the phone copy, and the lab events finally obeying the matrix.
 *
 *  - a popup with push queues exactly one job; a bell never does;
 *  - a popup answered before the worker ran does not buzz anyone's phone;
 *  - quiet hours drop the push but keep the bell;
 *  - the device-token endpoints move a phone between users on login/logout;
 *  - lab transitions reach the roles the MATRIX names, not the legacy role
 *    string, and both receptionists hear about final work — the old code told
 *    exactly one of them;
 *  - an overdue case alerts once per DAY, not once ever and not once per run.
 */
class PushAndLabNotificationTest extends TestCase
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

        $this->doctor = $this->user(Role::DOCTOR, 'doctor');
        $this->deskA  = $this->user(Role::FRONT_DESK, 'front_desk');
        $this->deskB  = $this->user(Role::FRONT_DESK, 'front_desk');
        $this->admin  = $this->user(Role::ADMIN, 'admin');

        $this->patient = Patient::create([
            'name' => 'N5 Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
    }

    private function user(string $slug, string $legacy): User
    {
        return User::factory()->create([
            'role' => $legacy, 'role_id' => Role::where('slug', $slug)->firstOrFail()->id,
            'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function consultation(): Consultation
    {
        return Consultation::create([
            'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'branch_id' => 1,
            'consultation_date' => now()->toDateString(), 'chief_complaint' => 'Pain 26',
        ]);
    }

    // ── push queueing ────────────────────────────────────────────────────────

    public function test_a_popup_with_push_queues_one_job_per_recipient_and_a_bell_queues_none(): void
    {
        Queue::fake();
        $this->actingAs($this->doctor);
        $this->consultation();

        // consultation.saved ships popup+push to front desk → two receptionists.
        Queue::assertPushed(SendPushNotification::class, 2);

        Queue::fake();
        NotificationRule::updateOrCreate(
            ['event_key' => 'payment.received', 'role' => Role::ADMIN, 'branch_id' => null],
            ['level' => 'bell', 'push' => true]
        );
        // invoice_payments.invoice_id is NOT NULL — a payment always settles an
        // invoice. Mint one rather than testing a row the schema forbids.
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => \App\Models\Invoice::nextNumber(),
            'patient_id'     => $this->patient->id,
            'invoice_date'   => now()->toDateString(),
            'status'         => 'draft',
        ]);
        \App\Models\InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'patient_id' => $this->patient->id, 'amount' => 100,
            'payment_mode' => 'cash', 'payment_date' => now()->toDateString(),
        ]);

        Queue::assertNotPushed(SendPushNotification::class);
        $this->assertFalse(
            AppNotification::where('event_key', 'payment.received')->firstOrFail()->push,
            'a bell-level rule never carries push, whatever the matrix ticked'
        );
    }

    public function test_a_popup_answered_before_the_worker_ran_never_buzzes_a_phone(): void
    {
        $this->actingAs($this->doctor);
        $this->consultation();

        $row = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        DeviceToken::register($this->deskA->id, 'tok-desk-a');

        // deskB presses Done first — the whole group is answered.
        $row->acknowledgeGroup($this->deskB->id);

        (new SendPushNotification($row->id))->handle(app(FcmSender::class));

        $this->assertNotNull($row->fresh()->push_sent_at, 'marked handled, so the sweep will not retry it');
    }

    public function test_the_sender_is_a_safe_no_op_while_fcm_is_disabled(): void
    {
        config(['fcm.enabled' => false]);
        $this->actingAs($this->doctor);
        $this->consultation();

        $row = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        DeviceToken::register($this->deskA->id, 'tok-1');

        $this->assertSame(0, app(FcmSender::class)->send($row));
        $this->assertNotNull($row->fresh()->push_sent_at);
        $this->assertNull($row->fresh()->acknowledged_at, 'the desk popup is untouched — only the phone copy was skipped');
    }

    public function test_quiet_hours_drop_the_push_but_keep_the_notification(): void
    {
        config(['fcm.enabled' => true, 'fcm.project_id' => 'test', 'fcm.quiet_hours.enabled' => true]);
        $this->travelTo(now()->setTime(23, 30));

        $this->actingAs($this->doctor);
        $this->consultation();

        $row = AppNotification::where('user_id', $this->deskA->id)->firstOrFail();
        DeviceToken::register($this->deskA->id, 'tok-1');

        $this->assertSame(0, app(FcmSender::class)->send($row), 'no push at 23:30');
        $this->assertNotNull($row->fresh()->push_sent_at);
        $this->assertSame(1, AppNotification::pendingPopups($this->deskA->id)->count(), 'the popup still waits for them');
    }

    // ── device tokens ────────────────────────────────────────────────────────

    public function test_a_phone_moves_to_whoever_logged_in_last_and_logout_retires_it(): void
    {
        $this->actingAs($this->deskA, 'sanctum')
            ->postJson('/api/v1/devices/token', ['token' => 'phone-1', 'device_name' => 'Redmi'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['token' => 'phone-1', 'user_id' => $this->deskA->id]);

        // Same handset, different staff member signs in.
        $this->actingAs($this->deskB, 'sanctum')
            ->postJson('/api/v1/devices/token', ['token' => 'phone-1'])
            ->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'phone-1')->count(), 'one row per handset, never two');
        $this->assertDatabaseHas('device_tokens', ['token' => 'phone-1', 'user_id' => $this->deskB->id]);

        $this->actingAs($this->deskB, 'sanctum')
            ->deleteJson('/api/v1/devices/token', ['token' => 'phone-1'])
            ->assertOk();

        $this->assertSame(0, DeviceToken::live()->where('token', 'phone-1')->count());
    }

    // ── lab, now obeying the matrix ──────────────────────────────────────────

    private function labCase(): LabCase
    {
        return LabCase::create([
            'case_number' => 'LC-' . random_int(1000, 9999),
            'branch_id'   => 1,
            'patient_id'  => $this->patient->id,
            'doctor_id'   => $this->doctor->id,
            'status'      => 'draft',
            'work_category' => 'Crown',
        ]);
    }

    public function test_final_work_reaches_BOTH_receptionists_not_just_the_lowest_id(): void
    {
        $case = $this->labCase();
        AppNotification::query()->delete(); // ignore the draft-created alert

        app(LabNotificationService::class)->onTransition($case, 'impression_sent', 'final_received', $this->admin);

        $rows = AppNotification::where('event_key', 'lab.final_received')->get();
        $this->assertEqualsCanonicalizing(
            [$this->deskA->id, $this->deskB->id],
            $rows->pluck('user_id')->all(),
            'the retired resolveFrontDesk() told exactly one receptionist'
        );
        $this->assertStringContainsString('Schedule', $rows->first()->action_label);
    }

    public function test_a_trial_reaches_the_case_doctor_and_not_the_front_desk(): void
    {
        $case = $this->labCase();
        AppNotification::query()->delete();

        app(LabNotificationService::class)->onTransition($case, 'order_placed', 'trial_received', $this->admin);

        $rows = AppNotification::where('event_key', 'lab.trial_received')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->doctor->id, $rows->first()->user_id);
    }

    public function test_an_overdue_case_alerts_once_a_day_not_once_ever_and_not_once_per_run(): void
    {
        $case = $this->labCase();
        $case->forceFill(['status' => 'order_placed', 'expected_return_date' => now()->subDays(3)])->save();
        AppNotification::query()->delete();

        $service = app(LabNotificationService::class);

        $service->fireOverdueAlerts();
        $service->fireOverdueAlerts();   // the same day, twice
        $this->assertSame(
            1,
            AppNotification::where('event_key', 'lab.overdue')->where('user_id', $this->doctor->id)->count(),
            'two runs on one day is still one alert'
        );

        $this->travelTo(now()->addDay());
        $service->fireOverdueAlerts();
        $this->assertSame(
            2,
            AppNotification::where('event_key', 'lab.overdue')->where('user_id', $this->doctor->id)->count(),
            'a new day is genuinely new news'
        );
    }
}
