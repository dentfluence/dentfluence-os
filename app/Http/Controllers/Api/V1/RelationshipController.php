<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Activity;
use App\Models\Relationship;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\RelationshipEngine;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RelationshipController (API v1)
 * --------------------------------
 * Mobile / Tulip face of the Relationship Engine.
 * THIN — every read goes through RelationshipEngine / ActivityEngine services;
 * the same services the web pages use, so behaviour never drifts.
 *
 *   GET  /api/v1/relationships/today            → TodayActionsEngine grouped output
 *   GET  /api/v1/relationships/search?q=        → Universal person search
 *   GET  /api/v1/relationships/{id}             → Relationship profile summary
 *   GET  /api/v1/relationships/{id}/timeline    → Paginated activity timeline
 *   GET  /api/v1/relationships/{id}/journeys    → All journeys with state
 *   POST /api/v1/relationships/{id}/activity    → Log an activity from mobile
 *
 * Auth: Sanctum (via 'auth:sanctum' middleware on the api route group).
 * All routes inherit the existing Api/V1 middleware stack.
 */
class RelationshipController extends ApiController
{
    public function __construct(
        private readonly RelationshipEngine  $relationshipEngine,
        private readonly ActivityEngine      $activityEngine,
        private readonly TodayActionsEngine  $todayActionsEngine,
    ) {}

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
            return $this->error('Failed to log activity. Please try again.', 500);
        }

        return $this->success([
            'id'          => $activity->id,
            'event'       => $activity->event,
            'description' => $activity->description,
            'occurred_at' => $activity->occurred_at?->toIso8601String(),
        ], 'Activity logged.', 201);
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
