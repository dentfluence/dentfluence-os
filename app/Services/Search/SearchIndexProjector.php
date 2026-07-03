<?php

namespace App\Services\Search;

use App\Models\Relationship;
use App\Models\SearchIndexEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SearchIndexProjector — Phase 6 · Slice 3 (Search Engine).
 *
 * Materialises a denormalised copy of the fields ProfileController::search()
 * already queries live (name/phone/email/score) into `search_index`, the
 * same "build → rebuild command → parity" discipline as TodayActionsProjector
 * (Phase 1), InsightsProjector, and AnalyticsProjector (Phase 6).
 *
 * Nothing reads this projection in any live route yet — `search.index`
 * (already declared, default off) is reserved for a future cutover of
 * ProfileController::search(); this slice only builds and proves the index.
 */
class SearchIndexProjector
{
    /**
     * Rebuild the index for every relationship. Safe to run repeatedly
     * (upsert on the unique `relationship_id` key — no duplicate rows).
     *
     * @return array{relationships:int, generated_at:string}
     */
    public function rebuildAll(): array
    {
        $now   = now();
        $count = 0;

        Relationship::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($relationships) use (&$count, $now) {
                $batch = [];
                foreach ($relationships as $relationship) {
                    $count++;
                    $batch[] = $this->rowFor($relationship->id, $now);
                }

                if ($batch !== []) {
                    $this->upsert($batch);
                }
            });

        return ['relationships' => $count, 'generated_at' => $now->toIso8601String()];
    }

    /**
     * Recompute one relationship's index row. This is what the incremental
     * observer (RelationshipSearchIndexObserver) calls on save.
     *
     * @return array{relationship_id:int, found:bool}
     */
    public function rebuildFor(int $relationshipId): array
    {
        if (! Relationship::query()->where('id', $relationshipId)->exists()) {
            return ['relationship_id' => $relationshipId, 'found' => false];
        }

        $this->upsert([$this->rowFor($relationshipId, now())]);

        return ['relationship_id' => $relationshipId, 'found' => true];
    }

    /**
     * Query the index in the SAME shape ProfileController::search() returns
     * from the live `relationships` table — nothing calls this yet (no
     * cutover in this slice), but it's ready to drop in behind `search.index`.
     *
     * @return array<int,array<string,mixed>>
     */
    public function query(string $term, int $limit = 8): array
    {
        $term = trim($term);

        if (strlen($term) < 3) {
            return [];
        }

        $rows = SearchIndexEntry::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderByDesc('score')
            ->limit($limit)
            ->get();

        return $rows->map(function (SearchIndexEntry $r) {
            $words    = explode(' ', $r->name ?? 'U');
            $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

            return [
                'id'       => $r->relationship_id,
                'name'     => $r->name,
                'phone'    => $r->phone,
                'email'    => $r->email,
                'source'   => $r->source,
                'score'    => $r->score,
                'status'   => $r->status,
                'initials' => $initials,
                'meta'     => collect([$r->phone, $r->email])->filter()->implode(' · '),
                'link'     => $r->link,
            ];
        })->all();
    }

    /**
     * Self-check: compare the stored index row(s) to a fresh read of
     * `relationships` for the same relationship, WITHOUT writing anything.
     * A mismatch means the index is stale (the relationship changed since
     * the last rebuild) — the same staleness check InsightsProjector::parity()
     * and AnalyticsProjector::parity() already do for their own projections.
     *
     * @return array{match:bool, checked:int, diffs:array<int,array<string,mixed>>}
     */
    public function parity(?int $sampleSize = 200, ?int $onlyRelationshipId = null): array
    {
        $query = Relationship::query()->orderBy('id');

        if ($onlyRelationshipId !== null) {
            $query->where('id', $onlyRelationshipId);
        } elseif ($sampleSize !== null) {
            $query->limit($sampleSize);
        }

        $checked = 0;
        $diffs   = [];

        $stored = SearchIndexEntry::query()->get()->keyBy('relationship_id');

        foreach ($query->get() as $relationship) {
            $checked++;
            $storedRow = $stored->get($relationship->id);

            if (! $storedRow) {
                $diffs[$relationship->id] = ['reason' => 'missing_stored_row'];
                continue;
            }

            $fresh = $this->fieldsFor($relationship->id);

            $storedFields = [
                'name'   => $storedRow->name,
                'phone'  => $storedRow->phone,
                'email'  => $storedRow->email,
                'score'  => $storedRow->score,
                'status' => $storedRow->status,
            ];

            if ($fresh['name'] !== $storedFields['name']
                || $fresh['phone'] !== $storedFields['phone']
                || $fresh['email'] !== $storedFields['email']
                || $fresh['score'] !== $storedFields['score']
                || $fresh['status'] !== $storedFields['status']
            ) {
                $diffs[$relationship->id] = ['fresh' => $fresh, 'stored' => $storedFields];
            }
        }

        return [
            'match'   => $diffs === [],
            'checked' => $checked,
            'diffs'   => $diffs,
        ];
    }

    /**
     * @return array{name:?string, phone:?string, email:?string, score:int, status:?string, source:?string, patient_name:?string, link:string}
     */
    protected function fieldsFor(int $relationshipId): array
    {
        $relationship = Relationship::query()->with('patient:id,relationship_id,name')->findOrFail($relationshipId);

        return [
            'name'         => $relationship->name,
            'phone'        => $relationship->phone,
            'email'        => $relationship->email,
            'score'        => (int) $relationship->score,
            'status'       => $relationship->status,
            'source'       => $relationship->source,
            'patient_name' => $relationship->patient?->name,
            'link'         => route('relationship.profile', $relationship->id),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function rowFor(int $relationshipId, Carbon $now): array
    {
        $fields = $this->fieldsFor($relationshipId);

        return array_merge($fields, [
            'relationship_id' => $relationshipId,
            'computed_at'     => $now,
            'generated_at'    => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    protected function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('search_index')->upsert(
                $chunk,
                ['relationship_id'],
                ['name', 'phone', 'email', 'score', 'status', 'source', 'patient_name', 'link', 'computed_at', 'generated_at', 'updated_at'],
            );
        }
    }
}
