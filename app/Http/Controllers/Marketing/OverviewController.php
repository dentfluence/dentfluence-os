<?php

namespace App\Http\Controllers\Marketing;

use App\Contracts\Marketing\Providers\ReviewProvider;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\FestivalDate;
use App\Models\Marketing\Idea;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostSchedule;
use App\Models\Review;
use App\Services\Marketing\MarketingScoreService;
use App\Support\Features\Feature;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Marketing — Dashboard (formerly "Overview").
 *
 * Re-engineered per docs/marketing-module-reengineering-plan.md (Slice 2):
 * this screen answers one question — "what should I do today?" — instead
 * of being a long scroll of KPI widgets. Everything here is a read-only
 * aggregation of data that already exists; no schema changes.
 */
class OverviewController extends Controller
{
    use ResolvesClinicId;

    public function __construct(private readonly ReviewProvider $reviewProvider) {}

    public function index(): View
    {
        $clinicId = $this->currentClinicId();
        $score    = (new MarketingScoreService($clinicId))->score();

        $streak            = $this->currentStreak($clinicId);
        $upcomingPosts      = $this->upcomingPosts($clinicId);
        $overdueSchedules   = $this->overdueScheduleCount($clinicId);
        $failedThisWeek     = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();
        $missedActivities   = $overdueSchedules + $failedThisWeek;
        $pendingReviews     = $this->reviewProvider->pendingCount($clinicId);
        $staleDrafts        = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'draft')
            ->where('created_at', '<=', now()->subDays(2))
            ->count();
        $upcomingFestival   = FestivalDate::active()->forMonth(now()->month, now()->year)->first();
        $reviewToPromote    = $this->reviewToPromote($clinicId);
        $streakAtRisk       = $streak > 0
            && now()->hour >= 15
            && !MarketingActivityLog::where('clinic_id', $clinicId)
                ->where('event', 'post_published')
                ->whereDate('occurred_at', now()->toDateString())
                ->exists();

        [$tasks, $estimatedMinutes] = $this->buildTodaysTasks(
            $overdueSchedules,
            $pendingReviews,
            $staleDrafts,
            $upcomingFestival,
            $upcomingPosts->isEmpty(),
            $reviewToPromote,
            $streak,
            $streakAtRisk
        );

        return view('marketing.overview.index', [
            'score'            => $score,
            'streak'           => $streak,
            'tasks'            => $tasks,
            'estimatedMinutes' => $estimatedMinutes,
            'upcomingPosts'    => $upcomingPosts,
            'pendingReviews'   => $pendingReviews,
            'missedActivities' => $missedActivities,
        ]);
    }

    /** Consecutive days (walking back from today/yesterday) with a post_published event. */
    private function currentStreak(int $clinicId): int
    {
        $publishedDays = MarketingActivityLog::where('clinic_id', $clinicId)
            ->where('event', 'post_published')
            ->where('occurred_at', '>=', now()->subDays(60))
            ->get()
            ->map(fn ($log) => $log->occurred_at->toDateString())
            ->unique();

        $streak = 0;
        $cursor = now()->startOfDay();

        // Don't break an in-progress streak just because today hasn't happened yet.
        if (!$publishedDays->contains($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        while ($publishedDays->contains($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** Next 3 posts due to go out. */
    private function upcomingPosts(int $clinicId)
    {
        return PostSchedule::with('post')
            ->where('status', 'pending')
            ->where('scheduled_at', '>=', now())
            ->whereHas('post', fn ($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get()
            ->map(function ($schedule) {
                $post      = $schedule->post;
                $platforms = is_array($post->platforms ?? null) ? $post->platforms : [];

                return [
                    'title'    => $post->title ?: substr((string) $post->caption, 0, 60),
                    'platform' => $platforms[0] ?? 'instagram',
                    'when'     => $schedule->scheduled_at->isToday()
                        ? 'Today, ' . $schedule->scheduled_at->format('h:i A')
                        : ($schedule->scheduled_at->isTomorrow()
                            ? 'Tomorrow, ' . $schedule->scheduled_at->format('h:i A')
                            : $schedule->scheduled_at->format('d M, h:i A')),
                ];
            });
    }

    /** Schedules that should already have fired but haven't (queue lag / failure). */
    private function overdueScheduleCount(int $clinicId): int
    {
        return PostSchedule::where('status', 'pending')
            ->where('scheduled_at', '<', now())
            ->whereHas('post', fn ($q) => $q->where('clinic_id', $clinicId))
            ->count();
    }

    /**
     * The most recent positive, rated review that hasn't already been turned
     * into a content idea — V4 automation. Integrated-only: a standalone
     * clinic has no automatic review-collection loop to source this from
     * (see ReviewProvider contract docblock). Defensive like the rest of the
     * cross-module reads in this controller — a problem here never breaks
     * the Dashboard.
     */
    private function reviewToPromote(int $clinicId): ?Review
    {
        if (!Feature::enabled('marketing.integrated_providers')) {
            return null;
        }

        try {
            $threshold = (int) config('reviews.positive_threshold', 4);

            return Review::with('patient')
                ->where('status', 'rated')
                ->where('rating', '>=', $threshold)
                ->where('responded_at', '>=', now()->subDays(14))
                ->latest('responded_at')
                ->get()
                ->first(fn (Review $r) => !Idea::where('clinic_id', $clinicId)
                    ->where('notes', 'like', "%review #{$r->id}%")
                    ->exists());
        } catch (Throwable $e) {
            Log::warning('Marketing dashboard: could not check for a review to promote', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Builds a capped, priority-ordered "what to do today" list with a rough time estimate. */
    private function buildTodaysTasks(
        int $overdueSchedules,
        int $pendingReviews,
        int $staleDrafts,
        ?FestivalDate $upcomingFestival,
        bool $nothingUpcoming,
        ?Review $reviewToPromote = null,
        int $streak = 0,
        bool $streakAtRisk = false
    ): array {
        $tasks   = [];
        $minutes = 0;

        // Streak-at-risk leads the list — loss-aversion is the strongest
        // habit-formation lever, and it costs nothing to check.
        if ($streakAtRisk) {
            $tasks[]  = ['label' => "You're on a {$streak}-day streak — post something before end of day to keep it", 'minutes' => 5];
            $minutes += 5;
        }

        if ($overdueSchedules > 0) {
            $tasks[]  = ['label' => "Check {$overdueSchedules} overdue scheduled post" . ($overdueSchedules === 1 ? '' : 's'), 'minutes' => 2];
            $minutes += 2;
        }

        if ($pendingReviews > 0) {
            $tasks[]  = ['label' => "Reply to {$pendingReviews} review" . ($pendingReviews === 1 ? '' : 's') . ' that need a response', 'minutes' => $pendingReviews * 2, 'action_url' => route('communication.reviews.index'), 'action_label' => 'Reply'];
            $minutes += $pendingReviews * 2;
        }

        if ($reviewToPromote) {
            $tasks[]  = [
                'label'         => "New {$reviewToPromote->rating}★ review from " . ($reviewToPromote->patient->name ?? 'a patient') . ' — turn it into a post',
                'minutes'       => 2,
                'action_url'    => route('marketing.ideas.from-review', $reviewToPromote),
                'action_label'  => 'Save as idea',
                'action_method' => 'POST',
            ];
            $minutes += 2;
        }

        if ($staleDrafts > 0) {
            $tasks[]  = ['label' => "Finish {$staleDrafts} draft post" . ($staleDrafts === 1 ? '' : 's') . ' waiting to go out', 'minutes' => $staleDrafts * 3];
            $minutes += $staleDrafts * 3;
        }

        if ($upcomingFestival) {
            $tasks[]  = ['label' => "Plan a post for {$upcomingFestival->name} coming up this month", 'minutes' => 3];
            $minutes += 3;
        }

        if ($nothingUpcoming && count($tasks) < 2) {
            $tasks[]  = ['label' => "Nothing scheduled — write today's post", 'minutes' => 5];
            $minutes += 5;
        }

        return [array_slice($tasks, 0, 5), $minutes];
    }
}
