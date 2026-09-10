<?php

namespace Tests\Feature\Api;

use App\Models\AppointmentCancellation;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * M-3 (Android V1.1) — the phone cancels an appointment under the SAME rule
 * as the web modal (W-9): a fixed reason and exactly one outcome.
 *
 * Before 8 Sep 2026 the API still accepted the old two-field body, so every
 * cancellation made from the phone landed as outcome 'unspecified' and no
 * callback task was ever created. These tests pin the parity:
 *
 *  - the old body is refused with a 422 that NAMES the missing fields
 *  - a callback from the phone lands in reception's task list, same as web
 *  - 'rebooked' cannot be sent here (the phone must call reschedule instead)
 *  - the options endpoint serves the same vocabulary the web modal renders
 */
class AppointmentCancelParityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    public function test_the_old_two_field_body_is_refused_and_names_what_is_missing(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/appointments/{$appt->id}/cancel", [
            'cancel_reason'   => 'Patient called',
            'cancelled_party' => 'patient',
        ])->assertStatus(422)->assertJsonValidationErrors(['reason_code', 'outcome']);

        $this->assertSame('scheduled', $appt->fresh()->status);
        $this->assertSame(0, AppointmentCancellation::count());
    }

    public function test_a_callback_from_the_phone_creates_the_same_task_the_web_does(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);
        $when  = today()->addDays(2)->toDateString();

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/appointments/{$appt->id}/cancel", [
            'cancel_reason'   => 'Fear or pain',
            'cancelled_party' => 'patient',
            'reason_code'     => 'fear_pain',
            'outcome'         => 'callback',
            'callback_date'   => $when,
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $row = AppointmentCancellation::where('appointment_id', $appt->id)->firstOrFail();
        $this->assertSame('callback', $row->outcome);
        $this->assertSame('fear_pain', $row->reason_code);
        $this->assertNotNull($row->task_id);

        $task = Task::findOrFail($row->task_id);
        $this->assertSame($when, $task->due_date->toDateString());
        $this->assertSame('call', $task->category);
        $this->assertSame('human', $task->task_type);
        $this->assertSame($appt->patient_id, $task->patient_id);
    }

    public function test_rebooked_is_not_an_outcome_the_phone_may_send(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/appointments/{$appt->id}/cancel", [
            'cancel_reason'   => 'Moved',
            'cancelled_party' => 'patient',
            'reason_code'     => 'time_clash',
            'outcome'         => 'rebooked',
        ])->assertStatus(422)->assertJsonValidationErrors('outcome');

        $this->assertSame('scheduled', $appt->fresh()->status, 'a rebooking is a reschedule, never a cancellation');
    }

    public function test_cancel_options_serve_the_web_modal_vocabulary(): void
    {
        Sanctum::actingAs($this->adminUser(), ['*']);

        $data = $this->getJson('/api/v1/appointments/cancel-options')->assertOk()->json('data');

        $codes = array_column($data['reasons'], 'code');
        $this->assertSame(\App\Enums\CancellationReason::values(), $codes);
        $this->assertSame(1, collect($data['reasons'])->firstWhere('code', 'clinic_side')['default_callback_days']);

        $outcomes = array_column($data['outcomes'], 'value');
        $this->assertSame(AppointmentCancellation::CHOOSABLE_OUTCOMES, $outcomes);
        $this->assertNotContains('rebooked', $outcomes);
    }
}
