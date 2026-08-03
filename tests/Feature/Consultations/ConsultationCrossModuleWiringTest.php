<?php

namespace Tests\Feature\Consultations;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 11 — cross-module wiring.
 *
 * Pins ConsultationClinicalWiringObserver:
 *  1. patients.last_visit_date advances on every consultation save (the
 *     recall engine's key column — previously writer-less, PRE audit P0),
 *     monotonically: a backdated entry never rewinds a fresher date.
 *  2. A linked appointment is closed (status -> done) — but never from a
 *     terminal or manually closed-out status.
 */
class ConsultationCrossModuleWiringTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function clinician(): User
    {
        return $this->userWithModulePerm('patients', true, true, false, 'Wiring Clinician ' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Wiring Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    public function test_consultation_save_advances_last_visit_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();
        $this->assertNull($patient->last_visit_date);

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'new',
                'chief_complaint'   => 'Recall wiring test',
                'consultation_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(now()->toDateString(), $patient->fresh()->last_visit_date?->toDateString());
    }

    public function test_backdated_entry_never_rewinds_last_visit_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();
        $patient->newQuery()->whereKey($patient->id)
            ->update(['last_visit_date' => now()->subDay()->toDateString()]);

        // A missed entry from last month gets recorded today…
        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'new',
                'chief_complaint'   => 'Missed entry from last month',
                'consultation_date' => now()->subMonth()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        // …and must NOT pull the fresher visit date backwards.
        $this->assertSame(now()->subDay()->toDateString(), $patient->fresh()->last_visit_date?->toDateString());
    }

    public function test_typed_workflows_also_advance_last_visit_date(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        // Minor Visit is the lightest write path — if the observer covers it,
        // it covers every path (model-event choke point).
        $this->actingAs($user)
            ->post(route('patients.consultations.minor-visit.store', $patient), [
                'doctor_id'                   => $user->id,
                'related_to_clinic_treatment' => '0',
                'procedure_performed'         => 'Denture adjustment',
                'consultation_date'           => now()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(now()->toDateString(), $patient->fresh()->last_visit_date?->toDateString());
    }

    public function test_linked_appointment_is_marked_done(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $appointment = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $user->id,
            'created_by'       => $user->id,
            'branch_id'        => 1,
            'type'             => 'consultation',
            'appointment_date' => now()->subDay()->toDateString(),
            'appointment_time' => '10:00',
            'status'           => AppointmentStatus::Scheduled->value,
        ]);

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'new',
                'chief_complaint'   => 'Linked appointment test',
                'consultation_date' => now()->subDay()->format('Y-m-d'),
                'appointment_id'    => $appointment->id,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Done->value, $fresh->status);
        $this->assertSame(AppointmentStatus::Scheduled->value, $fresh->previous_status);
    }

    public function test_terminal_appointment_status_is_never_overridden(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $appointment = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $user->id,
            'created_by'       => $user->id,
            'branch_id'        => 1,
            'type'             => 'consultation',
            'appointment_date' => now()->subDay()->toDateString(),
            'appointment_time' => '10:00',
            'status'           => AppointmentStatus::Cancelled->value,
        ]);

        $this->actingAs($user)
            ->post(route('patients.consultations.store', $patient), [
                'doctor_id'         => $user->id,
                'consultation_type' => 'new',
                'chief_complaint'   => 'Cancelled appointment stays cancelled',
                'consultation_date' => now()->subDay()->format('Y-m-d'),
                'appointment_id'    => $appointment->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Cancelled->value, $appointment->fresh()->status);
    }
}
