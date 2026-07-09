<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostVariant;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\Idea;
use App\Jobs\Marketing\ProcessScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class PublishController extends Controller
{
    use ResolvesClinicId;

    /**
     * Show the compose/publish page.
     *
     * Slice 3 addition (docs/marketing-module-reengineering-plan.md): a
     * lightweight Ideas → Drafts → Scheduled → Published board sits above
     * the composer so Content reads as one workflow instead of three
     * separate pages (Publish/Brainstorm/Ideas). Purely additive — the
     * compose/store/schedule logic below is untouched.
     */
    public function index(): View
    {
        $clinicId = $this->currentClinicId();

        $ideas = Idea::where('clinic_id', $clinicId)
            ->whereIn('status', ['idea', 'in_progress'])
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'content_type']);

        $drafts = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'draft')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'caption', 'content_type']);

        $scheduled = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'scheduled')
            ->orderBy('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'caption', 'content_type']);

        $published = MarketingPost::where('clinic_id', $clinicId)
            ->where('status', 'published')
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'caption', 'content_type']);

        $board = [
            'ideas'     => ['label' => 'Ideas',     'total' => Idea::where('clinic_id', $clinicId)->whereIn('status', ['idea', 'in_progress'])->count(), 'items' => $ideas],
            'drafts'    => ['label' => 'Drafts',    'total' => MarketingPost::where('clinic_id', $clinicId)->where('status', 'draft')->count(), 'items' => $drafts],
            'scheduled' => ['label' => 'Scheduled', 'total' => MarketingPost::where('clinic_id', $clinicId)->where('status', 'scheduled')->count(), 'items' => $scheduled],
            'published' => ['label' => 'Published', 'total' => MarketingPost::where('clinic_id', $clinicId)->where('status', 'published')->count(), 'items' => $published],
        ];

        return view('marketing.publish.index', compact('board'));
    }

    // -------------------------------------------------------------------------
    // Store — save master post + auto-create variants + optional schedule
    // -------------------------------------------------------------------------
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'nullable|string|max:255',
            'caption'      => 'required|string',
            'content_type' => 'required|in:reel,post,carousel,story,blog,offer',
            'platforms'    => 'required|array|min:1',
            'platforms.*'  => 'in:instagram,facebook,google_business,whatsapp,wordpress',
            'hashtags'     => 'nullable|array',
            'cta_type'     => 'nullable|string|max:50',
            'cta_text'     => 'nullable|string|max:100',
            'cta_url'      => 'nullable|url',
            'campaign_id'  => 'nullable|integer|exists:mkt_campaigns,id',
            'scheduled_at' => 'nullable|date|after:now',
            // Per-platform variant overrides (optional JSON blobs)
            'variants'     => 'nullable|array',
        ]);

        // 1. Create the master post
        $post = MarketingPost::create([
            'clinic_id'    => $this->currentClinicId(),
            'campaign_id'  => $validated['campaign_id'] ?? null,
            'title'        => $validated['title'] ?? null,
            'caption'      => $validated['caption'],
            'content_type' => $validated['content_type'],
            'platforms'    => $validated['platforms'],
            'hashtags'     => $validated['hashtags'] ?? [],
            'cta_type'     => $validated['cta_type'] ?? null,
            'cta_text'     => $validated['cta_text'] ?? null,
            'cta_url'      => $validated['cta_url'] ?? null,
            'status'       => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'assignee_id'  => auth()->id(),
            'created_by'   => auth()->id(),
            'updated_by'   => auth()->id(),
        ]);

        // 2. Auto-create a PostVariant per platform
        foreach ($validated['platforms'] as $platform) {
            $platformMeta = $validated['variants'][$platform] ?? [];

            PostVariant::create([
                'post_id'                => $post->id,
                'platform'               => $platform,
                'caption'                => null, // inherits master caption
                'platform_specific_meta' => $platformMeta ?: null,
                'status'                 => 'draft',
                'created_by'             => auth()->id(),
                'updated_by'             => auth()->id(),
            ]);
        }

        // 3. Create schedule if a time was provided
        if (! empty($validated['scheduled_at'])) {
            $scheduledAt = Carbon::parse($validated['scheduled_at']);

            $schedule = PostSchedule::create([
                'post_id'      => $post->id,
                'scheduled_at' => $scheduledAt,
                'status'       => 'pending',
                'created_by'   => auth()->id(),
                'updated_by'   => auth()->id(),
            ]);

            // Dispatch queue job to fire at the scheduled time
            ProcessScheduledPost::dispatch($schedule->id)
                ->delay($scheduledAt);
        }

        MarketingActivityLog::log(
            $this->currentClinicId(),
            'post_created',
            $post,
            "Post \"" . ($post->title ?: substr($post->caption, 0, 40)) . "\" created"
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'post_id' => $post->id]);
        }

        return redirect()->route('marketing.calendar')
            ->with('success', 'Post saved' . ($validated['scheduled_at'] ? ' and scheduled.' : ' as draft.'));
    }

    // -------------------------------------------------------------------------
    // Save Draft — quick save without redirect
    // -------------------------------------------------------------------------
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'nullable|string|max:255',
            'caption'      => 'required|string',
            'content_type' => 'required|in:reel,post,carousel,story,blog,offer',
            'platforms'    => 'nullable|array',
        ]);

        $post = MarketingPost::create([
            'clinic_id'    => $this->currentClinicId(),
            'title'        => $validated['title'] ?? null,
            'caption'      => $validated['caption'],
            'content_type' => $validated['content_type'],
            'platforms'    => $validated['platforms'] ?? [],
            'status'       => 'draft',
            'assignee_id'  => auth()->id(),
            'created_by'   => auth()->id(),
            'updated_by'   => auth()->id(),
        ]);

        return response()->json(['success' => true, 'post_id' => $post->id]);
    }

    // -------------------------------------------------------------------------
    // Update Variant — update platform-specific fields for one variant
    // -------------------------------------------------------------------------
    public function updateVariant(Request $request, MarketingPost $post, string $platform): JsonResponse
    {
        $validated = $request->validate([
            'caption'                => 'nullable|string',
            'platform_specific_meta' => 'nullable|array',
        ]);

        $variant = PostVariant::firstOrCreate(
            ['post_id' => $post->id, 'platform' => $platform],
            ['status' => 'draft', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]
        );

        $variant->update(array_merge($validated, ['updated_by' => auth()->id()]));

        return response()->json(['success' => true]);
    }
}
