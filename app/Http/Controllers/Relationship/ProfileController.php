<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LeadActivity;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentVisit;
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

    public function show(int $id): View
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

        // ── WhatsApp messages (if table exists) ───────────────────────────────

        $waMessages = collect();
        if ($patient && Schema::hasTable('wa_messages')) {
            $waMessages = DB::table('wa_messages')
                ->join('wa_threads', 'wa_threads.id', '=', 'wa_messages.wa_thread_id')
                ->where('wa_threads.patient_id', $patient->id)
                ->orderBy('wa_messages.created_at', 'desc')
                ->limit(20)
                ->select('wa_messages.*')
                ->get();
        }

        return view('relationship.profile.index', compact(
            'relationship',
            'lead',
            'patient',
            'householdPatients',
            'isHousehold',
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
            'timeline',
            'openTasks',
            'recentComms',
            'waMessages',
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
                'id'       => $r->id,
                'name'     => $r->name,
                'phone'    => $r->phone,
                'email'    => $r->email,
                'source'   => $r->source,
                'score'    => $r->score,
                'status'   => $r->status,
                'initials' => $initials,
                'meta'     => collect([$r->phone, $r->email])->filter()->implode(' · '),
                'link'     => route('relationship.profile', $r->id),
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
