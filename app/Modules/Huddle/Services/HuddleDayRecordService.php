<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

use App\Services\Analytics\ReportMetricsService;
use App\Support\Phi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HuddleDayRecordService — what actually happened on a given date.
 * ----------------------------------------------------------------------------
 * This is the honest half of huddle history. An appointment, a treatment visit,
 * a consultation and a payment all carry the date they belong to, so any past
 * day can be read back from them exactly as it was. Stock levels, open tasks,
 * the lab queue and the call pipeline carry no such date — they are the state
 * of the clinic right now — and nothing here touches them. A page that showed
 * today's stock under a date last month would not be history, it would be a
 * lie with a date on it.
 *
 * So: this service reconstructs the dated record for any day, and
 * HuddleCloseService's frozen snapshot carries the rest for days that were
 * actually ticked. Two different guarantees, shown separately, never mixed.
 */
class HuddleDayRecordService
{
    public function __construct(
        private readonly ReportMetricsService $metrics,
    ) {}

    /**
     * @return array{
     *   appointments: \Illuminate\Support\Collection,
     *   visits: \Illuminate\Support\Collection,
     *   consultations: \Illuminate\Support\Collection,
     *   counts: array<string, int>,
     *   collected: float,
     *   collection_events: int
     * }
     */
    public function forDate(?int $branchId, Carbon $day): array
    {
        $date = $day->toDateString();

        $appointments = DB::table('appointments')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users as doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'appointments.treatment_id')
            ->when($branchId, fn ($q) => $q->where('appointments.branch_id', $branchId))
            ->whereDate('appointments.appointment_date', $date)
            ->select([
                'appointments.id',
                'appointments.patient_id',
                'appointments.appointment_time',
                'appointments.status',
                'appointments.type',
                'appointments.is_walkin',
                'patients.name as patient_name',
                'doctors.name as doctor_name',
                'doctors.color as doctor_color',
                'treatment_types.name as treatment_name',
            ])
            ->orderBy('appointments.appointment_time')
            ->get();

        $visits = DB::table('treatment_visits')
            ->join('patients', 'patients.id', '=', 'treatment_visits.patient_id')
            ->leftJoin('users as tv_doctors', 'tv_doctors.id', '=', 'treatment_visits.doctor_id')
            ->whereNull('treatment_visits.deleted_at')
            ->whereDate('treatment_visits.visit_date', $date)
            ->select([
                'treatment_visits.id',
                'treatment_visits.patient_id',
                'treatment_visits.visit_type',
                'treatment_visits.treatment_name',
                'treatment_visits.status',
                'patients.name as patient_name',
                'tv_doctors.name as doctor_name',
                'tv_doctors.color as doctor_color',
            ])
            ->get();

        $consultations = DB::table('consultations')
            ->join('patients', 'patients.id', '=', 'consultations.patient_id')
            ->leftJoin('users as c_doctors', 'c_doctors.id', '=', 'consultations.doctor_id')
            ->whereNull('consultations.deleted_at')
            ->whereDate('consultations.consultation_date', $date)
            ->select([
                'consultations.id',
                'consultations.patient_id',
                'consultations.visit_type',
                'consultations.chief_complaint',
                'consultations.status',
                'patients.name as patient_name',
                'c_doctors.name as doctor_name',
                'c_doctors.color as doctor_color',
            ])
            ->get()
            ->map(function ($row) {
                // PHI read raw via DB::table() — Eloquent casts never ran.
                $row->chief_complaint = Phi::decrypt($row->chief_complaint);
                return $row;
            });

        $terminal = \App\Enums\AppointmentStatus::terminalValues();

        return [
            'appointments'  => $appointments,
            'visits'        => $visits,
            'consultations' => $consultations,
            'counts'        => [
                'booked'        => $appointments->count(),
                'walkins'       => $appointments->where('is_walkin', 1)->count(),
                'done'          => $appointments->where('status', 'done')->count(),
                'cancelled'     => $appointments->where('status', 'cancelled')->count(),
                'no_show'       => $appointments->where('status', 'no_show')->count(),
                'unclosed'      => $appointments->filter(fn ($a) => ! in_array($a->status, $terminal, true))->count(),
                'visits'        => $visits->count(),
                'consultations' => $consultations->count(),
            ],
            // Money comes from the one canonical definition (invoice_payments),
            // never re-summed here — same rule G-03 settled for the Huddle.
            'collected'         => $this->metrics->collected($day->copy()->startOfDay(), $day->copy()->endOfDay(), $branchId),
            'collection_events' => $this->metrics->collectionEvents($day->copy()->startOfDay(), $day->copy()->endOfDay(), $branchId),
        ];
    }
}
