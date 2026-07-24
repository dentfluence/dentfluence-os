<?php

namespace Tests\Feature\Appointments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: PERMISSIONS (current behaviour, bugs included).
 *
 * This captures the authorization behaviour EXACTLY as it is today, including
 * the two documented gaps that Slice 3 will change intentionally:
 *   - API appointment writes are gated on the role list admin,front_desk, so a
 *     DOCTOR is blocked (403) on the API even though they can write on web.
 *   - Web writes are gated only by the module's `view` permission (not edit /
 *     delete). That specific "view-only role can still write" bug requires
 *     seeding a view-only role from the RoleModulePermission table and is
 *     therefore captured in Slice 3's permission suite (where it is the change
 *     under test), not here. See "remaining behaviour" in the Slice 2 report.
 */
class AppointmentPermissionCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;
    use InteractsWithPermissions;

    // ── API ───────────────────────────────────────────────────────────────

    public function test_api_admin_can_book(): void
    {
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();
        Sanctum::actingAs($this->adminUser(), ['*']);

        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '14:00',
            'type'             => 'consultation',
        ])->assertStatus(201);
    }

    public function test_api_front_desk_can_book(): void
    {
        // CHANGED in Slice 3: the API now authorizes via the appointments
        // module permission (role_id / RoleModulePermission), not a role-name
        // list — so front_desk must hold the edit permission (it does: [1,1,0]).
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();
        Sanctum::actingAs($this->userForSystemRole('front_desk'), ['*']);

        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '14:30',
            'type'             => 'consultation',
        ])->assertStatus(201);
    }

    public function test_api_doctor_with_edit_permission_can_write(): void
    {
        // CHANGED in Slice 3 (this is the C2 fix). Previously the API route
        // list was admin,front_desk only, so a Doctor was blocked (403). The
        // Doctor role holds appointments edit [1,1,0], so under module-based
        // gating they can now manage the schedule on the API too.
        $appt = $this->makeAppointment();
        Sanctum::actingAs($this->userForSystemRole('doctor'), ['*']);

        $this->patchJson("/api/v1/appointments/{$appt->id}/status", ['status' => 'checkin'])
            ->assertOk()
            ->assertJsonPath('data.status', 'checkin');
    }

    public function test_api_unauthenticated_is_rejected(): void
    {
        $this->postJson('/api/v1/appointments', [])->assertStatus(401);
    }

    // ── WEB ───────────────────────────────────────────────────────────────

    public function test_web_admin_can_open_the_calendar(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('appointments.index'))
            ->assertOk();
    }

    public function test_web_user_without_module_permission_is_denied_view(): void
    {
        // A non-admin with no role_id resolves to no RoleModulePermission →
        // canAccess() is false → the module middleware denies (302 redirect
        // for a browser request, per RespondsWithAccessDenied).
        $this->actingAs($this->staffUser('front_desk'))
            ->get(route('appointments.index'))
            ->assertStatus(302);
    }

    public function test_web_user_without_module_permission_is_denied_write_json(): void
    {
        $appt = $this->makeAppointment();

        $this->actingAs($this->staffUser('front_desk'))
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'checkin'])
            ->assertStatus(403);
    }
}
