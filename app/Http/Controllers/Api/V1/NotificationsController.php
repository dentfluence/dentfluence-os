<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Api\V1\NotificationsController — mobile face of the in-app notification
 * bell (web: NotificationsController, /notifications). Same AppNotification
 * source, same auto-mark-viewed-as-read behaviour on the full list
 * (2026-07-14 parity: notifications had no mobile surface at all).
 *
 *   GET   /api/v1/notifications              paginated list (auto-marks read)
 *   GET   /api/v1/notifications/unread       unread count + 8 most recent
 *   PATCH /api/v1/notifications/{id}/read    mark one read
 *   POST  /api/v1/notifications/mark-all-read
 */
class NotificationsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $limit = max(1, min((int) $request->query('limit', 30), 100));
        $page  = AppNotification::forUser($userId)
            ->orderByDesc('created_at')
            ->paginate($limit);

        $items = collect($page->items())->map(fn ($n) => $this->map($n))->values();

        // Auto-mark viewed as read — same behaviour as the web page.
        AppNotification::forUser($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->success($items, '', 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $recent = AppNotification::forUser($userId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($n) => $this->map($n))
            ->values();

        return $this->success([
            'unread_count' => AppNotification::forUser($userId)->unread()->count(),
            'items'        => $recent,
        ], '');
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::forUser($request->user()->id)->find($id);
        if (! $notification) {
            return $this->error('Notification not found.', [], 404);
        }

        $notification->markRead();

        return $this->success(null, 'Marked read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::forUser($request->user()->id)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->success(null, 'All notifications marked read.');
    }

    // ── N-1: popup channel (same contract as web NotificationsController) ────

    /** GET /notifications/popups — desk popups this user still has to answer. */
    public function popups(Request $request): JsonResponse
    {
        $items = AppNotification::pendingPopups($request->user()->id)
            ->orderBy('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($n) => $this->map($n))
            ->values();

        return $this->success(['items' => $items], '');
    }

    /** POST /notifications/{id}/acknowledge — "Done": clears the popup for every recipient. */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->find($id);
        if (! $notification) {
            return $this->error('Notification not found.', [], 404);
        }

        $cleared = $notification->acknowledgeGroup($request->user()->id);

        return $this->success(['cleared' => $cleared], 'Acknowledged.');
    }

    /** POST /notifications/{id}/later — "Later": this user's copy drops to the bell. */
    public function later(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->find($id);
        if (! $notification) {
            return $this->error('Notification not found.', [], 404);
        }

        $notification->snoozeToBell();

        return $this->success(null, 'Moved to bell.');
    }

    /**
     * POST /devices/token — register this phone for pushes (N-5 sender reads
     * device_tokens). Body: token (required), platform, device_name, app_version.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => ['required', 'string', 'max:255'],
            'platform'    => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        \App\Models\DeviceToken::register($request->user()->id, $data['token'], $data);

        return $this->success(null, 'Device registered.');
    }

    /** DELETE /devices/token — on logout, so the next user of this phone does not get this user's pushes. */
    public function forgetDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        \App\Models\DeviceToken::where('token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->update(['invalidated_at' => now()]);

        return $this->success(null, 'Device forgotten.');
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
            'payload'      => $n->payload,
            'action_url'   => $n->action_url,
            'action_label' => $n->action_label,
            'is_read'      => (bool) $n->is_read,
            'icon'         => $n->icon,
            'color'        => $n->color,
            'time_ago'     => $n->created_at->diffForHumans(),
            'created_at'   => $n->created_at->toIso8601String(),
        ];
    }
}
