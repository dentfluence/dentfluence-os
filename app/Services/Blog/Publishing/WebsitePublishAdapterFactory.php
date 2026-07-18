<?php

namespace App\Services\Blog\Publishing;

use App\Models\Blog\BlogPublication;
use App\Models\Marketing\PlatformConnection;
use App\Services\Blog\BlogBlockRenderer;
use App\Services\Blog\Publishing\Contracts\WebsitePublishAdapter;
use RuntimeException;

/**
 * Resolves which WebsitePublishAdapter a clinic (or an existing publication)
 * talks to — the one place that decides the publishing target.
 * ----------------------------------------------------------------------------
 * Rule (Wave 1): a connected `wordpress` PlatformConnection → WordPressAdapter;
 * otherwise → StandaloneAdapter (the safe no-website default). The future
 * dentfluence_static tier is present only as a stub (Wave 4).
 *
 * forClinic() is used when first publishing (pick the target). forPublication()
 * rebuilds the correct adapter for an existing ledger row (retry / update /
 * delete / status), keyed off its stored target_type + connection.
 */
class WebsitePublishAdapterFactory
{
    public function __construct(private readonly BlogBlockRenderer $renderer)
    {
    }

    /**
     * Pick the publishing target for a clinic's next publish.
     */
    public function forClinic(int $clinicId): WebsitePublishAdapter
    {
        $conn = PlatformConnection::query()
            ->forClinic($clinicId)
            ->where('platform', 'wordpress')
            ->connected()
            ->first();

        if ($conn && ($client = WordpressClient::fromConnection($conn))) {
            return new WordPressAdapter($client, $this->renderer, (int) $conn->id);
        }

        // No connected WordPress (or incomplete creds) → standalone, never a
        // faked WordPress success. On dentfluence.test this is the path taken.
        return new StandaloneAdapter();
    }

    /**
     * Rebuild the adapter for an existing publication (retry/update/delete/
     * status). Throws for a WordPress row whose connection has vanished or been
     * disconnected, so the job records an honest failure instead of guessing.
     */
    public function forPublication(BlogPublication $publication): WebsitePublishAdapter
    {
        return match ($publication->target_type) {
            BlogPublication::TARGET_WORDPRESS         => $this->wordpressForPublication($publication),
            BlogPublication::TARGET_STANDALONE        => new StandaloneAdapter(),
            BlogPublication::TARGET_DENTFLUENCE_STATIC => new DentfluenceStaticAdapter(),
            default => throw new RuntimeException("Unknown publish target: {$publication->target_type}"),
        };
    }

    private function wordpressForPublication(BlogPublication $publication): WebsitePublishAdapter
    {
        $conn = $publication->platform_connection_id
            ? PlatformConnection::query()->where('platform', 'wordpress')->find($publication->platform_connection_id)
            : null;

        if (! $conn || ! $conn->isConnected()) {
            throw new RuntimeException('The WordPress connection for this post is no longer available — reconnect it in Marketing → Integrations.');
        }

        $client = WordpressClient::fromConnection($conn);
        if (! $client) {
            throw new RuntimeException('WordPress credentials are incomplete — reconnect in Marketing → Integrations.');
        }

        return new WordPressAdapter($client, $this->renderer, (int) $conn->id);
    }
}
