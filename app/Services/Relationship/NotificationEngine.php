<?php

namespace App\Services\Relationship;

use App\Models\AppNotification;
use App\Models\RelationshipNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * NotificationEngine — Phase 6, Relationship Engine
 *
 * Generates in-app notifications for relationship events. Dual-writes:
 *   1. app_notifications — THE canonical single store (Phase 4 decision, see
 *      docs/phase-4/README.md). This is what the topbar bell and
 *      NotificationsController actually read — the only thing users ever see.
 *   2. relationship_notifications — kept as an internal metadata/dedup table,
 *      NOT a second user-facing store. recentlySent() below reads it to power
 *      the minimal-noise guard, which app_notifications can't do today (it has
 *      no relationship_id column). Its own JSON CRUD API
 *      (Http/Controllers/Relationship/NotificationController.php) has zero UI
 *      callers — it is not an alternate read path, just unused surface area.
 *
 * Write ordering matters: app_notifications is written FIRST and independently
 * try/caught, so a relationship_notifications failure (e.g. a stale schema)
 * can never silently swallow the notification the user actually sees.
 *
 * Minimal-noise rule: before creating a notification, checks if the same
 * type + relationship_id combination was sent in the last 24 hours.
 *
 * Usage:
 *   app(NotificationEngine::class)->notify(
 *       type: 'followup_overdue',
 *       relationshipId: 12,
 *       recipients: ['role:front_desk', 'role:manager'],
 *       title: 'Follow-up Overdue',
 *       body:  'Patient Rohan has an overdue follow-up since 3 days.',
 *       link:  '/relationship/12',
 *       triggeredByEvent: 'opportunity.overdue',
 *   );
 */
class NotificationEngine
{
    /**
     * Default recipients for each notification type.
     * Override at call-site by passing $recipients.
     *
     * Values can be:
     *   - 'role:front_desk'  → all users with role = front_desk
     *   - 'role:doctor'      → all users with role in DOCTOR_ROLES
     *   - 'role:manager'     → all users with role = manager
     *   - 123 (int)          → specific user ID
     */
    protected array $defaultRecipients = [
        'followup_overdue'       => ['role:front_desk', 'role:manager'],
        'opportunity_accepted'   => ['role:doctor'],
        'membership_expiring'    => ['role:front_desk'],
        'recall_due'             => ['role:front_desk'],
        'task_overdue'           => ['role:manager'],   // assigned_to added dynamically
        'appointment_confirmed'  => ['role:doctor'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create notifications for a list of recipients.
     *
     * @param  string      $type              Notification type key
     * @param  int|null    $relationshipId    Relationship this notification is about
     * @param  array       $recipients        ['role:front_desk', 42, 'role:doctor', ...]
     * @param  string      $title
     * @param  string      $body
     * @param  string|null $link              Deep-link URL shown in the bell dropdown
     * @param  string|null $triggeredByEvent  ActivityEngine event that caused this
     */
    public function notify(
        string  $type,
        ?int    $relationshipId,
        array   $recipients,
        string  $title,
        string  $body       = '',
        ?string $link       = null,
        ?string $triggeredByEvent = null,
    ): void {
        try {
            // Minimal-noise: skip if same type + relationship was notified in last 24h
            if ($this->recentlySent($type, $relationshipId)) {
                Log::debug("NotificationEngine: skipping [{$type}] for relationship [{$relationshipId}] — sent in last 24h");
                return;
            }

            $userIds = $this->resolveUserIds($recipients);

            foreach ($userIds as $userId) {
                $this->createForUser(
                    userId:          $userId,
                    type:            $type,
                    relationshipId:  $relationshipId,
                    title:           $title,
                    body:            $body,
                    link:            $link,
                    triggeredByEvent: $triggeredByEvent,
                );
            }
        } catch (\Throwable $e) {
            // Notifications must never break the calling action
            Log::warning('NotificationEngine::notify failed', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convenience: notify everyone with a specific role.
     *
     * @param  string  $role  e.g. 'front_desk', 'doctor', 'manager'
     */
    public function notifyRole(
        string  $role,
        string  $type,
        ?int    $relationshipId,
        string  $title,
        string  $body       = '',
        ?string $link       = null,
        ?string $triggeredByEvent = null,
    ): void {
        $this->notify(
            type:             $type,
            relationshipId:   $relationshipId,
            recipients:       ["role:{$role}"],
            title:            $title,
            body:             $body,
            link:             $link,
            triggeredByEvent: $triggeredByEvent,
        );
    }

    /**
     * Notify using the default recipients for the given type.
     * Called by RulesEngine when an action triggers a notification.
     */
    public function notifyDefault(
        string  $type,
        ?int    $relationshipId,
        string  $title,
        string  $body       = '',
        ?string $link       = null,
        ?string $triggeredByEvent = null,
        array   $extraRecipients = [],
    ): void {
        $recipients = array_merge(
            $this->defaultRecipients[$type] ?? ['role:manager'],
            $extraRecipients,
        );

        $this->notify(
            type:             $type,
            relationshipId:   $relationshipId,
            recipients:       $recipients,
            title:            $title,
            body:             $body,
            link:             $link,
            triggeredByEvent: $triggeredByEvent,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a single RelationshipNotification row and mirror it to app_notifications.
     */
    protected function createForUser(
        int     $userId,
        string  $type,
        ?int    $relationshipId,
        string  $title,
        string  $body,
        ?string $link,
        ?string $triggeredByEvent,
    ): void {
        // 1. THE write — app_notifications is the single store users see (the
        // topbar bell). If this fails, notify() should know: let it throw.
        $appNotif = AppNotification::notify(
            userId:      $userId,
            type:        $this->mapToAppType($type),
            title:       $title,
            message:     $body,
            actionUrl:   $link ?? '',
            actionLabel: 'View',
        );

        // 2. Best-effort metadata/dedup mirror. Isolated try/catch so a
        // problem here (e.g. schema drift) can never look like the user's
        // actual notification (step 1, already committed) failed to send.
        try {
            RelationshipNotification::create([
                'relationship_id'   => $relationshipId,
                'recipient_id'      => $userId,
                'type'              => $type,
                'title'             => $title,
                'body'              => $body ?: null,
                'link'              => $link,
                'triggered_by_event'=> $triggeredByEvent,
                'app_notification_id' => $appNotif->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationEngine: relationship_notifications mirror write failed (app_notifications succeeded, user still got the bell notification)', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve a mixed array of recipient specs to a flat list of unique user IDs.
     *
     * Spec formats:
     *   'role:front_desk'  → look up all active users with that role
     *   'role:doctor'      → look up all active users in DOCTOR_ROLES
     *   42 (int)           → literal user ID
     */
    protected function resolveUserIds(array $recipients): array
    {
        $ids = [];

        foreach ($recipients as $spec) {
            if (is_int($spec)) {
                $ids[] = $spec;
                continue;
            }

            if (str_starts_with((string)$spec, 'role:')) {
                $role = substr($spec, 5);
                $ids  = array_merge($ids, $this->usersForRole($role));
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Return active user IDs for a given role string.
     * Handles 'doctor' as a group alias (DOCTOR_ROLES).
     */
    protected function usersForRole(string $role): array
    {
        $query = User::where('is_active', true);

        if ($role === 'doctor') {
            $query->whereIn('role', User::DOCTOR_ROLES);
        } else {
            $query->where('role', $role);
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Minimal-noise guard: returns true if same type + relationship was notified
     * within the last 24 hours (checks relationship_notifications table).
     */
    protected function recentlySent(string $type, ?int $relationshipId): bool
    {
        $query = RelationshipNotification::where('type', $type)
            ->where('created_at', '>=', Carbon::now()->subDay());

        if ($relationshipId !== null) {
            $query->where('relationship_id', $relationshipId);
        }

        return $query->exists();
    }

    /**
     * Map relationship notification types to app_notification types for the bell.
     * Falls back to 'system' for any unrecognised type.
     */
    protected function mapToAppType(string $type): string
    {
        return match ($type) {
            'appointment_confirmed' => 'appointment',
            'task_overdue'          => 'task_reminder',
            'followup_overdue',
            'opportunity_accepted',
            'membership_expiring',
            'recall_due'            => 'task_assigned',
            default                 => 'system',
        };
    }
}
