<?php

namespace App\Services\Relationship;

use App\Models\TodayAction;
use Illuminate\Support\Facades\DB;

/**
 * TodayActionsProjector — Phase 1 · Workstream E (slice E1).
 *
 * Materialises the Today's Actions page into a single derived view
 * (`today_actions`), so the page can read ONE table instead of querying ~12
 * domains at request time (the architecture's "no god readers" rule).
 *
 * In slice E1 this runs in SHADOW: the projection is built and parity-checked,
 * but the page still reads the live TodayActionsEngine. Slice E2 flips the read
 * to the projection behind the `today.projection` flag.
 *
 * The projection is derived and disposable — rebuild() fully replaces it inside
 * a transaction, so a rebuild is always safe and never a source of truth.
 */
class TodayActionsProjector
{
    public function __construct(
        private readonly TodayActionsEngine $engine,
    ) {}

    /**
     * Rebuild the whole projection from the engine. Idempotent: the table is
     * replaced wholesale, so running twice yields the same rows (no duplicates).
     *
     * @return array{rows:int, categories:int}
     */
    public function rebuild(): array
    {
        $groups = $this->engine->generate();

        $now  = now();
        $rows = [];

        foreach ($groups as $category => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    'category'         => $item['category']         ?? $category,
                    'priority'         => $item['priority']         ?? 'medium',
                    'patient_id'       => $item['patient_id']       ?? null,
                    'lead_id'          => $item['lead_id']          ?? null,
                    'relationship_id'  => $item['relationship_id']  ?? null,
                    'patient_name'     => $item['patient_name']     ?? 'Unknown',
                    'reason'           => $item['reason']           ?? '',
                    'suggested_action' => $item['suggested_action'] ?? '',
                    'link'             => $item['link']             ?? '#',
                    'meta'             => json_encode($item['meta'] ?? [], JSON_UNESCAPED_UNICODE),
                    'generated_at'     => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows) {
            TodayAction::query()->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('today_actions')->insert($chunk);
            }
        });

        return ['rows' => count($rows), 'categories' => count($groups)];
    }

    /**
     * Read the projection back, grouped by category, in the SAME item shape the
     * TodayActionsEngine returns. This is the read path slice E2 will consume, so
     * TodayController can switch source with no view changes.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    public function grouped(): array
    {
        $out = [];

        TodayAction::orderBy('category')->orderBy('id')->get()->each(function (TodayAction $row) use (&$out) {
            $out[$row->category][] = [
                'category'         => $row->category,
                'patient_name'     => $row->patient_name,
                'patient_id'       => $row->patient_id,
                'lead_id'          => $row->lead_id,
                'relationship_id'  => $row->relationship_id,
                'reason'           => $row->reason,
                'priority'         => $row->priority,
                'suggested_action' => $row->suggested_action,
                'link'             => $row->link,
                'meta'             => $row->meta ?? [],
            ];
        });

        return $out;
    }

    /**
     * Lightweight counts over the projection — for the reception dashboard and
     * the Daily Huddle snapshot (a shared read, no domain queries).
     *
     * @return array{total:int, by_category:array<string,int>, by_priority:array<string,int>, generated_at:?string}
     */
    public function summary(): array
    {
        $byCategory = TodayAction::query()
            ->select('category', DB::raw('COUNT(*) as c'))
            ->groupBy('category')
            ->pluck('c', 'category')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $byPriority = TodayAction::query()
            ->select('priority', DB::raw('COUNT(*) as c'))
            ->groupBy('priority')
            ->pluck('c', 'priority')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return [
            'total'        => array_sum($byCategory),
            'by_category'  => $byCategory,
            'by_priority'  => $byPriority,
            'generated_at' => TodayAction::max('generated_at'),
        ];
    }

    /**
     * Same shape as summary(), but computed directly from a fresh
     * TodayActionsEngine read instead of the `today_actions` table — for
     * callers (Daily Huddle) that must stay in sync with the live Today's
     * Actions page when `today.projection` is OFF. See the 2026-07-08 fix
     * note: Huddle previously always read summary() regardless of the flag,
     * so it could silently disagree with the live-read Today's Actions page
     * by up to one cron cycle (15 min) — or indefinitely, if the scheduler
     * wasn't running. Callers should check Feature::enabled('today.projection')
     * and use this method when it's OFF.
     *
     * @return array{total:int, by_category:array<string,int>, by_priority:array<string,int>, generated_at:?string}
     */
    public function liveSummary(): array
    {
        $groups = $this->engine->generate();

        $byCategory = [];
        $byPriority = [];

        foreach ($groups as $category => $items) {
            $byCategory[$category] = count($items);

            foreach ($items as $item) {
                $priority = $item['priority'] ?? 'medium';
                $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;
            }
        }

        return [
            'total'        => array_sum($byCategory),
            'by_category'  => $byCategory,
            'by_priority'  => $byPriority,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Compare the CURRENT projection against a fresh live read, per category.
     * Used to prove shadow parity before the E2 read cutover (mirrors the
     * timeline-parity harness). Does NOT rebuild — it checks what's stored now.
     *
     * @return array{match:bool, diffs:array<string,array{live:int,projection:int}>, live_total:int, projection_total:int}
     */
    public function parity(): array
    {
        $live       = $this->engine->generate();
        $liveCounts = [];
        foreach ($live as $category => $items) {
            $liveCounts[$category] = count($items);
        }

        $projCounts = TodayAction::query()
            ->select('category', DB::raw('COUNT(*) as c'))
            ->groupBy('category')
            ->pluck('c', 'category')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $categories = array_unique(array_merge(array_keys($liveCounts), array_keys($projCounts)));

        $diffs = [];
        foreach ($categories as $category) {
            $l = $liveCounts[$category] ?? 0;
            $p = $projCounts[$category] ?? 0;
            if ($l !== $p) {
                $diffs[$category] = ['live' => $l, 'projection' => $p];
            }
        }

        return [
            'match'            => $diffs === [],
            'diffs'            => $diffs,
            'live_total'       => array_sum($liveCounts),
            'projection_total' => array_sum($projCounts),
        ];
    }
}
