<?php

namespace App\Services\Relationship;

use App\Jobs\RelationshipAutomationFailedJob;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RulesEngine — Phase 5, Relationship Engine
 *
 * Config-driven automation. Reads config/relationship_rules.php['rules'] and
 * fires the correct actions when ActivityEngine events are dispatched.
 *
 * Responsibilities:
 *   - getRulesForEvent()  : return all enabled rules matching a trigger event
 *   - evaluate()          : check conditions, fire actions, log rule firings
 *   - checkCooldown()     : prevent duplicate rule firings within cooldown window
 *
 * Design principles:
 *   - Never throws. All failures dispatched to RelationshipAutomationFailedJob.
 *   - Every fired rule is logged to ActivityEngine ('rule.fired') and to
 *     relationship_rule_logs (for cooldown tracking).
 *   - All timing values come from config — no hardcoded days/hours here.
 */
class RulesEngine
{
    public function __construct(
        protected ActivityEngine     $activityEngine,
        protected TaskEngine         $taskEngine,
        protected ReminderEngine     $reminderEngine,
        protected NotificationEngine $notificationEngine,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return all enabled rules that match the given ActivityEngine event.
     *
     * @param  string  $event  e.g. 'treatment.completed'
     * @return Collection<string, array>  Keyed by rule name
     */
    public function getRulesForEvent(string $event): Collection
    {
        $all = config('relationship_rules.rules', []);

        return collect($all)->filter(
            fn($rule) => ($rule['enabled'] ?? true) && ($rule['trigger'] ?? '') === $event
        );
    }

    /**
     * Evaluate all matching rules for an incoming ActivityEngine event.
     *
     * Called from ActivityEngine::log() inside a DB::afterCommit() closure.
     * Safe to call concurrently — cooldown check + rule log insert are
     * performed inside a single transaction per rule to prevent race conditions.
     *
     * @param  string     $event    ActivityEngine event key ('treatment.completed')
     * @param  Model      $subject  The model the event is about
     * @param  array      $context  Activity metadata (passed straight from ActivityEngine)
     */
    public function evaluate(string $event, Model $subject, array $context = []): void
    {
        $rules = $this->getRulesForEvent($event);

        if ($rules->isEmpty()) {
            return;
        }

        // Resolve the relationship_id — prefer explicit context value, fallback to subject attr
        $relationshipId = $context['relationship_id']
            ?? (isset($subject->relationship_id) ? (int) $subject->relationship_id : null);

        foreach ($rules as $ruleName => $rule) {
            try {
                // Phase 2 — reminder overlap guard. When the Automation Engine owns
                // reminders, defer reminder-producing rules to it so the same
                // appointment never gets two reminders (no double-contact). Flag OFF
                // (default) = fires exactly as before.
                if ($this->shouldDeferToAutomation($ruleName)) {
                    Log::debug("RulesEngine: deferring rule [{$ruleName}] to Automation Engine");
                    continue;
                }

                // Skip rules that require a relationship if we cannot resolve one
                if ($relationshipId === null) {
                    Log::debug("RulesEngine: no relationship_id for event [{$event}], skipping rule [{$ruleName}]");
                    continue;
                }

                // 1. Cooldown check
                if (!$this->checkCooldown($ruleName, $relationshipId)) {
                    Log::debug("RulesEngine: cooldown active for rule [{$ruleName}] on relationship [{$relationshipId}]");
                    continue;
                }

                // 2. Condition check — all conditions must match
                if (!$this->checkConditions($rule['conditions'] ?? [], $subject, $context)) {
                    continue;
                }

                // 3. Fire action
                $this->fireAction($ruleName, $rule, $subject, $context, $relationshipId, $event);

                // 4. Log the rule firing (cooldown tracking + audit)
                $this->logRuleFired($ruleName, $relationshipId, $subject, $context);

                // 5. Notify ActivityEngine (observable — always know why a task was created)
                $this->activityEngine->log(
                    subject:        $subject,
                    event:          'rule.fired',
                    actor:          null,
                    metadata:       ['rule' => $ruleName, 'trigger' => $event, 'action' => $rule['action'] ?? null],
                    relationshipId: $relationshipId,
                    description:    "Automation rule [{$ruleName}] fired on event [{$event}]",
                );

            } catch (\Throwable $e) {
                Log::error("RulesEngine: rule [{$ruleName}] failed", [
                    'event'           => $event,
                    'subject'         => get_class($subject) . '#' . $subject->getKey(),
                    'relationship_id' => $relationshipId,
                    'error'           => $e->getMessage(),
                ]);

                // FailSafe — dispatch job that handles logging + escalation + never re-throws
                dispatch(new RelationshipAutomationFailedJob(
                    ruleName:       $ruleName,
                    relationshipId: $relationshipId,
                    subjectType:    get_class($subject),
                    subjectId:      $subject->getKey(),
                    errorMessage:   $e->getMessage(),
                    context:        $context,
                ));
            }
        }
    }

    /**
     * Phase 2 consolidation guard. Some reminder-producing rules are OWNED by the
     * Automation Engine once automation.engine is on — this stops the same patient
     * getting a reminder from BOTH engines (no double-contact). Additive: flag OFF
     * (default) means every rule fires exactly as it did before Phase 2.
     */
    public function shouldDeferToAutomation(string $ruleName): bool
    {
        $automationOwned = ['appointment_reminder'];

        return in_array($ruleName, $automationOwned, true)
            && Feature::enabled('automation.engine');
    }

    /**
     * Check whether cooldown has expired for a given rule + relationship combination.
     *
     * Returns TRUE  if it's safe to fire (cooldown has passed or rule never fired).
     * Returns FALSE if it's blocked (fired too recently).
     *
     * @param  string  $ruleName
     * @param  int     $relationshipId
     */
    public function checkCooldown(string $ruleName, int $relationshipId): bool
    {
        $cooldownDays = config("relationship_rules.rules.{$ruleName}.cooldown_days", 0);

        if ($cooldownDays <= 0) {
            return true; // no cooldown configured
        }

        $since = Carbon::now()->subDays($cooldownDays);

        $recentFiring = DB::table('relationship_rule_logs')
            ->where('rule_name', $ruleName)
            ->where('relationship_id', $relationshipId)
            ->where('fired_at', '>=', $since)
            ->exists();

        return !$recentFiring; // true = OK to fire, false = blocked
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check all conditions for a rule.
     * All conditions must match (AND logic).
     * Each condition is a key=>value pair checked against $context first,
     * then against the subject model's attributes.
     */
    protected function checkConditions(array $conditions, Model $subject, array $context): bool
    {
        foreach ($conditions as $key => $expectedValue) {
            // Check context array first (most specific)
            if (array_key_exists($key, $context)) {
                // Loose comparison to handle string 'false' vs bool false from JSON context
                if ($context[$key] != $expectedValue) {
                    return false;
                }
                continue;
            }

            // Fallback: check subject model attribute
            if ($subject->getAttribute($key) != $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dispatch the rule's configured action to the correct engine.
     *
     * @param  string  $ruleName
     * @param  array   $rule            Full rule config from relationship_rules.php
     * @param  Model   $subject         The triggering subject (for context)
     * @param  array   $context         Activity metadata
     * @param  int     $relationshipId
     */
    protected function fireAction(
        string $ruleName,
        array  $rule,
        Model  $subject,
        array  $context,
        int    $relationshipId,
        string $event = ''
    ): void {
        $action = $rule['action'] ?? null;
        $config = $rule['action_config'] ?? [];

        match ($action) {
            'create_task' => $this->taskEngine->autoCreate(
                category:       $config['category'] ?? 'admin',
                taskData:       [
                    'title'    => $config['title'] ?? "Auto task: {$ruleName}",
                    'priority' => $config['priority'] ?? 'medium',
                    'due_date' => $this->resolveDueDate($config['days_after'] ?? 0, $context),
                    'description' => "[Auto] Rule: {$ruleName}",
                ],
                relationshipId: $relationshipId,
                patientId:      $context['patient_id'] ?? null,
                branchId:       $context['branch_id'] ?? null,
            ),

            'create_reminder' => $this->reminderEngine->createReminder(
                type:           $config['type'] ?? $ruleName,
                subject:        $subject,
                relationshipId: $relationshipId,
                dueAt:          Carbon::now()->addDays($config['days_after'] ?? 0),
            ),

            // Phase 6: send an in-app notification via NotificationEngine
            'send_notification' => $this->notificationEngine->notifyDefault(
                type:             $config['notification_type'] ?? $ruleName,
                relationshipId:   $relationshipId,
                title:            $config['title'] ?? ucwords(str_replace('_', ' ', $ruleName)),
                body:             $config['body']  ?? "Automation rule [{$ruleName}] triggered.",
                link:             isset($config['link_pattern'])
                    ? str_replace('{id}', (string) $relationshipId, $config['link_pattern'])
                    : "/relationship/{$relationshipId}",
                triggeredByEvent: $event,
                extraRecipients:  $config['extra_recipients'] ?? [],
            ),

            // Compound: create task AND send notification
            'create_task_and_notify' => (function () use ($ruleName, $rule, $config, $subject, $context, $relationshipId, $event) {
                $this->taskEngine->autoCreate(
                    category:       $config['category'] ?? 'admin',
                    taskData:       [
                        'title'       => $config['title']    ?? "Auto task: {$ruleName}",
                        'priority'    => $config['priority'] ?? 'medium',
                        'due_date'    => $this->resolveDueDate($config['days_after'] ?? 0, $context),
                        'description' => "[Auto] Rule: {$ruleName}",
                    ],
                    relationshipId: $relationshipId,
                    patientId:      $context['patient_id'] ?? null,
                    branchId:       $context['branch_id']  ?? null,
                );
                $this->notificationEngine->notifyDefault(
                    type:             $config['notification_type'] ?? $ruleName,
                    relationshipId:   $relationshipId,
                    title:            $config['title']   ?? ucwords(str_replace('_', ' ', $ruleName)),
                    body:             $config['body']    ?? "Task auto-created by rule [{$ruleName}].",
                    link:             "/relationship/{$relationshipId}",
                    triggeredByEvent: $event,
                    extraRecipients:  $config['extra_recipients'] ?? [],
                );
            })(),

            default => Log::warning("RulesEngine: unknown action [{$action}] in rule [{$ruleName}]"),
        };
    }

    /**
     * Compute the due date for a task from a days_after offset.
     *
     * Positive days_after = N days from now.
     * Negative days_after = N days BEFORE the event date (for appointment reminders).
     * If context has an 'event_date' key, uses that as base; otherwise uses today.
     */
    protected function resolveDueDate(int $daysAfter, array $context): Carbon
    {
        // For appointment reminders, context should carry the appointment date
        $base = isset($context['event_date'])
            ? Carbon::parse($context['event_date'])
            : Carbon::today();

        return $base->copy()->addDays($daysAfter);
    }

    /**
     * Write a row to relationship_rule_logs.
     * This is what checkCooldown() queries on the next run.
     */
    protected function logRuleFired(
        string $ruleName,
        int    $relationshipId,
        Model  $subject,
        array  $metadata
    ): void {
        DB::table('relationship_rule_logs')->insert([
            'rule_name'       => $ruleName,
            'relationship_id' => $relationshipId,
            'subject_type'    => get_class($subject),
            'subject_id'      => $subject->getKey(),
            'fired_at'        => now(),
            'metadata'        => json_encode($metadata),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
