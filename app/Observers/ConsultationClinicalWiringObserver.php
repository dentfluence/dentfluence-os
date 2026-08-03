<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;

/**
 * ConsultationClinicalWiringObserver — Consultations Slice 11 (2026-08-03).
 *
 * The two cross-module facts a saved consultation establishes, wired at the
 * model-event choke point so all ten write paths (5 web + 5 API) are covered
 * without touching controller code (same pattern as ConsultationActivityObserver):
 *
 * 1. patients.last_visit_date — the recall engine keys entirely off this
 *    column, but NO code path ever wrote it (PRE audit P0: recall ran on a
 *    dead column, so recalls never reset after a real visit). A consultation
 *    IS a visit: advance the column, monotonically — a backdated entry must
 *    never pull a fresher visit date backwards.
 *
 * 2. appointments.status — a consultation explicitly linked to an appointment
 *    (appointment_id, set by the backdate/link picker) proves the patient
 *    actually arrived. Close the loop by marking the appointment 'done', but
 *    ONLY from an in-progress status: terminal states (cancelled / no_show)
 *    and manual front-desk closeouts (checkout / done) are never overridden.
 *
 * NOT wired here (reported as dependencies of their own modules, per the
 * "stop and report" rule): treatment_visits.consultation_id population
 * (Treatment Visits, C-5 structural) and a RulesEngine consumer for
 * 'consultation.completed' (PRE/Communication).
 */
class ConsultationClinicalWiringObserver
{
    public function created(Consultation $consultation): void
    {
        $this->advanceLastVisitDate($consultation);
        $this->closeLinkedAppointment($consultation);
    }

    public function updated(Consultation $consultation): void
    {
        // Only the clinical date matters here; monotonic advance means a
        // corrected date can push last_visit_date forward but never rewind it.
        if ($consultation->wasChanged('consultation_date')) {
            $this->advanceLastVisitDate($consultation);
        }
    }

    private function advanceLastVisitDate(Consultation $consultation): void
    {
        $patient = $consultation->patient;
        if (! $patient || ! $consultation->consultation_date) {
            return;
        }

        $visitDate = $consultation->consultation_date->toDateString();

        if ($patient->last_visit_date === null
            || $patient->last_visit_date->toDateString() < $visitDate) {
            // Quiet column write: no Patient model events (this is bookkeeping,
            // not a patient edit — keeps Patients-module observers/audit noise out).
            $patient->newQuery()->whereKey($patient->id)
                ->update(['last_visit_date' => $visitDate]);
        }
    }

    private function closeLinkedAppointment(Consultation $consultation): void
    {
        if (! $consultation->appointment_id) {
            return;
        }

        $appointment = Appointment::find($consultation->appointment_id);
        if (! $appointment || ! in_array($appointment->status, AppointmentStatus::inProgressValues(), true)) {
            return;
        }

        // Quiet column write (query, not model save) for the same reason as
        // last_visit_date: bookkeeping, not a user-driven appointment edit.
        $appointment->newQuery()->whereKey($appointment->id)->update([
            'previous_status' => $appointment->status,
            'status'          => AppointmentStatus::Done->value,
        ]);
    }
}
