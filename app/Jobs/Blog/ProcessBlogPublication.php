<?php

namespace App\Jobs\Blog;

use App\Models\Blog\BlogPublication;
use App\Services\Blog\Publishing\BlogPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a blog_publications row to its website target (WordPress) off the
 * queue, so a slow/flaky WP REST call never blocks the editor request.
 * ----------------------------------------------------------------------------
 * All the real work lives in BlogPublishingService::execute() (shared with the
 * inline standalone path). This job just loads the ledger row, runs it, and —
 * on failure — throws so Laravel retries per $tries/$backoff. The ledger row
 * already carries the honest status/error/retry_count each attempt, which is
 * what the editor's publishing panel shows.
 *
 * Dispatched by BlogPublishingService::publish()/retry(); a scheduled post is
 * dispatched with ->delay(scheduled_at) (mirrors Marketing\ProcessScheduledPost).
 */
class ProcessBlogPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry a failed publish a few times before giving up. */
    public int $tries = 3;

    /** Seconds between retries. */
    public int $backoff = 60;

    public function __construct(
        private readonly int $publicationId,
        private readonly string $action = 'publish', // 'publish' | 'update'
        private readonly ?int $userId = null,
    ) {
    }

    public function handle(BlogPublishingService $service): void
    {
        $pub = BlogPublication::find($this->publicationId);

        if (! $pub) {
            Log::warning("ProcessBlogPublication: publication #{$this->publicationId} not found.");

            return;
        }

        // Someone deleted the publication from the site between dispatch and run.
        if ($pub->status === 'deleted') {
            return;
        }

        $result = $service->execute($pub, $this->action, $this->userId);

        // Throw so the queue retries with backoff; the ledger row already
        // reflects the failure (status=failed, error, retry_count++).
        if (! $result->success) {
            throw new \RuntimeException($result->error ?? 'Blog publication failed.');
        }
    }
}
