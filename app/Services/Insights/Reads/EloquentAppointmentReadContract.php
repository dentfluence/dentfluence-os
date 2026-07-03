<?php

namespace App\Services\Insights\Reads;

use App\Contracts\Insights\AppointmentReadContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EloquentAppointmentReadContract — Phase 6 · Slice 4.
 *
 * The queries here are moved VERBATIM out of HealthSignalCalculator and
 * RiskSignalCalculator (Slice 1) — same tables, same conditions, same
 * meaning. This is a pure extraction: no calculator's computed values
 * change as a result.
 */
class EloquentAppointmentReadContract implements AppointmentReadContract
{
    public function lastCompletedVisitDate(array $patientIds): ?string
    {
        if ($patientIds === []) {
            return null;
        }

        return DB::table('appointments')
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'done')
            ->orderByDesc('appointment_date')
            ->value('appointment_date');
    }

    public function recentAppointmentStatuses(array $patientIds, int $limit): Collection
    {
        if ($patientIds === []) {
            return collect();
        }

        return DB::table('appointments')
            ->whereIn('patient_id', $patientIds)
            ->orderByDesc('appointment_date')
            ->limit($limit)
            ->pluck('status');
    }
}
