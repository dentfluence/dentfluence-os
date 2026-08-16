<?php

namespace App\Services\Clinical;

use App\Models\FollowUp;
use App\Models\TreatmentVisit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * VisitNextActionService
 * ----------------------
 * Turns "what does reception need to do next?" — answered ONCE by the doctor
 * at the chair on the Visit Log — into a scheduled follow-up, so the doctor
 * never re-creates that action in the next morning's Daily Huddle.
 *
 * CANONICAL RECORD: `follow_ups`.
 *
 * Deliberately NOT communication_queue. Two reasons, both load-bearing:
 *
 *  1. TodayActionsEngine::recallCalls() has no date predicate — a queue row
 *     dated three weeks out appears in TODAY's Communication List the moment
 *     it is written. That is precisely the behaviour this workflow forbids.
 *     TodayActionsEngine::followUpCalls() already gates on
 *     `due_date <= today`, which is the contract we need.
 *  2. One scheduled patient action = one canonical record. The Huddle and the
 *     Communication List are SURFACES over that record, not second copies of
 *     it. Writing to both tables would produce two rows, two completion paths
 *     (FollowUp::completed_at vs CommunicationQueue::autoClose) and a
 *     guaranteed orphan every time staff closed one of them.
 *
 * Surfaces that pick these rows up with no further work — all pre-existing:
 *   • Action Board  → TodayActionsEngine::followUpCalls()      (due_date <= today)
 *   • Daily Huddle  → HuddleController Comms Section 3          (due_date <= today)
 *   • Mobile        → HuddleBoardApiService::comms()
 *   • Completion    → TodayController::closeUnderlyingRecord()  ('follow_up_calls')
 *   • Follow-up Engine board → FollowUpController
 *
 * The Huddle additionally shows FUTURE-dated rows from here as read-only
 * "upcoming", which is how the manager briefs the call team days ahead
 * without the item becoming actionable early.
 */
class VisitNextActionService
{
    /**
     * What reception can be asked to do. Kept deliberately short — this is a
     * chairside picker, not a taxonomy. `key => [label, channel, priority]`.
     */
    public const ACTION_TYPES = [
        'wellness_call'      => ['label' => 'Wellness Call',       'channel' => 'call',     'priority' => 'medium'],
        'follow_up_call'     => ['label' => 'Follow-up Call',      'channel' => 'call',     'priority' => 'medium'],
        'treatment_followup' => ['label' => 'Treatment Follow-up', 'channel' => 'call',     'priority' => 'high'],
        'post_op_check'      => ['label' => 'Post-Op Check',       'channel' => 'call',     'priority' => 'high'],
        'book_appointment'   => ['label' => 'Book Appointment',    'channel' => 'call',     'priority' => 'medium'],
        'send_instructions'  => ['label' => 'Send Instructions',   'channel' => 'whatsapp', 'priority' => 'low'],
        'other'              => ['label' => 'Other',               'channel' => 'call',     'priority' => 'medium'],
    ];

    /** Marks a follow_ups row as doctor-authored from a visit. */
    public const TRIGGER_TYPE = 'visit_next_action';

    /**
     * Validation rules merged into TreatmentVisitService::rules().
     *
     * `due_mode` drives which date field matters, so the doctor can tap
     * "Tomorrow" without touching a date picker:
     *   tomorrow  → visit_date + 1
     *   in_days   → visit_date + due_in_days
     *   on_date   → due_date verbatim
     */
    public static function rules(): array
    {
        return [
            'next_actions'                 => ['nullable', 'array', 'max:5'],
            'next_actions.*.id'            => ['nullable', 'integer'],
            'next_actions.*.action_type'   => ['required_with:next_actions', 'string', 'in:' . implode(',', array_keys(self::ACTION_TYPES))],
            'next_actions.*.due_mode'      => ['required_with:next_actions', 'string', 'in:tomorrow,in_days,on_date'],
            'next_actions.*.due_in_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'next_actions.*.due_date'      => ['nullable', 'date'],
            'next_actions.*.instruction'   => ['nullable', 'string', 'max:1000'],
            'next_actions.*.assigned_to'   => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Reconcile this visit's next actions with the submitted rows.
     *
     * Idempotent by construction (CEO Test Case 3): the reconcile is keyed on
     * `follow_ups.treatment_visit_id`, so opening and re-saving the Visit Log
     * updates the same rows instead of inserting new ones.
     *
     * Rules of engagement:
     *   • $rows === null  → the payload never mentioned next actions (e.g. a
     *     mobile client on an older build). Leave existing rows untouched.
     *     Absence is not an instruction to delete.
     *   • COMPLETED rows are never modified or removed. Once the call team has
     *     executed and recorded an outcome, that is history — a doctor editing
     *     the visit afterwards cannot rewrite it.
     *   • A pending row the doctor removed from the form is soft-deleted.
     */
    public function syncFromVisit(TreatmentVisit $visit, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        /** @var Collection<int, FollowUp> $existing */
        $existing = FollowUp::fromVisit()
            ->where('treatment_visit_id', $visit->id)
            ->where('status', 'pending')
            ->get();

        $keptIds = [];

        foreach ($rows as $row) {
            if (empty($row['action_type']) || empty($row['due_mode'])) {
                continue;
            }

            $model = $this->matchExisting($existing, $row, $keptIds, $visit);
            $model->fill($this->attributesFor($visit, $row));

            // Fields RECEPTION owns, not the doctor. Once the front desk has
            // assigned the call to someone or moved it off the default 10:00
            // slot, a later edit of the visit must not silently undo that —
            // the doctor's form does not even show these fields, so a blank
            // here means "not mentioned", never "clear it".
            if (!$model->exists) {
                $model->created_by  = Auth::id();
                $model->assigned_to = $row['assigned_to'] ?? null;
                $model->due_time    = '10:00';
            } elseif (array_key_exists('assigned_to', $row) && $row['assigned_to'] !== null) {
                $model->assigned_to = $row['assigned_to'];
            }

            $model->save();
            $keptIds[] = $model->id;
        }

        // Anything the doctor took off the form goes away — but only if it is
        // still pending and still ours.
        $existing->reject(fn (FollowUp $f) => in_array($f->id, $keptIds, true))
                 ->each(fn (FollowUp $f) => $f->delete());
    }

    /**
     * Cancel this visit's outstanding next actions.
     *
     * Called when the visit itself is deleted — a follow-up for a visit that
     * no longer exists is noise the call team would have no context for.
     * Completed rows survive, same reasoning as syncFromVisit().
     */
    public function cancelForVisit(TreatmentVisit $visit): void
    {
        FollowUp::fromVisit()
            ->where('treatment_visit_id', $visit->id)
            ->where('status', 'pending')
            ->get()
            ->each(fn (FollowUp $f) => $f->delete());
    }

    /**
     * Shape used to re-populate the form when a visit is edited, and by the
     * Huddle to describe an upcoming action.
     */
    public static function present(FollowUp $followUp): array
    {
        return [
            'id'          => $followUp->id,
            'action_type' => $followUp->trigger_value,
            'label'       => $followUp->label,
            'due_mode'    => 'on_date',
            'due_date'    => $followUp->due_date?->format('Y-m-d'),
            'due_in_days' => null,
            'instruction' => $followUp->note,
            'assigned_to' => $followUp->assigned_to,
            'status'      => $followUp->status,
        ];
    }

    public static function label(?string $actionType): string
    {
        return self::ACTION_TYPES[$actionType]['label'] ?? 'Follow-up Call';
    }

    // ── internals ──────────────────────────────────────────────────────────

    /**
     * Find the row this submission refers to, or return a fresh model.
     *
     * Primary match is the explicit id the form round-trips. The secondary
     * match (same action type + same resolved due date) is belt and braces
     * against a client that drops ids — without it, a double-submit would
     * insert a second identical call for the same patient on the same day.
     */
    private function matchExisting(Collection $existing, array $row, array $keptIds, TreatmentVisit $visit): FollowUp
    {
        if (!empty($row['id'])) {
            $byId = $existing->firstWhere('id', (int) $row['id']);
            if ($byId && !in_array($byId->id, $keptIds, true)) {
                return $byId;
            }
        }

        $due = $this->resolveDueDate($row, $visit);

        $byShape = $existing->first(fn (FollowUp $f) =>
            !in_array($f->id, $keptIds, true)
            && $f->trigger_value === $row['action_type']
            && $f->due_date?->isSameDay($due)
        );

        return $byShape ?: new FollowUp();
    }

    private function attributesFor(TreatmentVisit $visit, array $row): array
    {
        $type   = $row['action_type'];
        $config = self::ACTION_TYPES[$type];

        return [
            'patient_id'         => $visit->patient_id,
            'treatment_visit_id' => $visit->id,
            'label'              => $config['label'],
            'trigger_type'       => self::TRIGGER_TYPE,
            'trigger_value'      => $type,
            'due_date'           => $this->resolveDueDate($row, $visit),
            'channel'            => $config['channel'],
            'priority'           => $config['priority'],
            'status'             => 'pending',
            'note'               => $row['instruction'] ?? null,
            'appears_in'         => ['daily_huddle', 'communication_manager'],
            // A human chose this deliberately at the chair; it is not a rule
            // engine emission, so auto_created stays false.
            'auto_created'       => false,
        ];
    }

    /**
     * Anchored on the VISIT date, not on now() — a visit back-dated to
     * yesterday and saved this morning must schedule "call after 4 days"
     * from the day the patient was actually seen.
     *
     * Never returns a date before the visit date.
     */
    private function resolveDueDate(array $row, TreatmentVisit $visit): Carbon
    {
        $anchor = $visit->visit_date
            ? Carbon::parse($visit->visit_date)->startOfDay()
            : Carbon::today();

        $due = match ($row['due_mode']) {
            'tomorrow' => $anchor->copy()->addDay(),
            'in_days'  => $anchor->copy()->addDays(max(1, (int) ($row['due_in_days'] ?? 1))),
            'on_date'  => !empty($row['due_date'])
                ? Carbon::parse($row['due_date'])->startOfDay()
                : $anchor->copy()->addDay(),
            default    => $anchor->copy()->addDay(),
        };

        return $due->lessThan($anchor) ? $anchor : $due;
    }
}
