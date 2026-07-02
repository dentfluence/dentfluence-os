<?php

namespace App\Services\Relationship;

use App\Models\Appointment;
use App\Models\CommunicationQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * YesterdayReviewService
 *
 * Surfaces two types of "unfinished business" from yesterday:
 *   1. Missed appointments (no-shows + cancellations from yesterday)
 *   2. Unactioned recall/communication items that were due yesterday but never closed
 *
 * Called internally by TodayActionsEngine — not exposed as a route.
 *
 * Returns arrays (not Eloquent collections) so TodayActionsEngine can
 * merge and prioritize freely.
 */
class YesterdayReviewService
{
    /**
     * Generate the full yesterday review array.
     *
     * @return array{
     *     missed_appointments: array,
     *     missed_calls: array,
     * }
     */
    public function generateYesterdayReview(): array
    {
        return [
            'missed_appointments' => $this->missedAppointments(),
            'missed_calls'        => $this->missedCalls(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Appointments from yesterday with status = no_show or cancelled.
     */
    private function missedAppointments(): array
    {
        try {
            $yesterday = Carbon::yesterday()->toDateString();

            return Appointment::with('patient:id,name,phone,relationship_id')
                ->whereDate('appointment_date', $yesterday)
                ->whereIn('status', ['no_show', 'cancelled'])
                ->orderBy('appointment_date')
                ->get()
                ->map(function (Appointment $appt) {
                    $patient = $appt->patient;

                    return [
                        'category'        => 'missed_appointments_yesterday',
                        'patient_name'    => $patient?->name ?? $appt->patient_name ?? 'Unknown',
                        'patient_id'      => $appt->patient_id,
                        'lead_id'         => null,
                        'relationship_id' => $patient?->relationship_id ?? null,
                        'reason'          => 'Missed appointment yesterday (' . ucfirst($appt->status) . ')',
                        'priority'        => 'high',
                        'suggested_action'=> 'Call to reschedule',
                        'link'            => $appt->patient_id
                            ? route('patients.show', $appt->patient_id)
                            : '#',
                        'meta'            => [
                            'appointment_date' => $appt->appointment_date?->format('d M Y'),
                            'status'           => $appt->status,
                            'phone'            => $patient?->phone,
                        ],
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('YesterdayReviewService::missedAppointments failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * CommunicationQueue items that were due yesterday and are still pending/unactioned.
     *
     * These are calls that should have been made yesterday but weren't.
     */
    private function missedCalls(): array
    {
        try {
            $yesterday = Carbon::yesterday();

            return CommunicationQueue::with('patient:id,name,phone,relationship_id')
                ->where(function ($q) use ($yesterday) {
                    // Due yesterday (follow_up_date or due_at was yesterday)
                    $q->whereDate('follow_up_date', $yesterday->toDateString())
                      ->orWhereDate('due_at', $yesterday->toDateString());
                })
                ->where('status', 'pending')
                ->orderByDesc('priority')
                ->get()
                ->map(function (CommunicationQueue $item) {
                    $patient = $item->patient;

                    return [
                        'category'        => 'missed_calls_yesterday',
                        'patient_name'    => $patient?->name ?? $item->person_name ?? 'Unknown',
                        'patient_id'      => $item->patient_id,
                        'lead_id'         => null,
                        'relationship_id' => $patient?->relationship_id ?? null,
                        'reason'          => 'Call was due yesterday — not yet actioned ('
                            . ($item->purpose_label ?? $item->purpose ?? 'follow-up') . ')',
                        'priority'        => 'high',
                        'suggested_action'=> 'Call today — overdue from yesterday',
                        'link'            => $item->patient_id
                            ? route('patients.show', $item->patient_id)
                            : '#',
                        'meta'            => [
                            'purpose'        => $item->purpose,
                            'comm_queue_id'  => $item->id,
                            'phone'          => $patient?->phone ?? $item->phone,
                            'due_at'         => $item->due_at?->format('d M Y H:i'),
                            'follow_up_date' => $item->follow_up_date?->format('d M Y'),
                        ],
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('YesterdayReviewService::missedCalls failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
