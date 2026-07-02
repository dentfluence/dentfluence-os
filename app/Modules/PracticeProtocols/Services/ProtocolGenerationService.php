<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Services;

use App\Models\Task;
use App\Models\User;
use App\Modules\PracticeProtocols\Models\PracticeProtocol;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns active practice protocols into real tasks for the staff who hold
 * the matching role.
 *
 * Design notes:
 *  - Idempotent: a protocol+user+date can only ever produce ONE task, so the
 *    command is safe to run repeatedly (cron retries, manual re-runs).
 *  - It only ever CREATES tasks. It never edits or deletes existing ones.
 *  - Role matching works during the role_id transition: a user matches if
 *    their new role_id equals the protocol's role, OR their legacy `role`
 *    string equals the role slug.
 */
class ProtocolGenerationService
{
    /**
     * Protocol categories → valid `tasks.category` enum values.
     * (tasks has no decon/reception; map them to the nearest existing bucket.)
     */
    private const CATEGORY_MAP = [
        'clinical'    => 'clinical',
        'admin'       => 'admin',
        'lab'         => 'lab',
        'decon'       => 'clinical',
        'reception'   => 'admin',
        'maintenance' => 'maintenance',
        'other'       => 'other',
    ];

    /**
     * Generate tasks for every active protocol due on the given date.
     *
     * @param  bool  $dryRun  when true, counts what WOULD be created without writing.
     * @return array{created:int, skipped:int, protocols:int}
     */
    public function generateFor(Carbon $date, bool $dryRun = false): array
    {
        $created = 0;
        $skipped = 0;

        $protocols = PracticeProtocol::query()
            ->active()
            ->dueOn($date)
            ->with('role')
            ->get();

        // A safe non-null fallback for tasks.created_by (column is NOT NULL).
        $systemUserId = User::where('role', 'admin')->value('id')
                        ?? User::query()->value('id');

        foreach ($protocols as $protocol) {
            $recipients = $this->recipientsFor($protocol);

            foreach ($recipients as $user) {
                // Idempotency guard — one task per protocol+user+date.
                $exists = Task::where('practice_protocol_id', $protocol->id)
                    ->where('assigned_to', $user->id)
                    ->whereDate('due_date', $date->toDateString())
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $created++;
                    continue;
                }

                Task::create([
                    'title'                => $protocol->title,
                    'description'          => $protocol->description,
                    'assigned_to'          => $user->id,
                    'created_by'           => $protocol->created_by ?? $systemUserId ?? $user->id,
                    'branch_id'            => $protocol->branch_id ?? $user->branch_id ?? 1,
                    'due_date'             => $date->toDateString(),
                    'due_time'             => $protocol->default_due_time,
                    'priority'             => $protocol->priority,
                    'category'             => self::CATEGORY_MAP[$protocol->category] ?? 'other',
                    'status'               => 'pending',
                    'practice_protocol_id' => $protocol->id,
                    'requires_evidence'    => $protocol->requires_evidence,
                ]);

                $created++;
            }
        }

        return [
            'created'   => $created,
            'skipped'   => $skipped,
            'protocols' => $protocols->count(),
        ];
    }

    /**
     * Active users who should receive a given protocol's task.
     * Matches on new role_id OR legacy role string, and honours the
     * protocol's branch (null branch = all branches).
     */
    private function recipientsFor(PracticeProtocol $protocol): \Illuminate\Support\Collection
    {
        $roleSlug = $protocol->role?->slug;

        return User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($protocol, $roleSlug) {
                $q->where('role_id', $protocol->role_id);
                if ($roleSlug) {
                    $q->orWhere('role', $roleSlug);
                }
            })
            ->when($protocol->branch_id, fn ($q) => $q->where('branch_id', $protocol->branch_id))
            ->get()
            ->unique('id')
            ->values();
    }
}
