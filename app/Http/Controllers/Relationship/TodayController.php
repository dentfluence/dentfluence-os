<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use App\Support\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TodayController — powers the /relationship/today page.
 *
 * index():     Loads TodayActionsEngine, groups results by category, passes to view.
 * logAction(): AJAX — marks an item as actioned, writes to ActivityEngine.
 */
class TodayController extends Controller
{
    /**
     * Human-readable labels for each category (used in the view).
     */
    private const CATEGORY_LABELS = [
        'new_enquiries'                => 'New Enquiries',
        'lead_followups'               => 'Lead Follow-ups',
        'opportunities'                => 'Treatment Opportunities',
        'recall_calls'                 => 'Recall Calls',
        'appointment_reminders'        => 'Appointment Reminders',
        'missed_calls_yesterday'       => 'Yesterday\'s Missed Calls',
        'missed_appointments_yesterday'=> 'Yesterday\'s Missed Appointments',
        'pending_estimates'            => 'Pending Estimates',
        'membership_renewals'          => 'Membership Renewals',
        'birthdays'                    => 'Birthday Wishes',
        'lab_ready'                    => 'Lab Work Ready',
        'payment_reminders'            => 'Payment Reminders',
    ];

    /**
     * Tabler icon for each category (used in the view).
     */
    private const CATEGORY_ICONS = [
        'new_enquiries'                => 'ti-inbox',
        'lead_followups'               => 'ti-phone-call',
        'opportunities'                => 'ti-report-money',
        'recall_calls'                 => 'ti-calendar-repeat',
        'appointment_reminders'        => 'ti-calendar-event',
        'missed_calls_yesterday'       => 'ti-phone-x',
        'missed_appointments_yesterday'=> 'ti-calendar-x',
        'pending_estimates'            => 'ti-file-invoice',
        'membership_renewals'          => 'ti-id-badge',
        'birthdays'                    => 'ti-cake',
        'lab_ready'                    => 'ti-flask',
        'payment_reminders'            => 'ti-receipt-2',
    ];

    /**
     * Priority sort order — lower number = shown first.
     */
    private const CATEGORY_PRIORITY = [
        'new_enquiries'                => 1,
        'missed_calls_yesterday'       => 2,
        'missed_appointments_yesterday'=> 3,
        'lead_followups'               => 4,
        'appointment_reminders'        => 5,
        'recall_calls'                 => 6,
        'opportunities'                => 7,
        'pending_estimates'            => 8,
        'birthdays'                    => 9,
        'lab_ready'                    => 10,
        'membership_renewals'          => 11,
        'payment_reminders'            => 12,
    ];

    public function __construct(
        private readonly TodayActionsEngine    $engine,
        private readonly TodayActionsProjector $projector,
        private readonly ActivityEngine        $activityEngine,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /relationship/today
    // ─────────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        // Source the 12 category groups.
        // Behind the `today.projection` flag we read the pre-computed projection
        // (one table, no god-reader). Default OFF = the live engine, unchanged.
        // We always project onto the full known category set so empty groups
        // still render identically whichever source is used.
        if (Feature::enabled('today.projection')) {
            $projected = $this->projector->grouped();
            $raw = [];
            foreach (array_keys(self::CATEGORY_LABELS) as $key) {
                $raw[$key] = $projected[$key] ?? [];
            }
            // Include any projected category not in the label map (forward-safe).
            foreach ($projected as $key => $items) {
                if (! array_key_exists($key, $raw)) {
                    $raw[$key] = $items;
                }
            }
        } else {
            $raw = $this->engine->generate();
        }

        // Build enriched groups array for the view
        $groups = [];
        foreach ($raw as $key => $items) {
            $groups[$key] = [
                'key'      => $key,
                'label'    => self::CATEGORY_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'icon'     => self::CATEGORY_ICONS[$key] ?? 'ti-circle',
                'items'    => $items,
                'count'    => count($items),
                'priority' => self::CATEGORY_PRIORITY[$key] ?? 99,
            ];
        }

        // Sort groups: non-empty first, then by priority
        uasort($groups, function ($a, $b) {
            $aEmpty = $a['count'] === 0;
            $bEmpty = $b['count'] === 0;

            if ($aEmpty !== $bEmpty) {
                return $aEmpty ? 1 : -1; // empty groups go to bottom
            }

            return $a['priority'] <=> $b['priority'];
        });

        $totalCount    = array_sum(array_column($groups, 'count'));
        $checklists    = config('relationship_rules.call_checklists', []);
        $responseOpts  = config('relationship_rules.response_options', []);
        $nextActions   = config('relationship_rules.next_actions', []);

        return view('relationship.today.index', compact(
            'groups',
            'totalCount',
            'checklists',
            'responseOpts',
            'nextActions',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /relationship/today/summary  (JSON) — slice E4
    // Shared read of the Today's Actions projection. The Daily Huddle (and any
    // other surface) consumes this instead of running its own domain queries.
    // ─────────────────────────────────────────────────────────────────────

    public function summary(): JsonResponse
    {
        return response()->json($this->projector->summary());
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/action  (AJAX)
    // ─────────────────────────────────────────────────────────────────────

    public function logAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'        => ['required', 'string'],
            'patient_id'      => ['nullable', 'integer'],
            'lead_id'         => ['nullable', 'integer'],
            'relationship_id' => ['nullable', 'integer'],
            'response'        => ['required', 'string'],
            'next_action'     => ['nullable', 'string'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        try {
            // Resolve the subject model — prefer Patient, fall back to Lead
            $subject = null;
            if ($validated['patient_id']) {
                $subject = \App\Models\Patient::find($validated['patient_id']);
            } elseif ($validated['lead_id']) {
                $subject = \App\Models\Lead::find($validated['lead_id']);
            }

            if ($subject) {
                $this->activityEngine->log(
                    subject       : $subject,
                    event         : 'call.logged',
                    actor         : auth()->user(),
                    metadata      : [
                        'category'    => $validated['category'],
                        'response'    => $validated['response'],
                        'next_action' => $validated['next_action'] ?? null,
                        'notes'       => $validated['notes'] ?? null,
                        'source'      => 'today_actions',
                    ],
                    relationshipId: $validated['relationship_id'] ?? null,
                    description   : 'Call logged from Today\'s Actions: ' . $validated['response'],
                );
            }

            // Resolve next action label from config
            $nextActionLabel = config('relationship_rules.next_actions.' . $validated['response'])
                ?? $validated['next_action']
                ?? 'No next action set';

            return response()->json([
                'success'          => true,
                'next_action_label'=> $nextActionLabel,
            ]);
        } catch (\Throwable $e) {
            Log::error('TodayController::logAction failed', [
                'data'  => $validated,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not log action. Please try again.',
            ], 500);
        }
    }
}
