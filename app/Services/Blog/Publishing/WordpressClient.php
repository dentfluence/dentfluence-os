<?php

namespace App\Services\Blog\Publishing;

use App\Models\Marketing\PlatformConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin WordPress REST client for the BLOG publish adapter.
 * ----------------------------------------------------------------------------
 * This deliberately MIRRORS the media-upload and term-resolve HTTP patterns of
 * the working social publisher (App\Services\Marketing\WordpressPublishService)
 * rather than refactoring that battle-tested class — the social path operates
 * on MarketingPost/PostVariant and is on the live clinic site, so it is left
 * untouched. This client is the single WP HTTP surface for BlogPost publishing
 * (create / update / delete / get post, upload media, resolve terms). It knows
 * nothing about the Blog domain; the WordPressAdapter drives it.
 *
 * Auth: WordPress Application Password over basic auth (username + app
 * password), exactly like the social service. Credentials come from the
 * clinic's connected `wordpress` PlatformConnection (meta.site_url,
 * meta.username, and access_token — decrypted by the model accessor).
 */
class WordpressClient
{
    public function __construct(
        private readonly string $siteUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    /**
     * Build from a connected `wordpress` PlatformConnection, or null if the
     * stored credentials are incomplete (never guess — the adapter reports a
     * clear "reconnect" error instead of pretending to publish).
     */
    public static function fromConnection(PlatformConnection $conn): ?self
    {
        $siteUrl  = rtrim((string) ($conn->meta['site_url'] ?? ''), '/');
        $username = (string) ($conn->meta['username'] ?? '');
        $password = (string) $conn->access_token; // decrypted by the model accessor

        if ($siteUrl === '' || $username === '' || $password === '') {
            return null;
        }

        return new self($siteUrl, $username, $password);
    }

    // -----------------------------------------------------------------------
    // Posts
    // -----------------------------------------------------------------------

    public function createPost(array $payload): Response
    {
        return $this->http()->timeout(30)->post("{$this->siteUrl}/wp-json/wp/v2/posts", $payload);
    }

    /** WP accepts POST to /posts/{id} as an update (partial). */
    public function updatePost(string $externalId, array $payload): Response
    {
        return $this->http()->timeout(30)->post("{$this->siteUrl}/wp-json/wp/v2/posts/{$externalId}", $payload);
    }

    /** force=false → move to trash (recoverable); true → permanent delete. */
    public function deletePost(string $externalId, bool $force = false): Response
    {
        return $this->http()->timeout(30)->delete(
            "{$this->siteUrl}/wp-json/wp/v2/posts/{$externalId}",
            ['force' => $force ? 'true' : 'false']
        );
    }

    public function getPost(string $externalId): Response
    {
        return $this->http()->timeout(20)->get("{$this->siteUrl}/wp-json/wp/v2/posts/{$externalId}", ['context' => 'edit']);
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Upload raw image bytes to the WP media library (the WP site cannot reach
     * our storage, so we push the bytes). Returns [id, source_url] or null on
     * failure (non-fatal — the post can still publish without a featured image).
     */
    public function uploadMedia(string $bytes, string $filename, string $mime): ?array
    {
        $r = $this->http()
            ->withHeaders(['Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"'])
            ->withBody($bytes, $mime)
            ->timeout(60)
            ->post("{$this->siteUrl}/wp-json/wp/v2/media");

        if (! $r->successful() || ! $r->json('id')) {
            Log::warning('BlogWordpressClient: media upload failed (' . $filename . '): '
                . ($r->json('message') ?? 'HTTP ' . $r->status()));

            return null;
        }

        return [
            'id'         => (int) $r->json('id'),
            'source_url' => (string) $r->json('source_url'),
        ];
    }

    // -----------------------------------------------------------------------
    // Taxonomy
    // -----------------------------------------------------------------------

    /**
     * Look a term up by exact (case-insensitive) name; create it if missing.
     * Returns the WP term id or null (non-fatal — the post publishes untagged).
     * Handles the "term_exists" race the same way the social service does.
     */
    public function resolveTermId(string $taxonomy, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $search = $this->http()->timeout(20)
            ->get("{$this->siteUrl}/wp-json/wp/v2/{$taxonomy}", ['search' => $name, 'per_page' => 100]);

        if ($search->successful()) {
            foreach ($search->json() ?? [] as $term) {
                $termName = html_entity_decode((string) ($term['name'] ?? ''), ENT_QUOTES);
                if (mb_strtolower($termName) === mb_strtolower($name)) {
                    return (int) $term['id'];
                }
            }
        }

        $create = $this->http()->timeout(20)
            ->post("{$this->siteUrl}/wp-json/wp/v2/{$taxonomy}", ['name' => $name]);

        if ($create->successful() && $create->json('id')) {
            return (int) $create->json('id');
        }

        // WP returns 400 "term_exists" with the existing id on create races.
        if ($create->json('code') === 'term_exists' && $create->json('data.term_id')) {
            return (int) $create->json('data.term_id');
        }

        Log::warning("BlogWordpressClient: {$taxonomy} resolve failed for \"{$name}\": "
            . ($create->json('message') ?? 'HTTP ' . $create->status()));

        return null;
    }

    private function http()
    {
        return Http::withBasicAuth($this->username, $this->password);
    }
}
