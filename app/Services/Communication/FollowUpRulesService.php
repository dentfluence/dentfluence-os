<?php

namespace App\Services\Communication;

use Carbon\Carbon;

/**
 * FollowUpRulesService
 *
 * Reads followup_rules.php and followup_settings.php.
 * Returns an array of follow-up objects to be created.
 * Called by any controller/event that needs to auto-create follow-ups.
 *
 * USAGE EXAMPLE:
 *   $service = new FollowUpRulesService();
 *   $followups = $service->resolve('treatment_status_changed', 'extraction', 'active');
 *   // Returns array of follow-up data arrays ready to insert
 */
class FollowUpRulesService
{
    protected array $rules;
    protected array $settings;

    public function __construct()
    {
        $this->rules    = config('followup_rules', []);
        $this->settings = config('followup_settings', []);
    }

    /**
     * Main entry point.
     * Resolve which follow-ups to create for a given trigger.
     *
     * @param string $triggerType  e.g. 'treatment_status_changed'
     * @param string $triggerValue e.g. 'extraction'
     * @param string $subValue     e.g. 'active' or 'complete' (optional)
     * @param array  $context      Extra context: patient_id, lead_id, assigned_to, base_date
     *
     * @return array  Array of follow-up data arrays
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

        // For treatment_status_changed: rules[$trigger][$treatment][$status]
        if ($triggerType === 'treatment_status_changed') {
            $rules = $section[$triggerValue][$subValue] ?? [];
        } else {
            // For prm_stage_changed, appointment_event, special_occasion
            $rules = $section[$triggerValue] ?? [];
        }

        if (empty($rules)) {
            return [];
        }

        $baseDate   = isset($context['base_date'])
            ? Carbon::parse($context['base_date'])
            : Carbon::today();

        $followUps  = [];

        foreach ($rules as $rule) {
            $dueDate = $this->calculateDueDate($baseDate, $rule['day_offset']);

            // Duplicate prevention check (skipped here — done in controller)
            $followUps[] = [
                'label'        => $rule['label'],
                'trigger_type' => $triggerType,
                'trigger_value'=> $triggerValue,
                'channel'      => $rule['channel'],
                'priority'     => $rule['priority'],
                'note'         => $rule['note'] ?? '',
                'due_date'     => $dueDate->toDateString(),
                'due_time'     => $this->settings['default_followup_time'] ?? '10:00',
                'appears_in'   => $rule['appears_in'] ?? ['communication_manager'],
                'patient_id'   => $context['patient_id'] ?? null,
                'lead_id'      => $context['lead_id'] ?? null,
                'assigned_to'  => $context['assigned_to'] ?? null,
                'auto_created' => true,
            ];
        }

        return $followUps;
    }

    /**
     * Calculate due date respecting working days.
     * Negative offsets (e.g. -1 for "day before appointment") are supported.
     */
    protected function calculateDueDate(Carbon $baseDate, int $dayOffset): Carbon
    {
        if ($dayOffset === 0) {
            return $baseDate->copy();
        }

        $date      = $baseDate->copy();
        $workDays  = $this->settings['working_days'] ?? [1,2,3,4,5,6];
        $step      = $dayOffset > 0 ? 1 : -1;
        $remaining = abs($dayOffset);

        // For large offsets (recalls) skip working day check for performance
        if (abs($dayOffset) > 30) {
            return $date->addDays($dayOffset);
        }

        while ($remaining > 0) {
            $date->addDays($step);
            $dayOfWeek = (int) $date->format('N'); // 1=Mon 7=Sun
            if (in_array($dayOfWeek, $workDays)) {
                $remaining--;
            }
        }

        return $date;
    }

    /**
     * Get all rules for a trigger type (for display in settings UI later).
     */
    public function getRulesFor(string $triggerType): array
    {
        return $this->rules[$triggerType] ?? [];
    }

    /**
     * Get settings value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
