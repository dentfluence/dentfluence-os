<?php

namespace App\Services\Patient;

use App\Models\Patient;
use App\Models\User;
use App\Services\Relationship\UnifiedTimelineService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PatientJourneyService — the CANONICAL patient-history read model
 * (Patients Module Phase 4, Slice 2 · Amendment 1, Technical Decision 0).
 *
 * Any surface that needs "what happened with this patient" consumes THIS
 * service: Patient Profile (web), Mobile App API, AI Copilot tools, and the
 * future Chairside App / Patient Microsite. Never query source models
 * directly for history, and never build a parallel aggregator.
 *
 * It is a thin facade over UnifiedTimelineService::forPatient() (the single
 * aggregator) adding the three concerns the aggregator deliberately leaves
 * to the caller:
 *   1. permission filtering  — events carry "module.action"; entries the
 *      viewer cannot access are dropped (e.g. no billing.view → no invoices)
 *   2. group filtering       — all|clinical|financial|comms|consent|reviews
 *   3. cursor pagination     — fixed page size + "load older" cursor
 *
 * New modules appear on the journey by emitting Activity-ledger events
 * (preferred) or one source adapter in UnifiedTimelineService — the public
 * shape of this service never changes for that.
 */
class PatientJourneyService
{
    public const GROUPS    = ['all', 'clinical', 'financial', 'comms', 'consent', 'reviews'];
    public const PAGE_SIZE = 20;

    public function __construct(private UnifiedTimelineService $timeline)
    {
    }

    /**
     * One page of the patient's journey, newest-first.
     *
     * @param  Patient      $patient
     * @param  User|null    $viewer  permission filtering context (null = system/AI: no filtering)
     * @param  string       $group   one of self::GROUPS
     * @param  Carbon|null  $before  cursor — only events strictly older than this
     * @param  int          $limit   page size
     * @return array{events: Collection, next_cursor: ?string, group: string}
     */
    public function for(
        Patient $patient,
        ?User $viewer = null,
        string $group = 'all',
        ?Carbon $before = null,
        int $limit = self::PAGE_SIZE,
    ): array {
        $group = in_array($group, self::GROUPS, true) ? $group : 'all';

        $events = $this->timeline->forPatient($patient, $before);

        if ($group !== 'all') {
            $events = $events->filter(fn ($e) => ($e['group'] ?? 'comms') === $group);
        }

        if ($viewer) {
            $events = $events->filter(fn ($e) => $this->canSee($viewer, (string) ($e['permission'] ?? 'patients.view')));
        }

        $events = $events->values();

        $page    = $events->take($limit)->values();
        $hasMore = $events->count() > $limit;

        return [
            'events'      => $page,
            'next_cursor' => $hasMore && $page->isNotEmpty()
                ? $page->last()['date']->toIso8601String()
                : null,
            'group'       => $group,
        ];
    }

    /**
     * AI/copilot convenience: the full journey as plain arrays (ISO dates),
     * unfiltered by permissions — callers gate access before invoking.
     */
    public function summarize(Patient $patient, int $limit = 100): array
    {
        return $this->timeline->forPatient($patient)
            ->take($limit)
            ->map(fn ($e) => [
                'date'        => $e['date']->toIso8601String(),
                'type'        => $e['type'],
                'group'       => $e['group'] ?? 'comms',
                'title'       => $e['title'],
                'description' => $e['description'],
                'actor'       => $e['actor'],
                'meta'        => $e['meta'],
            ])
            ->values()
            ->all();
    }

    /** "module.action" → User::canAccess(module, action). */
    private function canSee(User $viewer, string $permission): bool
    {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, 'view');

        return $viewer->canAccess($module, $action ?: 'view');
    }
}
