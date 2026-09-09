<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\NotificationRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * NotificationDispatcher — the one door every clinic event walks through.
 *
 *   app(NotificationDispatcher::class)->fire('consultation.saved', [
 *       'title'        => 'Ratan Varma — consultation done',
 *       'message'      => 'Collect ₹400 · X-ray · book in 7 days',
 *       'action_url'   => route('patients.show', $patient),
 *       'action_label' => 'Open patient',
 *       'source'       => $consultation,     // Model → source_type/id + group key
 *       'branch_id'    => $consultation->branch_id,
 *       'owner'        => $consultation->doctor_id,   // for OWNER-level rules
 *   ]);
 *
 * The caller knows WHAT happened. This class decides WHO hears about it and
 * HOW LOUDLY, from notification_rules (admin-edited matrix) with the
 * catalogue as fallback. Callers never name a role.
 *
 * Rules of the engine
 *  - ROLE targets → every active user carrying that Role slug in the branch
 *    (users with no branch count as everywhere; admins usually have none).
 *  - OWNER target → the single user the caller passed; never a role lookup.
 *  - The actor is never told about their own action.
 *  - One row per recipient; dedupe_key is UNIQUE, so re-firing for the same
 *    record is a silent no-op — callers may fire from model events freely.
 *  - Never throws. A notification is best-effort; it must not roll back the
 *    clinical or money write that produced it. Failures are report()ed.
 *  - Push (N-5) is queued, never sent inline: a popup-level row with push on
 *    dispatches SendPushNotification afterCommit. FcmSender does the rest.
 */
class NotificationDispatcher
{
    /**
     * @param  array{
     *   title:string, message?:?string, action_url?:?string, action_label?:?string,
     *   source?:?Model, source_type?:?string, source_id?:?int,
     *   branch_id?:?int, owner?:User|int|null, actor_id?:?int, type?:?string,
     *   dedupe_scope?:?string
     * } $ctx
     * @return int number of notification rows created
     */
    public function fire(string $eventKey, array $ctx): int
    {
        try {
            return $this->dispatch($eventKey, $ctx);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function dispatch(string $eventKey, array $ctx): int
    {
        $def      = NotificationCatalog::get($eventKey);
        $branchId = $ctx['branch_id'] ?? null;
        $rules    = NotificationRule::effectiveFor($eventKey, $branchId);

        if ($rules->isEmpty()) {
            return 0;
        }

        [$sourceType, $sourceId] = $this->sourceOf($ctx);
        $groupKey = $this->groupKey($eventKey, $sourceType, $sourceId, $ctx['dedupe_scope'] ?? null);
        $actorId  = array_key_exists('actor_id', $ctx) ? $ctx['actor_id'] : Auth::id();

        // recipient user_id => [level, push, role-label]
        $recipients = [];

        foreach ($rules as $role => $rule) {
            if ($role === NotificationCatalog::OWNER) {
                $ownerId = $this->ownerId($ctx['owner'] ?? null);
                if ($ownerId) {
                    $this->addRecipient($recipients, $ownerId, $rule, NotificationCatalog::OWNER);
                }
                continue;
            }

            foreach ($this->usersWithRole($role, $branchId) as $userId) {
                $this->addRecipient($recipients, $userId, $rule, $role);
            }
        }

        unset($recipients[$actorId]);

        $created = 0;
        foreach ($recipients as $userId => [$level, $push, $roleLabel]) {
            $row = [
                'user_id'      => $userId,
                'type'         => $ctx['type'] ?? $def['type'],
                'priority'     => $level,
                'event_key'    => $eventKey,
                'target_role'  => $roleLabel,
                'branch_id'    => $branchId,
                'source_type'  => $sourceType,
                'source_id'    => $sourceId,
                'group_key'    => $groupKey,
                'dedupe_key'   => $groupKey . ':u' . $userId,
                'title'        => mb_substr($ctx['title'], 0, 200),
                'message'      => $ctx['message'] ?? null,
                'action_url'   => $ctx['action_url'] ?? null,
                'action_label' => $ctx['action_label'] ?? null,
                'is_read'      => false,
                // Push intent is recorded on the row for N-5's sender; a bell-
                // level rule never pushes whatever the matrix says about push.
                'push'         => $push && $level === NotificationCatalog::LEVEL_POPUP,
            ];

            try {
                $created_row = AppNotification::create($row);
                $created++;

                // N-5: the phone copy. afterCommit so a rolled-back clinical
                // write can never leave a push already on its way — the worker
                // would read a row that no longer exists.
                if ($created_row->push) {
                    SendPushNotification::dispatch($created_row->id)->afterCommit();
                }
            } catch (QueryException $e) {
                // Unique dedupe_key hit — this recipient already has this
                // event for this record. Exactly the designed outcome.
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }
            }
        }

        return $created;
    }

    // ── Recipient resolution ─────────────────────────────────────────────────

    /** @param array<int, array{0:string,1:bool,2:string}> $recipients */
    private function addRecipient(array &$recipients, int $userId, array $rule, string $roleLabel): void
    {
        // A user reached by two rules (e.g. owner AND manager) keeps the
        // louder level and pushes if either rule says so.
        if (isset($recipients[$userId])) {
            [$level, $push] = $recipients[$userId];
            $louder = ($rule['level'] === NotificationCatalog::LEVEL_POPUP) ? $rule['level'] : $level;
            $recipients[$userId] = [$louder, $push || $rule['push'], $recipients[$userId][2]];

            return;
        }

        $recipients[$userId] = [$rule['level'], (bool) $rule['push'], $roleLabel];
    }

    /**
     * Active users holding a Role slug in the branch. The role_id system is
     * authoritative; the legacy `users.role` string is consulted only for
     * rows that never received a role_id, mapped through the same table
     * Role::slugForLegacyRoleString() uses.
     *
     * @return Collection<int, int> user ids
     */
    private function usersWithRole(string $roleSlug, ?int $branchId): Collection
    {
        $legacyStrings = $this->legacyStringsFor($roleSlug);

        return User::query()
            ->where('is_active', true)
            ->when($branchId, function ($q) use ($branchId) {
                $q->where(fn ($b) => $b->where('branch_id', $branchId)->orWhereNull('branch_id'));
            })
            ->where(function ($q) use ($roleSlug, $legacyStrings) {
                $q->whereHas('roleModel', fn ($r) => $r->where('slug', $roleSlug));
                if ($legacyStrings) {
                    $q->orWhere(fn ($l) => $l->whereNull('role_id')->whereIn('role', $legacyStrings));
                }
            })
            ->pluck('id');
    }

    /** Legacy `users.role` strings that map onto a Role slug (see Role::slugForLegacyRoleString). */
    private function legacyStringsFor(string $slug): array
    {
        return match ($slug) {
            Role::DOCTOR     => ['doctor', 'resident_dentist', 'associate_dentist', 'visiting_consultant', 'dentist'],
            Role::FRONT_DESK => ['front_desk', 'receptionist'],
            Role::ADMIN, Role::MANAGER, Role::ASSISTANT, Role::ACCOUNTS => [$slug],
            default          => [],
        };
    }

    private function ownerId(User|int|null $owner): ?int
    {
        if ($owner instanceof User) {
            return $owner->is_active ? $owner->id : null;
        }

        return $owner ? (int) $owner : null;
    }

    // ── Keys ─────────────────────────────────────────────────────────────────

    /** @return array{0:?string,1:?int} */
    private function sourceOf(array $ctx): array
    {
        if (isset($ctx['source']) && $ctx['source'] instanceof Model) {
            return [$ctx['source']->getMorphClass(), (int) $ctx['source']->getKey()];
        }

        return [$ctx['source_type'] ?? null, isset($ctx['source_id']) ? (int) $ctx['source_id'] : null];
    }

    /**
     * `dedupe_scope` widens the key for events that SHOULD repeat on a cycle.
     * A lab case overdue for ten days is ten separate pieces of news, once a
     * day — passing the date as the scope makes the daily sweep announce it
     * once per day instead of once ever (no scope) or ten times (no dedupe).
     */
    private function groupKey(string $eventKey, ?string $sourceType, ?int $sourceId, ?string $scope = null): string
    {
        if ($sourceType === null || $sourceId === null) {
            // No source → nothing to dedupe against; every fire is its own group.
            return $eventKey . ':' . now()->format('YmdHis') . ':' . bin2hex(random_bytes(3));
        }

        return $eventKey . ':' . class_basename($sourceType) . ':' . $sourceId
            . ($scope !== null ? ':' . $scope : '');
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        // MySQL 1062 / SQLite "UNIQUE constraint failed" / Postgres 23505
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());

        return $code === '1062' || $code === '23505' || $code === '19'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
