<?php

namespace App\Services\Insights\Reads;

use App\Contracts\Insights\CommunicationReadContract;
use Illuminate\Support\Facades\DB;

/**
 * EloquentCommunicationReadContract — Phase 6 · Slice 4.
 *
 * Queries moved verbatim out of HealthSignalCalculator and
 * RiskSignalCalculator (Slice 1) — pure extraction, no behaviour change.
 */
class EloquentCommunicationReadContract implements CommunicationReadContract
{
    public function responsivenessCounts(array $patientIds): array
    {
        if ($patientIds === []) {
            return ['total' => 0, 'positive' => 0];
        }

        $total = DB::table('communication_queue')
            ->whereIn('patient_id', $patientIds)
            ->whereNotNull('outcome')
            ->count();

        $positive = DB::table('communication_queue')
            ->whereIn('patient_id', $patientIds)
            ->whereIn('outcome', ['appointment_booked', 'completed', 'success', 'interested'])
            ->count();

        return ['total' => $total, 'positive' => $positive];
    }

    public function recallOutcomeCounts(array $patientIds): array
    {
        if ($patientIds === []) {
            return ['total' => 0, 'positive' => 0];
        }

        $total = DB::table('communication_queue')
            ->whereIn('patient_id', $patientIds)
            ->where('source_engine', 'recall')
            ->count();

        $positive = DB::table('communication_queue')
            ->whereIn('patient_id', $patientIds)
            ->where('source_engine', 'recall')
            ->whereIn('outcome', ['appointment_booked', 'completed', 'success'])
            ->count();

        return ['total' => $total, 'positive' => $positive];
    }
}
