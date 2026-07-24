<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Slice 3 — the corrected permission matrix (web + API), verified per persona.
 *
 * appointments module grants (from RolePermissionSeeder):
 *   admin      [view, edit, delete]
 *   front_desk [view, edit        ]
 *   doctor     [view, edit        ]
 *   assistant  [view              ]
 * plus three synthetic single-flag personas (view-only / edit-only / delete-only).
 *
 * Reads → module:appointments (view). Writes → ,edit. Delete → ,delete.
 * Independent gating: an edit-only user can write without holding view, etc.
 */
class AppointmentPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;
    use InteractsWithPermissions;

    // ── helpers: return the HTTP status for each surface/action ───────────

    private function webViewStatus(User $user): int
    {
        return $this->actingAs($user)->get(route('appointments.index'))->getStatusCode();
    }

    private function webWriteStatus(User $user): int
    {
        $appt = $this->makeAppointment(['status' => 'scheduled']);
        return $this->actingAs($user)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'checkin'])
            ->getStatusCode();
    }

    private function webDeleteStatus(User $user): int
    {
        $appt = $this->makeAppointment();
        return $this->actingAs($user)
            ->deleteJson(route('appointments.destroy', $appt))
            ->getStatusCode();
    }

    private function apiWriteStatus(User $user): int
    {
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();
        Sanctum::actingAs($user, ['*']);

        return $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '14:00',
            'type'             => 'consultation',
        ])->getStatusCode();
    }

    private function apiDeleteStatus(User $user): int
    {
        $appt = $this->makeAppointment();
        Sanctum::actingAs($user, ['*']);
        return $this->deleteJson("/api/v1/appointments/{$appt->id}")->getStatusCode();
    }

    // ── Personas ──────────────────────────────────────────────────────────

    public function test_admin_can_do_everything(): void
    {
        $u = $this->userForSystemRole('admin');
        $this->assertSame(200, $this->webViewStatus($u));
        $this->assertSame(200, $this->webWriteStatus($u));
        $this->assertSame(200, $this->webDeleteStatus($u));
        $this->assertSame(201, $this->apiWriteStatus($u));
        $this->assertSame(200, $this->apiDeleteStatus($this->userForSystemRole('admin')));
    }

    public function test_front_desk_can_view_and_write_but_not_delete(): void
    {
        $this->assertSame(200, $this->webViewStatus($this->userForSystemRole('front_desk')));
        $this->assertSame(200, $this->webWriteStatus($this->userForSystemRole('front_desk')));
        $this->assertSame(403, $this->webDeleteStatus($this->userForSystemRole('front_desk')));
        $this->assertSame(201, $this->apiWriteStatus($this->userForSystemRole('front_desk')));
        $this->assertSame(403, $this->apiDeleteStatus($this->userForSystemRole('front_desk')));
    }

    public function test_doctor_can_view_and_write_but_not_delete(): void
    {
        // The C2 fix: doctor now writes on the API (was 403 before Slice 3).
        $this->assertSame(200, $this->webViewStatus($this->userForSystemRole('doctor')));
        $this->assertSame(200, $this->webWriteStatus($this->userForSystemRole('doctor')));
        $this->assertSame(403, $this->webDeleteStatus($this->userForSystemRole('doctor')));
        $this->assertSame(201, $this->apiWriteStatus($this->userForSystemRole('doctor')));
        $this->assertSame(403, $this->apiDeleteStatus($this->userForSystemRole('doctor')));
    }

    public function test_assistant_is_view_only(): void
    {
        $this->assertSame(200, $this->webViewStatus($this->userForSystemRole('assistant')));
        $this->assertSame(403, $this->webWriteStatus($this->userForSystemRole('assistant')));
        $this->assertSame(403, $this->webDeleteStatus($this->userForSystemRole('assistant')));
        $this->assertSame(403, $this->apiWriteStatus($this->userForSystemRole('assistant')));
        $this->assertSame(403, $this->apiDeleteStatus($this->userForSystemRole('assistant')));
    }

    public function test_view_only_persona(): void
    {
        $this->assertSame(200, $this->webViewStatus($this->userForAppointmentPermission(true, false, false)));
        $this->assertSame(403, $this->webWriteStatus($this->userForAppointmentPermission(true, false, false)));
        $this->assertSame(403, $this->apiWriteStatus($this->userForAppointmentPermission(true, false, false)));
    }

    public function test_edit_only_persona_can_write_without_view(): void
    {
        // Independent gating: no view grant, but edit lets writes through.
        $this->assertSame(302, $this->webViewStatus($this->userForAppointmentPermission(false, true, false)));
        $this->assertSame(200, $this->webWriteStatus($this->userForAppointmentPermission(false, true, false)));
        $this->assertSame(201, $this->apiWriteStatus($this->userForAppointmentPermission(false, true, false)));
    }

    public function test_delete_only_persona_can_delete_without_view_or_edit(): void
    {
        $this->assertSame(302, $this->webViewStatus($this->userForAppointmentPermission(false, false, true)));
        $this->assertSame(403, $this->webWriteStatus($this->userForAppointmentPermission(false, false, true)));
        $this->assertSame(200, $this->webDeleteStatus($this->userForAppointmentPermission(false, false, true)));
        $this->assertSame(200, $this->apiDeleteStatus($this->userForAppointmentPermission(false, false, true)));
    }
}
