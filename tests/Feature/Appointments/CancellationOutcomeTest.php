<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentCancellation;
use App\Models\Task;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * W-9 — a cancellation must end somewhere.
 *
 * The rule these tests defend is not "a modal has three buttons". It is that
 * after this endpoint runs, the patient is either booked, in someone's task
 * list on a named day, or explicitly written off — and never simply gone.
 *
 * Each test sits on a way that rule could be quietly undone later:
 *  - drop the required outcome from the validator      → test 1
 *  - stop writing the task                             → test 2
 *  - "tidy up" by making the task a system task        → test 2
 *  - let a callback date ride along on "not returning" → test 3
 *  - decide the old mobile body should just be refused → test 4
 *  - rewrite cancel_reason into a code and break every
 *    existing screen that prints it                    → test 5
 */
class CancellationOutcomeTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    /** The rule itself: no decision, no cancellation. */
    public function test_cancellation_is_refused_when_no_outcome_is_given(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Patient called',
            'cancelled_party' => 'patient',
            'reason_code'     => 'cost',
            // outcome deliberately absent
        ])->assertStatus(422)->assertJsonValidationErrors('outcome');

        $this->assertSame('scheduled', $appt->fresh()->status, 'the appointment must survive a refused cancellation');
        $this->assertSame(0, AppointmentCancellation::count());
    }

    /** A callback is only real if it reaches the task list. */
    public function test_callback_outcome_creates_a_human_call_task_on_the_chosen_day(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);
        $when  = today()->addDays(14)->toDateString();

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Cost / affordability — will come after Diwali',
            'cancelled_party' => 'patient',
            'reason_code'     => 'cost',
            'reason_note'     => 'will come after Diwali',
            'outcome'         => 'callback',
            'callback_date'   => $when,
        ])->assertOk();

        $row = AppointmentCancellation::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertSame('callback', $row->outcome);
        $this->assertSame('cost', $row->reason_code);
        $this->assertSame($when, $row->callback_date->toDateString());
        $this->assertNotNull($row->task_id, 'a callback with no task is a callback nobody will make');

        $task = Task::findOrFail($row->task_id);
        $this->assertSame($when, $task->due_date->toDateString());
        $this->assertSame('call', $task->category);
        // A 'system' task disappears from reception's list once the
        // tasks.human_system_split flag is on. This one must never be one.
        $this->assertSame('human', $task->task_type);
        $this->assertSame('pending', $task->status);
        $this->assertSame($appt->patient_id, $task->patient_id);
        $this->assertSame($admin->id, $task->assigned_to);
    }

    /** "Not returning" is an answer, not a follow-up. */
    public function test_not_returning_records_the_decision_and_creates_no_task(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Treated elsewhere',
            'cancelled_party' => 'patient',
            'reason_code'     => 'treated_elsewhere',
            'outcome'         => 'not_returning',
            // A stale date left in the form must not survive the decision.
            'callback_date'   => today()->addDays(3)->toDateString(),
        ])->assertOk();

        $row = AppointmentCancellation::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertSame('not_returning', $row->outcome);
        $this->assertNull($row->callback_date, 'no date on a patient nobody intends to ring');
        $this->assertNull($row->task_id);
        $this->assertSame(0, Task::count());
        $this->assertSame('cancelled', $appt->fresh()->status);
    }

    /**
     * The mobile API (M-9) still posts the old two-field body. It must keep
     * working, and land as a countable "nobody decided" rather than nothing.
     */
    public function test_the_old_service_call_still_works_and_lands_as_unspecified(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        app(AppointmentService::class)->cancel($appt, 'Patient called', 'patient', $admin);

        $row = AppointmentCancellation::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertSame('unspecified', $row->outcome);
        $this->assertSame('other', $row->reason_code);
        $this->assertNull($row->task_id);
        $this->assertSame('cancelled', $appt->fresh()->status);
    }

    /**
     * Regression guard. cancel_reason is printed by screens and exports that
     * predate this row; it must stay a readable sentence, not become a code.
     */
    public function test_the_appointments_free_text_reason_is_unchanged(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Fear or pain — wants to come with her husband',
            'cancelled_party' => 'patient',
            'reason_code'     => 'fear_pain',
            'reason_note'     => 'wants to come with her husband',
            'outcome'         => 'callback',
            'callback_date'   => today()->addDays(2)->toDateString(),
        ])->assertOk();

        $fresh = $appt->fresh();
        $this->assertSame('Fear or pain — wants to come with her husband', $fresh->cancel_reason);
        $this->assertSame('patient', $fresh->cancelled_party);
        // previous_status still feeds the revert button.
        $this->assertSame('scheduled', $fresh->previous_status);
    }

    /** An appointment cancelled twice keeps both stories. */
    public function test_a_second_cancellation_adds_a_row_rather_than_overwriting(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Time clash',
            'cancelled_party' => 'patient',
            'reason_code'     => 'time_clash',
            'outcome'         => 'callback',
            'callback_date'   => today()->addDays(3)->toDateString(),
        ])->assertOk();

        $this->actingAs($admin)->patchJson(route('appointments.revert', $appt))->assertOk();

        $this->actingAs($admin)->patchJson(route('appointments.cancel', $appt), [
            'cancel_reason'   => 'Treated elsewhere',
            'cancelled_party' => 'patient',
            'reason_code'     => 'treated_elsewhere',
            'outcome'         => 'not_returning',
        ])->assertOk();

        $this->assertSame(2, AppointmentCancellation::where('appointment_id', $appt->id)->count());
        $this->assertSame('not_returning', $appt->fresh()->latestCancellation->outcome);
    }
}
