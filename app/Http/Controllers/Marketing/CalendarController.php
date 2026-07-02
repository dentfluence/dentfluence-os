<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class CalendarController extends Controller
{
    private const CLINIC_ID = 1;

    // -------------------------------------------------------------------------
    // Index — calendar view for a given month
    // -------------------------------------------------------------------------
    public function index(Request $request): View
    {
        $clinicId = self::CLINIC_ID;
        $month    = (int) $request->get('month', now()->month);
        $year     = (int) $request->get('year',  now()->year);

        // Load all scheduled posts for the month via PostSchedule
        $schedules = PostSchedule::with('post')
            ->whereYear('scheduled_at', $year)
            ->whereMonth('scheduled_at', $month)
            ->whereHas('post', fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('scheduled_at')
            ->get();

        // Also include draft/pending posts for the month (no schedule row yet)
        $draftPosts = MarketingPost::where('clinic_id', $clinicId)
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->whereDoesntHave('schedules')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Build flat array of post chips for the calendar
        $posts = collect();

        foreach ($schedules as $schedule) {
            $post = $schedule->post;
            if (! $post) continue;

            $platforms = $post->platforms ?? [];
            $platform  = is_array($platforms) ? ($platforms[0] ?? 'instagram') : 'instagram';

            // Try to get campaign color
            $campaignColor = '#6366f1';
            if ($post->campaign_id) {
                static $campaignColors = [];
                if (! isset($campaignColors[$post->campaign_id])) {
                    $c = Campaign::find($post->campaign_id);
                    $campaignColors[$post->campaign_id] = $c?->campaign_color ?? '#6366f1';
                }
                $campaignColor = $campaignColors[$post->campaign_id];
            }

            $posts->push([
                'id'             => $post->id,
                'date'           => $schedule->scheduled_at->toDateString(),
                'time'           => $schedule->scheduled_at->format('H:i'),
                'platform'       => $platform,
                'title'          => $post->title ?: substr($post->caption, 0, 60),
                'content_type'   => $post->content_type,
                'status'         => $schedule->status === 'done' ? 'published' : $post->status,
                'campaign_color' => $campaignColor,
            ]);
        }

        // Group by date for calendar rendering
        $postsByDate = $posts->groupBy('date');

        return view('marketing.calendar.index', compact('posts', 'postsByDate', 'month', 'year', 'draftPosts'));
    }

    // -------------------------------------------------------------------------
    // Reschedule — PUT, update scheduled_at for a post
    // -------------------------------------------------------------------------
    public function reschedule(Request $request, MarketingPost $post): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $newTime = Carbon::parse($validated['scheduled_at']);

        // Find or create the pending schedule for this post
        $schedule = PostSchedule::where('post_id', $post->id)
            ->where('status', 'pending')
            ->first();

        if ($schedule) {
            $schedule->update(['scheduled_at' => $newTime, 'updated_by' => auth()->id()]);
        } else {
            PostSchedule::create([
                'post_id'      => $post->id,
                'scheduled_at' => $newTime,
                'status'       => 'pending',
                'created_by'   => auth()->id(),
                'updated_by'   => auth()->id(),
            ]);
            $post->update(['status' => 'scheduled', 'updated_by' => auth()->id()]);
        }

        return response()->json(['success' => true, 'scheduled_at' => $newTime->toIso8601String()]);
    }

    // -------------------------------------------------------------------------
    // Export — CSV of all scheduled posts for the month
    // -------------------------------------------------------------------------
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $clinicId = self::CLINIC_ID;
        $month    = (int) $request->get('month', now()->month);
        $year     = (int) $request->get('year',  now()->year);

        $schedules = PostSchedule::with('post')
            ->whereYear('scheduled_at', $year)
            ->whereMonth('scheduled_at', $month)
            ->whereHas('post', fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('scheduled_at')
            ->get();

        $filename = "content-calendar-{$year}-{$month}.csv";

        return response()->streamDownload(function () use ($schedules) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Time', 'Platform', 'Title', 'Content Type', 'Status']);

            foreach ($schedules as $schedule) {
                $post      = $schedule->post;
                $platforms = $post->platforms ?? [];
                fputcsv($handle, [
                    $schedule->scheduled_at->format('Y-m-d'),
                    $schedule->scheduled_at->format('H:i'),
                    implode(', ', is_array($platforms) ? $platforms : []),
                    $post->title ?: substr($post->caption, 0, 60),
                    $post->content_type,
                    $post->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
