<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostVariant;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\MarketingActivityLog;
use App\Jobs\Marketing\ProcessScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class PublishController extends Controller
{
    private const CLINIC_ID = 1;

    /** Show the compose/publish page */
    public function index(): View
    {
        return view('marketing.publish.index');
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
            'clinic_id'    => self::CLINIC_ID,
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
            self::CLINIC_ID,
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
            'clinic_id'    => self::CLINIC_ID,
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
