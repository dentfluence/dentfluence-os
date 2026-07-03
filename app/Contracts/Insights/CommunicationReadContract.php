<?php

namespace App\Contracts\Insights;

/**
 * CommunicationReadContract — Phase 6 · Slice 4 (read-contracts).
 *
 * The thin seam between the Insights signal calculators and the
 * `communication_queue` table. See AppointmentReadContract for the rationale.
 */
interface CommunicationReadContract
{
    /**
     * Every outbound communication with a recorded outcome, and how many of
     * those were positive — across ALL sources (used by Health's
     * responsiveness factor).
     *
     * @param  array<int,int>  $patientIds
     * @return array{total:int, positive:int}
     */
    public function responsivenessCounts(array $patientIds): array;

    /**
     * Recall-sourced (`source_engine = 'recall'`) communications, and how
     * many resulted in a positive outcome (used by Risk's unanswered-outreach
     * factor).
     *
     * @param  array<int,int>  $patientIds
     * @return array{total:int, positive:int}
     */
    public function recallOutcomeCounts(array $patientIds): array;
}
