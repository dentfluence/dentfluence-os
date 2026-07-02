<?php

namespace App\Models;

use App\Exceptions\InvalidTransitionException;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RelationshipJourney — one segment of a person's lifecycle with the clinic.
 *
 * A Relationship can have many journeys (one lead journey, many opportunity
 * journeys, one recall journey running forever, etc.). Each journey has a
 * defined set of valid states and a state machine that enforces transitions.
 *
 * @property int         $id
 * @property int         $relationship_id
 * @property string      $type    lead|treatment|recall|opportunity|membership|referral
 * @property string      $state
 * @property array|null  $metadata
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $closed_at
 */
class RelationshipJourney extends Model
{
    // ── Journey type constants ─────────────────────────────────────────────────

    const TYPE_LEAD        = 'lead';
    const TYPE_TREATMENT   = 'treatment';
    const TYPE_RECALL      = 'recall';
    const TYPE_OPPORTUNITY = 'opportunity';
    const TYPE_MEMBERSHIP  = 'membership';
    const TYPE_REFERRAL    = 'referral';

    // ── Lead Journey states ────────────────────────────────────────────────────

    const LEAD_NEW_ENQUIRY       = 'new_enquiry';
    const LEAD_CONTACTED         = 'contacted';
    const LEAD_APPOINTMENT_BOOKED = 'appointment_booked';
    const LEAD_CONSULTATION      = 'consultation';
    const LEAD_TREATMENT_PLANNED = 'treatment_planned';
    const LEAD_TREATMENT_STARTED = 'treatment_started';
    const LEAD_CLOSED            = 'closed';
    const LEAD_LOST              = 'lost';

    /**
     * Valid state transitions for the Lead journey.
     * Key = current state, value = array of allowed next states.
     */
    const LEAD_TRANSITIONS = [
        self::LEAD_NEW_ENQUIRY        => [self::LEAD_CONTACTED, self::LEAD_APPOINTMENT_BOOKED, self::LEAD_LOST],
        self::LEAD_CONTACTED          => [self::LEAD_APPOINTMENT_BOOKED, self::LEAD_LOST],
        self::LEAD_APPOINTMENT_BOOKED => [self::LEAD_CONSULTATION, self::LEAD_LOST],
        self::LEAD_CONSULTATION       => [self::LEAD_TREATMENT_PLANNED, self::LEAD_LOST],
        self::LEAD_TREATMENT_PLANNED  => [self::LEAD_TREATMENT_STARTED, self::LEAD_LOST],
        self::LEAD_TREATMENT_STARTED  => [self::LEAD_CLOSED],
        self::LEAD_CLOSED             => [],   // terminal state
        self::LEAD_LOST               => [],   // terminal state
    ];

    /**
     * VALID_TRANSITIONS — master map used by canTransitionTo() and transition().
     *
     * Keyed by journey type, then current state → allowed next states.
     * More restrictive than the per-type constants above; those remain for
     * backward-compat reference. This is the single enforced source of truth
     * for Phase 4 onwards.
     */
    const VALID_TRANSITIONS = [
        'lead' => [
            'new_enquiry'        => ['contacted', 'lost'],
            'contacted'          => ['appointment_booked', 'lost'],
            'appointment_booked' => ['consultation', 'lost'],
            'consultation'       => ['treatment_planned', 'lost'],
            'treatment_planned'  => ['treatment_started', 'lost'],
            'treatment_started'  => ['closed'],
            'lost'               => [],   // terminal
            'closed'             => [],   // terminal
        ],
    ];

    // ── Recall Journey states ──────────────────────────────────────────────────

    const RECALL_DUE        = 'due';
    const RECALL_CONTACTED  = 'contacted';
    const RECALL_BOOKED     = 'booked';
    const RECALL_COMPLETED  = 'completed';
    const RECALL_DECLINED   = 'declined';

    const RECALL_TRANSITIONS = [
        self::RECALL_DUE       => [self::RECALL_CONTACTED, self::RECALL_DECLINED],
        self::RECALL_CONTACTED => [self::RECALL_BOOKED, self::RECALL_DECLINED],
        self::RECALL_BOOKED    => [self::RECALL_COMPLETED, self::RECALL_DECLINED],
        self::RECALL_COMPLETED => [],  // terminal — a new recall journey starts next cycle
        self::RECALL_DECLINED  => [],
    ];

    // ── Opportunity Journey states ─────────────────────────────────────────────

    const OPPORTUNITY_IDENTIFIED = 'identified';
    const OPPORTUNITY_PRESENTED  = 'presented';
    const OPPORTUNITY_QUOTED     = 'quoted';
    const OPPORTUNITY_ACCEPTED   = 'accepted';
    const OPPORTUNITY_DEFERRED   = 'deferred';
    const OPPORTUNITY_DECLINED   = 'declined';
    const OPPORTUNITY_COMPLETED  = 'completed';

    const OPPORTUNITY_TRANSITIONS = [
        self::OPPORTUNITY_IDENTIFIED => [self::OPPORTUNITY_PRESENTED, self::OPPORTUNITY_DECLINED],
        self::OPPORTUNITY_PRESENTED  => [self::OPPORTUNITY_QUOTED, self::OPPORTUNITY_DECLINED],
        self::OPPORTUNITY_QUOTED     => [self::OPPORTUNITY_ACCEPTED, self::OPPORTUNITY_DEFERRED, self::OPPORTUNITY_DECLINED],
        self::OPPORTUNITY_ACCEPTED   => [self::OPPORTUNITY_COMPLETED, self::OPPORTUNITY_DECLINED],
        self::OPPORTUNITY_DEFERRED   => [self::OPPORTUNITY_PRESENTED, self::OPPORTUNITY_DECLINED],
        self::OPPORTUNITY_COMPLETED  => [],
        self::OPPORTUNITY_DECLINED   => [],
    ];

    // ── Model config ───────────────────────────────────────────────────────────

    protected $fillable = [
        'relationship_id',
        'type',
        'state',
        'metadata',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'started_at' => 'datetime',
        'closed_at'  => 'datetime',
    ];

    // ── State machine ──────────────────────────────────────────────────────────

    /**
     * Check whether a transition from the current state to $newState is allowed.
     *
     * Returns true if the transition is in the allowlist for this journey type.
     * Returns true by default for journey types that don't have a defined
     * transition map (future types), so new types don't need code changes here.
     */
    public function canTransitionTo(string $newState): bool
    {
        // Check VALID_TRANSITIONS (Phase 4 enforced map) first.
        // Falls through to per-type constants for journey types not yet in VALID_TRANSITIONS.
        if (isset(self::VALID_TRANSITIONS[$this->type])) {
            $allowed = self::VALID_TRANSITIONS[$this->type][$this->state] ?? [];
            return in_array($newState, $allowed, true);
        }

        // Fallback: legacy per-type constants (recall, opportunity, etc.)
        $map = match ($this->type) {
            self::TYPE_RECALL      => self::RECALL_TRANSITIONS,
            self::TYPE_OPPORTUNITY => self::OPPORTUNITY_TRANSITIONS,
            default                => null,
        };

        // No transition map defined for this type — allow freely
        if ($map === null) {
            return true;
        }

        $allowed = $map[$this->state] ?? [];

        return in_array($newState, $allowed, true);
    }

    /**
     * Perform a validated state transition.
     *
     * Validates via canTransitionTo(), updates state, stamps closed_at for
     * terminal states, and logs the event to ActivityEngine.
     *
     * @param  string         $newState  Target state.
     * @param  \App\Models\User|null $actor Who triggered the change (null = system).
     *
     * @throws InvalidTransitionException  If the transition is not allowed.
     */
    public function transition(string $newState, $actor = null): void
    {
        if (! $this->canTransitionTo($newState)) {
            throw new InvalidTransitionException(
                "Cannot transition {$this->type} journey from '{$this->state}' to '{$newState}'."
            );
        }

        $oldState     = $this->state;
        $terminalStates = ['closed', 'lost', 'completed', 'declined'];

        $this->update([
            'state'     => $newState,
            'closed_at' => in_array($newState, $terminalStates) ? now() : $this->closed_at,
        ]);

        // Log to ActivityEngine — never breaks the transition if logging fails
        try {
            app(ActivityEngine::class)->log(
                $this,
                'journey.transitioned',
                $actor,
                [
                    'journey_type' => $this->type,
                    'from'         => $oldState,
                    'to'           => $newState,
                ],
                $this->relationship_id,
            );
        } catch (\Throwable) {
            // ActivityEngine failure must never block the transition
        }
    }

    /**
     * Convenience: is this journey closed (terminal state with closed_at set)?
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }
}
