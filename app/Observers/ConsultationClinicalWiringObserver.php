<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Scopes\BranchScope;
use Carbon\Carbon;

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

    /**
     * Row 2.8 — a deleted consultation must give its date back.
     *
     * advanceLastVisitDate() is deliberately monotonic: a backdated correction
     * must never drag a fresher visit date backwards. That rule is right for a
     * write and wrong for a delete. With created()/updated() only, deleting the
     * visit that set last_visit_date left the column pointing at a consultation
     * that no longer exists, and since the recall engine keys entirely off this
     * column, that patient's recall stayed suppressed until their next visit —
     * silently, with nothing on any screen to show it.
     *
     * So the delete path RECOMPUTES from what is left rather than advancing,
     * and is allowed to move the date backwards, or to null it when the patient
     * has no consultations at all.
     */
    public function deleted(Consultation $consultation): void
    {
        $this->recomputeLastVisitDate($consultation);
    }

    /**
     * Restoring can only ever add a visit back, so the monotonic forward rule
     * is the correct one here — recomputing would be equivalent but would also
     * quietly rewrite the column on every restore.
     */
    public function restored(Consultation $consultation): void
    {
        $this->advanceLastVisitDate($consultation);
    }

    private function advanceLastVisitDate(Consultation $consultation): void
    {
        $patient = $consultation->patient;
        if (! $patient || ! $consultation->consultation_date) {
            return;
        }

        $visitDate = $consultation->consultation_date->toDateString();
        $current   = $this->storedLastVisitDate($patient);

        if ($current === null || $current < $visitDate) {
            // Quiet column write: no Patient model events (this is bookkeeping,
            // not a patient edit — keeps Patients-module observers/audit noise out).
            $patient->newQuery()->whereKey($patient->id)
                ->update(['last_visit_date' => $visitDate]);
        }
    }

    /**
     * Row 2.8 — read the column from the DATABASE, never from the loaded
     * Patient model.
     *
     * These writes are quiet on purpose: they update the column behind the
     * model's back, so the in-memory Patient keeps whatever last_visit_date it
     * was loaded with. $consultation->patient caches that stale model for the
     * rest of the request, and the two handlers run against the same instance:
     * delete then restore in one request read 'the date before the delete',
     * decided nothing had changed, and left the column at the older visit.
     * Caught by test_restoring_a_consultation_puts_its_date_back.
     */
    private function storedLastVisitDate($patient): ?string
    {
        $value = $patient->newQuery()->whereKey($patient->id)->value('last_visit_date');

        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    /**
     * The truth for last_visit_date is "the latest consultation this patient
     * still has". Soft-deleted rows are excluded by the model's SoftDeletes
     * scope; BranchScope is dropped on purpose, because a patient's visit
     * history is not a per-branch fact and a branch-scoped recompute would
     * blank the date for a visit recorded at another chair.
     *
     * Same quiet column write as advanceLastVisitDate(): bookkeeping, not a
     * patient edit.
     */
    private function recomputeLastVisitDate(Consultation $consultation): void
    {
        $patient = $consultation->patient;
        if (! $patient) {
            return;
        }

        $latest = Consultation::withoutGlobalScope(BranchScope::class)
            ->where('patient_id', $patient->id)
            ->max('consultation_date');

        $latest = $latest ? Carbon::parse($latest)->toDateString() : null;

        // Same reason as advanceLastVisitDate(): compare against the stored
        // column, not the loaded model, which these quiet writes leave stale.
        if ($this->storedLastVisitDate($patient) === $latest) {
            return;
        }

        $patient->newQuery()->whereKey($patient->id)
            ->update(['last_visit_date' => $latest]);
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
