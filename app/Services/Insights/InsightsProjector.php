<?php

namespace App\Services\Insights;

use App\Models\InsightSignal;
use App\Models\Relationship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * InsightsProjector — Phase 6 · Slice 1.
 *
 * Materialises the 3 Insights signals (Health/LTV/Risk) into the derived
 * `insight_signals` table, the same "build → rebuild command → parity"
 * discipline already proven by TodayActionsProjector (Phase 1 · Workstream
 * E). The projection is disposable — rebuild() always fully recomputes the
 * rows it touches, so it is never a source of truth.
 *
 * Nothing reads this projection in any live UI yet in Slice 1 — it is
 * populated shadow-only, exactly like Today's Actions E1.
 */
class InsightsProjector
{
    public function __construct(private readonly InsightsEngine $engine) {}

    /**
     * Rebuild every relationship's signals. Safe to run repeatedly (upsert on
     * the (relationship_id, signal) unique key — no duplicate rows).
     *
     * @return array{relationships:int, rows:int, generated_at:string}
     */
    public function rebuildAll(): array
    {
        $now               = now();
        $rowsWritten       = 0;
        $relationshipsSeen = 0;

        Relationship::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($relationships) use (&$rowsWritten, &$relationshipsSeen, $now) {
                $batch = [];
                foreach ($relationships as $relationship) {
                    $relationshipsSeen++;
                    foreach ($this->rowsFor($relationship, $now) as $row) {
                        $batch[] = $row;
                    }
                }

                if ($batch !== []) {
                    $this->upsert($batch);
                    $rowsWritten += count($batch);
                }
            });

        return ['relationships' => $relationshipsSeen, 'rows' => $rowsWritten, 'generated_at' => $now->toIso8601String()];
    }

    /**
     * Recompute one relationship's 3 signals. This is what the incremental
     * event listener calls — small, targeted, cheap.
     *
     * @return array{relationship_id:int, found:bool, rows:int}
     */
    public function rebuildFor(int $relationshipId): array
    {
        $relationship = Relationship::find($relationshipId);

        if (! $relationship) {
            return ['relationship_id' => $relationshipId, 'found' => false, 'rows' => 0];
        }

        $now  = now();
        $rows = $this->rowsFor($relationship, $now);
        $this->upsert($rows);

        return ['relationship_id' => $relationshipId, 'found' => true, 'rows' => count($rows), 'generated_at' => $now->toIso8601String()];
    }

    /**
     * Read the stored signals for one relationship, keyed by signal name.
     * This is the read path a future consumer (Today's Actions, profile
     * header, AI Assistant) would use — nothing calls it yet in Slice 1.
     *
     * @return array<string,array<string,mixed>>
     */
    public function signalsFor(int $relationshipId): array
    {
        return InsightSignal::query()
            ->forRelationship($relationshipId)
            ->get()
            ->keyBy('signal')
            ->map(fn (InsightSignal $row) => [
                'signal'          => $row->signal,
                'score'           => $row->score,
                'level'           => $row->level,
                'value_realized'  => $row->value_realized,
                'value_projected' => $row->value_projected,
                'factors'         => $row->factors,
                'computed_at'     => $row->computed_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Self-check: recompute fresh values and diff against what's stored,
     * WITHOUT writing anything. Insights is net-new — there is no legacy
     * single-score system to diff against — so this proves the same thing
     * TodayActionsProjector::parity() proves for its projection: the stored
     * rows are not stale/drifted from a from-scratch recomputation.
     *
     * @param  int|null  $sampleSize          null = check every relationship with at least one stored signal.
     * @param  int|null  $onlyRelationshipId  scope the check to a single relationship (ignores $sampleSize).
     * @return array{match:bool, checked:int, diffs:array<int,array<string,mixed>>}
     */
    public function parity(?int $sampleSize = 200, ?int $onlyRelationshipId = null): array
    {
        $query = Relationship::query()
            ->whereIn('id', InsightSignal::query()->distinct()->pluck('relationship_id'))
            ->orderBy('id');

        if ($onlyRelationshipId !== null) {
            $query->where('id', $onlyRelationshipId);
        } elseif ($sampleSize !== null) {
            $query->limit($sampleSize);
        }

        $checked = 0;
        $diffs   = [];

        foreach ($query->get() as $relationship) {
            $checked++;
            $fresh  = $this->engine->calculateAll($relationship);
            $stored = InsightSignal::query()->forRelationship($relationship->id)->get()->keyBy('signal');

            foreach ($fresh as $signal => $values) {
                $storedRow = $stored->get($signal);

                if (! $storedRow) {
                    $diffs[$relationship->id][$signal] = ['reason' => 'missing_stored_row'];
                    continue;
                }

                $mismatch = $this->diffOne($values, $storedRow);
                if ($mismatch !== null) {
                    $diffs[$relationship->id][$signal] = $mismatch;
                }
            }
        }

        return [
            'match'   => $diffs === [],
            'checked' => $checked,
            'diffs'   => $diffs,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function rowsFor(Relationship $relationship, Carbon $now): array
    {
        $signals = $this->engine->calculateAll($relationship);
        $rows    = [];

        foreach ($signals as $signal => $values) {
            $rows[] = [
                'relationship_id' => $relationship->id,
                'signal'          => $signal,
                'score'           => $values['score'] ?? null,
                'level'           => $values['level'] ?? null,
                'value_realized'  => $values['value_realized'] ?? null,
                'value_projected' => $values['value_projected'] ?? null,
                'factors'         => json_encode($values['factors'] ?? [], JSON_UNESCAPED_UNICODE),
                'computed_at'     => $now,
                'generated_at'    => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        return $rows;
    }

    protected function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('insight_signals')->upsert(
                $chunk,
                ['relationship_id', 'signal'],
                ['score', 'level', 'value_realized', 'value_projected', 'factors', 'computed_at', 'generated_at', 'updated_at'],
            );
        }
    }

    /**
     * @param  array<string,mixed>  $fresh
     */
    protected function diffOne(array $fresh, InsightSignal $stored): ?array
    {
        $freshScore  = isset($fresh['score']) ? round((float) $fresh['score'], 2) : null;
        $storedScore = $stored->score !== null ? round((float) $stored->score, 2) : null;

        $freshRealized  = isset($fresh['value_realized']) ? round((float) $fresh['value_realized'], 2) : null;
        $storedRealized = $stored->value_realized !== null ? round((float) $stored->value_realized, 2) : null;

        $freshProjected  = isset($fresh['value_projected']) ? round((float) $fresh['value_projected'], 2) : null;
        $storedProjected = $stored->value_projected !== null ? round((float) $stored->value_projected, 2) : null;

        if ($freshScore !== $storedScore || $freshRealized !== $storedRealized || $freshProjected !== $storedProjected) {
            return [
                'fresh'  => ['score' => $freshScore, 'value_realized' => $freshRealized, 'value_projected' => $freshProjected],
                'stored' => ['score' => $storedScore, 'value_realized' => $storedRealized, 'value_projected' => $storedProjected],
            ];
        }

        return null;
    }
}
