<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\LeadActivity;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\ReferralReward;
use App\Models\Relationship;
use App\Models\Review;
use App\Models\Task;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentVisit;
use App\Services\Insights\InsightsEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * ProfileController — Phase 3: Relationship Profile + Unified Timeline
 *
 * Loads the full profile for any Relationship record whether the person is:
 *   - A lead only (no patient yet)
 *   - A converted patient only (no relationship_id on lead)
 *   - Both (linked lead + patient via relationship_id)
 *
 * Routes:
 *   GET /relationship/{id}         → show()   [relationship.profile]
 *   GET /relationship/search       → search() [relationship.search]  AJAX
 */
class ProfileController extends Controller
{
    // ── Main profile page ──────────────────────────────────────────────────────

    public function show(int $id, InsightsEngine $insights): View
    {
        // Load relationship with all associations
        $relationship = Relationship::with([
            'lead',
            'patient',
            'journeys' => fn ($q) => $q->orderBy('started_at', 'desc'),
        ])->findOrFail($id);

        $lead    = $relationship->lead;
        $patient = $relationship->patient;

        // ── Household patients (slice 4) ─────────────────────────────────────────
        // Most relationships map to a single patient. ~18 "household" relationships
        // share a phone and link several patients. Read them ALL — branch-scope-free,
        // so the whole household is visible regardless of which branch each belongs
        // to. Read-only and additive: the primary $patient above still drives every
        // existing stat/timeline path unchanged.
        $householdPatients = Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->where('relationship_id', $relationship->id)
            ->orderBy('id')
            ->get();
        $isHousehold = $householdPatients->count() > 1;

        // ── Referral chain (read-only surface — data already existed on Patient) ──
        $referredByPatient = null;
        $referralsMade     = collect();
        $referralValue     = 0;

        if ($patient) {
            $referredByPatient = $patient->referred_patient_id
                ? Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)->find($patient->referred_patient_id)
                : null;

            $referralsMade = Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                ->where('referred_patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($referralsMade->isNotEmpty()) {
                $referralValue = Invoice::whereIn('patient_id', $referralsMade->pluck('id'))
                    ->whereIn('status', ['paid', 'partially_paid'])
                    ->sum('paid_amount');
            }
        }

        // ── Referral reward state — who's already been rewarded, who's eligible ──
        $referralRewardEnabled = AppSetting::get('referral.reward_enabled', '0') === '1';
        $referralRewardAmount  = (float) AppSetting::get('referral.reward_amount', '500');
        $referralRewards        = collect();
        $referralPaidPatientIds = collect();

        if ($referralsMade->isNotEmpty()) {
            $referralRewards = ReferralReward::whereIn('referred_patient_id', $referralsMade->pluck('id'))
                ->get()
                ->keyBy('referred_patient_id');

            $referralPaidPatientIds = Invoice::whereIn('patient_id', $referralsMade->pluck('id'))
                ->whereIn('status', ['paid', 'partially_paid'])
                ->distinct()
                ->pluck('patient_id');
        }

        // ── Extended family (patient_links) — beyond the household panel ─────────
        // Household above = patients sharing one relationship/phone. patient_links
        // covers family who are separate patient records with their own phone
        // (e.g. a spouse or adult child with an independent number).
        $extendedFamily = collect();
        if ($patient) {
            $householdIds   = $householdPatients->pluck('id');
            $extendedFamily = $patient->linkedPatients()->get()
                ->merge($patient->linkedByPatients()->get())
                ->unique('id')
                ->reject(fn ($p) => $householdIds->contains($p->id))
                ->values();
        }

        // ── Review status (Phase B reviews module — surfaced here for the first time) ──
        $latestReview = $patient
            ? Review::where('patient_id', $patient->id)->latest('requested_at')->first()
            : null;

        // ── Summary stats ──────────────────────────────────────────────────────

        // Lifetime revenue: sum of all paid amounts on this patient's invoices
        $lifetimeRevenue = 0;
        $totalVisits     = 0;
        $pendingTreatment = 0;
        $opportunities   = collect();
        $recallStatus    = null;
        $membershipStatus = null;

        if ($patient) {
            $lifetimeRevenue = Invoice::where('patient_id', $patient->id)
                ->whereIn('status', ['paid', 'partially_paid'])
                ->sum('paid_amount');

            $totalVisits = TreatmentVisit::where('patient_id', $patient->id)->count();

            // Count open treatment plan items
            $pendingTreatment = DB::table('treatment_plan_items')
                ->join('treatment_plans', 'treatment_plans.id', '=', 'treatment_plan_items.treatment_plan_id')
                ->where('treatment_plans.patient_id', $patient->id)
                ->whereIn('treatment_plan_items.status', ['pending', 'ongoing'])
                ->count();

            // Open opportunities (TreatmentOpportunity phase 4 will add relationship_id — for now use patient_id)
            $opportunities = TreatmentOpportunity::where('patient_id', $patient->id)
                ->whereNotIn('status', ['completed', 'declined'])
                ->orderBy('follow_up_date')
                ->get();

            $recallStatus     = $patient->recall_status ?? null;
            $membershipStatus = $patient->effectiveMembershipStatus ?? $patient->membership_status ?? null;
        }

        // Relationship age (since first contact)
        $since = $relationship->relationship_since ?? $relationship->created_at->toDate();
        $relationshipAge = Carbon::parse($since)->diffForHumans(now(), [
            'parts'  => 2,
            'join'   => ' ',
            'short'  => false,
        ]);

        // Score badge colour
        $score      = $relationship->score ?? 0;
        $scoreColor = match (true) {
            $score >= 75 => 'green',
            $score >= 45 => 'amber',
            default      => 'red',
        };

        // Next recommended action (simple rule, Phase 5 will use RulesEngine)
        $nextAction = $this->resolveNextAction($relationship, $patient, $recallStatus, $opportunities);

        // ── Insights signals (LTV tier + risk score) ───────────────────────────
        // Computed live from the Insights engine (app/Services/Insights) — same
        // deterministic calculators the insight_signals projection uses, just
        // read directly here instead of through the (currently unwired) stored
        // table. Cheap: a handful of read-contract queries scoped to this one
        // relationship, same cost class as the other stats above.
        $ltvSignal  = $insights->calculate($relationship, \App\Models\InsightSignal::SIGNAL_LTV);
        $riskSignal = $insights->calculate($relationship, \App\Models\InsightSignal::SIGNAL_RISK);

        // ── Build unified timeline (paginated) ────────────────────────────────
        // Sprint 3 cutover: behind the activity.single_ledger_reads flag the
        // timeline is served by UnifiedTimelineService (a faithful mirror of
        // buildTimeline). Flag OFF (default) = legacy inline builder, unchanged.
        // Rollback is instant: disable the flag. UI layout is unchanged either way.

        $timeline = \App\Support\Features\Feature::enabled('activity.single_ledger_reads')
            ? app(\App\Services\Relationship\UnifiedTimelineService::class)->for($relationship)
            : $this->buildTimeline($relationship, $lead, $patient);

        // ── Open tasks ────────────────────────────────────────────────────────

        $openTasks = collect();
        if ($patient) {
            $openTasks = Task::where('patient_id', $patient->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('due_date')
                ->limit(20)
                ->get();
        }

        // ── Recent communications (for Communications tab) ────────────────────

        $recentComms = collect();
        if ($patient && Schema::hasTable('patient_communications')) {
            $recentComms = DB::table('patient_communications')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        // ── WhatsApp thread (inline chat lives on the Communication tab) ───────
        // Matches by patient_id OR lead_id so a conversation started before
        // conversion (lead-only) still shows up here, not just post-conversion.
        // Reuses the same OutboundMessageService gate the standalone WhatsApp
        // inbox (/communication/whatsapp) uses, so DPDP consent + Meta's 24h
        // window rules are enforced identically from either screen.

        $waThread       = null;
        $waMessages     = collect();
        $waGate         = null;
        $waTemplates    = [];
        $waTemplateGate = null;

        if (Schema::hasTable('wa_threads') && ($patient || $lead)) {
            $waThread = \App\Models\WaThread::query()
                ->where(function ($q) use ($patient, $lead) {
                    if ($patient) {
                        $q->orWhere('patient_id', $patient->id);
                    }
                    if ($lead) {
                        $q->orWhere('lead_id', $lead->id);
                    }
                })
                ->orderByDesc('last_message_at')
                ->first();

            if ($waThread) {
                $waMessages = $waThread->messages()->with('sentBy')->get();

                $outbound       = app(\App\Services\Whatsapp\OutboundMessageService::class);
                $waGate         = $outbound->consentGate($waThread, 'service');
                $waTemplates    = config('whatsapp.templates', []);
                $waTemplateGate = $outbound->consentGate($waThread, 'service', isTemplate: true);
            }
        }

        return view('relationship.profile.index', compact(
            'relationship',
            'lead',
            'patient',
            'householdPatients',
            'isHousehold',
            'referredByPatient',
            'referralsMade',
            'referralValue',
            'referralRewardEnabled',
            'referralRewardAmount',
            'referralRewards',
            'referralPaidPatientIds',
            'extendedFamily',
            'latestReview',
            'lifetimeRevenue',
            'totalVisits',
            'pendingTreatment',
            'opportunities',
            'recallStatus',
            'membershipStatus',
            'since',
            'relationshipAge',
            'score',
            'scoreColor',
            'nextAction',
            'ltvSignal',
            'riskSignal',
            'timeline',
            'openTasks',
            'recentComms',
            'waThread',
            'waMessages',
            'waGate',
            'waTemplates',
            'waTemplateGate',
        ));
    }

    // ── Universal Search — AJAX ────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 3) {
            return response()->json([]);
        }

        $results = Relationship::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            })
            ->with('patient:id,relationship_id,name')
            ->orderBy('score', 'desc')
            ->limit(8)
            ->get();

        return response()->json($results->map(function (Relationship $r) {
            // Resolve initials for avatar
            $words    = explode(' ', $r->name ?? 'U');
            $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

            return [
                'id'         => $r->id,
                'name'       => $r->name,
                'phone'      => $r->phone,
                'email'      => $r->email,
                'source'     => $r->source,
                'score'      => $r->score,
                'status'     => $r->status,
                'initials'   => $initials,
                'meta'       => collect([$r->phone, $r->email])->filter()->implode(' · '),
                'link'       => route('relationship.profile', $r->id),
                // Additive — lets callers (e.g. the Recall Pipeline's "+ Add Recall"
                // patient picker) know whether this relationship has an actual
                // Patient record to recall, without a second query.
                'patient_id' => $r->patient?->id,
            ];
        }));
    }

    // ── Private: Unified Timeline builder ─────────────────────────────────────

    /**
     * Merge all data sources into a single chronological timeline array.
     *
     * Each entry shape:
     *   [
     *     'date'        => Carbon
     *     'type'        => string  (activity|communication|appointment|task|note|whatsapp)
     *     'icon'        => string  (SVG path or emoji label for icon lookup)
     *     'title'       => string
     *     'description' => string|null
     *     'actor'       => string|null
     *     'meta'        => string|null  (e.g. amount, duration, outcome)
     *   ]
     *
     * @return Collection  Sorted newest-first, limited to 100 entries total.
     */
    /**
     * Legacy inline timeline builder — preserved verbatim as the fallback path.
     * Public so the timeline-parity harness can compare it against
     * UnifiedTimelineService. Not called elsewhere when the flag is on.
     */
    public function buildTimeline(Relationship $relationship, $lead, $patient): Collection
    {
        $entries = collect();

        // 1. New ActivityEngine entries (relationship_id linked)
        $activities = Activity::where('relationship_id', $relationship->id)
            ->orderBy('occurred_at', 'desc')
            ->limit(60)
            ->get();

        foreach ($activities as $act) {
            $entries->push([
                'date'        => $act->occurred_at,
                'type'        => 'activity',
                'icon_type'   => $this->iconForEvent($act->event),
                'title'       => $act->description ?? ucfirst(str_replace(['.', '_'], ' ', $act->event)),
                'description' => null,
                'actor'       => $act->actor_type ? $this->resolveActorName($act) : 'System',
                'meta'        => $act->metadata ? $this->formatMeta($act->metadata) : null,
            ]);
        }

        // 2. Old lead_activities (read-only legacy)
        if ($lead) {
            $leadActs = LeadActivity::where('lead_id', $lead->id)
                ->orderBy('activity_date', 'desc')
                ->limit(30)
                ->get();

            foreach ($leadActs as $la) {
                $entries->push([
                    'date'        => Carbon::parse($la->activity_date ?? $la->created_at),
                    'type'        => 'communication',
                    'icon_type'   => $la->type ?? 'call',
                    'title'       => $la->label ?? ucfirst($la->type ?? 'Activity'),
                    'description' => $la->note,
                    'actor'       => $la->by,
                    'meta'        => $la->outcome,
                ]);
            }
        }

        // 3. Patient-linked data
        if ($patient) {

            // Appointments
            $appts = Appointment::where('patient_id', $patient->id)
                ->orderBy('appointment_date', 'desc')
                ->limit(30)
                ->get();

            foreach ($appts as $appt) {
                $entries->push([
                    'date'        => Carbon::parse($appt->appointment_date),
                    'type'        => 'appointment',
                    'icon_type'   => 'appointment',
                    'title'       => 'Appointment — ' . ucfirst($appt->type ?? 'Visit'),
                    'description' => $appt->notes ?? null,
                    'actor'       => $appt->doctor_id
                        ? DB::table('users')->where('id', $appt->doctor_id)->value('name')
                        : null,
                    'meta'        => ucfirst($appt->status ?? ''),
                ]);
            }

            // Patient communications (old table)
            if (Schema::hasTable('patient_communications')) {
                $comms = DB::table('patient_communications')
                    ->where('patient_id', $patient->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();

                foreach ($comms as $comm) {
                    $entries->push([
                        'date'        => Carbon::parse($comm->sent_at ?? $comm->created_at),
                        'type'        => 'communication',
                        'icon_type'   => $comm->type ?? 'call',
                        'title'       => ucfirst($comm->type ?? 'Communication') . ' — ' . ucfirst($comm->direction ?? ''),
                        'description' => $comm->message ?? null,
                        'actor'       => $comm->staff_name ?? null,
                        'meta'        => ucfirst($comm->status ?? ''),
                    ]);
                }
            }

            // Tasks
            $tasks = Task::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            foreach ($tasks as $task) {
                $entries->push([
                    'date'        => Carbon::parse($task->due_date ?? $task->created_at),
                    'type'        => 'task',
                    'icon_type'   => 'task',
                    'title'       => $task->title ?? $task->task_title ?? 'Task',
                    'description' => $task->description ?? null,
                    'actor'       => null,
                    'meta'        => ucfirst($task->status ?? ''),
                ]);
            }

            // Patient notes
            $notes = PatientNote::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            foreach ($notes as $note) {
                $entries->push([
                    'date'        => $note->created_at,
                    'type'        => 'note',
                    'icon_type'   => 'note',
                    'title'       => 'Note — ' . ucfirst($note->note_type ?? 'General'),
                    'description' => $note->note,
                    'actor'       => null,
                    'meta'        => null,
                ]);
            }
        }

        // Sort all entries newest first and cap at 100
        return $entries
            ->sortByDesc('date')
            ->values()
            ->take(100);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /** Map event key to an icon type string for the view's icon lookup. */
    private function iconForEvent(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'call')        => 'call',
            str_starts_with($event, 'whatsapp')    => 'whatsapp',
            str_starts_with($event, 'appointment') => 'appointment',
            str_starts_with($event, 'payment')     => 'payment',
            str_starts_with($event, 'lead')        => 'lead',
            str_starts_with($event, 'recall')      => 'recall',
            str_starts_with($event, 'task')        => 'task',
            str_starts_with($event, 'note')        => 'note',
            default                                 => 'activity',
        };
    }

    /** Try to resolve the actor's name from actor_type/actor_id (User model). */
    private function resolveActorName(Activity $act): string
    {
        if (str_contains($act->actor_type ?? '', 'User')) {
            $user = DB::table('users')->where('id', $act->actor_id)->value('name');
            return $user ?? 'Staff';
        }
        return 'System';
    }

    /** Format metadata array to a short human string for the timeline. */
    private function formatMeta(array $meta): ?string
    {
        // Show up to 2 key=value pairs, skipping large/nested values
        $parts = [];
        foreach ($meta as $k => $v) {
            if (is_scalar($v) && strlen((string) $v) < 40) {
                $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
            }
            if (count($parts) >= 2) break;
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    /** Determine the next recommended action for this relationship. */
    private function resolveNextAction(Relationship $rel, ?Patient $patient, ?string $recallStatus, Collection $opps): string
    {
        if ($rel->status === 'lost') {
            return 'Relationship marked as lost — no action needed.';
        }

        if ($recallStatus === 'overdue') {
            return 'Call for overdue recall appointment.';
        }

        if ($opps->where('follow_up_date', '<=', now()->toDateString())->count() > 0) {
            return 'Follow up on overdue treatment opportunity.';
        }

        if ($recallStatus === 'due') {
            return 'Schedule recall appointment.';
        }

        if (! $patient) {
            return 'Follow up with lead to schedule first visit.';
        }

        return 'No urgent action — relationship is healthy.';
    }
}
