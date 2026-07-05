<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Activity;
use App\Models\CommunicationQueue;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\Scopes\BranchScope;
use App\Models\TreatmentOpportunity;
use App\Services\Prm\LeadFollowUpService;
use App\Services\Prm\PrmRelationshipAdapter;
use App\Services\RecallEngineService;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\OutcomeAutomationService;
use App\Services\Relationship\RelationshipEngine;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RelationshipController (API v1)
 * --------------------------------
 * Mobile / Tulip face of PRE (the Relationship Engine — leads, opportunities,
 * recalls, and the unified relationship spine). THIN — every read/write goes
 * through the SAME services + reliable legacy columns the web PRE pages use
 * (LeadPipelineController, OpportunityPipelineController,
 * RecallPipelineController, RelationshipListController, ProfileController),
 * so behaviour never drifts between web and app. PRM no longer exists
 * anywhere in this codebase (hard-deleted 2026-07-04) — PRE is the only
 * lead/relationship engine, on web and now on mobile.
 *
 *   GET  /api/v1/relationships                       → List/search/filter (mobile equiv. of /relationship/list)
 *   GET  /api/v1/relationships/today                  → TodayActionsEngine grouped output
 *   GET  /api/v1/relationships/search?q=               → Universal person search
 *   GET  /api/v1/relationships/pipelines/leads         → Lead pipeline, grouped by stage
 *   GET  /api/v1/relationships/pipelines/opportunities → Opportunity pipeline, grouped by status
 *   GET  /api/v1/relationships/pipelines/recalls       → Recall pipeline, grouped by status
 *   POST /api/v1/relationships/recalls                 → Manually add a recall for a patient
 *   GET  /api/v1/relationships/{id}                    → Relationship profile summary (+ household)
 *   GET  /api/v1/relationships/{id}/timeline           → Paginated activity timeline
 *   GET  /api/v1/relationships/{id}/journeys           → All journeys with state
 *   POST /api/v1/relationships/{id}/activity           → Log an activity from mobile
 *   POST /api/v1/leads/quick-add                       → Quick-add a lead (4 fields)
 *   POST /api/v1/leads/{lead}/move                      → Move a lead to a different stage
 *   POST /api/v1/leads/{lead}/activity                  → Log an activity on a lead
 *   POST /api/v1/leads/{lead}/convert                    → Convert a lead to a patient
 *
 * Auth: Sanctum (via 'auth:sanctum' middleware on the api route group).
 * All routes inherit the existing Api/V1 middleware stack.
 */
class RelationshipController extends ApiController
{
    /**
     * Lead pipeline stage metadata. Kept local — mirrors
     * LeadPipelineController::STAGES (private there too) so this controller
     * never reaches into a web controller, matching this codebase's existing
     * "self-contained pipeline controller" convention.
     */
    private const LEAD_STAGES = [
        'new_lead'     => ['label' => 'New Lead',     'color' => '#534AB7', 'bg' => '#EEEDFE'],
        'contacted'    => ['label' => 'Contacted',    'color' => '#0F6E56', 'bg' => '#E1F5EE'],
        'appointment'  => ['label' => 'Appointment',  'color' => '#854F0B', 'bg' => '#FAEEDA'],
        'consultation' => ['label' => 'Consultation', 'color' => '#185FA5', 'bg' => '#E6F1FB'],
        'plan_given'   => ['label' => 'Plan Given',   'color' => '#993556', 'bg' => '#FBEAF0'],
        'converted'    => ['label' => 'Converted',    'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        'lost'         => ['label' => 'Lost',         'color' => '#8A1F1F', 'bg' => '#FDECEC'],
    ];

    /** Column colours for the legacy CommunicationQueue recall statuses. */
    private const RECALL_STATUS_STYLES = [
        'pending'             => ['color' => '#854F0B', 'bg' => '#FAEEDA'],
        'waiting_for_patient' => ['color' => '#185FA5', 'bg' => '#E6F1FB'],
        'overdue'             => ['color' => '#8A1F1F', 'bg' => '#FDECEC'],
        'closed'              => ['color' => '#3B6D11', 'bg' => '#EAF3DE'],
    ];

    /** Max cards per group in a pipeline response — keeps mobile payloads light. */
    private const CARDS_PER_GROUP = 50;

    public function __construct(
        private readonly RelationshipEngine       $relationshipEngine,
        private readonly ActivityEngine           $activityEngine,
        private readonly TodayActionsEngine       $todayActionsEngine,
        private readonly OutcomeAutomationService $outcomeAutomation,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Searchable, filterable, paginated browse over the whole relationship
     * base — mobile equivalent of /relationship/list (RelationshipListController).
     *
     * Query params (all optional): q, status (active|dormant|lost),
     * has (lead|patient), sort (name|score|relationship_since|created_at),
     * dir (asc|desc), page, limit (default 20, max 100).
     */
    public function list(Request $request): JsonResponse
    {
        $sorts    = ['name', 'score', 'relationship_since', 'created_at'];
        $statuses = ['active', 'dormant', 'lost'];

        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : null;
        $has    = in_array($request->query('has'), ['lead', 'patient'], true) ? $request->query('has') : null;
        $sort   = in_array($request->query('sort'), $sorts, true) ? $request->query('sort') : 'created_at';
        $dir    = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = Relationship::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($has === 'lead') {
            $query->whereHas('lead');
        } elseif ($has === 'patient') {
            $query->whereHas('patient');
        }

        $limit = max(1, min((int) $request->query('limit', 20), 100));
        $page  = $query->orderBy($sort, $dir)->paginate($limit)->appends($request->query());

        $items = $page->getCollection()->map(fn (Relationship $r) => [
            'id'                 => $r->id,
            'name'               => $r->name,
            'phone'              => $r->phone,
            'email'              => $r->email,
            'score'              => $r->score,
            'status'             => $r->status,
            'source'             => $r->source,
            'relationship_since' => $r->relationship_since?->toDateString(),
        ]);

        return $this->success($items, '', 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/today
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Today's relationship actions — the mobile equivalent of /relationship/today.
     *
     * Returns the full grouped output from TodayActionsEngine so mobile can
     * display prioritised call lists by category. Each category is an array
     * of action items in the documented item shape.
     *
     * Query params:
     *   ?categories=recall_calls,lead_followups  (optional, comma-separated filter)
     */
    public function today(Request $request): JsonResponse
    {
        $actions = $this->todayActionsEngine->generate();

        // Optional category filter — mobile can ask for a subset
        if ($request->filled('categories')) {
            $allowed = array_map('trim', explode(',', $request->query('categories')));
            $actions = array_intersect_key($actions, array_flip($allowed));
        }

        // Summary counts for the mobile header bar
        $totals = [];
        $grandTotal = 0;
        foreach ($actions as $category => $items) {
            $count          = count($items);
            $totals[$category] = $count;
            $grandTotal    += $count;
        }

        return $this->success(
            $actions,
            '',
            200,
            [
                'totals'      => $totals,
                'grand_total' => $grandTotal,
                'as_of'       => now()->toIso8601String(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/{id}
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Relationship profile summary — lead + patient + journeys + recent activities.
     * Reuses RelationshipEngine::getProfile() exactly as the web page does.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $relationship = $this->findRelationship($id);

        $profile = $this->relationshipEngine->getProfile($relationship->id);

        // Household patients (mirrors ProfileController::show()'s slice-4 panel) —
        // most relationships map to a single patient; a handful share a phone
        // and link several patients. Read branch-scope-free so the whole
        // household is visible regardless of which branch each patient sits in.
        $householdPatients = Patient::withoutGlobalScope(BranchScope::class)
            ->where('relationship_id', $relationship->id)
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        // Shape for mobile — flatten the nested Eloquent models to plain arrays
        return $this->success([
            'id'               => $relationship->id,
            'name'             => $relationship->name,
            'phone'            => $relationship->phone,
            'email'            => $relationship->email,
            'score'            => $relationship->score,
            'status'           => $relationship->status,
            'source'           => $relationship->source,
            'relationship_since' => $relationship->relationship_since?->toDateString(),
            'lead'             => $profile['lead'] ? [
                'id'    => $profile['lead']->id,
                'stage' => $profile['lead']->stage,
                'treatment' => $profile['lead']->treatment,
            ] : null,
            'patient'          => $profile['patient'] ? [
                'id'   => $profile['patient']->id,
                'name' => $profile['patient']->name,
            ] : null,
            'is_household'      => $householdPatients->count() > 1,
            'household_patients'=> $householdPatients->map(fn (Patient $p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'phone' => $p->phone,
            ])->values(),
            'journey_count'    => $profile['journeys']->count(),
            'recent_activities'=> $profile['activities']->take(10)->map(fn ($a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->description,
                'occurred_at' => $a->occurred_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/{id}/timeline
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Paginated activity timeline for one relationship.
     *
     * Query params:
     *   ?page=1&limit=25   (default: 25 per page, max 100)
     *   ?event=call.logged (optional event filter)
     */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $relationship = $this->findRelationship($id);

        $limit = max(1, min((int) $request->query('limit', 25), 100));

        $page = Activity::forRelationship($relationship->id)
            ->recent()
            ->when($request->filled('event'), fn ($q) => $q->ofEvent($request->query('event')))
            ->paginate($limit);

        $items = $page->getCollection()->map(fn (Activity $a) => [
            'id'             => $a->id,
            'event'          => $a->event,
            'description'    => $a->description,
            'actor_type'     => $a->actor_type,
            'actor_id'       => $a->actor_id,
            'subject_type'   => $a->subject_type,
            'subject_id'     => $a->subject_id,
            'metadata'       => $a->metadata,
            'occurred_at'    => $a->occurred_at?->toIso8601String(),
        ]);

        return $this->success(
            $items,
            '',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/{id}/journeys
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * All journeys for a relationship, with current state and metadata.
     */
    public function journeys(Request $request, int $id): JsonResponse
    {
        $relationship = $this->findRelationship($id);

        $journeys = $relationship->journeys()
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($j) => [
                'id'         => $j->id,
                'type'       => $j->type,
                'state'      => $j->state,
                'started_at' => $j->started_at?->toDateString(),
                'closed_at'  => $j->closed_at?->toDateString(),
                'is_active'  => $j->closed_at === null,
                'metadata'   => $j->metadata ?? [],
            ]);

        return $this->success($journeys);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/search?q=
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Universal relationship search — name, phone, or email.
     * Returns lightweight results suitable for mobile type-ahead.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) ($request->query('q') ?? ''));

        if (strlen($term) < 2) {
            return $this->success([], 'Query too short — minimum 2 characters.');
        }

        $results = Relationship::query()
            ->where(function ($q) use ($term) {
                $q->where('name',  'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderByDesc('score')
            ->limit(20)
            ->get()
            ->map(fn (Relationship $r) => [
                'id'     => $r->id,
                'name'   => $r->name,
                'phone'  => $r->phone,
                'email'  => $r->email,
                'score'  => $r->score,
                'status' => $r->status,
                'source' => $r->source,
            ]);

        return $this->success($results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationships/{id}/activity
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Log an activity from mobile (e.g. a call outcome, note, or action taken).
     *
     * Required body:
     *   event       string   Event key in dot notation: 'call.logged', 'note.added', …
     *   description string   Human-readable summary (shown on Timeline)
     *
     * Optional body:
     *   metadata    object   Any extra context (outcome, duration, channel, …)
     *   actor_type  string   Defaults to 'App\Models\User' (the authenticated user)
     */
    public function logActivity(Request $request, int $id): JsonResponse
    {
        $relationship = $this->findRelationship($id);

        $validated = $request->validate([
            'event'       => ['required', 'string', 'max:100', 'regex:/^[a-z_]+\.[a-z_]+$/'],
            'description' => ['required', 'string', 'max:1000'],
            'metadata'    => ['nullable', 'array'],
        ]);

        // Use the Relationship itself as the subject so the activity is
        // always linked to the right record regardless of lead/patient state.
        $activity = $this->activityEngine->log(
            subject:        $relationship,
            event:          $validated['event'],
            actor:          $request->user(),
            metadata:       $validated['metadata'] ?? [],
            relationshipId: $relationship->id,
            description:    $validated['description'],
        );

        if ($activity === null) {
            return $this->error('Failed to log activity. Please try again.', [], 500);
        }

        return $this->success([
            'id'          => $activity->id,
            'event'       => $activity->event,
            'description' => $activity->description,
            'occurred_at' => $activity->occurred_at?->toIso8601String(),
        ], 'Activity logged.', 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/pipelines/leads
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lead pipeline, grouped by the reliable legacy `leads.stage` column —
     * mobile equivalent of /relationship/pipeline (LeadPipelineController).
     * Shaped as groups (not kanban columns) since mobile renders this as a
     * stage-tab list, not a side-scrolling board.
     */
    public function pipelineLeads(Request $request): JsonResponse
    {
        $leads = Lead::query()
            ->select([
                'id', 'name', 'phone', 'stage', 'treatment', 'lead_value',
                'followup_date', 'assigned_to', 'urgency', 'relationship_id',
            ])
            ->orderByRaw('followup_date IS NULL, followup_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $leads->groupBy('stage');

        $groups = [];
        foreach (self::LEAD_STAGES as $key => $meta) {
            $bucket = $grouped->get($key, collect());
            $groups[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'count'  => $bucket->count(),
                'value'  => (float) $bucket->sum('lead_value'),
                'items'  => $bucket->take(self::CARDS_PER_GROUP)->map(fn (Lead $l) => [
                    'id'              => $l->id,
                    'name'            => $l->name,
                    'phone'           => $l->phone,
                    'stage'           => $l->stage,
                    'treatment'       => $l->treatment,
                    'lead_value'      => (float) $l->lead_value,
                    'followup_date'   => $l->followup_date?->toDateString(),
                    'assigned_to'     => $l->assigned_to,
                    'urgency'         => $l->urgency,
                    'relationship_id' => $l->relationship_id,
                ])->values(),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_GROUP),
            ];
        }

        $activeCount   = $leads->whereNotIn('stage', ['converted', 'lost'])->count();
        $pipelineValue = (float) $leads->whereNotIn('stage', ['converted', 'lost'])->sum('lead_value');

        return $this->success($groups, '', 200, [
            'total_leads'    => $leads->count(),
            'active_count'   => $activeCount,
            'pipeline_value' => $pipelineValue,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/pipelines/opportunities
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Opportunity pipeline, grouped by TreatmentOpportunity::STAGES — mobile
     * equivalent of /relationship/opportunities (OpportunityPipelineController).
     */
    public function pipelineOpportunities(Request $request): JsonResponse
    {
        $opportunities = TreatmentOpportunity::query()
            ->with(['relationship:id,name,phone'])
            ->select([
                'id', 'relationship_id', 'patient_id', 'type', 'label', 'status',
                'priority', 'follow_up_date', 'estimated_value', 'assigned_to',
            ])
            ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $opportunities->groupBy('status');

        $groups = [];
        foreach (TreatmentOpportunity::STAGES as $key => $meta) {
            $bucket = $grouped->get($key, collect());
            $groups[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'count'  => $bucket->count(),
                'value'  => (float) $bucket->sum('estimated_value'),
                'items'  => $bucket->take(self::CARDS_PER_GROUP)->map(fn (TreatmentOpportunity $o) => [
                    'id'               => $o->id,
                    'relationship_id'  => $o->relationship_id,
                    'patient_id'       => $o->patient_id,
                    'name'             => $o->relationship?->name,
                    'phone'            => $o->relationship?->phone,
                    'type'             => $o->type,
                    'label'            => $o->label,
                    'status'           => $o->status,
                    'priority'         => $o->priority,
                    'follow_up_date'   => $o->follow_up_date?->toDateString(),
                    'estimated_value'  => (float) $o->estimated_value,
                    'assigned_to'      => $o->assigned_to,
                ])->values(),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_GROUP),
            ];
        }

        $openStatuses  = array_diff(array_keys(TreatmentOpportunity::STAGES), ['completed', 'declined']);
        $openCount     = $opportunities->whereIn('status', $openStatuses)->count();
        $pipelineValue = (float) $opportunities->whereIn('status', $openStatuses)->sum('estimated_value');

        return $this->success($groups, '', 200, [
            'total'          => $opportunities->count(),
            'open_count'     => $openCount,
            'pipeline_value' => $pipelineValue,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/pipelines/recalls
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recall pipeline, grouped by CommunicationQueue::STATUSES — mobile
     * equivalent of /relationship/recalls (RecallPipelineController).
     */
    public function pipelineRecalls(Request $request): JsonResponse
    {
        $recalls = CommunicationQueue::query()
            ->where(function ($q) {
                $q->where('purpose', 'recall')->orWhere('source_engine', 'recall');
            })
            ->select([
                'id', 'person_name', 'phone', 'channel', 'status', 'priority',
                'follow_up_date', 'due_at', 'attempt_count', 'assigned_to', 'is_overdue',
            ])
            ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $recalls->groupBy('status');

        $groups = [];
        foreach (CommunicationQueue::STATUSES as $key => $label) {
            $bucket = $grouped->get($key, collect());
            $groups[] = [
                'key'    => $key,
                'label'  => $label,
                'color'  => self::RECALL_STATUS_STYLES[$key]['color'] ?? '#534AB7',
                'bg'     => self::RECALL_STATUS_STYLES[$key]['bg'] ?? '#EEEDFE',
                'count'  => $bucket->count(),
                'items'  => $bucket->take(self::CARDS_PER_GROUP)->map(fn (CommunicationQueue $c) => [
                    'id'             => $c->id,
                    'person_name'    => $c->person_name,
                    'phone'          => $c->phone,
                    'channel'        => $c->channel,
                    'status'         => $c->status,
                    'priority'       => $c->priority,
                    'follow_up_date' => $c->follow_up_date?->toDateString(),
                    'due_at'         => $c->due_at?->toIso8601String(),
                    'attempt_count'  => $c->attempt_count,
                    'assigned_to'    => $c->assigned_to,
                    'is_overdue'     => (bool) $c->is_overdue,
                ])->values(),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_GROUP),
            ];
        }

        $openCount    = $recalls->where('status', '!=', 'closed')->count();
        $overdueCount = $recalls->filter(fn ($r) => $r->is_overdue || $r->status === 'overdue')->count();

        return $this->success($groups, '', 200, [
            'total'         => $recalls->count(),
            'open_count'    => $openCount,
            'overdue_count' => $overdueCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationships/recalls
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Manually add a recall for a patient — mobile equivalent of
     * RecallPipelineController::store(). Same table, same tagging
     * ('manual', distinct from the 6 automated triggers), shows up on the
     * recall board and Today's Actions exactly like any other recall.
     */
    public function recallStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id'     => ['required', 'integer', 'exists:patients,id'],
            'priority'       => ['required', 'in:high,medium,low'],
            'follow_up_date' => ['required', 'date'],
            'note'           => ['nullable', 'string', 'max:500'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        if (! $patient->phone) {
            return $this->error('This patient has no phone number on file — add one before creating a recall.', [], 422);
        }

        app(RecallEngineService::class)->createManual($patient, $data);

        return $this->success(null, 'Recall added for ' . $patient->name . '.', 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationships/call-outcomes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Grouped call-outcome vocabulary for the Activity Completion Bottom
     * Sheet (mobile + web). Static reference data — no query, safe to cache
     * client-side.
     */
    public function callOutcomes(Request $request): JsonResponse
    {
        return $this->success(
            collect(CommunicationQueue::callOutcomeGroups())
                ->map(fn (array $group) => collect($group)->map(
                    fn (string $label, string $key) => ['key' => $key, 'label' => $label]
                )->values())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationships/recalls/{queueId}/complete
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Complete a recall via the Activity Completion Bottom Sheet: records the
     * call outcome + notes + next follow-up date, then runs the matching PRE
     * automation (OutcomeAutomationService) — create appointment, close
     * recall, schedule follow-up, mark invalid contact, disable automations,
     * etc. One engine shared by web and mobile so behaviour never drifts.
     *
     * Required body:  outcome (string, see /call-outcomes for valid keys)
     * Optional body:  notes, next_follow_up_date (date),
     *                 doctor_id, appointment_date, appointment_time
     *                 (only consumed when outcome = appointment_booked)
     */
    public function recallComplete(Request $request, int $queueId): JsonResponse
    {
        $comm = CommunicationQueue::findOrFail($queueId);

        $validated = $request->validate([
            'outcome'              => ['required', 'string', 'in:' . implode(',', array_keys(CommunicationQueue::allCallOutcomes()))],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'next_follow_up_date'  => ['nullable', 'date'],
            'doctor_id'            => ['nullable', 'integer', 'exists:users,id'],
            'appointment_date'     => ['nullable', 'date'],
            'appointment_time'     => ['nullable', 'string'],
        ]);

        $result = $this->outcomeAutomation->apply(
            comm:    $comm,
            outcome: $validated['outcome'],
            actor:   $request->user(),
            options: [
                'notes'                => $validated['notes']               ?? null,
                'next_follow_up_date'  => $validated['next_follow_up_date'] ?? null,
                'doctor_id'            => $validated['doctor_id']           ?? null,
                'appointment_date'     => $validated['appointment_date']    ?? null,
                'appointment_time'     => $validated['appointment_time']    ?? null,
            ],
        );

        return $this->success($result, 'Activity logged.', 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lead lifecycle writes — mobile equivalents of LeadPipelineController's
    // Phase 8 writes (moveStage / logActivity / convertToPatient / quick-add).
    // Same effect, same relationship-spine mirroring via PrmRelationshipAdapter
    // (name kept post-PRM-retirement — it's the shared spine-writing
    // primitive, not a PRM-specific class).
    // ─────────────────────────────────────────────────────────────────────────

    /** POST /api/v1/leads/{lead}/move */
    public function leadMoveStage(Request $request, int $lead): JsonResponse
    {
        $request->validate(['stage' => 'required|string']);

        $leadModel = Lead::findOrFail($lead);
        $oldStage  = $leadModel->stage;
        $newStage  = $request->input('stage');

        $leadModel->update(['stage' => $newStage]);

        $leadModel->activities()->create([
            'type'          => 'stage_change',
            'label'         => 'Stage Changed',
            'note'          => "Moved from {$oldStage} → {$newStage}",
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => $request->user()->name ?? 'Staff',
        ]);

        $created = app(LeadFollowUpService::class)->createForStage($leadModel, $newStage);

        app(PrmRelationshipAdapter::class)->onStageChanged($leadModel, $oldStage, $newStage, $request->user());

        return $this->success(['followups_created' => $created], 'Lead moved to ' . $newStage . '.');
    }

    /** POST /api/v1/leads/{lead}/activity */
    public function leadLogActivity(Request $request, int $lead): JsonResponse
    {
        $validated = $request->validate([
            'type'    => 'required|string',
            'label'   => 'required|string',
            'note'    => 'nullable|string',
            'outcome' => 'nullable|string',
        ]);

        $leadModel = Lead::findOrFail($lead);

        $activity = $leadModel->activities()->create([
            'type'          => $validated['type'],
            'label'         => $validated['label'],
            'outcome'       => $validated['outcome'] ?? null,
            'note'          => $validated['note'] ?? null,
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => $request->user()->name ?? 'Staff',
        ]);

        app(PrmRelationshipAdapter::class)->onActivityLogged(
            $leadModel, $validated['type'], $validated['label'], $validated['note'] ?? null,
            $validated['outcome'] ?? null, $request->user(),
        );

        return $this->success(['id' => $activity->id], 'Activity logged.', 201);
    }

    /** POST /api/v1/leads/{lead}/convert */
    public function leadConvert(Request $request, int $lead): JsonResponse
    {
        $leadModel = Lead::findOrFail($lead);
        $leadModel->update(['stage' => 'converted']);

        $leadModel->activities()->create([
            'type'          => 'stage_change',
            'label'         => 'Converted to Patient',
            'note'          => 'Lead marked as converted.',
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => $request->user()->name ?? 'Staff',
        ]);

        // Reuse the Patient already linked to this lead's Relationship, if any —
        // avoids creating a duplicate when a lead is converted more than once.
        $patient = $leadModel->relationship_id
            ? Patient::where('relationship_id', $leadModel->relationship_id)->first()
            : null;

        if (! $patient) {
            $patient = Patient::create([
                'name'            => $leadModel->name,
                'phone'           => $leadModel->phone,
                'alternate_phone' => $leadModel->alt_phone,
                'email'           => $leadModel->email,
                'date_of_birth'   => $leadModel->dob,
                'gender'          => $leadModel->gender,
                'occupation'      => $leadModel->occupation,
                'area'            => $leadModel->location,
                'referred_by'     => $leadModel->referred_by,
                'source'          => $leadModel->source ?: $leadModel->lead_source,
                'chief_complaint' => $leadModel->treatment,
                'branch_id'       => $request->user()->branch_id ?? 1,
                'created_by'      => $request->user()->id,
            ]);

            if ($leadModel->relationship_id) {
                $patient->relationship_id = $leadModel->relationship_id;
                $patient->save();
            }
        }

        app(PrmRelationshipAdapter::class)->onConverted($leadModel, $request->user());

        return $this->success(['patient_id' => $patient->id], 'Lead converted to patient.');
    }

    /** POST /api/v1/leads/quick-add — 4-field quick add (name/phone/lead_source/treatment). */
    public function leadQuickAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'phone'       => 'required|string|max:20',
            'lead_source' => 'required|string|max:50',
            'treatment'   => 'nullable|string',
        ]);

        $data['stage']  = 'new_lead';
        $data['source'] = Lead::LEAD_SOURCES[$data['lead_source']] ?? $data['lead_source'];

        $leadModel = Lead::create($data);

        $leadModel->activities()->create([
            'type'          => 'note',
            'label'         => 'Lead Created (Quick Add)',
            'note'          => "Added via Quick Add. Source: {$data['source']}.",
            'activity_date' => today(),
            'activity_time' => now()->format('h:i A'),
            'by'            => $request->user()->name ?? 'Staff',
        ]);

        return $this->success(['id' => $leadModel->id], 'Lead added.', 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a Relationship by ID. Throws a clean enveloped 404 on miss.
     * We do NOT scope to branch here — Relationships are clinic-wide records.
     */
    private function findRelationship(int $id): Relationship
    {
        $relationship = Relationship::find($id);

        if (! $relationship) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Relationship not found.',
                'errors'  => [],
            ], 404));
        }

        return $relationship;
    }
}
