<?php

namespace App\Models;

use App\Services\Notifications\NotificationCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * NotificationRule — one (event, role) → level/push decision.
 *
 * See the migration for the shape. The admin edits these through the
 * Settings → Notifications matrix (N-4); NotificationDispatcher reads them.
 */
class NotificationRule extends Model
{
    protected $fillable = [
        'event_key', 'role', 'level', 'push', 'branch_id', 'updated_by',
    ];

    protected $casts = [
        'push' => 'boolean',
    ];

    /**
     * Effective rules for one event in one branch: branch rows win over the
     * clinic-wide (NULL) rows for the same role; catalogue defaults fill any
     * role that has no row at all.
     *
     * @return Collection<string, array{level:string, push:bool}> keyed by role
     */
    public static function effectiveFor(string $eventKey, ?int $branchId, bool $includeOff = false): Collection
    {
        $rows = static::query()
            ->where('event_key', $eventKey)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->get();

        $effective = collect(NotificationCatalog::defaultRules($eventKey))
            ->keyBy('role')
            ->map(fn ($r) => ['level' => $r['level'], 'push' => (bool) $r['push']]);

        // Clinic-wide rows first, then branch rows on top.
        foreach ($rows->sortBy(fn ($r) => $r->branch_id === null ? 0 : 1) as $row) {
            $effective[$row->role] = ['level' => $row->level, 'push' => (bool) $row->push];
        }

        // The dispatcher wants only live rules; the Settings matrix wants
        // every cell, 'off' included, so it can show what an admin chose.
        return $includeOff
            ? $effective
            : $effective->filter(fn ($r) => $r['level'] !== NotificationCatalog::LEVEL_OFF);
    }
}
