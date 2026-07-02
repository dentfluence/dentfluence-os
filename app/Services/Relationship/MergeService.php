<?php

namespace App\Services\Relationship;

use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Relationship\RelationshipMerged;
use App\Models\Relationship;
use App\Models\RelationshipMerge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MergeService — Phase 1 (Workstream A).
 *
 * Merges a DUPLICATE relationship into a SURVIVING one, reassigning every
 * child record and recording a reversible audit trail (RelationshipMerge).
 *
 * Safety (clinical): merging two people's histories is dangerous. Therefore:
 *   - This service is NOT auto-invoked in Phase 1. It is called manually /
 *     from the dedup review queue only.
 *   - Every merge is fully reversible via undo(), which uses the recorded
 *     per-table row ids and restores the soft-deleted duplicate.
 *
 * All target tables reference a relationship via the `relationship_id` column.
 */
class MergeService
{
    /** Tables whose `relationship_id` must move on a merge. */
    private const TARGET_TABLES = [
        'leads',
        'patients',
        'treatment_opportunities',
        'tasks',
        'relationship_journeys',
        'activities',
        'relationship_notifications',
        'relationship_contact_log',
        'relationship_rule_logs',
    ];

    public function __construct(private readonly DomainEventBus $bus)
    {
    }

    /**
     * Merge $duplicate into $surviving. Returns the audit record.
     *
     * @throws \InvalidArgumentException if merging a relationship into itself.
     */
    public function merge(
        Relationship $surviving,
        Relationship $duplicate,
        ?int $userId = null,
        string $reason = 'manual',
    ): RelationshipMerge {
        if ($surviving->id === $duplicate->id) {
            throw new \InvalidArgumentException('Cannot merge a relationship into itself.');
        }

        $merge = DB::transaction(function () use ($surviving, $duplicate, $userId, $reason) {
            $reassignments = [];

            foreach (self::TARGET_TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'relationship_id')) {
                    continue;
                }

                $ids = DB::table($table)
                    ->where('relationship_id', $duplicate->id)
                    ->pluck('id')
                    ->all();

                if ($ids) {
                    DB::table($table)->whereIn('id', $ids)->update(['relationship_id' => $surviving->id]);
                    $reassignments[$table] = $ids;
                }
            }

            $record = RelationshipMerge::create([
                'surviving_relationship_id' => $surviving->id,
                'merged_relationship_id'    => $duplicate->id,
                'reason'                    => $reason,
                'reassignments'             => $reassignments,
                'snapshot'                  => $duplicate->attributesToArray(),
                'merged_by'                 => $userId,
            ]);

            // Soft-delete the duplicate (Relationship uses SoftDeletes) so it is
            // preserved for reversibility and never re-used.
            $duplicate->delete();

            return $record;
        });

        // Publish AFTER commit — subscribers see a consistent state.
        $this->bus->publish(new RelationshipMerged(
            survivingRelationshipId: $surviving->id,
            mergedRelationshipId: $duplicate->id,
            mergeRecordId: $merge->id,
        ));

        return $merge;
    }

    /**
     * Reverse a merge: move the recorded rows back to the merged relationship
     * and restore it. Idempotent-ish — marks undone_at so it is not repeated.
     */
    public function undo(RelationshipMerge $merge): void
    {
        if ($merge->undone_at) {
            return;
        }

        DB::transaction(function () use ($merge) {
            $duplicate = Relationship::withTrashed()->find($merge->merged_relationship_id);
            if ($duplicate && $duplicate->trashed()) {
                $duplicate->restore();
            }

            foreach ((array) $merge->reassignments as $table => $ids) {
                if (! Schema::hasTable($table) || empty($ids)) {
                    continue;
                }
                DB::table($table)
                    ->whereIn('id', $ids)
                    ->update(['relationship_id' => $merge->merged_relationship_id]);
            }

            $merge->undone_at = now();
            $merge->save();
        });
    }
}
