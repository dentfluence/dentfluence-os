<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Services\Relationship\TodayActionsProjector;
use Illuminate\View\View;

/**
 * ReceptionController — PRE (Phase 1 · Workstream E, slice E3).
 *
 * A reception-focused landing that reads the SAME pre-computed Today's Actions
 * projection (no god-reader) and splits it into two work queues:
 *   • Today's Calls — everyone reception should phone today.
 *   • Today's Work  — non-call tasks (lab ready, birthdays, missed visits).
 *
 * Read-only and additive — a NEW route. It reads the projection directly (which
 * the rebuild command / schedule keeps fresh), independent of the
 * `today.projection` read-cutover flag. The full Today's Actions page is still
 * one click away.
 *
 * Route: GET /relationship/reception  [relationship.reception]
 */
class ReceptionController extends Controller
{
    /** Categories that mean "pick up the phone". */
    private const CALL_CATEGORIES = [
        'new_enquiries', 'lead_followups', 'recall_calls', 'appointment_reminders',
        'pending_estimates', 'payment_reminders', 'opportunities',
        'membership_renewals', 'missed_calls_yesterday',
    ];

    /** Categories that are non-call tasks. */
    private const WORK_CATEGORIES = [
        'lab_ready', 'birthdays', 'missed_appointments_yesterday',
    ];

    /** Human labels (kept local so this slice is self-contained). */
    private const LABELS = [
        'new_enquiries'                 => 'New Enquiry',
        'lead_followups'                => 'Lead Follow-up',
        'recall_calls'                  => 'Recall Call',
        'appointment_reminders'         => 'Appointment Reminder',
        'pending_estimates'             => 'Pending Estimate',
        'payment_reminders'             => 'Payment Reminder',
        'opportunities'                 => 'Treatment Opportunity',
        'membership_renewals'           => 'Membership Renewal',
        'missed_calls_yesterday'        => "Yesterday's Missed Call",
        'lab_ready'                     => 'Lab Work Ready',
        'birthdays'                     => 'Birthday',
        'missed_appointments_yesterday' => "Yesterday's Missed Appointment",
    ];

    private const PRIORITY_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    public function __construct(
        private readonly TodayActionsProjector $projector,
    ) {}

    public function index(): View
    {
        $grouped = $this->projector->grouped();
        $summary = $this->projector->summary();

        $calls = $this->queue($grouped, self::CALL_CATEGORIES);
        $work  = $this->queue($grouped, self::WORK_CATEGORIES);

        return view('relationship.reception.index', [
            'calls'       => $calls,
            'work'        => $work,
            'summary'     => $summary,
            'generatedAt' => $summary['generated_at'],
            'highCount'   => $summary['by_priority']['high'] ?? 0,
        ]);
    }

    /**
     * Flatten the given categories into one priority-sorted queue, tagging each
     * item with its category label.
     *
     * @param  array<string, array<int, array<string,mixed>>> $grouped
     * @param  array<int,string> $categories
     * @return array<int, array<string,mixed>>
     */
    private function queue(array $grouped, array $categories): array
    {
        $items = [];

        foreach ($categories as $category) {
            foreach ($grouped[$category] ?? [] as $item) {
                $item['category_label'] = self::LABELS[$category] ?? ucwords(str_replace('_', ' ', $category));
                $items[] = $item;
            }
        }

        usort($items, function ($a, $b) {
            $ra = self::PRIORITY_RANK[$a['priority'] ?? 'low'] ?? 2;
            $rb = self::PRIORITY_RANK[$b['priority'] ?? 'low'] ?? 2;
            return $ra <=> $rb;
        });

        return $items;
    }
}
