<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\ActionOptionList;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\CommunicationQueue;
use App\Models\Finance\FinancePatientMembership;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentVisit;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use App\Services\Whatsapp\OutboundMessageService;
use App\Support\Features\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        'appointment_reminders_today'  => "Today's Appointments — Confirm",
        'wellness_check_yesterday'     => "Yesterday's Treated Patients — Wellness Call",
        'new_enquiries'                => 'New Enquiries',
        'lead_followups'               => 'Lead Follow-ups',
        'opportunities'                => 'Treatment Opportunities',
        'recall_calls'                 => 'Recall Calls',
        'follow_up_calls'              => 'Follow-up Calls',
        'appointment_reminders'        => 'Appointment Reminders',
        'missed_calls_yesterday'       => 'Yesterday\'s Missed Calls',
        'missed_appointments_yesterday'=> 'Yesterday\'s Missed Appointments',
        'pending_estimates'            => 'Pending Estimates',
        'membership_renewals'          => 'Membership Renewals',
        'birthdays'                    => 'Birthday Wishes',
        'lab_ready'                    => 'Lab Work Ready',
        'payment_reminders'            => 'Payment Reminders',
        'appointment_reminders_tomorrow'=> "Tomorrow Morning's Appointments",
        'completed_calls'               => 'Completed Calls',
        // Renamed 2026-07-08 (Sumit) — this is the catch-all bucket for
        // manually-added calls that aren't a patient recall/follow-up
        // (vendor/lab/doctor/other) — "Other Calls" reads more plainly than
        // the old "Logged Communications". Same category key, same data.
        'logged_communications'         => 'Other Calls',
    ];

    /**
     * Tabler icon for each category (used in the view).
     */
    private const CATEGORY_ICONS = [
        'appointment_reminders_today'  => 'ti-calendar-check',
        'wellness_check_yesterday'     => 'ti-heart',
        'new_enquiries'                => 'ti-inbox',
        'lead_followups'               => 'ti-phone-call',
        'opportunities'                => 'ti-report-money',
        'recall_calls'                 => 'ti-calendar-repeat',
        'follow_up_calls'              => 'ti-phone-calling',
        'appointment_reminders'        => 'ti-calendar-event',
        'missed_calls_yesterday'       => 'ti-phone-x',
        'missed_appointments_yesterday'=> 'ti-calendar-x',
        'pending_estimates'            => 'ti-file-invoice',
        'membership_renewals'          => 'ti-id-badge',
        'birthdays'                    => 'ti-cake',
        'lab_ready'                    => 'ti-flask',
        'payment_reminders'            => 'ti-receipt-2',
        'appointment_reminders_tomorrow'=> 'ti-calendar-event',
        'completed_calls'               => 'ti-circle-check',
        'logged_communications'         => 'ti-phone-outgoing',
    ];

    /**
     * Priority sort order — lower number = shown first.
     */
    // Re-ranked 2026-07-03 at Sumit's request: the day should open with
    // confirming today's own appointments (incl. evening sessions), then
    // yesterday's follow-ups, then everything else, and finish with
    // confirming tomorrow morning's appointments last.
    private const CATEGORY_PRIORITY = [
        'appointment_reminders_today'  => 1,  // confirm today's own sessions first, incl. evening
        'follow_up_calls'              => 2,  // booked call-backs (Yesterday's Flow, Follow-up Engine, etc.) due today/overdue
        'wellness_check_yesterday'     => 3,  // check on patients treated yesterday — doing okay?
        'missed_calls_yesterday'       => 4,  // someone tried to reach the clinic yesterday
        'missed_appointments_yesterday'=> 5,  // yesterday's no-show — rebook fast
        'new_enquiries'                => 6,  // fresh lead — call within 30 min or lose them
        'recall_calls'                 => 7,  // bring patients back for due recall
        'lab_ready'                    => 8,  // patient waiting on a crown/denture etc.
        'lead_followups'               => 9,  // ongoing lead nurture
        'logged_communications'        => 9,  // manually-logged calls not in another category
        'opportunities'                => 10, // proposed treatment conversion
        'pending_estimates'            => 11, // estimate awaiting approval
        'payment_reminders'            => 12, // collections
        'membership_renewals'          => 13, // plan renewal reminder
        'birthdays'                    => 14, // relationship touch — least urgent
        'appointment_reminders'        => 14, // fallback bucket, only used if today/tomorrow split fails
        'appointment_reminders_tomorrow'=> 15, // confirm tomorrow morning's sessions — last
    ];

    public function __construct(
        private readonly TodayActionsEngine    $engine,
        private readonly TodayActionsProjector $projector,
        private readonly ActivityEngine        $activityEngine,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /relationship/today
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View
    {
        $today = \Illuminate\Support\Carbon::today();
        $selectedDate = $today->copy();

        if ($request->filled('date')) {
            try {
                $selectedDate = \Illuminate\Support\Carbon::parse($request->query('date'))->startOfDay();
            } catch (\Throwable $e) {
                $selectedDate = $today->copy();
            }
        }

        $mode = $selectedDate->isSameDay($today)
            ? 'today'
            : ($selectedDate->greaterThan($today) ? 'future' : 'past');

        if ($mode !== 'today') {
            // Date-picker modes: a lightweight preview (future) or a completed-
            // call history read (past). Neither touches the live engine's
            // "today" path below — that stays exactly as it always has.
            $raw = $mode === 'future'
                ? $this->engine->generateUpcoming($selectedDate)
                : $this->engine->generatePast($selectedDate);
        } else {
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

            // Split the single "appointment_reminders" bucket into "today" and
            // "tomorrow morning" so they can sit at opposite ends of the list
            // (confirm today's sessions first thing, tomorrow's sessions last).
            // Pure display-layer split — TodayActionsEngine itself is untouched.
            if (array_key_exists('appointment_reminders', $raw)) {
                $todayItems    = [];
                $tomorrowItems = [];

                foreach ($raw['appointment_reminders'] as $item) {
                    $isToday = false;
                    $rawDate = $item['meta']['appointment_date'] ?? null;

                    if ($rawDate) {
                        try {
                            $isToday = \Illuminate\Support\Carbon::createFromFormat('d M Y', $rawDate)->isToday();
                        } catch (\Throwable $e) {
                            $isToday = false; // if unparsable, fall back to the "tomorrow" bucket
                        }
                    }

                    if ($isToday) {
                        $todayItems[] = $item;
                    } else {
                        $tomorrowItems[] = $item;
                    }
                }

                unset($raw['appointment_reminders']);
                $raw['appointment_reminders_today']    = $todayItems;
                $raw['appointment_reminders_tomorrow'] = $tomorrowItems;
            }
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
        $responseOpts  = $this->buildResponseOptions();
        $nextActions   = $this->buildNextActions();
        $requiresNotesMap = $this->buildRequiresNotesMap();
        $dismissReasons = ActionOptionList::query()->dismissReasons()->get()->values();

        return view('relationship.today.index', compact(
            'groups',
            'totalCount',
            'checklists',
            'responseOpts',
            'nextActions',
            'requiresNotesMap',
            'dismissReasons',
            'selectedDate',
            'mode',
            'today',
        ));
    }

    /**
     * Build the category => [key => label] call-outcome map for the drawer.
     *
     * Starts from config('relationship_rules.response_options') (the
     * long-standing fallback — always present even for a brand-new install
     * with an empty action_option_lists table), then overrides any category
     * that has active DB rows configured from Settings > Call Outcomes.
     * See docs/feature-specs/feature-spec-custom-call-outcomes.md.
     */
    private function buildResponseOptions(): array
    {
        $merged = config('relationship_rules.response_options', []);

        $dbRows = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->active()
            ->get()
            ->groupBy('action_category');

        foreach ($dbRows as $category => $rows) {
            $merged[$category] = ActionOptionList::labelMap($rows);
        }

        return $merged;
    }

    /**
     * Build the response-key => next-action-label map, same shape as
     * config('relationship_rules.next_actions'). DB rows may set
     * `next_action_key` as a literal override label for their outcome key —
     * if a category-specific outcome has no override and no matching config
     * entry, the "Suggested Next Action" box simply stays empty, which is
     * correct for outcomes like "Confirmed attendance" that need no follow-up.
     */
    private function buildNextActions(): array
    {
        $overrides = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->whereNotNull('next_action_key')
            ->active()
            ->pluck('next_action_key', 'key')
            ->toArray();

        return array_merge(config('relationship_rules.next_actions', []), $overrides);
    }

    /**
     * category => [key => true] for every outcome that requires a note before
     * submit. Only rows with requires_notes = true are included, so the
     * client-side payload stays small. Mirrors the server-side check in
     * logAction() — this is UX only (disables the submit button early), the
     * real gate is server-side.
     */
    private function buildRequiresNotesMap(): array
    {
        $rows = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->where('requires_notes', true)
            ->active()
            ->get(['action_category', 'key']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->action_category][$row->key] = true;
        }

        return $map;
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
            'subject_id'      => ['nullable', 'integer'],
            'response'        => ['required', 'string'],
            'next_action'     => ['nullable', 'string'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        // If this outcome is configured (Settings > Call Outcomes) to require a
        // note, enforce it server-side too — the drawer disables submit client-
        // side, but this is the real gate. See feature-spec-custom-call-outcomes.md.
        $optionRow = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->where('key', $validated['response'])
            ->where(function ($q) use ($validated) {
                $q->where('action_category', $validated['category'])
                  ->orWhere('action_category', 'default');
            })
            ->active()
            ->orderByRaw("action_category = ? desc", [$validated['category']])
            ->first();

        if ($optionRow?->requires_notes && blank($validated['notes'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'This outcome requires a note before it can be logged.',
            ], 422);
        }

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

            // Revised 2026-07-08 (Sumit) — Log stopped auto-closing every
            // outcome, because every seeded row defaulted to
            // `closes_task = true` with nothing varying it, so failed
            // attempts ("No answer" etc.) vanished from the board instead of
            // staying open for a retry.
            //
            // Revised again 2026-07-10 (Sumit) — the opposite problem: with
            // Log never closing anything, staff had to remember to flip to
            // the separate Close tab after every genuinely resolved call
            // (booked, confirmed, declined...), which was confusing them
            // about how many actions were actually done. `closes_task` is
            // now per-outcome (seeded with real values, editable per clinic
            // in Settings > Call Outcomes) and IS read here again: a
            // resolved outcome auto-closes via the same closeUnderlyingRecord()
            // the Close tab uses, a "needs retry" outcome (no answer, busy,
            // still deciding...) leaves the row open. The Close tab remains
            // for staff to manually give up on a row after however many
            // failed attempts.
            if ($optionRow?->closes_task) {
                $this->closeUnderlyingRecord($validated);
            }

            // Resolve next action label from config
            $nextActionLabel = config('relationship_rules.next_actions.' . $validated['response'])
                ?? $validated['next_action']
                ?? 'No next action set';

            return response()->json([
                'success'          => true,
                'closed'           => (bool) $optionRow?->closes_task,
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

    /**
     * Mark the row behind a logged Today's Action as handled, so it stops
     * being pulled back in by TodayActionsEngine on the next page load.
     * Called from two places: automatically by logAction() when the logged
     * outcome's `closes_task` is true, and always by the drawer's explicit
     * Close tab. Three cases, mirroring the exact same category split
     * TodayActionsEngine/dismiss() already use:
     *
     *  - Queue-backed categories (recall_calls, missed_calls_yesterday,
     *    logged_communications) → the row IS the source of truth, so this
     *    permanently closes it (CommunicationQueue::autoClose) with the
     *    logged outcome — a real call happened, it must never resurface.
     *  - follow_up_calls → FollowUp has its own completed_at/completed_by/
     *    completion_note fields built for exactly this; use them.
     *  - Everything else in DISMISSIBLE_MODELS is a live-computed query (no
     *    single "the row" to close) → same "not today" TodayActionDismissal
     *    suppression the Dismiss button already writes, just triggered from
     *    a logged call instead of an explicit dismiss reason. For
     *    lead_followups in particular this only suppresses today's
     *    occurrence — the lead's followup_date isn't touched, so if it's
     *    still due tomorrow it will (correctly) come back, same as every
     *    other date-driven category behaves today.
     */
    private function closeUnderlyingRecord(array $validated): void
    {
        $category  = $validated['category'];
        $subjectId = $validated['subject_id'] ?? null;

        if (in_array($category, self::QUEUE_BACKED_CATEGORIES, true)) {
            if ($subjectId) {
                CommunicationQueue::find($subjectId)?->autoClose(
                    $validated['response'],
                    $validated['notes'] ?? 'Logged from Today\'s Actions'
                );
            }
            return;
        }

        if ($category === 'follow_up_calls') {
            if ($subjectId) {
                FollowUp::where('id', $subjectId)->update([
                    'status'          => 'completed',
                    'completed_at'    => now(),
                    'completed_by'    => auth()->id(),
                    'completion_note' => $validated['notes'] ?? null,
                ]);
            }
            return;
        }

        $modelClass = self::DISMISSIBLE_MODELS[$category] ?? null;
        if (! $modelClass) {
            return; // category has no suppression mechanism (yet) — nothing to do
        }

        // Lead-based categories (new_enquiries, lead_followups) key off
        // lead_id — there is no separate meta.id for these on the frontend.
        $resolvedSubjectId = $modelClass === Lead::class
            ? ($validated['lead_id'] ?? $subjectId)
            : $subjectId;

        if (! $resolvedSubjectId) {
            return;
        }

        TodayActionDismissal::updateOrCreate(
            [
                'category'           => $category,
                'subject_type'       => $modelClass,
                'subject_id'         => $resolvedSubjectId,
                'dismissed_for_date' => \Illuminate\Support\Carbon::today()->toDateString(),
            ],
            [
                'reason_key'   => $validated['response'],
                'notes'        => $validated['notes'] ?? null,
                'dismissed_by' => auth()->id(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/close  (AJAX)
    //
    // The explicit "Close" tab (2026-07-08) — the drawer's action zone was
    // split into Log (records an outcome, never closes — see logAction()
    // above) and Close (this: actually removes the row from today's list).
    // No outcome is required here; it's for "I'm done with this one" after
    // however many Log attempts. Reuses closeUnderlyingRecord() exactly —
    // same per-category suppression logic Log used to trigger automatically.
    // ─────────────────────────────────────────────────────────────────────

    public function closeAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'        => ['required', 'string'],
            'patient_id'      => ['nullable', 'integer'],
            'lead_id'         => ['nullable', 'integer'],
            'relationship_id' => ['nullable', 'integer'],
            'subject_id'      => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        try {
            // closeUnderlyingRecord() expects a 'response' key (used as the
            // stored outcome/reason on CommunicationQueue::autoClose() and
            // TodayActionDismissal.reason_key) — there's no logged outcome
            // here, so use a fixed marker instead of forcing a fake one.
            $this->closeUnderlyingRecord(array_merge($validated, ['response' => 'closed_manually']));

            $subject = $this->resolveNoteSubject($validated['patient_id'] ?? null, $validated['lead_id'] ?? null);
            if ($subject) {
                $this->activityEngine->log(
                    subject       : $subject,
                    event         : 'today_action.closed',
                    actor         : auth()->user(),
                    metadata      : [
                        'category' => $validated['category'],
                        'notes'    => $validated['notes'] ?? null,
                        'source'   => 'today_actions',
                    ],
                    relationshipId: $validated['relationship_id'] ?? null,
                    description   : "Closed from Today's Actions",
                );
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('TodayController::closeAction failed', [
                'data'  => $validated,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not close. Please try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/dismiss  (AJAX)
    //
    // Clears a row without logging a call outcome — requires a reason so
    // outcome data (and anything built on it) stays honest. Two paths:
    //  - recall_calls / missed_calls_yesterday are backed by CommunicationQueue
    //    rows, so this just calls its existing dismiss() method (same one used
    //    by Missed Calls / Recall Pipeline bulk-dismiss).
    //  - everything else is computed live with no row to flag, so a
    //    TodayActionDismissal suppression row is written for "today only" —
    //    see docs/feature-specs/feature-spec-action-board-dismiss.md.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * category => model class, for the live-computed categories that use
     * TodayActionDismissal. Also reused by logAction() below (2026-07-08 fix)
     * to suppress a category-appropriate row once a call outcome that
     * `closes_task` has been logged against it — see that method's docblock.
     */
    private const DISMISSIBLE_MODELS = [
        'opportunities'                 => TreatmentOpportunity::class,
        'appointment_reminders'         => Appointment::class,
        'missed_appointments_yesterday' => Appointment::class,
        'pending_estimates'             => TreatmentOpportunity::class,
        'membership_renewals'           => FinancePatientMembership::class,
        'birthdays'                     => Patient::class,
        'lab_ready'                     => LabCase::class,
        'payment_reminders'             => Invoice::class,
        'wellness_check_yesterday'      => TreatmentVisit::class,
        'new_enquiries'                 => Lead::class,
        'lead_followups'                => Lead::class,
    ];

    /** category keys whose Today's Actions row is backed by a communication_queue record. */
    private const QUEUE_BACKED_CATEGORIES = ['recall_calls', 'missed_calls_yesterday', 'logged_communications'];

    public function dismiss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'        => ['required', 'string'],
            'subject_id'      => ['required', 'integer'],
            'reason_key'      => ['required', 'string'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'patient_id'      => ['nullable', 'integer'],
            'relationship_id' => ['nullable', 'integer'],
        ]);

        $reason = ActionOptionList::query()
            ->where('option_type', 'dismiss_reason')
            ->where('key', $validated['reason_key'])
            ->active()
            ->first();

        if (! $reason) {
            return response()->json(['success' => false, 'message' => 'Unknown dismiss reason.'], 422);
        }

        if ($reason->requires_notes && blank($validated['notes'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'This reason requires a note before it can be dismissed.',
            ], 422);
        }

        try {
            // CommunicationQueue-backed categories — reuse the existing, already-
            // proven ignore/dismiss path (Missed Calls / Recall Pipeline).
            if ($validated['category'] === 'recall_calls' || $validated['category'] === 'missed_calls_yesterday') {
                $queueItem = CommunicationQueue::find($validated['subject_id']);
                if (! $queueItem) {
                    return response()->json(['success' => false, 'message' => 'Item not found — it may already be handled.'], 404);
                }
                $queueItem->dismiss(auth()->id(), $reason->label . ($validated['notes'] ?? '' ? ' — ' . $validated['notes'] : ''));
            } else {
                $modelClass = self::DISMISSIBLE_MODELS[$validated['category']] ?? null;
                if (! $modelClass) {
                    return response()->json(['success' => false, 'message' => 'This category cannot be dismissed.'], 422);
                }

                TodayActionDismissal::updateOrCreate(
                    [
                        'category'            => $validated['category'],
                        'subject_type'        => $modelClass,
                        'subject_id'          => $validated['subject_id'],
                        'dismissed_for_date'  => \Illuminate\Support\Carbon::today()->toDateString(),
                    ],
                    [
                        'reason_key'   => $reason->key,
                        'notes'        => $validated['notes'] ?? null,
                        'dismissed_by' => auth()->id(),
                    ]
                );
            }

            // Log to the Timeline too, same as every other action on this board.
            $subject = null;
            if ($request->filled('patient_id')) {
                $subject = Patient::find($request->integer('patient_id'));
            }
            if ($subject) {
                $this->activityEngine->log(
                    subject       : $subject,
                    event         : 'today_action.dismissed',
                    actor         : auth()->user(),
                    metadata      : [
                        'category'   => $validated['category'],
                        'reason'     => $reason->label,
                        'notes'      => $validated['notes'] ?? null,
                        'source'     => 'today_actions',
                    ],
                    relationshipId: $request->integer('relationship_id') ?: null,
                    description   : 'Dismissed from Today\'s Actions: ' . $reason->label,
                );
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('TodayController::dismiss failed', [
                'data'  => $validated,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not dismiss. Please try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET  /relationship/today/notes   (AJAX — loaded when the drawer opens)
    // POST /relationship/today/notes   (AJAX — Add Note)
    //
    // Same Suggestion / Patient-Response note log already live on Lead &
    // Opportunity Pipeline (see OpportunityPipelineController::notesFor() /
    // addNote()), ported here as-is — no new table, reuses ActivityEngine.
    // Subject resolution mirrors logAction(): prefer Patient, fall back to
    // Lead, same as every other write on this board. See
    // docs/feature-specs/feature-spec-action-board-instruction-log.md.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve the Timeline subject for a Today's Action row — same
     * precedence logAction() already uses (Patient first, then Lead).
     * Returns null if the row has neither (nothing to attach notes to).
     */
    private function resolveNoteSubject(?int $patientId, ?int $leadId): ?Model
    {
        if ($patientId) {
            return Patient::find($patientId);
        }
        if ($leadId) {
            return Lead::find($leadId);
        }
        return null;
    }

    public function notes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer'],
            'lead_id'    => ['nullable', 'integer'],
        ]);

        $subject = $this->resolveNoteSubject($validated['patient_id'] ?? null, $validated['lead_id'] ?? null);

        if (! $subject) {
            return response()->json(['success' => true, 'notes' => []]);
        }

        $notes = Activity::query()
            ->with('actor')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->ofEvent('today_action.note_added')
            ->recent()
            ->get()
            ->map(fn (Activity $note) => [
                'note_type'   => $note->metadata['note_type'] ?? 'suggestion',
                'text'        => $note->metadata['text'] ?? $note->description,
                'author'      => $note->actor?->name ?? 'Staff',
                'occurred_at' => $note->occurred_at?->format('d M Y, g:i A'),
            ]);

        return response()->json(['success' => true, 'notes' => $notes]);
    }

    public function addNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note_type'       => ['required', 'in:suggestion,response'],
            'text'            => ['required', 'string', 'max:1000'],
            'category'        => ['nullable', 'string'],
            'patient_id'      => ['nullable', 'integer'],
            'lead_id'         => ['nullable', 'integer'],
            'relationship_id' => ['nullable', 'integer'],
        ]);

        $subject = $this->resolveNoteSubject($validated['patient_id'] ?? null, $validated['lead_id'] ?? null);

        if (! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'This item has no patient or lead attached — a note cannot be added.',
            ], 422);
        }

        $label = $validated['note_type'] === 'suggestion' ? 'Suggestion' : 'Patient response';

        $this->activityEngine->log(
            subject       : $subject,
            event         : 'today_action.note_added',
            actor         : auth()->user(),
            metadata      : [
                'note_type' => $validated['note_type'],
                'text'      => $validated['text'],
                'category'  => $validated['category'] ?? null,
            ],
            relationshipId: $validated['relationship_id'] ?? null,
            description   : "{$label} added from Today's Actions: " . Str::limit($validated['text'], 80),
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/birthday-whatsapp  (AJAX)
    //
    // One-click WhatsApp send for a Birthday Wishes row — replaces the Call
    // Workflow drawer for this category only (Sumit's call, 2026-07-06:
    // a birthday doesn't need a logged phone call). Reuses the exact same
    // template + token-building approach as RecallEngineService::composeMessage()
    // and the exact same send path (OutboundMessageService::sendText(), DPDP
    // consent-gated) as MissedCallsController::bulkWhatsapp(). No outcome
    // logging/checklist — send, and the row is marked done.
    // ─────────────────────────────────────────────────────────────────────

    public function sendBirthdayWhatsapp(Request $request, OutboundMessageService $outbound): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
        ]);

        $patient = Patient::find($validated['patient_id']);

        if (! $patient || ! $patient->phone) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found or has no phone number on file.',
            ], 404);
        }

        try {
            // Same token set RecallEngineService::composeMessage('birthday', ...) builds,
            // so the wording matches whatever staff already see in the Recall Pipeline.
            $tokens = [
                'PatientName'      => $patient->name,
                'PatientFirstName' => explode(' ', trim($patient->name))[0] ?? $patient->name,
                'ContactNumber'    => $patient->phone,
                'Age'              => (string) ($patient->date_of_birth?->age ?? ''),
                'ClinicName'       => AppSetting::get('clinic_name', config('clinic.name', 'the clinic')),
            ];

            $template = MessageTemplate::query()->ofType('birthday')->active()->first();
            $body = $template
                ? $template->renderBody($tokens)
                : "Happy Birthday, {$tokens['PatientFirstName']}! Wishing you a wonderful year ahead from all of us at {$tokens['ClinicName']}.";

            $result = $outbound->sendText($patient->phone, $body, [
                'category'     => 'service',
                'patient_id'   => $patient->id,
                'contact_name' => $patient->name,
            ]);

            if (! $result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['reason'] ?? 'Could not send WhatsApp message (consent not granted or send failed).',
                ], 422);
            }

            $this->activityEngine->log(
                subject       : $patient,
                event         : 'whatsapp.sent',
                actor         : auth()->user(),
                metadata      : [
                    'category' => 'birthdays',
                    'purpose'  => 'birthday_greeting',
                    'source'   => 'today_actions',
                ],
                relationshipId: $patient->relationship_id ?? null,
                description   : 'Birthday WhatsApp greeting sent from Today\'s Actions',
            );

            // Same 2026-07-08 fix as logAction() below — without this, the row
            // faded client-side (Alpine `actioned[]`) but nothing told
            // birthdays() to stop bringing this patient back on refresh.
            TodayActionDismissal::updateOrCreate(
                [
                    'category'           => 'birthdays',
                    'subject_type'       => Patient::class,
                    'subject_id'         => $patient->id,
                    'dismissed_for_date' => \Illuminate\Support\Carbon::today()->toDateString(),
                ],
                [
                    'reason_key'   => 'whatsapp_sent',
                    'notes'        => null,
                    'dismissed_by' => auth()->id(),
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('TodayController::sendBirthdayWhatsapp failed', [
                'patient_id' => $validated['patient_id'],
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not send WhatsApp message. Please try again.',
            ], 500);
        }
    }
}
