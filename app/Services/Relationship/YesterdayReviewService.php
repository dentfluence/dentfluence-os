<?php

namespace App\Services\Relationship;

use App\Models\Appointment;
use App\Models\CommunicationQueue;
use App\Models\TodayActionDismissal;
use App\Support\ClinicFlowRange;
use Illuminate\Database\Eloquent\Builder;
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

    // ── Full-list support (Missed Calls page) ──────────────────────────────

    /**
     * The base query for "missed calls" — due yesterday-or-earlier and still
     * pending. Shared by the dashboard preview (missedCalls(), ->limit()'d)
     * and the full paginated Missed Calls list page, so both surfaces stay
     * in lock-step with a single source of truth.
     *
     * Unlike missedCalls() (fixed to exactly "yesterday"), this widens to
     * "yesterday or earlier" so the full list page functions as the true
     * backlog view (badges like "910" mean there's a backlog older than
     * one day — the dashboard card only ever samples yesterday's slice).
     *
     * @param bool $includeIgnored  pass true to also show ignored items
     *                              (used by the list page's "Show ignored" toggle).
     */
    public function missedCallsQuery(bool $includeIgnored = false): Builder
    {
        $yesterday = Carbon::yesterday()->endOfDay();

        $query = CommunicationQueue::with('patient:id,name,phone,relationship_id')
            ->where(function ($q) use ($yesterday) {
                $q->where('follow_up_date', '<=', $yesterday->toDateString())
                  ->orWhere('due_at', '<=', $yesterday);
            })
            ->where('status', 'pending')
            ->orderByDesc('priority')
            ->orderBy('follow_up_date');

        if (! $includeIgnored) {
            $query->notIgnored();
        }

        return $query;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Appointments from yesterday with status = no_show or cancelled.
     *
     * "Yesterday" resolves through ClinicFlowRange — on a Monday this is
     * Saturday (or Saturday+Sunday if the clinic happened to open that
     * Sunday), not a dark Sunday. See App\Support\ClinicFlowRange.
     */
    private function missedAppointments(): array
    {
        try {
            [$start, $end] = ClinicFlowRange::resolve();

            // Excludes anything already handled today via Today's Actions
            // "Log & Close" or Dismiss — same TodayActionDismissal suppression
            // every other live-computed category uses. Fixed 2026-07-08: this
            // category previously had no such filter at all, so a logged call
            // always reappeared on refresh.
            $dismissedIds = TodayActionDismissal::dismissedIdsFor(
                'missed_appointments_yesterday', Appointment::class, Carbon::today()
            );

            return Appointment::with('patient:id,name,phone,relationship_id')
                ->whereBetween('appointment_date', [$start->toDateString(), $end->copy()->endOfDay()])
                ->whereIn('status', ['no_show', 'cancelled'])
                ->whereNotIn('id', $dismissedIds)
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
                        'reason'          => 'Missed appointment (' . $appt->appointment_date?->format('d M') . ', ' . ucfirst($appt->status) . ')',
                        'priority'        => 'high',
                        'suggested_action'=> 'Call to reschedule',
                        'link'            => $appt->patient_id
                            ? route('patients.show', $appt->patient_id)
                            : '#',
                        'meta'            => [
                            'id'               => $appt->id,
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
     * "Yesterday" resolves through ClinicFlowRange — see missedAppointments().
     */
    private function missedCalls(): array
    {
        try {
            [$start, $end] = ClinicFlowRange::resolve();
            $startDate = $start->toDateString();
            $endBound  = $end->copy()->endOfDay();

            return CommunicationQueue::with('patient:id,name,phone,relationship_id')
                ->where(function ($q) use ($startDate, $endBound) {
                    // Due in range (follow_up_date or due_at fell within it)
                    $q->whereBetween('follow_up_date', [$startDate, $endBound])
                      ->orWhereBetween('due_at', [$startDate, $endBound]);
                })
                ->where('status', 'pending')
                ->notIgnored() // Missed Calls (2026-07-05): honour per-item Ignore
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
                        'reason'          => 'Call was due — not yet actioned ('
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
