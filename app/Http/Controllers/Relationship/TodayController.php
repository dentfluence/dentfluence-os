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
use App\Services\Communication\WhatsAppLinkService;
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
        // 2026-08-26 (Sumit): this and 'follow_up_calls' are the same job —
        // ring a patient about their treatment. They were split across two
        // bands with unrelated names, so the board read as two separate kinds
        // of work. Same key, same producer (TreatmentVisit), same dismissal
        // and Settings toggle — only the name and its position change.
        'wellness_check_yesterday'     => 'Follow-up Calls — Treated Yesterday',
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
        'lab_ready'                    => 'Lab Work Ready',
        'payment_reminders'            => 'Payment Reminders',
        'appointment_reminders_tomorrow'=> "Tomorrow Morning's Appointments",
        'completed_calls'               => 'Completed Calls',
        // Renamed 2026-07-08 (Sumit) — this is the catch-all bucket for
        // manually-added calls that aren't a patient recall/follow-up
        // (vendor/lab/doctor/other) — "Other Calls" reads more plainly than
        // the old "Logged Communications". Same category key, same data.
        'logged_communications'         => 'Other Calls',
        // Sprint A / G-27 (2026-08-24): automation-created follow-up tasks.
        'tasks'                         => 'Follow-up Tasks',
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
        'lab_ready'                    => 'ti-flask',
        'payment_reminders'            => 'ti-receipt-2',
        'appointment_reminders_tomorrow'=> 'ti-calendar-event',
        'completed_calls'               => 'ti-circle-check',
        'logged_communications'         => 'ti-phone-outgoing',
        'tasks'                         => 'ti-checklist',
    ];

    /**
     * Information architecture (2026-08-25, Sumit) — the three bands the
     * board reads in. Presentation only: the engine still generates every
     * category, the controller only decides ORDER and INCLUSION.
     *
     *   essential — immediate clinic operations, must be worked today
     *   growth    — leads / revenue opportunities (new enquiries stay HERE,
     *               and stay visible: a fresh enquiry has a 30-minute
     *               response window, CEO decision 2026-08-25)
     *   other     — secondary reminders and relationship maintenance
     */
    private const CATEGORY_GROUPS = [
        // category => [band, rank within that band]  (Sumit's exact order)
        'appointment_reminders_today'    => ['essential', 1],
        'appointment_reminders_tomorrow' => ['essential', 2],
        'appointment_reminders'          => ['essential', 3],
        'follow_up_calls'                => ['essential', 4],
        // Directly under follow_up_calls, not adrift in Other Reminders:
        // a patient treated yesterday is the most time-critical follow-up
        // call the clinic makes.
        'wellness_check_yesterday'       => ['essential', 5],
        'lab_ready'                      => ['essential', 6],
        'missed_appointments_yesterday'  => ['essential', 7],

        'new_enquiries'                  => ['growth', 1],
        'lead_followups'                 => ['growth', 2],
        'opportunities'                  => ['growth', 3],
        'membership_renewals'            => ['growth', 4],
        'missed_calls_yesterday'         => ['growth', 5],

        'recall_calls'                   => ['other', 1],
        'payment_reminders'              => ['other', 2],
        'pending_estimates'              => ['other', 3],
        'logged_communications'          => ['other', 4],
        'tasks'                          => ['other', 5],
        'completed_calls'                => ['other', 6],
    ];

    /** Band render order + human label. */
    public const GROUP_ORDER = [
        'essential' => ['rank' => 1, 'label' => 'Most Important',       'sub' => 'Immediate clinic actions'],
        'growth'    => ['rank' => 2, 'label' => 'Leads & Opportunities', 'sub' => 'Growth and revenue opportunities'],
        'other'     => ['rank' => 3, 'label' => 'Other Reminders',       'sub' => 'Secondary follow-ups and reminders'],
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
        'tasks'                        => 10, // automation follow-up tasks — same tier as opportunities
        'appointment_reminders'        => 14, // fallback bucket, only used if today/tomorrow split fails
        'appointment_reminders_tomorrow'=> 15, // confirm tomorrow morning's sessions — last
    ];

    public function __construct(
        private readonly TodayActionsEngine    $engine,
        private readonly TodayActionsProjector $projector,
        private readonly ActivityEngine        $activityEngine,
        private readonly \App\Services\Relationship\OutcomeAutomationService $outcomeAutomation,
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
                // includeDone: rows already handled today come back annotated
                // with a 'done' key so the board can render them faded with
                // their outcome instead of silently dropping them (2026-07-14).
                // dueWindow 'today' (Sprint A, 2026-08-24): the board shows
                // only work due TODAY — overdue call-debt lives on the
                // Pending Calls board instead of padding this one forever.
                $raw = $this->engine->generate(includeDone: true, dueWindow: 'today');
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

        $responseOpts = $this->buildResponseOptions();

        if ($mode === 'today') {
            // Stamp each open row with its most recent call.logged activity from
            // today (if any) — so a "No answer" attempt is visible on the board —
            // resolve human labels for both that and the engine's 'done' info,
            // and sink done rows to the bottom of their card.
            $this->annotateCallState($raw, $today, $responseOpts);

            // ── Sprint A (2026-08-24): Today vs Pending split ─────────────
            // Today's Actions = what is due TODAY. An open call whose due
            // date has already passed is not "today's work" — it is missed
            // work, and it moves to the Pending Calls board (same cards,
            // /relationship/today/pending) instead of silently padding this
            // one forever. Items with no due_date (fresh enquiries, today's
            // appointments, lab-ready, …) always belong to Today. Done rows
            // stay on Today so finished work reads as finished.
            // Projection-path safety net: the projector snapshot is combined
            // (no due-window), so strip overdue items from the board here.
            // On the live-engine path the window already excluded them and
            // this is a no-op.
            $this->extractPendingItems($raw, $today->toDateString());

            // Badge count is UNCAPPED and query-based — never derived from
            // the capped board sample (the "+1760 invisible" bug class).
            $pendingCount = $this->engine->pendingCallsCount();
        }

        // ── Settings -> Today's Actions (2026-08-25) ────────────────────
        // PRESENTATION/INCLUSION ONLY. The engine has already generated
        // everything; these settings decide only what reaches the board.
        // Nothing here changes generation, dedup, dismissal or the drawer.
        $hidden = $this->hiddenCategories();

        if (AppSetting::get('today.hide_birthdays', '1') === '1') {
            $this->stripBirthdayRows($raw);
        }

        // Build enriched groups array for the view
        $groups = [];
        foreach ($raw as $key => $items) {
            if (in_array($key, $hidden, true)) {
                continue; // hidden in Settings -> Today's Actions
            }
            $doneCount = count(array_filter($items, fn ($i) => ! empty($i['done'])));

            $groups[$key] = [
                'key'        => $key,
                'label'      => self::CATEGORY_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'icon'       => self::CATEGORY_ICONS[$key] ?? 'ti-circle',
                'items'      => $items,
                'count'      => count($items) - $doneCount, // open items only
                'done_count' => $doneCount,
                'priority'   => self::CATEGORY_PRIORITY[$key] ?? 99,
                'group'       => self::bandOf($key),
                'group_rank'  => self::GROUP_ORDER[self::bandOf($key)]['rank'],
                'group_label' => self::GROUP_ORDER[self::bandOf($key)]['label'],
                'group_sub'   => self::GROUP_ORDER[self::bandOf($key)]['sub'],
                'group_order' => self::CATEGORY_GROUPS[$key][1] ?? 99,
            ];
        }

        // Sort groups: non-empty first (a card with only done items still
        // counts as non-empty — staff should see it finished, not gone),
        // then by priority
        uasort($groups, function ($a, $b) {
            $aEmpty = $a['count'] === 0 && ($a['done_count'] ?? 0) === 0;
            $bEmpty = $b['count'] === 0 && ($b['done_count'] ?? 0) === 0;

            if ($aEmpty !== $bEmpty) {
                return $aEmpty ? 1 : -1; // empty groups go to bottom
            }

            // Band first (Most Important -> Leads & Opportunities -> Other
            // Reminders), then the clinic's existing within-band priority.
            return [$a['group_rank'], $a['group_order']]
               <=> [$b['group_rank'], $b['group_order']];
        });

        $totalCount    = array_sum(array_column($groups, 'count')); // open items only
        $pendingCount  = $pendingCount ?? 0;
        $boardMode     = 'today';
        $checklists    = config('relationship_rules.call_checklists', []);
        $nextActions   = $this->buildNextActions();
        $requiresNotesMap = $this->buildRequiresNotesMap();
        $closesTaskMap  = $this->buildClosesTaskMap();
        $callResults    = $this->buildCallResults($responseOpts);
        $dismissReasons = ActionOptionList::query()->dismissReasons()->get()->values();

        return view('relationship.today.index', compact(
            'groups',
            'totalCount',
            'checklists',
            'responseOpts',
            'nextActions',
            'requiresNotesMap',
            'closesTaskMap',
            'callResults',
            'dismissReasons',
            'selectedDate',
            'mode',
            'today',
            'pendingCount',
            'boardMode',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /relationship/today/pending — Pending Calls (Sprint A, 2026-08-24)
    //
    // The exception/backlog board: open call-debt whose due date has passed.
    // Same engine, same cards, same drawer as Today's Actions — ONLY the due
    // window differs (Today = due today · Pending = due earlier, still open).
    // It should trend toward empty; a growing Pending board is the
    // accountability signal that calls are being missed, not a second to-do
    // list. Completing an item here goes through the exact same logAction /
    // dismiss endpoints, so outcomes, automations and attribution are
    // identical to Today.
    // ─────────────────────────────────────────────────────────────────────

    public function pending(Request $request): \Illuminate\View\View
    {
        $today = \Illuminate\Support\Carbon::today();

        // Same source selection as index()'s today path.
        if (Feature::enabled('today.projection')) {
            $projected = $this->projector->grouped();
            $raw = [];
            foreach (array_keys(self::CATEGORY_LABELS) as $key) {
                $raw[$key] = $projected[$key] ?? [];
            }
            foreach ($projected as $key => $items) {
                if (! array_key_exists($key, $raw)) {
                    $raw[$key] = $items;
                }
            }
        } else {
            $raw = $this->engine->generate(includeDone: false, dueWindow: 'overdue');
        }

        $responseOpts = $this->buildResponseOptions();
        $this->annotateCallState($raw, $today, $responseOpts);

        // Keep ONLY overdue open items — the mirror image of index().
        $todayStr = $today->toDateString();
        foreach ($raw as $key => $items) {
            $raw[$key] = array_values(array_filter($items, function ($item) use ($todayStr) {
                return empty($item['done'])
                    && ! empty($item['due_date'])
                    && $item['due_date'] < $todayStr;
            }));
        }

        $groups = [];
        foreach ($raw as $key => $items) {
            if ($items === []) {
                continue; // Pending shows only categories that actually have backlog
            }
            $groups[$key] = [
                'key'        => $key,
                'label'      => self::CATEGORY_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'icon'       => self::CATEGORY_ICONS[$key] ?? 'ti-circle',
                'items'      => $items,
                'count'      => count($items),
                'done_count' => 0,
                'priority'   => self::CATEGORY_PRIORITY[$key] ?? 99,
                'group'       => self::bandOf($key),
                'group_rank'  => self::GROUP_ORDER[self::bandOf($key)]['rank'],
                'group_label' => self::GROUP_ORDER[self::bandOf($key)]['label'],
                'group_sub'   => self::GROUP_ORDER[self::bandOf($key)]['sub'],
                'group_order' => self::CATEGORY_GROUPS[$key][1] ?? 99,
            ];
        }
        uasort($groups, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        $totalCount       = array_sum(array_column($groups, 'count'));
        $checklists       = config('relationship_rules.call_checklists', []);
        $nextActions      = $this->buildNextActions();
        $requiresNotesMap = $this->buildRequiresNotesMap();
        $closesTaskMap    = $this->buildClosesTaskMap();
        $callResults      = $this->buildCallResults($responseOpts);
        $dismissReasons   = ActionOptionList::query()->dismissReasons()->get()->values();
        $selectedDate     = $today->copy();
        $mode             = 'today';        // reuse the live-board rendering path
        $pendingCount     = $totalCount;
        $boardMode        = 'pending';

        return view('relationship.today.index', compact(
            'groups',
            'totalCount',
            'checklists',
            'responseOpts',
            'nextActions',
            'requiresNotesMap',
            'closesTaskMap',
            'callResults',
            'dismissReasons',
            'selectedDate',
            'mode',
            'today',
            'pendingCount',
            'boardMode',
        ));
    }

    /**
     * Remove overdue open call-debt from the Today board, returning how many
     * items moved (they render on /relationship/today/pending instead).
     * Only items carrying a normalized due_date participate — categories
     * without one (today's appointments, lab ready, new enquiries, …) are
     * always today-work by construction. Done rows are never moved.
     */
    private function extractPendingItems(array &$raw, string $todayStr): int
    {
        $moved = 0;

        foreach ($raw as $key => $items) {
            $kept = [];
            foreach ($items as $item) {
                $isOverdue = empty($item['done'])
                    && ! empty($item['due_date'])
                    && $item['due_date'] < $todayStr;

                if ($isOverdue) {
                    $moved++;
                } else {
                    $kept[] = $item;
                }
            }
            $raw[$key] = $kept;
        }

        return $moved;
    }

    /**
     * Board display state (2026-07-14): resolve human labels for the engine's
     * 'done' annotations, and stamp still-open rows with the latest call.logged
     * activity from today (a non-closing outcome like "No answer" keeps the
     * row open — but staff should still see that an attempt happened and what
     * the patient's response was). One batched query, no N+1.
     */
    private function annotateCallState(array &$raw, \Illuminate\Support\Carbon $today, array $responseOpts): void
    {
        // ── Collect subjects across the whole board ──────────────────────
        $patientIds = [];
        $leadIds    = [];
        foreach ($raw as $items) {
            foreach ($items as $item) {
                if (! empty($item['patient_id'])) {
                    $patientIds[] = $item['patient_id'];
                } elseif (! empty($item['lead_id'])) {
                    $leadIds[] = $item['lead_id'];
                }
            }
        }

        // ── Latest call.logged per (subject, category), today ────────────
        $lastCalls = [];
        if ($patientIds || $leadIds) {
            Activity::query()
                ->with('actor:id,name')
                ->where('event', 'call.logged')
                ->whereDate('occurred_at', $today->toDateString())
                ->where(function ($q) use ($patientIds, $leadIds) {
                    if ($patientIds) {
                        $q->orWhere(fn ($q2) => $q2->where('subject_type', Patient::class)
                            ->whereIn('subject_id', array_unique($patientIds)));
                    }
                    if ($leadIds) {
                        $q->orWhere(fn ($q2) => $q2->where('subject_type', Lead::class)
                            ->whereIn('subject_id', array_unique($leadIds)));
                    }
                })
                ->orderBy('occurred_at') // chronological — the latest log wins below
                ->get()
                ->each(function (Activity $act) use (&$lastCalls) {
                    $prefix = $act->subject_type === Patient::class ? 'P' : 'L';
                    $key    = $prefix . ':' . $act->subject_id . '|' . ($act->metadata['category'] ?? '');

                    $lastCalls[$key] = [
                        // 'response' = web drawer log; 'outcome' = the shared
                        // OutcomeAutomationService (Sprint A) — accept both.
                        'outcome' => $act->metadata['response'] ?? $act->metadata['outcome'] ?? null,
                        'notes'   => $act->metadata['notes'] ?? null,
                        'at'      => $act->occurred_at?->format('g:i A'),
                        'by'      => $act->actor?->name,
                    ];
                });
        }

        // ── Stamp items + resolve labels + sink done rows to the bottom ──
        foreach ($raw as $groupKey => &$items) {
            foreach ($items as &$item) {
                $category = $item['category'] ?? $groupKey;

                if (! empty($item['done'])) {
                    $item['done']['label'] = $this->outcomeLabel($category, $item['done']['outcome'], $responseOpts);
                    continue;
                }

                $subjectKey = ! empty($item['patient_id'])
                    ? 'P:' . $item['patient_id']
                    : (! empty($item['lead_id']) ? 'L:' . $item['lead_id'] : null);

                if ($subjectKey && isset($lastCalls[$subjectKey . '|' . $category])) {
                    $item['last_call'] = $lastCalls[$subjectKey . '|' . $category];
                    $item['last_call']['label'] = $this->outcomeLabel($category, $item['last_call']['outcome'], $responseOpts);
                }
            }
            unset($item);

            usort($items, fn ($a, $b) => (int) ! empty($a['done']) <=> (int) ! empty($b['done']));
        }
        unset($items);
    }

    /** Human label for an outcome/reason key, using the same category => options map the drawer uses. */
    private function outcomeLabel(string $category, ?string $key, array $responseOpts): string
    {
        if (blank($key)) {
            return 'Completed';
        }

        return match ($key) {
            'closed_manually' => 'Closed',
            'whatsapp_sent'   => 'WhatsApp sent',
            'completed'       => 'Completed',
            default           => ($responseOpts[$category][$key]
                ?? $responseOpts['default'][$key]
                ?? ucwords(str_replace('_', ' ', $key))),
        };
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
    /**
     * Categories switched off in Settings -> Today's Actions.
     * Default: everything visible. Read-only presentation gate — the engine
     * still generates these rows, they simply do not reach this board.
     */
    /**
     * The board's category vocabulary + its band, for Settings.
     * Single source: CATEGORY_LABELS / CATEGORY_GROUPS on this controller —
     * Settings must never keep its own copy of this list.
     */
    /** Which band a category belongs to. */
    private static function bandOf(string $key): string
    {
        return self::CATEGORY_GROUPS[$key][0] ?? 'other';
    }

    public static function boardCategories(): array
    {
        $out = [];

        foreach (self::CATEGORY_LABELS as $key => $label) {
            $group = self::bandOf($key);

            $out[$key] = [
                'label'       => $label,
                'group'       => $group,
                'group_rank'  => self::GROUP_ORDER[$group]['rank'],
                'group_label' => self::GROUP_ORDER[$group]['label'],
                'order'       => self::CATEGORY_GROUPS[$key][1] ?? 99,
            ];
        }

        uasort($out, fn ($a, $b) => [$a['group_rank'], $a['order']] <=> [$b['group_rank'], $b['order']]);

        return $out;
    }

    private function hiddenCategories(): array
    {
        $hidden = [];

        foreach (array_keys(self::CATEGORY_LABELS) as $key) {
            if (AppSetting::get("today.show.{$key}", '1') !== '1') {
                $hidden[] = $key;
            }
        }

        return $hidden;
    }

    /**
     * Birthday suppression (2026-08-25, Sumit).
     *
     * Birthdays are NOT a board category — they arrive through two separate
     * producers and were duplicating each other in the queue:
     *   1. RecallEngineService::recallBirthday() queues a CommunicationQueue
     *      row with purpose = 'recall_birthday'  -> surfaces in recall_calls
     *   2. RulesEngine rule 'birthday_3d' creates a system Task
     *      (description "[Auto] Rule: birthday_3d") -> surfaces in tasks
     *
     * This strips both at the VIEW layer only. Neither producer is touched,
     * nothing is disabled, and no third producer is introduced — turning the
     * setting off brings the same rows straight back.
     */
    private function stripBirthdayRows(array &$raw): void
    {
        foreach (['recall_calls', 'tasks'] as $key) {
            if (empty($raw[$key])) {
                continue;
            }

            $raw[$key] = array_values(array_filter($raw[$key], function (array $item) {
                if (($item['meta']['purpose'] ?? null) === 'recall_birthday') {
                    return false;
                }

                $haystack = strtolower(
                    ($item['suggested_action'] ?? '') . ' ' . ($item['meta']['category'] ?? '')
                );

                return ! str_contains($haystack, 'birthday');
            }));
        }
    }

    /**
     * CALL RESULT — the two-step drawer vocabulary (2026-08-26, Sumit).
     *
     * Reception should record ONE thing first: did the call connect? Only
     * then does "what did the patient say?" make sense. That split is not a
     * new concept — CommunicationQueue has drawn exactly this line since the
     * mobile picker shipped (CALL_OUTCOMES_CONNECTED vs
     * CALL_OUTCOMES_NOT_CONNECTED, see callOutcomeGroups()). The web drawer
     * was the odd one out, flattening both into a single confusing dropdown
     * next to a separate "Suggestion" and "Patient Response" note type.
     *
     * NOTHING new is stored. Every option below is an existing
     * ActionOptionList row (Settings > Call Outcomes remains the single
     * source of truth for labels, requires_notes and closes_task); this only
     * decides which of the four buckets each key is presented under. A key
     * not named here is a CONNECTED outcome by definition, so it lands in
     * "answered" and becomes a PATIENT RESPONSE choice.
     *
     * Keys are matched against the category's own configured outcomes only —
     * we never inject an option a clinic has switched off.
     */
    private const CONTACT_RESULT_KEYS = [
        'no_answer'         => ['no_answer', 'voicemail', 'not_reachable'],
        'unable_to_connect' => ['busy', 'switched_off', 'out_of_coverage', 'rejected'],
        'wrong_number'      => ['wrong_number', 'invalid_number'],
    ];

    /** Display order + labels for the four result buttons. */
    public const CONTACT_RESULTS = [
        'answered'          => 'Answered',
        'no_answer'         => 'No Answer',
        'unable_to_connect' => 'Unable to Connect',
        'wrong_number'      => 'Wrong Number',
    ];

    /**
     * category => result bucket => [outcome key => label].
     *
     * "answered" holds the PATIENT RESPONSE choices. A bucket with exactly one
     * key needs no second step — picking the button IS the outcome. A bucket
     * with none is not offered at all for that category (e.g. a clinic that
     * has removed "Wrong number" from payment reminders simply does not see
     * that button), because inventing an option the clinic has not configured
     * would submit an outcome with no closes_task rule behind it.
     */
    private function buildCallResults(array $responseOpts): array
    {
        $out = [];

        foreach ($responseOpts as $category => $options) {
            $buckets = ['answered' => [], 'no_answer' => [], 'unable_to_connect' => [], 'wrong_number' => []];

            foreach ($options as $key => $label) {
                $bucket = 'answered';

                foreach (self::CONTACT_RESULT_KEYS as $name => $keys) {
                    if (in_array($key, $keys, true)) {
                        $bucket = $name;
                        break;
                    }
                }

                $buckets[$bucket][$key] = $label;
            }

            $out[$category] = $buckets;
        }

        return $out;
    }

    /**
     * category => [key => bool] — whether logging this outcome completes the
     * action. Read straight off ActionOptionList (the same column logAction()
     * enforces), so the drawer can tell staff in plain words what will happen
     * BEFORE they save: "Marks this action complete" vs "Attempt recorded —
     * stays due for another try." Presentation only; the server remains the
     * authority.
     */
    private function buildClosesTaskMap(): array
    {
        $rows = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->active()
            ->get(['action_category', 'key', 'closes_task']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->action_category][$row->key] = (bool) $row->closes_task;
        }

        return $map;
    }

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
            // Who placed the call. 'inbound' = the patient rang US — the
            // callback case. Optional and defaulted, so every existing caller
            // (mobile, API, older cached JS) keeps working unchanged.
            'direction'       => ['nullable', 'in:outbound,inbound'],
        ]);

        $direction = $validated['direction'] ?? 'outbound';

        // ── Callback resolution (2026-08-26) ─────────────────────────────
        // An inbound confirmation is not an ordinary outbound confirmation:
        // the audit trail has to read "Patient called back - confirmed" so an
        // owner can see the difference between staff reaching the patient and
        // the patient rescuing a missed attempt. That outcome key already
        // exists (added 2026-08-25 for exactly this workflow); the drawer now
        // reaches it via direction + response instead of asking reception to
        // find it in a flat list of outcomes. The ORIGINAL attempt is a
        // separate Activity row and is never touched.
        if ($direction === 'inbound'
            && $validated['category'] === 'appointment_reminders'
            && $validated['response'] === 'confirmed_attendance'
            && ActionOptionList::query()
                ->where('option_type', 'call_outcome')
                ->where('action_category', 'appointment_reminders')
                ->where('key', 'patient_called_back_confirmed')
                ->active()
                ->exists()) {
            $validated['response'] = 'patient_called_back_confirmed';
        }

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

        // ── PRE Sprint A (2026-08-24) — ONE OUTCOME PATH ──────────────────
        // Queue-backed rows route through OutcomeAutomationService, the same
        // engine the mobile Activity Completion Bottom Sheet uses — so an
        // outcome behaves identically on web and mobile: "will call back"
        // reschedules follow_up_date (+2d) instead of closing forever,
        // "wrong number" marks the contact invalid, "deceased" disables all
        // automations, "not interested" books the 12-month preventive recall.
        // The service logs the Activity entry itself (actor + timestamp), so
        // this branch must NOT also log one (no double Timeline rows).
        // Computed categories (leads, opportunities, memberships, …) keep the
        // existing closes_task/dismissal path below — the service only speaks
        // CommunicationQueue.
        $serviceOutcome = self::WEB_OUTCOME_TO_SERVICE[$validated['response']] ?? $validated['response'];

        if (in_array($validated['category'], self::QUEUE_BACKED_CATEGORIES, true)
            && ! empty($validated['subject_id'])
            && array_key_exists($serviceOutcome, CommunicationQueue::allCallOutcomes())) {

            $comm = CommunicationQueue::find($validated['subject_id']);

            if ($comm) {
                // Idempotency guard: a double-submit (or a simultaneous mobile
                // completion) must not re-run automations on a closed row.
                if ($comm->status === 'closed') {
                    return response()->json([
                        'success'           => true,
                        'closed'            => true,
                        'next_action_label' => 'Already completed',
                    ]);
                }

                try {
                    $result = $this->outcomeAutomation->apply(
                        comm:    $comm,
                        outcome: $serviceOutcome,
                        actor:   $request->user(),
                        options: ['notes' => $validated['notes'] ?? null, 'direction' => $direction],
                    );

                    $freshStatus = $comm->fresh()->status;

                    return response()->json([
                        'success'           => true,
                        // "closed" here means "leaves the pending board":
                        // closed outright, or rescheduled/waiting (the
                        // requeue-due pass brings it back when due).
                        'closed'            => in_array($freshStatus, ['closed', 'waiting_for_patient'], true),
                        'next_action_label' => config('relationship_rules.next_actions.' . $validated['response'])
                            ?? $validated['next_action']
                            ?? 'Logged',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('TodayController::logAction outcome automation failed', [
                        'comm_id' => $comm->id,
                        'error'   => $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not log action. Please try again.',
                    ], 500);
                }
            }
            // Row not found (deleted between render and submit) — fall through
            // to the generic path so the activity is still recorded.
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
                        'direction'   => $direction,
                        'source'      => 'today_actions',
                    ],
                    relationshipId: $validated['relationship_id'] ?? null,
                    description   : ($direction === 'inbound' ? 'Inbound call' : 'Outbound call')
                        . ' logged from Today\'s Actions: ' . $validated['response'],
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

        if ($category === 'tasks') {
            // Sprint A / G-27 (2026-08-24): completing a system task from the
            // board marks the Task itself done — same store TaskEngine dedups
            // against, so the rule will not immediately recreate it.
            if ($subjectId) {
                \App\Models\Task::where('id', $subjectId)
                    ->where('task_type', 'system')
                    ->update(['status' => 'done', 'done_at' => now()]);
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
        // 'birthdays' retired from the board (2026-07-26) — a category that
        // cannot appear cannot be dismissed.
        'lab_ready'                     => LabCase::class,
        'payment_reminders'             => Invoice::class,
        'wellness_check_yesterday'      => TreatmentVisit::class,
        'new_enquiries'                 => Lead::class,
        'lead_followups'                => Lead::class,
        // 2026-07-26: follow_up_calls was missing here, so an individual
        // Dismiss on a Follow-up Call card was rejected with "This category
        // cannot be dismissed" even for a user holding relationship,edit.
        // Dismiss ≠ complete: it suppresses the card for TODAY via
        // TodayActionDismissal (auditable, reason + actor recorded) and leaves
        // the FollowUp row pending, so a still-due follow-up returns tomorrow.
        // Completing it remains the Log/Close path (closeUnderlyingRecord).
        'follow_up_calls'               => FollowUp::class,
        // Sprint A / G-27 (2026-08-24): system tasks (automation output) are
        // now on the board; Dismiss suppresses for today, Log/Close completes
        // the task itself (closeUnderlyingRecord).
        'tasks'                         => \App\Models\Task::class,
    ];

    /** category keys whose Today's Actions row is backed by a communication_queue record. */
    private const QUEUE_BACKED_CATEGORIES = ['recall_calls', 'missed_calls_yesterday', 'logged_communications'];

    /**
     * Sprint A (2026-08-24): the web drawer's outcome vocabulary
     * (config/relationship_rules.php response_options + Settings) predates
     * the mobile one (CommunicationQueue::allCallOutcomes()). Where both
     * describe the same real-world outcome, translate web → service so ONE
     * automation path runs. Keys with no equivalent (voicemail, custom
     * clinic-added outcomes) keep the legacy closes_task path.
     */
    private const WEB_OUTCOME_TO_SERVICE = [
        'connected_booked'         => 'appointment_booked',
        'connected_callback'       => 'will_call_back',
        'connected_not_interested' => 'not_interested',
    ];

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
            // 2026-07-26: this used to hardcode 'recall_calls' and
            // 'missed_calls_yesterday' while the QUEUE_BACKED_CATEGORIES
            // constant (used by logAction/closeAction) already listed three —
            // so dismissing a Logged Communication was refused outright. One
            // constant now governs all three actions.
            if (in_array($validated['category'], self::QUEUE_BACKED_CATEGORIES, true)) {
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
            'category'   => ['nullable', 'string'],
        ]);

        $subject = $this->resolveNoteSubject($validated['patient_id'] ?? null, $validated['lead_id'] ?? null);

        if (! $subject) {
            return response()->json(['success' => true, 'notes' => [], 'interactions' => []]);
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

        return response()->json([
            'success'      => true,
            'notes'        => $notes,
            'interactions' => $this->interactionHistory($subject, $validated['category'] ?? null),
        ]);
    }

    /**
     * INTERACTION HISTORY — owner/admin audit for one action (2026-08-26).
     *
     * The question this answers, in one read: who called, when, what came of
     * it, who took the callback, what the patient said. Every row already
     * exists in `activities`; nothing new is written and no new table, column
     * or route is introduced — this simply reads the three events that make up
     * a call's story instead of only the note events.
     *
     *   call.logged            staff recorded a result (outbound or inbound)
     *   call.inbound           the patient rang in via Communication
     *   today_action.note_added  a free-text note
     *
     * Chronological ASCENDING on purpose: the first attempt must stay at the
     * top and stay visible. A later interaction NEVER replaces an earlier one
     * — that is the whole point of the callback scenario.
     *
     * @return array<int, array<string, mixed>>
     */
    private function interactionHistory(Model $subject, ?string $category): array
    {
        $responseOpts = $this->buildResponseOptions();

        $rows = Activity::query()
            ->with('actor:id,name')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->whereIn('event', ['call.logged', 'call.inbound', 'today_action.note_added'])
            ->where('occurred_at', '>=', now()->subDays(60))
            ->orderBy('occurred_at')
            ->limit(40)
            ->get();

        $out = [];

        foreach ($rows as $act) {
            $meta = $act->metadata ?? [];

            // Scope call results to the action being viewed; notes and inbound
            // calls are not category-tagged, so they always show.
            if ($act->event === 'call.logged'
                && $category
                && ! empty($meta['category'])
                && $meta['category'] !== $category) {
                continue;
            }

            $outcomeKey = $meta['response'] ?? $meta['outcome'] ?? null;

            if ($act->event === 'today_action.note_added') {
                $kind      = 'note';
                $direction = null;
                $label     = ($meta['note_type'] ?? 'suggestion') === 'response'
                    ? 'Patient response noted'
                    : 'Note added';
            } elseif ($act->event === 'call.inbound') {
                $kind      = 'call';
                $direction = 'inbound';
                $label     = 'Inbound call received';
            } else {
                $kind      = 'call';
                $direction = $meta['direction'] ?? 'outbound';
                $label     = $this->outcomeLabel($meta['category'] ?? ($category ?? 'default'), $outcomeKey, $responseOpts);
            }

            $out[] = [
                'kind'      => $kind,
                'direction' => $direction,
                'label'     => $label,
                'notes'     => $meta['notes'] ?? $meta['text'] ?? null,
                'actor'     => $act->actor?->name ?? 'System',
                'at'        => $act->occurred_at?->format('d M, g:i A'),
                'time'      => $act->occurred_at?->format('g:i A'),
            ];
        }

        return $out;
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

    public function sendBirthdayWhatsapp(Request $request, WhatsAppLinkService $link): JsonResponse
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

            // Interim click-to-chat: instead of the (parked) WhatsApp Cloud API,
            // return a wa.me link for staff to send from their own WhatsApp.
            // Consent is still enforced exactly as the API path would have been
            // (shadow-logged unless guard.consent_required is on).
            $decision = $link->guardDecision($patient, 'service');
            if (! $decision['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $decision['reason'] ?? 'WhatsApp consent required before sending.',
                ], 422);
            }

            $waUrl = $link->url($patient->phone, $body);

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

            return response()->json(['success' => true, 'url' => $waUrl]);
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
