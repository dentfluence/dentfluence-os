<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\PostSchedule;
use App\Services\Marketing\MarketingScoreService;
use Illuminate\View\View;

class OverviewController extends Controller
{
    private const CLINIC_ID = 1;

    public function index(): View
    {
        $clinicId = self::CLINIC_ID;
        $scorer   = new MarketingScoreService($clinicId);

        // ── Marketing Score + post stats ─────────────────────────────────────
        $published       = MarketingPost::where('clinic_id', $clinicId)->where('status', 'published')->whereMonth('updated_at', now()->month)->count();
        $publishedLast   = MarketingPost::where('clinic_id', $clinicId)->where('status', 'published')->whereMonth('updated_at', now()->subMonth()->month)->count();
        $scheduled       = MarketingPost::where('clinic_id', $clinicId)->where('status', 'scheduled')->count();
        $missed          = MarketingPost::where('clinic_id', $clinicId)->where('status', 'failed')->whereMonth('updated_at', now()->month)->count();
        $drafts          = MarketingPost::where('clinic_id', $clinicId)->where('status', 'draft')->count();
        $pendingApproval = MarketingPost::where('clinic_id', $clinicId)->where('status', 'pending')->count();

        $publishedTrend  = $publishedLast > 0
            ? round(($published - $publishedLast) / $publishedLast * 100)
            : 0;

        $stats = [
            'score'            => $scorer->score(),
            'published'        => $published,
            'published_trend'  => ($publishedTrend >= 0 ? '+' : '') . $publishedTrend . '%',
            'scheduled'        => $scheduled,
            'scheduled_trend'  => '+0%',
            'missed'           => $missed,
            'missed_trend'     => '0%',
            'drafts'           => $drafts,
            'pending_approval' => $pendingApproval,
            'pending_trend'    => '0%',
        ];

        // ── Active/paused campaigns (top 3) ─────────────────────────────────
        $runningCampaigns = Campaign::where('clinic_id', $clinicId)
            ->whereIn('status', ['active', 'paused'])
            ->with('goals')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get()
            ->map(function ($c) {
                $leads   = $c->goals->firstWhere('goal_type', 'leads');
                $appts   = $c->goals->firstWhere('goal_type', 'appointments');
                $revenue = $c->goals->firstWhere('goal_type', 'revenue');

                return [
                    'name'         => $c->name,
                    'description'  => $c->description ?? '—',
                    'status'       => $c->status,
                    'leads'        => $leads  ? (int) $leads->actual_value  : 0,
                    'appointments' => $appts  ? (int) $appts->actual_value  : 0,
                    'revenue'      => $revenue ? number_format($revenue->actual_value) : '0',
                ];
            })
            ->toArray();

        // ── Upcoming schedule — today + tomorrow ─────────────────────────────
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $upcomingSchedule = [];
        PostSchedule::with('post')
            ->where('status', 'pending')
            ->whereDate('scheduled_at', '>=', $today)
            ->whereDate('scheduled_at', '<=', $tomorrow)
            ->whereHas('post', fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('scheduled_at')
            ->get()
            ->each(function ($schedule) use (&$upcomingSchedule) {
                $label = $schedule->scheduled_at->isToday()
                    ? 'Today - ' . $schedule->scheduled_at->format('d M')
                    : 'Tomorrow - ' . $schedule->scheduled_at->format('d M');

                $post      = $schedule->post;
                $platforms = $post->platforms ?? [];
                $platform  = is_array($platforms) ? ($platforms[0] ?? 'instagram') : 'instagram';

                $upcomingSchedule[$label][] = [
                    'time'         => $schedule->scheduled_at->format('h:i A'),
                    'platform'     => $platform,
                    'title'        => $post->title ?: substr($post->caption, 0, 60),
                    'content_type' => $post->content_type,
                ];
            });

        // ── Recent activity feed ─────────────────────────────────────────────
        $activityFeed = MarketingActivityLog::where('clinic_id', $clinicId)
            ->with('user')
            ->orderByDesc('occurred_at')
            ->limit(6)
            ->get()
            ->map(fn($log) => [
                'description' => $log->description,
                'user'        => $log->user?->name ?? 'System',
                'time'        => $log->occurred_at->diffForHumans(),
                'event'       => $log->event,
            ])
            ->toArray();

        return view('marketing.overview.index', compact(
            'stats',
            'runningCampaigns',
            'upcomingSchedule',
            'activityFeed'
        ));
    }
}
