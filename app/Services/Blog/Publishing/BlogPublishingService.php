<?php

namespace App\Services\Blog\Publishing;

use App\Jobs\Blog\ProcessBlogPublication;
use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPublication;
use App\Models\Marketing\MarketingActivityLog;
use App\Services\Blog\BlogPostService;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the blog_publications ledger: it owns the "publish / update /
 * delete / retry / status" workflow so the controller stays thin and the
 * adapters stay pure (they only talk to a website and return a result).
 * ----------------------------------------------------------------------------
 * Ledger rule: exactly one row per (post × target_type). publish() upserts it,
 * marks it pending, and either QUEUES ProcessBlogPublication (WordPress — real
 * network work, retryable) or runs inline (standalone/stub — no network). A
 * scheduled post's WordPress job is dispatched with a delay to scheduled_at,
 * mirroring the social ProcessScheduledPost pattern.
 *
 * The shared execute() is the single place a publication actually goes out —
 * called by the job AND the inline path — so both cannot drift.
 */
class BlogPublishingService
{
    public function __construct(
        private readonly WebsitePublishAdapterFactory $factory,
        private readonly BlogPostService $posts,
    ) {
    }

    // -----------------------------------------------------------------------
    // Public workflow (called from the controller)
    // -----------------------------------------------------------------------

    /**
     * Create-or-sync the post to its resolved website target. Returns the
     * ledger row (pending/publishing for WordPress, already-final for the
     * inline standalone path). Never throws for a missing website — that
     * resolves to StandaloneAdapter and records an honest standalone row.
     */
    public function publish(BlogPost $post, ?int $userId): BlogPublication
    {
        $adapter = $this->factory->forClinic($post->clinic_id);

        $pub = BlogPublication::firstOrNew([
            'blog_post_id' => $post->id,
            'target_type'  => $adapter->targetType(),
        ]);

        // An existing remote post means this is a sync (update), not a create.
        $action = ($pub->exists && $pub->external_id) ? 'update' : 'publish';

        $pub->platform_connection_id = $adapter->connectionId();
        $pub->status = 'pending';
        $pub->error  = null;
        $pub->save();

        if ($adapter->targetType() === BlogPublication::TARGET_WORDPRESS) {
            $this->dispatchWordpress($pub, $post, $action, $userId);
        } else {
            // Standalone / stub: no network, resolve immediately.
            $this->execute($pub->fresh(), $action, $userId);
        }

        return $pub->fresh();
    }

    /**
     * Re-attempt a failed (or stale) publication. Re-queues for WordPress,
     * re-runs inline otherwise.
     */
    public function retry(BlogPublication $pub, ?int $userId): BlogPublication
    {
        $pub->update(['status' => 'pending', 'error' => null]);
        $action = $pub->external_id ? 'update' : 'publish';

        if ($pub->target_type === BlogPublication::TARGET_WORDPRESS) {
            ProcessBlogPublication::dispatch($pub->id, $action, $userId);
        } else {
            $this->execute($pub->fresh(), $action, $userId);
        }

        return $pub->fresh();
    }

    /**
     * Remove the post from its website. Runs inline (delete is a single call);
     * marks the ledger row deleted on success, failed (with error) otherwise.
     */
    public function deleteFromSite(BlogPublication $pub, ?int $userId): PublicationResult
    {
        try {
            $adapter = $this->factory->forPublication($pub);
        } catch (\Throwable $e) {
            $pub->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return PublicationResult::fail($e->getMessage());
        }

        $result = $adapter->delete($pub);

        if ($result->success) {
            $pub->update(['status' => 'deleted', 'last_synced_at' => now(), 'error' => null]);
            $this->logEvent($pub, 'blog_unpublished', 'removed from ' . $pub->target_type, $userId);
        } else {
            $pub->update(['status' => 'failed', 'error' => $result->error]);
        }

        return $result;
    }

    /** Read the live remote state (verify) without changing the stored status. */
    public function refreshStatus(BlogPublication $pub): PublicationResult
    {
        try {
            $adapter = $this->factory->forPublication($pub);
        } catch (\Throwable $e) {
            return PublicationResult::fail($e->getMessage());
        }

        $result = $adapter->status($pub);

        if ($result->success) {
            $pub->update(['last_synced_at' => now()]);
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Shared executor (job + inline path both land here)
    // -----------------------------------------------------------------------

    /**
     * Actually push a publication out. Marks it publishing, resolves the adapter
     * for its stored target, calls publish/update, and records the outcome. Does
     * NOT throw — returns the PublicationResult; the job decides whether to throw
     * for a retry based on that result.
     */
    public function execute(BlogPublication $pub, string $action, ?int $userId): PublicationResult
    {
        $post = $pub->post;
        if (! $post) {
            $this->markFailed($pub, 'The blog post for this publication no longer exists.');

            return PublicationResult::fail('Post not found.');
        }

        $pub->update(['status' => 'publishing']);

        try {
            $adapter = $this->factory->forPublication($pub);
        } catch (\Throwable $e) {
            $this->markFailed($pub, $e->getMessage());

            return PublicationResult::fail($e->getMessage());
        }

        $result = ($action === 'update' && $pub->external_id)
            ? $adapter->update($post, $pub)
            : $adapter->publish($post);

        if ($result->success) {
            $this->applySuccess($pub, $post, $result, $userId);
        } else {
            $this->markFailed($pub, $result->error ?? 'Publish failed.');
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function dispatchWordpress(BlogPublication $pub, BlogPost $post, string $action, ?int $userId): void
    {
        $job = new ProcessBlogPublication($pub->id, $action, $userId);

        // Scheduled + future → delay to scheduled_at (social pattern). WordPress
        // also understands a 'future' status, so the post publishes at the right
        // time even if the worker fires early.
        if ($post->status === 'scheduled' && $post->scheduled_at && $post->scheduled_at->isFuture()) {
            dispatch($job->delay($post->scheduled_at));
        } else {
            dispatch($job);
        }
    }

    private function applySuccess(BlogPublication $pub, BlogPost $post, PublicationResult $result, ?int $userId): void
    {
        $pub->update([
            'status'         => 'published',
            'external_id'    => $result->externalId ?? $pub->external_id,
            'external_url'   => $result->externalUrl ?? $pub->external_url,
            'last_synced_at' => now(),
            'error'          => null,
        ]);

        // Safety net: if the post is editorially published but the first-publish
        // seam was somehow missed (slug lock / timestamps), stamp it now. The
        // editor's save already calls this, so this is idempotent.
        if ($post->status === 'published' && $post->first_published_at === null) {
            $this->posts->markFirstPublished($post);
            $post->save();
        }

        $this->logEvent($pub, 'blog_published', 'published to ' . $pub->target_type, $userId);
    }

    private function markFailed(BlogPublication $pub, string $error): void
    {
        $pub->update([
            'status'      => 'failed',
            'error'       => $error,
            'retry_count' => $pub->retry_count + 1,
        ]);

        Log::warning("BlogPublishingService: publication #{$pub->id} failed: {$error}");
    }

    private function logEvent(BlogPublication $pub, string $event, string $description, ?int $userId): void
    {
        $post = $pub->post;
        if (! $post) {
            return;
        }

        MarketingActivityLog::log(
            $post->clinic_id,
            $event,
            $post,
            "Blog post \"{$post->title}\" {$description}",
            ['publication_id' => $pub->id, 'target' => $pub->target_type, 'external_url' => $pub->external_url],
            $userId
        );
    }
}
