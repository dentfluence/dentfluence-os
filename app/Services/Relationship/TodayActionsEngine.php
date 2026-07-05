<?php

namespace App\Services\Relationship;

use App\Models\Appointment;
use App\Models\CommunicationQueue;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentVisit;
use App\Models\Finance\FinancePatientMembership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * TodayActionsEngine
 *
 * The most important page in Dentfluence: everything reception
 * needs to do today, generated automatically from live data.
 *
 * 13 categories, each returning a flat array of action items.
 * Every item shares the same shape so the view is uniform.
 *
 * Item shape:
 * [
 *   'category'         => string,
 *   'patient_name'     => string,
 *   'patient_id'       => int|null,
 *   'lead_id'          => int|null,
 *   'relationship_id'  => int|null,
 *   'reason'           => string,      // one-line why they appear today
 *   'priority'         => 'high'|'medium'|'low',
 *   'suggested_action' => string,
 *   'primary_action'   => string|null, // optional; 'whatsapp' = one-click send
 *                                      // button instead of the Call drawer.
 *                                      // Absent/null = default 'call' behaviour.
 *   'link'             => string,      // route to open the record
 *   'meta'             => array,       // extra context for the drawer
 * ]
 *
 * Usage:
 *   $actions = app(TodayActionsEngine::class)->generate();
 *   // $actions is an array keyed by category, each value = array of items
 */
class TodayActionsEngine
{
    public function __construct(
        private readonly YesterdayReviewService $yesterdayReview,
    ) {}

    /**
     * Generate all today's actions, grouped by category.
     *
     * @return array<string, array>  Keys = category names; values = item arrays
     */
    public function generate(): array
    {
        $groups = [];

        // Run all categories — each is fault-tolerant (errors return [])
        $categories = [
            'new_enquiries'                => fn () => $this->newEnquiries(),
            'lead_followups'               => fn () => $this->leadFollowups(),
            'opportunities'                => fn () => $this->opportunities(),
            'recall_calls'                 => fn () => $this->recallCalls(),
            'follow_up_calls'              => fn () => $this->followUpCalls(),
            'appointment_reminders'        => fn () => $this->appointmentReminders(),
            'pending_estimates'            => fn () => $this->pendingEstimates(),
            'membership_renewals'          => fn () => $this->membershipRenewals(),
            'birthdays'                    => fn () => $this->birthdays(),
            'lab_ready'                    => fn () => $this->labReady(),
            'payment_reminders'            => fn () => $this->paymentReminders(),
            'wellness_check_yesterday'     => fn () => $this->wellnessCheckYesterday(),
            'logged_communications'        => fn () => $this->loggedCommunications(),
        ];

        foreach ($categories as $key => $resolver) {
            try {
                $groups[$key] = $resolver();
            } catch (\Throwable $e) {
                Log::warning("TodayActionsEngine: category [{$key}] failed", ['error' => $e->getMessage()]);
                $groups[$key] = [];
            }
        }

        // Yesterday review — returns two sub-categories
        try {
            $yesterday = $this->yesterdayReview->generateYesterdayReview();
            $groups['missed_calls_yesterday']        = $yesterday['missed_calls'];
            $groups['missed_appointments_yesterday'] = $yesterday['missed_appointments'];
        } catch (\Throwable $e) {
            Log::warning('TodayActionsEngine: yesterday review failed', ['error' => $e->getMessage()]);
            $groups['missed_calls_yesterday']        = [];
            $groups['missed_appointments_yesterday'] = [];
        }

        return $groups;
    }

    /**
     * Flat list of all items (unsorted). Used for totalling counts.
     */
    public function generateFlat(): array
    {
        return array_merge(...array_values($this->generate()));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 1 — New Enquiries
    // Leads created in last 24 hours, stage = new_enquiry
    // ═══════════════════════════════════════════════════════════════════════

    private function newEnquiries(): array
    {
        $cutoff = Carbon::now()->subHours(24);

        return Lead::query()
            ->where('stage', 'new_enquiry')
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit($this->limit())
            ->get()
            ->map(fn (Lead $lead) => [
                'category'        => 'new_enquiries',
                'patient_name'    => $lead->name,
                'patient_id'      => null,
                'lead_id'         => $lead->id,
                'relationship_id' => $lead->relationship_id ?? null,
                'reason'          => 'New enquiry received ' . $lead->created_at->diffForHumans(),
                'priority'        => 'high',
                'suggested_action'=> 'Call within 30 minutes — first contact wins',
                // Phase 8 PRM Retirement (Slice 5) — link into PRE instead of the retired PRM board.
                'link'            => $lead->relationship_id
                    ? route('relationship.profile', $lead->relationship_id)
                    : route('relationship.pipeline'),
                'meta'            => [
                    'phone'        => $lead->phone,
                    'source'       => $lead->lead_source ?? $lead->source,
                    'treatment'    => $lead->treatment,
                    'ai_summary'   => $lead->ai_summary ?? null,
                    'created_at'   => $lead->created_at->format('d M Y H:i'),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 2 — Lead Follow-ups
    // followup_date = today or overdue, stage not in [converted, lost]
    // ═══════════════════════════════════════════════════════════════════════

    private function leadFollowups(): array
    {
        return Lead::query()
            ->whereNotNull('followup_date')
            ->where('followup_date', '<=', Carbon::today())
            ->whereNotIn('stage', ['converted', 'lost'])
            ->orderBy('followup_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (Lead $lead) => [
                'category'        => 'lead_followups',
                'patient_name'    => $lead->name,
                'patient_id'      => null,
                'lead_id'         => $lead->id,
                'relationship_id' => $lead->relationship_id ?? null,
                'reason'          => $lead->followup_date->isToday()
                    ? 'Follow-up due today'
                    : 'Follow-up overdue by ' . $lead->followup_date->diffForHumans(now(), true),
                'priority'        => $lead->followup_date->isPast() && !$lead->followup_date->isToday()
                    ? 'high' : 'medium',
                'suggested_action'=> 'Call and update lead stage',
                // Phase 8 PRM Retirement (Slice 5) — link into PRE instead of the retired PRM board.
                'link'            => $lead->relationship_id
                    ? route('relationship.profile', $lead->relationship_id)
                    : route('relationship.pipeline'),
                'meta'            => [
                    'phone'        => $lead->phone,
                    'stage'        => $lead->stage,
                    'followup_date'=> $lead->followup_date->format('d M Y'),
                    'treatment'    => $lead->treatment,
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 3 — Treatment Opportunities
    // follow_up_date <= today, status not in [completed, declined]
    // ═══════════════════════════════════════════════════════════════════════

    private function opportunities(): array
    {
        return TreatmentOpportunity::with('patient:id,name,phone,relationship_id')
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<=', Carbon::today())
            ->whereNotIn('status', ['completed', 'declined'])
            ->orderBy('follow_up_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (TreatmentOpportunity $opp) => [
                'category'        => 'opportunities',
                'patient_name'    => $opp->patient?->name ?? 'Unknown',
                'patient_id'      => $opp->patient_id,
                'lead_id'         => null,
                'relationship_id' => $opp->patient?->relationship_id ?? null,
                'reason'          => $opp->follow_up_date->isToday()
                    ? 'Opportunity follow-up due today'
                    : 'Opportunity overdue — ' . $opp->follow_up_date->diffForHumans(now(), true) . ' ago',
                'priority'        => $opp->isOverdue() ? 'high' : 'medium',
                'suggested_action'=> 'Call and confirm if patient wants to proceed',
                'link'            => route('patients.show', $opp->patient_id),
                'meta'            => [
                    'phone'          => $opp->patient?->phone,
                    'treatment'      => $opp->label ?? null,
                    'status'         => $opp->status,
                    'follow_up_date' => $opp->follow_up_date->format('d M Y'),
                    'value'          => $opp->estimated_value ?? null,
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 4 — Recall Calls
    // communication_queue where purpose contains 'recall', status = pending
    // ═══════════════════════════════════════════════════════════════════════

    private function recallCalls(): array
    {
        return CommunicationQueue::with('patient:id,name,phone,relationship_id')
            ->where(function ($q) {
                $q->where('purpose', 'like', '%recall%')
                  ->orWhere('purpose', 'recall_due')
                  ->orWhere('purpose', 'recall_birthday');
            })
            ->where('status', 'pending')
            ->orderBy('follow_up_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (CommunicationQueue $item) => [
                'category'        => 'recall_calls',
                'patient_name'    => $item->patient?->name ?? $item->person_name ?? 'Unknown',
                'patient_id'      => $item->patient_id,
                'lead_id'         => null,
                'relationship_id' => $item->patient?->relationship_id ?? null,
                'reason'          => $this->recallReason($item),
                'priority'        => $item->priority ?? 'medium',
                'suggested_action'=> 'Call and book a recall appointment',
                'link'            => $item->patient_id
                    ? route('patients.show', $item->patient_id)
                    : '#',
                'meta'            => [
                    'phone'          => $item->patient?->phone ?? $item->phone,
                    'comm_queue_id'  => $item->id,
                    'purpose'        => $item->purpose,
                    'follow_up_date' => $item->follow_up_date?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    /**
     * Reason text for a recall queue item — prefers the actual note stored
     * when the recall was queued (each automated trigger writes a specific,
     * useful note; manual recalls carry whatever the staff member typed),
     * falling back to a purpose-based label only if no note exists.
     * Previously this was a single hardcoded "last visit was over 6 months
     * ago" string for every recall regardless of why it was actually queued
     * — including manually-added ones, which made their notes invisible.
     */
    private function recallReason(CommunicationQueue $item): string
    {
        if ($item->note) {
            return $item->note;
        }

        return match ($item->purpose) {
            'recall_no_visit'      => 'Recall due — no visit in 6+ months',
            'recall_approved_plan' => 'Approved treatment plan — no appointment booked',
            'recall_post_op'       => 'Post-op follow-up due',
            'recall_lab_received'  => 'Lab work ready — no appointment booked',
            'recall_7day_followup' => '7-day post-treatment follow-up',
            'recall_birthday'      => 'Birthday re-engagement',
            'recall_manual'        => 'Manually added recall',
            default                => 'Recall due',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY — Follow-up Calls
    // FollowUp model — pending, due today or overdue. This is the general
    // "book a call/reminder" record (Follow-up Engine, Yesterday's Flow's
    // "Book Follow-up call?" toggle, PRM lead follow-ups, etc.) — previously
    // invisible from Today's Actions entirely; added 2026-07-06 so anything
    // booked this way actually surfaces here instead of only on the separate
    // legacy Follow-up Engine board.
    // ═══════════════════════════════════════════════════════════════════════

    private function followUpCalls(): array
    {
        return FollowUp::with([
                'patient:id,name,phone,relationship_id',
                'lead:id,name,phone,relationship_id',
            ])
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', Carbon::today())
            ->orderBy('due_date')
            ->limit($this->limit())
            ->get()
            ->map(function (FollowUp $fu) {
                $label = $fu->note ?: ($fu->label ?: 'Follow-up call');

                return [
                    'category'        => 'follow_up_calls',
                    'patient_name'    => $fu->subjectName(),
                    'patient_id'      => $fu->patient_id,
                    'lead_id'         => $fu->lead_id,
                    'relationship_id' => $fu->patient?->relationship_id ?? $fu->lead?->relationship_id ?? null,
                    'reason'          => $fu->due_date->isToday()
                        ? $label
                        : $label . ' — overdue by ' . $fu->due_date->diffForHumans(now(), true),
                    'priority'        => $fu->priority ?? 'medium',
                    'suggested_action'=> 'Call as scheduled',
                    'link'            => $fu->patient_id
                        ? route('patients.show', $fu->patient_id)
                        : ($fu->lead?->relationship_id
                            ? route('relationship.profile', $fu->lead->relationship_id)
                            : route('relationship.pipeline')),
                    'meta'            => [
                        'phone'        => $fu->subjectPhone(),
                        'follow_up_id' => $fu->id,
                        'channel'      => $fu->channel,
                        'due_date'     => $fu->due_date->format('d M Y'),
                    ],
                ];
            })
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 12 — Logged Communications
    // Manually-logged calls/comms (source_engine = 'manual') that don't fall
    // into any other category — i.e. everything the retired Communication
    // Manager screen used to be the only place to review. Excludes anything
    // recall-flavoured so it never double-counts with recallCalls() above.
    // ═══════════════════════════════════════════════════════════════════════

    private function loggedCommunications(): array
    {
        return CommunicationQueue::with('patient:id,name,phone,relationship_id')
            ->where('source_engine', 'manual')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('purpose')
                  ->orWhere(function ($q2) {
                      $q2->where('purpose', 'not like', '%recall%')
                         ->where('purpose', '!=', 'recall_due')
                         ->where('purpose', '!=', 'recall_birthday');
                  });
            })
            ->orderByRaw("FIELD(priority,'high','medium','low')")
            ->orderBy('follow_up_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (CommunicationQueue $item) => [
                'category'        => 'logged_communications',
                'patient_name'    => $item->patient?->name ?? $item->person_name ?? 'Unknown',
                'patient_id'      => $item->patient_id,
                'lead_id'         => null,
                'relationship_id' => $item->patient?->relationship_id ?? null,
                'reason'          => $item->note
                    ?: (CommunicationQueue::PURPOSES[$item->purpose] ?? ucfirst(str_replace('_', ' ', $item->comm_type ?? 'Communication'))),
                'priority'        => $item->priority ?? 'medium',
                'suggested_action'=> 'Follow up on this logged communication',
                'link'            => $item->patient_id
                    ? route('patients.show', $item->patient_id)
                    : '#',
                'meta'            => [
                    'phone'          => $item->patient?->phone ?? $item->phone,
                    'comm_queue_id'  => $item->id,
                    'purpose'        => $item->purpose,
                    'channel'        => $item->channel,
                    'follow_up_date' => $item->follow_up_date?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 5 — Appointment Reminders
    // Appointments today or tomorrow (within N hours), not cancelled
    // ═══════════════════════════════════════════════════════════════════════

    private function appointmentReminders(): array
    {
        $hoursAhead = (int) config('relationship_rules.today_actions.appointment_reminder_hours_ahead', 24);
        $cutoff     = Carbon::now()->addHours($hoursAhead)->toDateString();

        return Appointment::with('patient:id,name,phone,relationship_id')
            ->whereDate('appointment_date', '<=', $cutoff)
            ->whereDate('appointment_date', '>=', Carbon::today()->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('appointment_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (Appointment $appt) => [
                'category'        => 'appointment_reminders',
                'patient_name'    => $appt->patient?->name ?? $appt->patient_name ?? 'Unknown',
                'patient_id'      => $appt->patient_id,
                'lead_id'         => null,
                'relationship_id' => $appt->patient?->relationship_id ?? null,
                'reason'          => $appt->appointment_date->isToday()
                    ? 'Appointment is today'
                    : 'Appointment tomorrow — reminder call due',
                'priority'        => $appt->appointment_date->isToday() ? 'high' : 'medium',
                'suggested_action'=> 'Call to confirm attendance',
                'link'            => $appt->patient_id
                    ? route('patients.show', $appt->patient_id)
                    : '#',
                'meta'            => [
                    'phone'            => $appt->patient?->phone,
                    'appointment_date' => $appt->appointment_date->format('d M Y'),
                    'time'             => $appt->appointment_time ?? null,
                    'doctor'           => $appt->doctor_name ?? null,
                    'treatment'        => $appt->treatment_type ?? $appt->notes ?? null,
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 6 — Pending Estimates
    // TreatmentOpportunity status = quoted, follow_up_date overdue
    // ═══════════════════════════════════════════════════════════════════════

    private function pendingEstimates(): array
    {
        return TreatmentOpportunity::with('patient:id,name,phone,relationship_id')
            ->where('status', 'quoted')
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<', Carbon::today())
            ->orderBy('follow_up_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (TreatmentOpportunity $opp) => [
                'category'        => 'pending_estimates',
                'patient_name'    => $opp->patient?->name ?? 'Unknown',
                'patient_id'      => $opp->patient_id,
                'lead_id'         => null,
                'relationship_id' => $opp->patient?->relationship_id ?? null,
                'reason'          => 'Estimate sent — awaiting decision (overdue by '
                    . $opp->follow_up_date->diffForHumans(now(), true) . ')',
                'priority'        => 'medium',
                'suggested_action'=> 'Call to check if they have reviewed the estimate',
                'link'            => route('patients.show', $opp->patient_id),
                'meta'            => [
                    'phone'          => $opp->patient?->phone,
                    'treatment'      => $opp->label ?? null,
                    'value'          => $opp->estimated_value ?? null,
                    'follow_up_date' => $opp->follow_up_date->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 7 — Membership Renewals
    // FinancePatientMembership expiring within N days
    // ═══════════════════════════════════════════════════════════════════════

    private function membershipRenewals(): array
    {
        $daysAhead = (int) config('relationship_rules.today_actions.membership_renewal_days_ahead', 30);
        $cutoff    = Carbon::today()->addDays($daysAhead);

        return FinancePatientMembership::with('patient:id,name,phone,relationship_id')
            ->where('status', 'active')
            ->whereBetween('end_date', [Carbon::today(), $cutoff])
            ->orderBy('end_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (FinancePatientMembership $m) => [
                'category'        => 'membership_renewals',
                'patient_name'    => $m->patient?->name ?? 'Unknown',
                'patient_id'      => $m->patient_id,
                'lead_id'         => null,
                'relationship_id' => $m->patient?->relationship_id ?? null,
                'reason'          => 'Membership expires in ' . Carbon::today()->diffInDays($m->end_date) . ' days ('
                    . $m->end_date->format('d M Y') . ')',
                'priority'        => $m->daysUntilExpiry() <= 7 ? 'high' : 'medium',
                'suggested_action'=> 'Call to renew membership before expiry',
                'link'            => route('patients.show', $m->patient_id),
                'meta'            => [
                    'phone'    => $m->patient?->phone,
                    'end_date' => $m->end_date->format('d M Y'),
                    'plan'     => $m->plan?->name ?? null,
                    'days_left'=> $m->daysUntilExpiry(),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 8 — Birthdays
    // Patients with birthday today ± window
    // ═══════════════════════════════════════════════════════════════════════

    private function birthdays(): array
    {
        // Recall & Birthday Settings (2026-07-05) — AppSetting override, falling
        // back to the original config default so clinics that never open the
        // settings page keep today's exact behaviour. Disabling the trigger here
        // only hides it from Today's Actions; it does NOT affect
        // RecallEngineService::recallBirthday(), which has its own identical
        // flag/window pair (recall.birthday_enabled / recall.birthday_window_days)
        // — same AppSetting keys are intentionally shared so one Settings field
        // controls both surfaces consistently.
        if (\App\Models\AppSetting::get('recall.birthday_enabled', '1') !== '1') {
            return [];
        }

        $window = (int) \App\Models\AppSetting::get(
            'recall.birthday_window_days',
            config('relationship_rules.today_actions.birthday_window_days', 1)
        );

        $items = [];

        // Build date window: today ± $window days as MM-DD strings for matching
        for ($offset = -$window; $offset <= $window; $offset++) {
            $date  = Carbon::today()->addDays($offset);
            $mmdd  = $date->format('m-d');

            $patients = Patient::query()
                ->where('dob_unknown', false)
                ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = ?", [$mmdd])
                ->limit($this->limit())
                ->get();

            foreach ($patients as $patient) {
                $label = match ($offset) {
                    0  => 'Birthday today',
                    1  => 'Birthday tomorrow',
                    -1 => 'Birthday was yesterday',
                    default => ($offset > 0
                        ? "Birthday in {$offset} days"
                        : 'Birthday ' . abs($offset) . ' days ago'),
                };

                $items[] = [
                    'category'        => 'birthdays',
                    'patient_name'    => $patient->name,
                    'patient_id'      => $patient->id,
                    'lead_id'         => null,
                    'relationship_id' => $patient->relationship_id ?? null,
                    'reason'          => $label,
                    'priority'        => $offset === 0 ? 'high' : 'low',
                    'suggested_action'=> 'Send a WhatsApp birthday greeting',
                    // Birthdays don't need a staff phone call — a WhatsApp send is the
                    // whole action here (2026-07-06, Sumit's call: don't add birthdays
                    // to the call backlog). 'primary_action' tells the view to render
                    // a one-click Send WhatsApp button instead of the Call drawer.
                    // Every other category is untouched and implicitly still 'call'.
                    'primary_action'  => 'whatsapp',
                    'link'            => route('patients.show', $patient->id),
                    'meta'            => [
                        'phone'         => $patient->phone,
                        'date_of_birth' => $patient->date_of_birth?->format('d M Y'),
                        'age'           => $patient->date_of_birth?->age,
                        'offset_days'   => $offset,
                    ],
                ];
            }
        }

        // Sort: today first, then tomorrow, then yesterday
        usort($items, fn ($a, $b) => abs($a['meta']['offset_days']) <=> abs($b['meta']['offset_days']));

        return $items;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 9 — Lab Ready
    // LabCase status = final_received or complete, no upcoming appointment
    // ═══════════════════════════════════════════════════════════════════════

    private function labReady(): array
    {
        $readyStatuses = config(
            'relationship_rules.today_actions.lab_ready_statuses',
            ['final_received', 'complete']
        );

        // Patient IDs who have an upcoming scheduled appointment (exclude them)
        $patientsWithAppt = Appointment::whereDate('appointment_date', '>=', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->pluck('patient_id')
            ->unique()
            ->toArray();

        return LabCase::with('patient:id,name,phone,relationship_id')
            ->whereIn('status', $readyStatuses)
            ->whereNotNull('patient_id')
            ->whereNotIn('patient_id', $patientsWithAppt)
            ->orderBy('updated_at')
            ->limit($this->limit())
            ->get()
            ->map(fn (LabCase $case) => [
                'category'        => 'lab_ready',
                'patient_name'    => $case->patient?->name ?? 'Unknown',
                'patient_id'      => $case->patient_id,
                'lead_id'         => null,
                'relationship_id' => $case->patient?->relationship_id ?? null,
                'reason'          => 'Lab work ready — no upcoming appointment to deliver it',
                'priority'        => 'medium',
                'suggested_action'=> 'Call to schedule fitting/delivery appointment',
                'link'            => route('patients.show', $case->patient_id),
                'meta'            => [
                    'phone'         => $case->patient?->phone,
                    'lab_case_id'   => $case->id,
                    'work_category' => $case->work_category,
                    'status'        => $case->status,
                    'ready_since'   => $case->updated_at?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 10 — Payment Reminders
    // Overdue invoices above threshold
    // ═══════════════════════════════════════════════════════════════════════

    private function paymentReminders(): array
    {
        $threshold = (int) config('relationship_rules.today_actions.payment_reminder_threshold', 500);

        return Invoice::with('patient:id,name,phone,relationship_id')
            ->where('due_date', '<', Carbon::today())
            ->where('balance_due', '>', $threshold)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('patient_id')
            ->orderByDesc('balance_due')
            ->limit($this->limit())
            ->get()
            ->map(fn (Invoice $inv) => [
                'category'        => 'payment_reminders',
                'patient_name'    => $inv->patient?->name ?? 'Unknown',
                'patient_id'      => $inv->patient_id,
                'lead_id'         => null,
                'relationship_id' => $inv->patient?->relationship_id ?? null,
                'reason'          => '₹' . number_format($inv->balance_due, 0) . ' overdue since '
                    . $inv->due_date->format('d M Y'),
                'priority'        => $inv->balance_due > 5000 ? 'high' : 'medium',
                'suggested_action'=> 'Call to arrange payment',
                'link'            => route('patients.show', $inv->patient_id),
                'meta'            => [
                    'phone'       => $inv->patient?->phone,
                    'invoice_no'  => $inv->invoice_number ?? $inv->id,
                    'balance_due' => $inv->balance_due,
                    'due_date'    => $inv->due_date->format('d M Y'),
                    'total'       => $inv->total_amount,
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY 11 — Wellness Check (Yesterday's Treated Patients)
    // TreatmentVisit status = completed, visit_date = yesterday.
    // "Yesterday's follow-up" = check in on how a patient is doing after
    // the procedure we did on them yesterday — not a missed-call catch-up.
    // ═══════════════════════════════════════════════════════════════════════

    private function wellnessCheckYesterday(): array
    {
        return TreatmentVisit::with('patient:id,name,phone,relationship_id')
            ->where('status', 'completed')
            ->whereDate('visit_date', Carbon::yesterday())
            ->whereNotNull('patient_id')
            ->orderBy('visit_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (TreatmentVisit $visit) => [
                'category'        => 'wellness_check_yesterday',
                'patient_name'    => $visit->patient?->name ?? 'Unknown',
                'patient_id'      => $visit->patient_id,
                'lead_id'         => null,
                'relationship_id' => $visit->patient?->relationship_id ?? null,
                'reason'          => 'Treated yesterday ('
                    . ($visit->treatment_name ?? $visit->procedure ?? 'procedure')
                    . ') — wellness check-in call',
                'priority'        => 'high',
                'suggested_action'=> "Call to check how they're feeling after yesterday's treatment",
                'link'            => $visit->patient_id
                    ? route('patients.show', $visit->patient_id)
                    : '#',
                'meta'            => [
                    'phone'          => $visit->patient?->phone,
                    'treatment_name' => $visit->treatment_name,
                    'procedure'      => $visit->procedure,
                    'visit_date'     => $visit->visit_date?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DATE-PICKER MODES — added so Today's Actions can look at a date other
    // than today. These reuse the exact same due-date fields the categories
    // above already read (followup_date, follow_up_date, next_recall_date,
    // date_of_birth, end_date) — no schema change, no new "schedule" table.
    // Categories that only make sense "right now" (new enquiries, yesterday's
    // missed calls, lab-ready, pending estimates, payment reminders) are
    // deliberately NOT included here — they're state checks, not
    // schedulable-by-date items.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Forward-looking preview for a single future date. Items keep the same
     * shape as generate() so the existing card grid + Call drawer work
     * unchanged — this is a preview, not a stored schedule, so it reflects
     * whatever is true right now for that date (e.g. a patient could still
     * visit before then and drop off the recall list).
     */
    public function generateUpcoming(Carbon $date): array
    {
        $groups = [];

        $categories = [
            'recall_calls'          => fn () => $this->recallCallsOnDate($date),
            'lead_followups'        => fn () => $this->leadFollowupsOnDate($date),
            'opportunities'         => fn () => $this->opportunitiesOnDate($date),
            'appointment_reminders' => fn () => $this->appointmentsOnDate($date),
            'membership_renewals'   => fn () => $this->membershipOnDate($date),
            'birthdays'             => fn () => $this->birthdaysOnDate($date),
        ];

        foreach ($categories as $key => $resolver) {
            try {
                $groups[$key] = $resolver();
            } catch (\Throwable $e) {
                Log::warning("TodayActionsEngine::generateUpcoming: category [{$key}] failed", [
                    'error' => $e->getMessage(), 'date' => $date->toDateString(),
                ]);
                $groups[$key] = [];
            }
        }

        return $groups;
    }

    /**
     * Retrospective view for a past date — reads completed communication_queue
     * rows (status = closed) whose completion (updated_at) falls on that day,
     * with their logged outcome. Read-only in the view (no Call button —
     * it already happened).
     */
    public function generatePast(Carbon $date): array
    {
        $items = CommunicationQueue::with('patient:id,name,phone,relationship_id')
            ->where('status', 'closed')
            ->whereDate('updated_at', $date)
            ->orderByDesc('updated_at')
            ->limit($this->limit())
            ->get()
            ->map(function (CommunicationQueue $item) {
                $outcome  = $item->outcome ?? 'completed';
                $priority = match ($outcome) {
                    'appointment_booked', 'treatment_started'  => 'low',
                    'not_interested', 'unreachable', 'lost'    => 'high',
                    default                                     => 'medium',
                };

                return [
                    'category'        => 'completed_calls',
                    'patient_name'    => $item->patient?->name ?? $item->person_name ?? 'Unknown',
                    'patient_id'      => $item->patient_id,
                    'lead_id'         => null,
                    'relationship_id' => $item->patient?->relationship_id ?? null,
                    'reason'          => ucwords(str_replace('_', ' ', $outcome)) . ' — ' . ($item->purpose ?? 'call'),
                    'priority'        => $priority,
                    'suggested_action'=> null,
                    'link'            => $item->patient_id ? route('patients.show', $item->patient_id) : '#',
                    'meta'            => [
                        'phone'         => $item->patient?->phone ?? $item->phone,
                        'outcome'       => $outcome,
                        'source_engine' => $item->source_engine,
                        'completed_at'  => $item->updated_at?->format('d M Y H:i'),
                    ],
                ];
            })
            ->toArray();

        return ['completed_calls' => $items];
    }

    // ── Date-mode helpers (mirror the "today" category methods above, but
    //    matched to an exact given date instead of "today or overdue") ──────

    /**
     * Same source as recallCalls() (today mode) — the communication_queue call
     * list, not patients.next_recall_date (a different, clinical-only field).
     * Fixed 2026-07-06: this previously read next_recall_date, so any recall
     * actually queued in communication_queue — including manually-added ones
     * from the Recall Pipeline's "+ Add Recall" — never showed up here even
     * though it was correctly on the Recall Pipeline board for that date.
     */
    private function recallCallsOnDate(Carbon $date): array
    {
        return CommunicationQueue::with('patient:id,name,phone,relationship_id')
            ->where(function ($q) {
                $q->where('purpose', 'like', '%recall%')
                  ->orWhere('purpose', 'recall_due')
                  ->orWhere('purpose', 'recall_birthday');
            })
            ->where('status', 'pending')
            ->whereDate('follow_up_date', $date)
            ->orderBy('follow_up_date')
            ->limit($this->limit())
            ->get()
            ->map(fn (CommunicationQueue $item) => [
                'category'        => 'recall_calls',
                'patient_name'    => $item->patient?->name ?? $item->person_name ?? 'Unknown',
                'patient_id'      => $item->patient_id,
                'lead_id'         => null,
                'relationship_id' => $item->patient?->relationship_id ?? null,
                'reason'          => $this->recallReason($item),
                'priority'        => $item->priority ?? 'medium',
                'suggested_action'=> 'Call and book a recall appointment',
                'link'            => $item->patient_id
                    ? route('patients.show', $item->patient_id)
                    : '#',
                'meta'            => [
                    'phone'          => $item->patient?->phone ?? $item->phone,
                    'comm_queue_id'  => $item->id,
                    'purpose'        => $item->purpose,
                    'follow_up_date' => $item->follow_up_date?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    private function leadFollowupsOnDate(Carbon $date): array
    {
        return Lead::query()
            ->whereDate('followup_date', $date)
            ->whereNotIn('stage', ['converted', 'lost'])
            ->limit($this->limit())
            ->get()
            ->map(fn (Lead $lead) => [
                'category'        => 'lead_followups',
                'patient_name'    => $lead->name,
                'patient_id'      => null,
                'lead_id'         => $lead->id,
                'relationship_id' => $lead->relationship_id ?? null,
                'reason'          => 'Follow-up scheduled for ' . $date->format('d M Y'),
                'priority'        => 'medium',
                'suggested_action'=> 'Call and update lead stage',
                'link'            => $lead->relationship_id
                    ? route('relationship.profile', $lead->relationship_id)
                    : route('relationship.pipeline'),
                'meta'            => [
                    'phone'         => $lead->phone,
                    'stage'         => $lead->stage,
                    'followup_date' => $lead->followup_date->format('d M Y'),
                    'treatment'     => $lead->treatment,
                ],
            ])
            ->toArray();
    }

    private function opportunitiesOnDate(Carbon $date): array
    {
        return TreatmentOpportunity::with('patient:id,name,phone,relationship_id')
            ->whereDate('follow_up_date', $date)
            ->whereNotIn('status', ['completed', 'declined'])
            ->limit($this->limit())
            ->get()
            ->map(fn (TreatmentOpportunity $opp) => [
                'category'        => 'opportunities',
                'patient_name'    => $opp->patient?->name ?? 'Unknown',
                'patient_id'      => $opp->patient_id,
                'lead_id'         => null,
                'relationship_id' => $opp->patient?->relationship_id ?? null,
                'reason'          => 'Opportunity follow-up scheduled for ' . $date->format('d M Y'),
                'priority'        => 'medium',
                'suggested_action'=> 'Call and confirm if patient wants to proceed',
                'link'            => route('patients.show', $opp->patient_id),
                'meta'            => [
                    'phone'          => $opp->patient?->phone,
                    'treatment'      => $opp->label ?? null,
                    'status'         => $opp->status,
                    'follow_up_date' => $opp->follow_up_date->format('d M Y'),
                    'value'          => $opp->estimated_value ?? null,
                ],
            ])
            ->toArray();
    }

    private function appointmentsOnDate(Carbon $date): array
    {
        return Appointment::with('patient:id,name,phone,relationship_id')
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->limit($this->limit())
            ->get()
            ->map(fn (Appointment $appt) => [
                'category'        => 'appointment_reminders',
                'patient_name'    => $appt->patient?->name ?? $appt->patient_name ?? 'Unknown',
                'patient_id'      => $appt->patient_id,
                'lead_id'         => null,
                'relationship_id' => $appt->patient?->relationship_id ?? null,
                'reason'          => 'Appointment on ' . $date->format('d M Y'),
                'priority'        => 'medium',
                'suggested_action'=> 'Call to confirm attendance',
                'link'            => $appt->patient_id ? route('patients.show', $appt->patient_id) : '#',
                'meta'            => [
                    'phone'            => $appt->patient?->phone,
                    'appointment_date' => $appt->appointment_date->format('d M Y'),
                    'time'             => $appt->appointment_time ?? null,
                ],
            ])
            ->toArray();
    }

    private function membershipOnDate(Carbon $date): array
    {
        return FinancePatientMembership::with('patient:id,name,phone,relationship_id')
            ->where('status', 'active')
            ->whereDate('end_date', $date)
            ->limit($this->limit())
            ->get()
            ->map(fn (FinancePatientMembership $m) => [
                'category'        => 'membership_renewals',
                'patient_name'    => $m->patient?->name ?? 'Unknown',
                'patient_id'      => $m->patient_id,
                'lead_id'         => null,
                'relationship_id' => $m->patient?->relationship_id ?? null,
                'reason'          => 'Membership expires ' . $date->format('d M Y'),
                'priority'        => 'medium',
                'suggested_action'=> 'Call to renew membership before expiry',
                'link'            => route('patients.show', $m->patient_id),
                'meta'            => [
                    'phone'    => $m->patient?->phone,
                    'end_date' => $m->end_date->format('d M Y'),
                    'plan'     => $m->plan?->name ?? null,
                ],
            ])
            ->toArray();
    }

    private function birthdaysOnDate(Carbon $date): array
    {
        $mmdd = $date->format('m-d');

        return Patient::query()
            ->where('dob_unknown', false)
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = ?", [$mmdd])
            ->limit($this->limit())
            ->get()
            ->map(fn (Patient $patient) => [
                'category'        => 'birthdays',
                'patient_name'    => $patient->name,
                'patient_id'      => $patient->id,
                'lead_id'         => null,
                'relationship_id' => $patient->relationship_id ?? null,
                'reason'          => 'Birthday on ' . $date->format('d M Y'),
                'priority'        => 'low',
                'suggested_action'=> 'Send a WhatsApp birthday greeting',
                // Same one-click WhatsApp action as birthdays() above — see comment there.
                'primary_action'  => 'whatsapp',
                'link'            => route('patients.show', $patient->id),
                'meta'            => [
                    'phone'         => $patient->phone,
                    'date_of_birth' => $patient->date_of_birth?->format('d M Y'),
                ],
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function limit(): int
    {
        return (int) config('relationship_rules.today_actions.max_per_category', 50);
    }
}
