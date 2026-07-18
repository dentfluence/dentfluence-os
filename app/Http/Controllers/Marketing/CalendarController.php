<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Blog\BlogPost;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\Campaign;
use App\Support\Features\Feature;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class CalendarController extends Controller
{
    use ResolvesClinicId;

    // -------------------------------------------------------------------------
    // Index — calendar view for a given month
    // -------------------------------------------------------------------------
    public function index(Request $request): View
    {
        $clinicId = $this->currentClinicId();
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

        // ── Blog posts (Blog Marketing Hub) ──────────────────────────────
        // The blog no longer has its own calendar — its scheduled/published
        // posts surface here, on the ONE marketing calendar, as read-only
        // "Blog" chips that link straight to the blog editor. Scheduled posts
        // sit on scheduled_at, published posts on published_at (each on the
        // field its own status actually uses). Gated on the blog.hub feature.
        if (Feature::enabled('blog.hub')) {
            foreach ($this->blogCalendarEntries($clinicId, $year, $month) as $entry) {
                $posts->push($entry);
            }
        }

        // Group by date for calendar rendering
        $postsByDate = $posts->groupBy('date');

        return view('marketing.calendar.index', compact('posts', 'postsByDate', 'month', 'year', 'draftPosts'));
    }

    /**
     * Blog-post calendar chips for the given month, in the same flat shape the
     * calendar view renders social chips with. Read-only: each carries a `url`
     * to the blog editor (no drag-drop reschedule). Scheduled posts are placed
     * on scheduled_at, published posts on published_at.
     *
     * @return array<int, array<string,mixed>>
     */
    private function blogCalendarEntries(int $clinicId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $scheduled = BlogPost::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->get()
            ->map(fn (BlogPost $p) => $this->blogCalendarEntry($p, $p->scheduled_at));

        $published = BlogPost::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'published')
            ->whereBetween('published_at', [$start, $end])
            ->get()
            ->map(fn (BlogPost $p) => $this->blogCalendarEntry($p, $p->published_at));

        return $scheduled->concat($published)->all();
    }

    /** Flatten one blog post into a calendar chip (matches social chip keys). */
    private function blogCalendarEntry(BlogPost $post, $at): array
    {
        return [
            'id'             => 'blog-' . $post->uuid,
            'date'           => optional($at)->toDateString(),
            'time'           => optional($at)->format('H:i'),
            'platform'       => 'blog',
            'title'          => $post->title ?: '(untitled)',
            'content_type'   => 'blog',
            'status'         => $post->status,
            'campaign_color' => '#6366f1',
            // Presence of `url` is what marks a chip as a (linked) blog entry
            // in the view; social chips have no url.
            'url'            => route('marketing.blog.edit', ['blog' => $post->uuid]),
        ];
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
        $clinicId = $this->currentClinicId();
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
