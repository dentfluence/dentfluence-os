<?php

namespace App\Jobs\Marketing;

use App\Integration\IntegrationEngine;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PlatformConnection;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\PostVariant;
use App\Support\Features\Feature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Processes a scheduled post when its time arrives.
 *
 * Phase 5: Dispatches to real platform adapters if a PlatformConnection exists.
 *          Falls back to "mark published" if no connection (so the calendar still works).
 *
 * Dispatched by PublishController::store() with ->delay($scheduledAt).
 */
class ProcessScheduledPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Number of times to attempt if it fails */
    public int $tries = 3;

    /** Seconds to wait between retries */
    public int $backoff = 60;

    public function __construct(
        private readonly int $scheduleId
    ) {}

    public function handle(): void
    {
        $schedule = PostSchedule::find($this->scheduleId);

        if (! $schedule) {
            Log::warning("ProcessScheduledPost: schedule #{$this->scheduleId} not found.");
            return;
        }

        // Skip if already processed or cancelled
        if ($schedule->status !== 'pending') {
            return;
        }

        $schedule->update(['status' => 'processing']);

        try {
            $post = $schedule->post;

            if (! $post) {
                throw new \Exception("Post not found for schedule #{$this->scheduleId}");
            }

            // ── Phase 5: per-platform dispatch ────────────────────────────────
            $variants = PostVariant::where('post_id', $post->id)->get();
            $results  = [];

            foreach ($variants as $variant) {
                $platformResult = $this->dispatchToPlatform($variant, $post);
                $results[$variant->platform] = $platformResult;

                // NOTE: this used to write a 'meta' key, which isn't a real
                // column on mkt_post_variants (the column is
                // platform_specific_meta) — Eloquent silently drops unknown
                // fillable keys, so external_id/publish_error were NEVER
                // actually saved anywhere the UI could show them. Fixed to
                // write the real columns the migration + model define.
                $variant->update([
                    'status'                 => $platformResult['success'] ? 'published' : 'failed',
                    'published_at'           => $platformResult['success'] ? now() : null,
                    'external_id'            => $platformResult['platform_post_id'] ?? null,
                    'publish_error'          => $platformResult['error'] ?? null,
                    'platform_specific_meta' => array_merge($variant->platform_specific_meta ?? [], [
                        'publish_result' => $platformResult,
                    ]),
                ]);
            }
            // ─────────────────────────────────────────────────────────────────

            // Mark master post as published
            $post->update(['status' => 'published', 'updated_by' => null]);

            // Mark schedule as done
            $schedule->update([
                'status'       => 'done',
                'processed_at' => now(),
            ]);

            // Log activity
            MarketingActivityLog::log(
                $post->clinic_id,
                'post_published',
                $post,
                "Post \"" . ($post->title ?: substr($post->caption, 0, 40)) . "\" published (scheduled)",
                ['schedule_id' => $this->scheduleId],
                null
            );

        } catch (\Throwable $e) {
            Log::error("ProcessScheduledPost failed for schedule #{$this->scheduleId}: " . $e->getMessage());

            $schedule->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at'  => now(),
                'retry_count'   => ($schedule->retry_count ?? 0) + 1,
            ]);

            // Mark post as failed
            $schedule->post?->update(['status' => 'failed']);

            throw $e; // Let Laravel retry per $this->tries
        }
    }

    // -----------------------------------------------------------------------
    // Platform adapters (Phase 5 stubs — Phase 6 adds full SDK calls)
    // -----------------------------------------------------------------------

    /**
     * Dispatch a single variant to its target platform.
     * Returns ['success' => bool, 'platform_post_id' => string|null, 'error' => string|null]
     */
    private function dispatchToPlatform(PostVariant $variant, MarketingPost $post): array
    {
        $clinicId = $post->clinic_id;
        $platform = $variant->platform;

        // WhatsApp is selectable in the compose form (PublishController validates
        // it as an allowed platform) but was never actually wired here — it fell
        // through to the generic "no connection" / "platform_not_implemented"
        // branches below, both of which return success:true. That silently lied:
        // the post would show as "published" on WhatsApp while nothing was ever
        // sent. Also, unlike Instagram/Facebook/Google Business/WordPress (which
        // publish to a public page), "publishing" via WhatsApp would mean
        // broadcasting to a list of patients/leads — a fundamentally different,
        // consent-gated flow (see Phase 4's CommunicationGuard), not a simple
        // page post. Real WhatsApp Business API isn't configured yet (confirmed
        // via .env — dry-run only; Sumit's buying the real API a few months after
        // VPS go-live). Fail honestly instead of pretending it worked.
        if ($platform === 'whatsapp') {
            return [
                'success' => false,
                'error'   => 'WhatsApp marketing broadcast is not built yet — the WhatsApp Business API isn\'t configured. This post will NOT be sent on WhatsApp. Remove the WhatsApp platform from this post, or wait until WhatsApp broadcast is wired up.',
            ];
        }

        $conn = PlatformConnection::where('clinic_id', $clinicId)
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->first();

        // No connection — mark as "published" in the UI so the calendar works
        if (! $conn) {
            Log::info("ProcessScheduledPost: no {$platform} connection for clinic {$clinicId} — skipping live publish.");
            return ['success' => true, 'platform_post_id' => null, 'note' => 'no_connection'];
        }

        // Token expired — fail gracefully
        if ($conn->isTokenExpired()) {
            return ['success' => false, 'error' => "{$platform} token has expired. Reconnect in Marketing → Integrations."];
        }

        try {
            return match ($platform) {
                'instagram'        => $this->publishToInstagram($variant, $post, $conn),
                'facebook'         => $this->publishToFacebook($variant, $post, $conn),
                'google_business'  => $this->publishToGoogleBusiness($variant, $post, $conn),
                'wordpress'        => $this->publishToWordpress($variant, $post, $conn),
                default            => ['success' => true, 'note' => 'platform_not_implemented'],
            };
        } catch (\Throwable $e) {
            Log::error("ProcessScheduledPost: {$platform} publish failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Instagram — two-step Meta Graph API publish:
     *   Step 1: POST /media  → creates a container (returns creation_id)
     *   Step 2: POST /media_publish  → publishes the container
     *
     * Requires on PlatformConnection:
     *   - external_account_id : Instagram Business User ID
     *   - access_token        : long-lived page access token (encrypted, decrypted by model)
     *
     * Image: first media attached to the post is used.
     * Caption-only posts are not supported by the Instagram Content Publishing API
     * (an image_url is required). Falls back gracefully if no media attached.
     */
    private function publishToInstagram(PostVariant $variant, MarketingPost $post, PlatformConnection $conn): array
    {
        $igUserId = $conn->external_account_id;
        $token    = $conn->access_token; // model should decrypt if encrypted
        $base     = "https://graph.facebook.com/v19.0";

        if (! $igUserId || ! $token) {
            return ['success' => false, 'error' => 'Instagram account ID or token missing. Reconnect in Integrations.'];
        }

        $caption = $variant->caption ?: $post->caption;

        // ── Step 1: Create media container ───────────────────────────────
        $mediaPayload = ['caption' => $caption];

        // Attach image if available
        $firstMedia = $post->media()->first();
        if ($firstMedia && $firstMedia->url) {
            $mediaPayload['image_url'] = $firstMedia->url;
        } else {
            // Instagram requires an image — skip live publish, mark as note
            Log::info("ProcessScheduledPost: Instagram post #{$post->id} has no image; skipping live publish.");
            return ['success' => true, 'platform_post_id' => null, 'note' => 'no_image_skipped'];
        }

        // Phase 7 (Integration boundary): routed through MetaConnector once
        // `integration.meta` is on; legacy inline Graph calls otherwise —
        // default OFF means the block below behaves exactly as before this
        // slice. A live publish creates a real post, so exactly one branch
        // ever runs — never both for the same schedule.
        $viaConnector = Feature::enabled('integration.meta');
        $engine       = app(IntegrationEngine::class);

        if ($viaConnector) {
            $step1 = $engine->meta()->createInstagramContainer($igUserId, $token, $mediaPayload);
            if (! $step1['success']) {
                Log::warning("ProcessScheduledPost: Instagram container create failed: {$step1['error']}");
                $engine->logMetaPublish('instagram', true, false, $step1['error']);
                return ['success' => false, 'error' => "Instagram media container error: {$step1['error']}"];
            }

            $containerId = $step1['id'];
            Log::info("ProcessScheduledPost: Instagram container created: {$containerId}");

            $step2 = $engine->meta()->publishInstagramContainer($igUserId, $token, $containerId);
            if (! $step2['success']) {
                Log::warning("ProcessScheduledPost: Instagram publish failed: {$step2['error']}");
                $engine->logMetaPublish('instagram', true, false, $step2['error']);
                return ['success' => false, 'error' => "Instagram publish error: {$step2['error']}"];
            }

            Log::info("ProcessScheduledPost: Instagram published OK — post ID {$step2['id']}");
            $engine->logMetaPublish('instagram', true, true);
            return ['success' => true, 'platform_post_id' => $step2['id']];
        }

        // ── legacy inline Graph calls (unchanged) ───────────────────────────
        $r1 = Http::withToken($token)
            ->timeout(20)
            ->post("{$base}/{$igUserId}/media", $mediaPayload);

        if (! $r1->successful() || ! $r1->json('id')) {
            $err = $r1->json('error.message') ?? "HTTP {$r1->status()}";
            Log::warning("ProcessScheduledPost: Instagram container create failed: {$err}");
            $engine->logMetaPublish('instagram', false, false, $err);
            return ['success' => false, 'error' => "Instagram media container error: {$err}"];
        }

        $containerId = $r1->json('id');
        Log::info("ProcessScheduledPost: Instagram container created: {$containerId}");

        // ── Step 2: Publish container ─────────────────────────────────────
        $r2 = Http::withToken($token)
            ->timeout(20)
            ->post("{$base}/{$igUserId}/media_publish", [
                'creation_id' => $containerId,
            ]);

        if (! $r2->successful() || ! $r2->json('id')) {
            $err = $r2->json('error.message') ?? "HTTP {$r2->status()}";
            Log::warning("ProcessScheduledPost: Instagram publish failed: {$err}");
            $engine->logMetaPublish('instagram', false, false, $err);
            return ['success' => false, 'error' => "Instagram publish error: {$err}"];
        }

        $platformPostId = $r2->json('id');
        Log::info("ProcessScheduledPost: Instagram published OK — post ID {$platformPostId}");
        $engine->logMetaPublish('instagram', false, true);

        return ['success' => true, 'platform_post_id' => $platformPostId];
    }

    /**
     * Facebook Pages — Graph API page feed post.
     * Real call: POST /v19.0/{page-id}/feed
     *
     * Requires on PlatformConnection:
     *   - external_account_id : Facebook Page ID
     *   - access_token        : page access token
     *
     * Attaches a link (first media URL) or posts text-only if no media.
     */
    private function publishToFacebook(PostVariant $variant, MarketingPost $post, PlatformConnection $conn): array
    {
        $pageId = $conn->external_account_id;
        $token  = $conn->access_token;
        $base   = "https://graph.facebook.com/v19.0";

        if (! $pageId || ! $token) {
            return ['success' => false, 'error' => 'Facebook Page ID or token missing. Reconnect in Integrations.'];
        }

        $message = $variant->caption ?: $post->caption;
        $payload = ['message' => $message];

        // Optionally attach a link/image
        $firstMedia = $post->media()->first();
        if ($firstMedia && $firstMedia->url) {
            $payload['link'] = $firstMedia->url;
        }

        // Phase 7: same Integration Engine routing as publishToInstagram() above.
        $viaConnector = Feature::enabled('integration.meta');
        $engine       = app(IntegrationEngine::class);

        if ($viaConnector) {
            $result = $engine->meta()->publishFacebookFeed($pageId, $token, $payload);
            $engine->logMetaPublish('facebook', true, $result['success'], $result['error']);

            if (! $result['success']) {
                Log::warning("ProcessScheduledPost: Facebook post failed: {$result['error']}");
                return ['success' => false, 'error' => "Facebook post error: {$result['error']}"];
            }

            Log::info("ProcessScheduledPost: Facebook published OK — post ID {$result['id']}");
            return ['success' => true, 'platform_post_id' => $result['id']];
        }

        // ── legacy inline Graph call (unchanged) ────────────────────────────
        $r = Http::withToken($token)
            ->timeout(20)
            ->post("{$base}/{$pageId}/feed", $payload);

        if (! $r->successful()) {
            $err = $r->json('error.message') ?? "HTTP {$r->status()}";
            Log::warning("ProcessScheduledPost: Facebook post failed: {$err}");
            $engine->logMetaPublish('facebook', false, false, $err);
            return ['success' => false, 'error' => "Facebook post error: {$err}"];
        }

        $platformPostId = $r->json('id');
        Log::info("ProcessScheduledPost: Facebook published OK — post ID {$platformPostId}");
        $engine->logMetaPublish('facebook', false, true);

        return ['success' => true, 'platform_post_id' => $platformPostId];
    }

    /**
     * Google Business Profile — create a localPost via the My Business API.
     * Real endpoint: POST https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/localPosts
     *
     * Requires on PlatformConnection:
     *   - external_account_id : "{accountId}/{locationId}" (stored as "accounts/xxx/locations/yyy")
     *   - access_token        : OAuth2 access token (short-lived; should be refreshed via OAuthService)
     *
     * Post types supported: STANDARD (text/image). EVENT and OFFER require extra fields.
     */
    private function publishToGoogleBusiness(PostVariant $variant, MarketingPost $post, PlatformConnection $conn): array
    {
        $locationName = $conn->external_account_id; // e.g. "accounts/123/locations/456"
        $token        = $conn->access_token;

        if (! $locationName || ! $token) {
            return ['success' => false, 'error' => 'Google Business location or token missing. Reconnect in Integrations.'];
        }

        $summary = $variant->caption ?: $post->caption;
        $summary = mb_substr($summary, 0, 1500); // GBP max summary length

        $payload = [
            'languageCode' => 'en-US',
            'summary'      => $summary,
            'callToAction' => [
                'actionType' => 'LEARN_MORE',
                'url'        => $conn->meta['website_url'] ?? config('app.url'),
            ],
        ];

        // Attach image if available
        $firstMedia = $post->media()->first();
        if ($firstMedia && $firstMedia->url) {
            $payload['media'] = [
                [
                    'mediaFormat'  => 'PHOTO',
                    'sourceUrl'    => $firstMedia->url,
                ]
            ];
        }

        // Phase 7: this is a Google vendor call (not Meta), so it routes
        // through `integration.google` — the same flag as the Google OAuth
        // methods in OAuthService (Slice 2). Discovered living in this Meta-
        // adjacent job while wrapping Instagram/Facebook above.
        $viaConnector = Feature::enabled('integration.google');
        $engine       = app(IntegrationEngine::class);

        if ($viaConnector) {
            $result = $engine->google()->publishBusinessPost($locationName, $token, $payload);
            $engine->logGoogleBusinessPublish(true, $result['success'], $result['error']);

            if (! $result['success']) {
                Log::warning("ProcessScheduledPost: Google Business post failed: {$result['error']}");
                return ['success' => false, 'error' => "Google Business error: {$result['error']}"];
            }

            Log::info("ProcessScheduledPost: Google Business published OK — {$result['id']}");
            return ['success' => true, 'platform_post_id' => $result['id']];
        }

        // ── legacy inline call (unchanged) ──────────────────────────────────
        $r = Http::withToken($token)
            ->timeout(30)
            ->post("https://mybusiness.googleapis.com/v4/{$locationName}/localPosts", $payload);

        if (! $r->successful()) {
            $err = $r->json('error.message') ?? "HTTP {$r->status()}";
            Log::warning("ProcessScheduledPost: Google Business post failed: {$err}");
            $engine->logGoogleBusinessPublish(false, false, $err);
            return ['success' => false, 'error' => "Google Business error: {$err}"];
        }

        $platformPostId = $r->json('name'); // GBP returns the resource name
        Log::info("ProcessScheduledPost: Google Business published OK — {$platformPostId}");
        $engine->logGoogleBusinessPublish(false, true);

        return ['success' => true, 'platform_post_id' => $platformPostId];
    }

    /**
     * WordPress — Post via WP REST API using app-password auth.
     * meta['site_url'] and meta['username'] are stored on the PlatformConnection.
     * access_token stores the app-password (encrypted).
     */
    private function publishToWordpress(PostVariant $variant, MarketingPost $post, PlatformConnection $conn): array
    {
        $siteUrl  = $conn->meta['site_url']  ?? null;
        $username = $conn->meta['username']  ?? null;
        $password = $conn->access_token;

        if (! $siteUrl || ! $username || ! $password) {
            return ['success' => false, 'error' => 'WordPress credentials incomplete. Re-connect in Integrations.'];
        }

        $payload = [
            'title'   => $post->title ?: substr($post->caption, 0, 60),
            'content' => $variant->caption ?: $post->caption,
            'status'  => 'publish',
        ];

        // Phase 7: WordPress is the clinic's own website — routes through the
        // new `integration.website` flag (Slice 3), separate from Meta/Google.
        $viaConnector = Feature::enabled('integration.website');
        $engine       = app(IntegrationEngine::class);

        if ($viaConnector) {
            $result = $engine->website()->publishWordpress($siteUrl, $username, $password, $payload);
            $engine->logWebsitePublish(true, $result['success'], $result['error']);

            return $result['success']
                ? ['success' => true, 'platform_post_id' => $result['id']]
                : ['success' => false, 'error' => 'WP API error: ' . $result['error']];
        }

        // ── legacy inline call (unchanged) ──────────────────────────────────
        $r = Http::withBasicAuth($username, $password)
            ->post("{$siteUrl}/wp-json/wp/v2/posts", $payload);

        if ($r->successful()) {
            $engine->logWebsitePublish(false, true);
            return ['success' => true, 'platform_post_id' => (string) $r->json('id')];
        }

        $err = $r->json('message') ?? ('HTTP ' . $r->status());
        $engine->logWebsitePublish(false, false, $err);

        return ['success' => false, 'error' => 'WP API error: ' . $err];
    }
}
