<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    // ── Full notifications page ───────────────────────────────────────────────

    public function index()
    {
        $userId = Auth::id();

        $notifications = AppNotification::forUser($userId)
            ->orderByDesc('created_at')
            ->paginate(30);

        // Auto-mark viewed as read
        AppNotification::forUser($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return view('notifications.index', compact('notifications'));
    }

    // ── AJAX: unread count + recent list (topbar dropdown) ───────────────────

    public function unread()
    {
        $userId = Auth::id();

        $unreadCount = AppNotification::forUser($userId)->unread()->count();

        $recent = AppNotification::forUser($userId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($n) => [
                'id'           => $n->id,
                'type'         => $n->type,
                'title'        => $n->title,
                'message'      => $n->message,
                'action_url'   => $n->action_url,
                'action_label' => $n->action_label,
                'is_read'      => $n->is_read,
                'icon'         => $n->icon,
                'color'        => $n->color,
                'time_ago'     => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread_count' => $unreadCount,
            'items'        => $recent,
        ]);
    }

    // ── Mark single notification as read ─────────────────────────────────────

    public function markRead(int $id)
    {
        $notification = AppNotification::forUser(Auth::id())->findOrFail($id);
        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    // ── Mark all as read ─────────────────────────────────────────────────────

    public function markAllRead()
    {
        AppNotification::forUser(Auth::id())->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
