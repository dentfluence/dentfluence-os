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
            ->map(fn ($n) => $this->map($n));

        return response()->json([
            'unread_count' => $unreadCount,
            'items'        => $recent,
        ]);
    }

    // ── N-1: popup channel ────────────────────────────────────────────────────
    // Polled every 8s by the topbar (N-3). Returns ONLY the popups this user
    // still has to answer — the desk modal, not the bell. Cheap by design:
    // one indexed query, no joins, so the short interval costs nothing.

    public function popups()
    {
        $items = AppNotification::pendingPopups(Auth::id())
            ->orderBy('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($n) => $this->map($n));

        return response()->json(['items' => $items]);
    }

    /** "Done" — clears the popup for everyone who received it. */
    public function acknowledge(int $id)
    {
        $notification = AppNotification::where('user_id', Auth::id())->findOrFail($id);
        $cleared = $notification->acknowledgeGroup(Auth::id());

        return response()->json(['ok' => true, 'cleared' => $cleared]);
    }

    /** "Later" — stops popping for THIS user only; stays in their bell, unread. */
    public function later(int $id)
    {
        $notification = AppNotification::where('user_id', Auth::id())->findOrFail($id);
        $notification->snoozeToBell();

        return response()->json(['ok' => true]);
    }

    private function map(AppNotification $n): array
    {
        return [
            'id'           => $n->id,
            'type'         => $n->type,
            'priority'     => $n->priority,
            'event_key'    => $n->event_key,
            'title'        => $n->title,
            'message'      => $n->message,
            'action_url'   => $n->action_url,
            'action_label' => $n->action_label,
            'is_read'      => $n->is_read,
            'icon'         => $n->icon,
            'color'        => $n->color,
            'time_ago'     => $n->created_at->diffForHumans(),
        ];
    }

    // ── AJAX: sidebar workflow queue badges (UX-07, Freeze Spec 2026-08-05) ──
    // Read-side surfacing of rows that ALREADY exist (pending billing prompts,
    // draft lab cases) — no new events, no writes. Polled every 30s by the
    // sidebar so front desk / lab see new work without the verbal handoff.
    // Counts are role-scoped: you only receive numbers for modules you can see.

    public function navBadges()
    {
        $user = Auth::user();

        return response()->json([
            'billing_prompts' => $user->canAccess('finance')
                ? \App\Models\BillingPrompt::where('status', 'pending')->count()
                : 0,
            'lab_drafts' => $user->canAccess('lab')
                ? \App\Models\LabCase::where('status', 'draft')->count()
                : 0,
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
