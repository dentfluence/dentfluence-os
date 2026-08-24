<?php

namespace App\Observers;

use App\Models\TreatmentVisit;

/**
 * TreatmentVisitClinicalWiringObserver — PRE Sprint A / register G-11 (2026-08-24).
 *
 * A treatment visit IS a visit. The recall engine's no-visit trigger keys
 * entirely off patients.last_visit_date, but until now only Consultations
 * advanced it (ConsultationClinicalWiringObserver, Slice 11) — so a patient
 * mid-treatment with weekly visits still looked "not seen for 6 months" and
 * was recalled while sitting in the chair (register G-11 / recall R-9).
 *
 * Same choke-point pattern and same rules as the consultation observer:
 *  - model events cover every write path (web + API + services) with no
 *    controller changes;
 *  - monotonic advance — a backdated visit must never pull a fresher
 *    last_visit_date backwards;
 *  - quiet column write (query, not model save): bookkeeping, not a patient
 *    edit — keeps Patients-module observers/audit noise out.
 */
class TreatmentVisitClinicalWiringObserver
{
    public function created(TreatmentVisit $visit): void
    {
        $this->advanceLastVisitDate($visit);
    }

    public function updated(TreatmentVisit $visit): void
    {
        // Only the clinical date matters; a corrected date can push
        // last_visit_date forward but never rewind it.
        if ($visit->wasChanged('visit_date')) {
            $this->advanceLastVisitDate($visit);
        }
    }

    private function advanceLastVisitDate(TreatmentVisit $visit): void
    {
        $patient = $visit->patient;
        if (! $patient || ! $visit->visit_date) {
            return;
        }

        $visitDate = $visit->visit_date->toDateString();

        // Never advance past today — a scheduled future visit is a plan,
        // not a fact, and must not suppress recalls before it happens.
        if ($visitDate > now()->toDateString()) {
            return;
        }

        if ($patient->last_visit_date === null
            || $patient->last_visit_date->toDateString() < $visitDate) {
            $patient->newQuery()->whereKey($patient->id)
                ->update(['last_visit_date' => $visitDate]);
        }
    }
}
