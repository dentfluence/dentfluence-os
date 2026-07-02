<?php

namespace App\Services\Relationship;

use Carbon\Carbon;

/**
 * FollowUpRuleEngine — Phase 2, Slice 6 (rules consolidation).
 *
 * The Rules-Engine-owned home for follow-up rule resolution. This is the
 * legacy App\Services\Communication\FollowUpRulesService config "ported into the
 * Rules Engine": it reads the SAME config (followup_rules.php + followup_settings.php)
 * and resolves the SAME follow-up definitions — proven byte-identical by
 * FollowUpRuleEngineParityTest and the legacy characterization test.
 *
 * Behind the `rules.single_engine` flag, the two live call sites
 * (LeadFollowUpService, FollowUpController) resolve through here instead of
 * instantiating the legacy service directly. Flag OFF (default) = legacy path,
 * unchanged. Flag ON = this engine owns the decision. Instant rollback = flag off.
 * The legacy class stays warm until the flag has soaked; it is only deleted later,
 * deliberately, with sign-off.
 *
 * Output records are unchanged: both paths return the same definition arrays and
 * the callers still write follow_ups rows — so the Follow-up queue UI is untouched.
 */
class FollowUpRuleEngine
{
    protected array $rules;
    protected array $settings;

    public function __construct()
    {
        $this->rules    = config('followup_rules', []);
        $this->settings = config('followup_settings', []);
    }

    /**
     * Resolve which follow-ups to create for a given trigger.
     * Faithful port of FollowUpRulesService::resolve().
     *
     * @return array  Array of follow-up definition arrays.
     */
    public function resolve(
        string $triggerType,
        string $triggerValue,
        string $subValue = '',
        array  $context  = []
    ): array {
        $section = $this->rules[$triggerType] ?? [];

        if (empty($section)) {
            return [];
        }

        if ($triggerType === 'treatment_status_changed') {
            $rules = $section[$triggerValue][$subValue] ?? [];
        } else {
            $rules = $section[$triggerValue] ?? [];
        }

        if (empty($rules)) {
            return [];
        }

        $baseDate = isset($context['base_date'])
            ? Carbon::parse($context['base_date'])
            : Carbon::today();

        $followUps = [];

        foreach ($rules as $rule) {
            $dueDate = $this->calculateDueDate($baseDate, $rule['day_offset']);

            $followUps[] = [
                'label'         => $rule['label'],
                'trigger_type'  => $triggerType,
                'trigger_value' => $triggerValue,
                'channel'       => $rule['channel'],
                'priority'      => $rule['priority'],
                'note'          => $rule['note'] ?? '',
                'due_date'      => $dueDate->toDateString(),
                'due_time'      => $this->settings['default_followup_time'] ?? '10:00',
                'appears_in'    => $rule['appears_in'] ?? ['communication_manager'],
                'patient_id'    => $context['patient_id'] ?? null,
                'lead_id'       => $context['lead_id'] ?? null,
                'assigned_to'   => $context['assigned_to'] ?? null,
                'auto_created'  => true,
            ];
        }

        return $followUps;
    }

    /**
     * Calculate due date respecting working days.
     * Faithful port of FollowUpRulesService::calculateDueDate().
     */
    protected function calculateDueDate(Carbon $baseDate, int $dayOffset): Carbon
    {
        if ($dayOffset === 0) {
            return $baseDate->copy();
        }

        $date      = $baseDate->copy();
        $workDays  = $this->settings['working_days'] ?? [1, 2, 3, 4, 5, 6];
        $step      = $dayOffset > 0 ? 1 : -1;
        $remaining = abs($dayOffset);

        if (abs($dayOffset) > 30) {
            return $date->addDays($dayOffset);
        }

        while ($remaining > 0) {
            $date->addDays($step);
            $dayOfWeek = (int) $date->format('N');
            if (in_array($dayOfWeek, $workDays)) {
                $remaining--;
            }
        }

        return $date;
    }
}
