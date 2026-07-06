<?php

namespace App\Services\Relationship;

use App\Models\Appointment;
use App\Models\User;

/**
 * AppointmentActivityLogger
 * --------------------------
 * Thin adapter between the appointment lifecycle (web calendar + mobile API)
 * and the existing ActivityEngine / patient Timeline. Both entry points
 * (web AppointmentController and the API AppointmentService) call this so
 * "booked / checked-in / completed / cancelled" always reads the same on a
 * patient's Relationship Timeline, regardless of which channel did it.
 *
 * This intentionally does NOT touch the `audit_logs` staff-accountability
 * trail (see App\Traits\Auditable) — that's a separate, admin-facing
 * concern. This class is purely for the patient-facing narrative.
 */
class AppointmentActivityLogger
{
    public function __construct(private ActivityEngine $activityEngine) {}

    public function booked(Appointment $appointment, ?User $actor): void
    {
        $appointment->loadMissing(['patient', 'doctor']);

        $doctorName = $appointment->doctor?->name;
        $when       = $this->when($appointment);

        $description = $doctorName
            ? "Appointment booked with Dr. {$doctorName} for {$when}"
            : "Appointment booked for {$when}";

        $this->log($appointment, 'appointment.booked', $actor, $description, [
            'doctor_id'         => $appointment->doctor_id,
            'appointment_date'  => (string) $appointment->appointment_date,
            'appointment_time'  => $appointment->appointment_time,
            'type'              => $appointment->type,
            'is_walkin'         => (bool) $appointment->is_walkin,
        ]);
    }

    public function cancelled(Appointment $appointment, ?User $actor, ?string $reason = null, ?string $cancelledParty = null): void
    {
        $who   = $actor?->name ?? 'System';
        $party = $cancelledParty ? ' — ' . ucfirst($cancelledParty) : '';
        $why   = $reason ? " — {$reason}" : '';

        $this->log($appointment, 'appointment.cancelled', $actor, "Cancelled by {$who}{$party}{$why}", [
            'reason'          => $reason,
            'cancelled_party' => $cancelledParty,
        ]);
    }

    public function checkedIn(Appointment $appointment, ?User $actor): void
    {
        $who = $actor?->name ?? 'Staff';
        $this->log($appointment, 'appointment.checked_in', $actor, "{$who} checked in the patient");
    }

    public function completed(Appointment $appointment, ?User $actor): void
    {
        $who = $actor?->name;
        $this->log($appointment, 'appointment.completed', $actor, $who ? "Visit completed by {$who}" : 'Visit completed');
    }

    private function when(Appointment $appointment): string
    {
        $date = $appointment->appointment_date instanceof \Carbon\Carbon
            ? $appointment->appointment_date->format('d M Y')
            : (string) $appointment->appointment_date;

        return trim($date . ' ' . $appointment->appointment_time);
    }

    private function log(Appointment $appointment, string $event, ?User $actor, string $description, array $metadata = []): void
    {
        $appointment->loadMissing('patient');
        $relationshipId = $appointment->patient?->relationship_id;

        // No relationship_id yet (e.g. legacy/unmigrated patient) — ActivityEngine
        // still writes the row, it just won't surface on a Timeline until linked.
        $this->activityEngine->log($appointment, $event, $actor, $metadata, $relationshipId, $description);
    }
}
