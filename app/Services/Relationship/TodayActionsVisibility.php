<?php

namespace App\Services\Relationship;

use App\Models\AppSetting;

/**
 * TodayActionsVisibility — shared presentation filter for Today's Actions output.
 *
 * The TodayActionsEngine generates everything; these rules decide only what a
 * staff-facing surface is allowed to SHOW. Nothing here changes generation,
 * dedup, dismissal or any producer — switching the settings back brings the
 * same rows straight back.
 *
 * Extracted 2026-09-08. The rules previously lived as private methods on
 * TodayController, so the Daily Huddle (web + mobile), which reads the engine
 * directly, showed rows the Today's Actions board had already suppressed —
 * birthday recalls and hidden categories were reappearing in the huddle Comms
 * List. Both surfaces now share this one filter.
 */
class TodayActionsVisibility
{
    /**
     * Apply every visibility rule to a raw engine/projection group array.
     *
     * @param  array<string, array<int, array<string,mixed>>>  $raw
     * @return array<string, array<int, array<string,mixed>>>
     */
    public function apply(array $raw): array
    {
        if ($this->birthdaysHidden()) {
            $this->stripBirthdayRows($raw);
        }

        foreach (array_keys($raw) as $key) {
            if ($this->categoryHidden($key)) {
                unset($raw[$key]);
            }
        }

        return $raw;
    }

    /** Settings -> Today's Actions: "Hide birthday rows" (default ON). */
    public function birthdaysHidden(): bool
    {
        return AppSetting::get('today.hide_birthdays', '1') === '1';
    }

    /** Settings -> Today's Actions: per-category show/hide (default shown). */
    public function categoryHidden(string $category): bool
    {
        return AppSetting::get("today.show.{$category}", '1') !== '1';
    }

    /**
     * Birthday suppression (2026-08-25, Sumit).
     *
     * Birthdays are NOT a board category — they arrive through two separate
     * producers and duplicate each other in the queue:
     *   1. RecallEngineService::recallBirthday() queues a CommunicationQueue
     *      row with purpose = 'recall_birthday'  -> surfaces in recall_calls
     *   2. RulesEngine rule 'birthday_3d' creates a system Task
     *      (description "[Auto] Rule: birthday_3d") -> surfaces in tasks
     *
     * This strips both at the VIEW layer only. Neither producer is touched.
     *
     * 2026-09-11: 'missed_calls_yesterday' added. A birthday recall hidden on
     * its due day was never actioned, so the next morning it came back as a
     * HIGH "Overdue (Recall birthday)" missed call — 4 of the 7 rows on the
     * live board that day. Hidden means hidden on every day it could appear.
     *
     * @param  array<string, array<int, array<string,mixed>>>  $raw
     */
    public function stripBirthdayRows(array &$raw): void
    {
        foreach (['recall_calls', 'tasks', 'missed_calls_yesterday'] as $key) {
            if (empty($raw[$key])) {
                continue;
            }

            $raw[$key] = array_values(array_filter($raw[$key], function (array $item) {
                if (($item['meta']['purpose'] ?? null) === 'recall_birthday') {
                    return false;
                }

                $haystack = strtolower(
                    ($item['suggested_action'] ?? '') . ' ' . ($item['meta']['category'] ?? '')
                );

                return ! str_contains($haystack, 'birthday');
            }));
        }
    }
}
