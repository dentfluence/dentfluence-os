<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\RelationshipNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * NotificationController — Phase 6, Relationship Engine
 *
 * JSON API for relationship-specific notifications.
 * These are distinct from app_notifications (generic bell) —
 * relationship notifications carry richer metadata (relationship_id,
 * triggered_by_event, recipient_role) and are managed here.
 *
 * Routes:
 *   GET  /relationship/notifications            → index()
 *   POST /relationship/notifications/{id}/read  → markRead()
 *   POST /relationship/notifications/read-all   → markAllRead()
 */
class NotificationController extends Controller
{
    /**
     * GET /relationship/notifications
     *
     * Returns unread relationship notifications for the authenticated user.
     * Used by any future relationship-specific notification view.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $notifications = RelationshipNotification::forUser($userId)
            ->notDismissed()
            ->with('relationship:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'               => $n->id,
                'relationship_id'  => $n->relationship_id,
                'type'             => $n->type,
                'title'            => $n->title,
                'body'             => $n->body,
                'link'             => $n->link,
                'is_read'          => $n->isRead(),
                'triggered_by'     => $n->triggered_by_event,
                'time_ago'         => $n->created_at->diffForHumans(),
                'created_at'       => $n->created_at->toIso8601String(),
                'relationship'     => $n->relationship ? [
                    'id'    => $n->relationship->id,
                    'name'  => $n->relationship->name,
                    'phone' => $n->relationship->phone,
                ] : null,
            ]);

        $unreadCount = RelationshipNotification::forUser($userId)
            ->unread()
            ->notDismissed()
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'items'        => $notifications,
        ]);
    }

    /**
     * POST /relationship/notifications/{id}/read
     *
     * Marks a single relationship notification as read.
     * Scope check: only the recipient can mark their own notification.
     */
    public function markRead(int $id): JsonResponse
    {
        $notification = RelationshipNotification::forUser(Auth::id())
            ->findOrFail($id);

        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /relationship/notifications/read-all
     *
     * Marks all unread relationship notifications as read for the auth user.
     */
    public function markAllRead(): JsonResponse
    {
        RelationshipNotification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
