<?php

namespace App\Services\Relationship;

use App\Http\Controllers\Relationship\ProfileController;
use App\Models\Relationship;
use Carbon\Carbon;

/**
 * TimelineParityService — Phase 1 · Sprint 3 (timeline cutover safety).
 *
 * Compares the LEGACY profile timeline (ProfileController::buildTimeline)
 * against the UNIFIED timeline (UnifiedTimelineService) for a relationship,
 * event by event, and reports any that appear in one but not the other.
 *
 * Used by `relationship:timeline-parity` to prove parity on real data BEFORE
 * the profile is switched to the unified source. If anything is missing in
 * either direction, the cutover must not happen.
 */
class TimelineParityService
{
    public function __construct(private readonly UnifiedTimelineService $unified)
    {
    }

    /**
     * @return array{
     *   relationship_id:int, match:bool,
     *   legacy_count:int, unified_count:int,
     *   missing_in_unified:array<int,string>, missing_in_legacy:array<int,string>
     * }
     */
    public function compare(Relationship $relationship): array
    {
        // Legacy path — reuse the controller's exact builder.
        $legacy = app(ProfileController::class)
            ->buildTimeline($relationship, $relationship->lead, $relationship->patient);

        $unified = $this->unified->for($relationship);

        $legacySigs  = $legacy->map(fn ($e) => $this->signature($e))->all();
        $unifiedSigs = $unified->map(fn ($e) => $this->signature($e))->all();

        $missingInUnified = array_values(array_diff($legacySigs, $unifiedSigs));
        $missingInLegacy  = array_values(array_diff($unifiedSigs, $legacySigs));

        return [
            'relationship_id'    => $relationship->id,
            'legacy_count'       => count($legacySigs),
            'unified_count'      => count($unifiedSigs),
            'missing_in_unified' => $missingInUnified,
            'missing_in_legacy'  => $missingInLegacy,
            'match'              => empty($missingInUnified) && empty($missingInLegacy),
        ];
    }

    /** Stable per-event signature: minute-precision date + type + title. */
    private function signature(array $entry): string
    {
        $date = $entry['date'] instanceof Carbon
            ? $entry['date']->format('Y-m-d H:i')
            : (string) ($entry['date'] ?? '');

        return $date . '|' . ($entry['type'] ?? '') . '|' . ($entry['title'] ?? '');
    }
}
