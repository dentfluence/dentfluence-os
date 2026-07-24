<?php

namespace Tests\Feature\Appointments\Concerns;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

/**
 * Shared setup for the Appointments characterization suite (Slice 2).
 *
 * These helpers deliberately mirror the pattern already used by
 * AppointmentStatusFlowTest (direct Model::create + branch_id 1, which the
 * branches migration seeds). No production factories exist for Patient /
 * Appointment, so we do NOT introduce any here — that would be a change
 * outside the test-only mandate.
 */
trait InteractsWithAppointments
{
    protected int $branchId = 1;

    /** Legacy-admin (role string, no role_id) — passes canAccess() everywhere. */
    protected function adminUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'admin',
            'branch_id' => $this->branchId,
            'is_active' => true,
        ], $overrides));
    }

    /** A user with an arbitrary legacy role string. */
    protected function staffUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => $role,
            'branch_id' => $this->branchId,
            'is_active' => true,
        ], $overrides));
    }

    /** An active doctor in the branch (usable as appointment doctor_id). */
    protected function doctorUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'doctor',
            'branch_id' => $this->branchId,
            'is_active' => true,
        ], $overrides));
    }

    protected function newPatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name'      => 'Char Patient',
            'phone'     => '9' . fake()->numerify('#########'),
            'branch_id' => $this->branchId,
        ], $overrides));
    }

    /**
     * Create an appointment directly (no controller, no activity log) so tests
     * can characterize a specific endpoint in isolation.
     */
    protected function makeAppointment(array $overrides = []): Appointment
    {
        $patient = $overrides['patient_id'] ?? $this->newPatient()->id;
        $doctor  = $overrides['doctor_id']  ?? $this->doctorUser()->id;

        return Appointment::create(array_merge([
            'patient_id'       => $patient,
            'doctor_id'        => $doctor,
            'branch_id'        => $this->branchId,
            'created_by'       => $doctor,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'duration_minutes' => 30,
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ], $overrides));
    }
}
