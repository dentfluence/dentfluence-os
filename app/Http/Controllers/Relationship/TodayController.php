<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use App\Services\Whatsapp\OutboundMessageService;
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
        'logged_communications'         => 'Logged Communications',
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
        $responseOpts  = config('relationship_rules.response_options', []);
        $nextActions   = config('relationship_rules.next_actions', []);

        return view('relationship.today.index', compact(
            'groups',
            'totalCount',
            'checklists',
            'responseOpts',
            'nextActions',
            'selectedDate',
            'mode',
            'today',
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
