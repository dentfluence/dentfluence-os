<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorBlockedSlot;
use App\Models\Patient;
use App\Models\User;
use App\Services\Relationship\AppointmentActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * AppointmentService
 * ------------------
 * The shared "brain" for appointments on the API side. Mirrors how
 * PatientService works for patients: branch-scoped queries, create, and the
 * status lifecycle live here so the mobile app and any future client behave
 * the same. (The existing web AppointmentController keeps its own rich
 * calendar logic untouched — this is purely additive.)
 *
 * Status lifecycle (from the appointments table enum):
 *   scheduled → checkin → in_chair → checkout → done
 *   (or) cancelled / no_show
 */
class AppointmentService
{
    /** The eager-loads every appointment payload needs. */
    private const WITH = ['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'];

    public function __construct(private AppointmentActivityLogger $activityLogger) {}

    /**
     * Branch-scoped, filtered, ordered appointment query.
     *
     * Recognised filters (all optional):
     *   scope        today | upcoming | all   (default: a -7..+60 day window)
     *   date         YYYY-MM-DD  (single day)
     *   date_from / date_to      (explicit range)
     *   doctor_id, patient_id, status
     */
    public function filteredQuery(int $branchId, array $filters = []): Builder
    {
        $query = Appointment::with(self::WITH)
            ->where('branch_id', $branchId);

        $scope = $filters['scope'] ?? null;

        if (! empty($filters['date'])) {
            $query->whereDate('appointment_date', $filters['date']);
        } elseif (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = $filters['date_from'] ?? today()->toDateString();
            $to   = $filters['date_to'] ?? today()->addDays(60)->toDateString();
            $query->whereBetween('appointment_date', [$from, $to]);
        } elseif ($scope === 'today') {
            $query->whereDate('appointment_date', today());
        } elseif ($scope === 'upcoming') {
            $query->whereDate('appointment_date', '>=', today())
                  ->whereIn('status', ['scheduled', 'checkin', 'in_chair']);
        } elseif ($scope !== 'all') {
            // Default: a useful window around today.
            $query->whereBetween('appointment_date', [
                today()->subDays(7)->toDateString(),
                today()->addDays(60)->toDateString(),
            ]);
        }

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('appointment_date')->orderBy('appointment_time');
    }

    /** Today's live status counters for a branch (used by the dashboard). */
    public function todayCounts(int $branchId): array
    {
        $base = Appointment::where('branch_id', $branchId)
            ->whereDate('appointment_date', today());

        return [
            'total'     => (clone $base)->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'checkin'   => (clone $base)->where('status', 'checkin')->count(),
            'in_chair'  => (clone $base)->where('status', 'in_chair')->count(),
            'done'      => (clone $base)->where('status', 'done')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'no_show'   => (clone $base)->where('status', 'no_show')->count(),
            'walkin'    => (clone $base)->where('is_walkin', true)->count(),
        ];
    }

    /**
     * Create a scheduled appointment for an EXISTING patient.
     * (Walk-in / new-patient creation stays in the web controller.)
     */
    public function create(array $in, User $actor): Appointment
    {
        $appointment = Appointment::create([
            'patient_id'            => $in['patient_id'],
            'doctor_id'             => $in['doctor_id'],
            'branch_id'             => $actor->branch_id,
            'created_by'            => $actor->id,
            'appointment_date'      => $in['appointment_date'],
            'appointment_time'      => $in['appointment_time'],
            'duration_minutes'      => $in['duration_minutes'] ?? 30,
            'type'                  => $in['type'] ?? 'consultation',
            'status'                => 'scheduled',
            'treatment_category_id' => $in['treatment_category_id'] ?? null,
            'treatment_id'          => $in['treatment_id'] ?? null,
            'operatory_id'          => $in['operatory_id'] ?? null,
            'notes'                 => $in['notes'] ?? null,
            'chief_complaint'       => $in['chief_complaint'] ?? null,
        ]);

        $this->activityLogger->booked($appointment, $actor);

        return $appointment->load(self::WITH);
    }

    /**
     * Move an appointment through its status lifecycle, stamping the relevant
     * time field. Saves the old status so the web "revert" button still works.
     */
    public function updateStatus(Appointment $appointment, string $status, ?User $actor = null): Appointment
    {
        $update = [
            'previous_status' => $appointment->status,
            'status'          => $status,
        ];

        match ($status) {
            'checkin'  => $update['checked_in_at'] = now(),
            'in_chair' => $update['in_chair_at']   = now(),
            'done'     => $update['completed_at']  = now(),
            default    => null,
        };

        $appointment->update($update);

        match ($status) {
            'checkin' => $this->activityLogger->checkedIn($appointment, $actor),
            'done'    => $this->activityLogger->completed($appointment, $actor),
            default   => null,
        };

        return $appointment->fresh()->load(self::WITH);
    }

    /** Cancel an appointment, recording the reason and who initiated it. */
    public function cancel(Appointment $appointment, string $reason, ?string $cancelledParty = null, ?User $actor = null): Appointment
    {
        $appointment->update([
            'previous_status' => $appointment->status,
            'status'          => 'cancelled',
            'cancel_reason'   => $reason,
            'cancelled_party' => $cancelledParty,
        ]);

        $this->activityLogger->cancelled($appointment, $actor, $reason, $cancelledParty);

        return $appointment->fresh()->load(self::WITH);
    }

    /**
     * Walk-in: an immediately checked-in appointment. The patient is either an
     * existing one (patient_id, already branch-verified by the controller) or a
     * brand-new minimal record created from first/last name + phone.
     */
    public function createWalkIn(array $in, User $actor): Appointment
    {
        $branchId = $actor->branch_id;

        if (! empty($in['patient_id'])) {
            $patient = Patient::find($in['patient_id']);
        } else {
            $patient = Patient::create([
                'first_name' => $in['first_name'] ?? null,
                'last_name'  => $in['last_name'] ?? null,
                'name'       => trim(($in['first_name'] ?? '') . ' ' . ($in['last_name'] ?? '')),
                'phone'      => $in['phone'] ?? null,
                'branch_id'  => $branchId,
                'created_by' => $actor->id,
            ]);
        }

        $doctorId = $in['doctor_id']
            ?? User::where('branch_id', $branchId)->where('is_active', true)->value('id');

        $appointment = Appointment::create([
            'patient_id'            => $patient->id,
            'doctor_id'             => $doctorId,
            'branch_id'             => $branchId,
            'created_by'            => $actor->id,
            'appointment_date'      => $in['appointment_date'] ?? today()->toDateString(),
            'appointment_time'      => $in['appointment_time'] ?? now()->format('H:i'),
            'duration_minutes'      => $in['duration_minutes'] ?? 30,
            'type'                  => 'consultation',
            'status'                => 'checkin',
            'is_walkin'             => true,
            'checked_in_at'         => now(),
            'treatment_category_id' => $in['treatment_category_id'] ?? null,
            'treatment_id'          => $in['treatment_id'] ?? null,
            'operatory_id'          => $in['operatory_id'] ?? null,
            'notes'                 => $in['notes'] ?? 'Walk-in',
        ]);

        $this->activityLogger->booked($appointment, $actor);

        return $appointment->load(self::WITH);
    }

    /** Block a doctor's time slot (personal time, leave, etc.). */
    public function blockSlot(array $in, User $actor): DoctorBlockedSlot
    {
        return DoctorBlockedSlot::create([
            'doctor_id'  => $in['doctor_id'],
            'block_date' => $in['block_date'],
            'start_time' => $in['start_time'],
            'end_time'   => $in['end_time'],
            'reason'     => $in['reason'] ?? null,
            'block_type' => $in['block_type'] ?? 'personal',
            'created_by' => $actor->id,
        ]);
    }

    /** Blocked slots in a date range, for the calendar (branch-scoped via doctor). */
    public function blockedSlotsInRange(int $branchId, string $from, string $to)
    {
        return DoctorBlockedSlot::with('doctor:id,name')
            ->inRange($from, $to)
            ->whereHas('doctor', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('block_date')
            ->orderBy('start_time')
            ->get();
    }
}
