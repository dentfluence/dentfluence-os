<?php

namespace App\Services\Blog\Publishing;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPublication;
use App\Services\Blog\BlogBlockRenderer;
use App\Services\Blog\Publishing\Contracts\WebsitePublishAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publishes a BlogPost to a WordPress site via the REST API.
 * ----------------------------------------------------------------------------
 * The canonical content (body_json) is rendered to portable HTML by
 * BlogBlockRenderer — the exact same renderer that produces body_html — so the
 * website receives identical markup to what the Hub previews. Categories/tags
 * resolve to (and cache) WP term ids on the Blog taxonomy rows; the featured
 * asset's bytes are uploaded to the WP media library and set as featured_media.
 *
 * Unlike the social publisher (which always drafts), the blog adapter honours
 * the post's editorial status: published → WP `publish`, scheduled → WP
 * `future` (WordPress publishes it at scheduled_at), draft → WP `draft`.
 *
 * SEO: WordPress core exposes only slug + excerpt (both mapped). Meta
 * description / canonical / OG need a SEO plugin (Yoast/RankMath) — see the
 * clearly-commented applySeoPluginFields() seam, a deliberate no-op for now.
 */
class WordPressAdapter implements WebsitePublishAdapter
{
    public function __construct(
        private readonly WordpressClient $client,
        private readonly BlogBlockRenderer $renderer,
        private readonly int $connectionId,
    ) {
    }

    public function targetType(): string
    {
        return BlogPublication::TARGET_WORDPRESS;
    }

    public function connectionId(): ?int
    {
        return $this->connectionId;
    }

    // -----------------------------------------------------------------------
    // Publish / update / delete / status
    // -----------------------------------------------------------------------

    public function publish(BlogPost $post): PublicationResult
    {
        $payload = $this->buildPayload($post);

        $r = $this->client->createPost($payload);

        if (! $r->successful() || ! $r->json('id')) {
            return PublicationResult::fail('WordPress API error: ' . ($r->json('message') ?? 'HTTP ' . $r->status()));
        }

        return PublicationResult::ok(
            (string) $r->json('id'),
            (string) $r->json('link'),
            'wp_status:' . $r->json('status')
        );
    }

    public function update(BlogPost $post, BlogPublication $publication): PublicationResult
    {
        if (empty($publication->external_id)) {
            // No remote post to update — fall back to a fresh create so a
            // "sync" on a never-synced post still does the right thing.
            return $this->publish($post);
        }

        $r = $this->client->updatePost((string) $publication->external_id, $this->buildPayload($post));

        if (! $r->successful() || ! $r->json('id')) {
            return PublicationResult::fail('WordPress update error: ' . ($r->json('message') ?? 'HTTP ' . $r->status()));
        }

        return PublicationResult::ok(
            (string) $r->json('id'),
            (string) $r->json('link'),
            'wp_status:' . $r->json('status')
        );
    }

    public function delete(BlogPublication $publication): PublicationResult
    {
        if (empty($publication->external_id)) {
            return PublicationResult::fail('Nothing to delete — this post was never published to WordPress.');
        }

        // force=false → trash, recoverable from wp-admin (never hard-delete).
        $r = $this->client->deletePost((string) $publication->external_id, force: false);

        if (! $r->successful()) {
            return PublicationResult::fail('WordPress delete error: ' . ($r->json('message') ?? 'HTTP ' . $r->status()));
        }

        return PublicationResult::ok((string) $publication->external_id, $publication->external_url, 'trashed');
    }

    public function status(BlogPublication $publication): PublicationResult
    {
        if (empty($publication->external_id)) {
            return PublicationResult::fail('No WordPress post id on record.');
        }

        $r = $this->client->getPost((string) $publication->external_id);

        if (! $r->successful() || ! $r->json('id')) {
            return PublicationResult::fail('Could not read WordPress post: ' . ($r->json('message') ?? 'HTTP ' . $r->status()));
        }

        return PublicationResult::ok(
            (string) $r->json('id'),
            (string) $r->json('link'),
            'wp_status:' . $r->json('status')
        );
    }

    // -----------------------------------------------------------------------
    // Payload
    // -----------------------------------------------------------------------

    /**
     * Build the WP post payload shared by create + update. Media/term resolution
     * failures are non-fatal (logged in the client) — the post still publishes,
     * just without that image/term, so a flaky WP taxonomy never blocks a save.
     */
    private function buildPayload(BlogPost $post): array
    {
        $body = is_array($post->body_json) ? $post->body_json : ['version' => 1, 'blocks' => []];

        $payload = [
            'title'   => $post->title ?: 'Untitled',
            'content' => $this->renderer->render($body),
            'slug'    => $post->slug,
            'excerpt' => (string) ($post->excerpt ?? ''),
            'status'  => $this->mapStatus($post),
        ];

        // Scheduled → tell WordPress WHEN to publish (it handles the timer).
        if ($payload['status'] === 'future' && $post->scheduled_at) {
            $payload['date_gmt'] = $post->scheduled_at->copy()->utc()->format('Y-m-d\TH:i:s');
        }

        if ($categoryId = $this->resolveCategoryTerm($post)) {
            $payload['categories'] = [$categoryId];
        }

        $tagIds = $this->resolveTagTerms($post);
        if ($tagIds !== []) {
            $payload['tags'] = $tagIds;
        }

        if ($mediaId = $this->uploadFeatured($post)) {
            $payload['featured_media'] = $mediaId;
        }

        // SEO plugin fields (Yoast/RankMath) — no-op seam, see method doc.
        $this->applySeoPluginFields($payload, $post);

        return $payload;
    }

    /**
     * Editorial status → WordPress post status.
     *   published                        → publish
     *   scheduled (future scheduled_at)  → future  (WP auto-publishes then)
     *   scheduled (due/past) / anything  → publish / draft
     */
    private function mapStatus(BlogPost $post): string
    {
        return match ($post->status) {
            'published' => 'publish',
            'scheduled' => ($post->scheduled_at && $post->scheduled_at->isFuture()) ? 'future' : 'publish',
            default     => 'draft',
        };
    }

    /**
     * Resolve (and cache) the WP category term for the post's BlogCategory.
     * Persists wp_term_id on first resolve so later syncs skip the lookup.
     */
    private function resolveCategoryTerm(BlogPost $post): ?int
    {
        $category = $post->category;
        if (! $category) {
            return null;
        }

        if ($category->wp_term_id) {
            return (int) $category->wp_term_id;
        }

        $termId = $this->client->resolveTermId('categories', $category->name);
        if ($termId !== null) {
            $category->forceFill(['wp_term_id' => $termId])->save();
        }

        return $termId;
    }

    /**
     * Resolve (and cache) WP tag terms for the post's BlogTags.
     * @return array<int,int>
     */
    private function resolveTagTerms(BlogPost $post): array
    {
        $ids = [];

        foreach ($post->tags as $tag) {
            if ($tag->wp_term_id) {
                $ids[] = (int) $tag->wp_term_id;
                continue;
            }

            $termId = $this->client->resolveTermId('tags', $tag->name);
            if ($termId !== null) {
                $tag->forceFill(['wp_term_id' => $termId])->save();
                $ids[] = $termId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Upload the featured DAM asset's bytes to WP media and return the media id.
     * Reads from the public disk (same storage the social publisher uses).
     */
    private function uploadFeatured(BlogPost $post): ?int
    {
        $asset = $post->featuredAsset;
        if (! $asset || ! $asset->file_path) {
            return null;
        }

        $relPath = $this->storageRelativePath((string) $asset->file_path);
        if ($relPath === '' || ! Storage::disk('public')->exists($relPath)) {
            Log::warning("WordPressAdapter: featured image missing in storage for blog post #{$post->id}: {$asset->file_path}");

            return null;
        }

        $uploaded = $this->client->uploadMedia(
            Storage::disk('public')->get($relPath),
            $asset->file_name ?: basename($relPath),
            $asset->mime_type ?: 'image/jpeg'
        );

        return $uploaded['id'] ?? null;
    }

    /**
     * Normalize a stored asset path (plain relative, "/storage/…", or full URL)
     * to a Storage::disk('public') relative path. Mirrors the social service.
     */
    private function storageRelativePath(string $filePath): string
    {
        $path = parse_url($filePath, PHP_URL_PATH) ?: $filePath;

        if (str_contains($path, '/storage/')) {
            $path = Str::after($path, '/storage/');
        }

        return ltrim($path, '/');
    }

    // -----------------------------------------------------------------------
    // SEO plugin seam
    // -----------------------------------------------------------------------

    /**
     * SEAM (no-op for now): map BlogPostSeo → a SEO plugin's REST meta.
     *
     * WordPress CORE has no meta-description / canonical / OpenGraph fields —
     * those are owned by Yoast SEO or RankMath, each exposing their values under
     * a plugin-specific `meta` shape (e.g. Yoast's `_yoast_wpseo_metadesc`,
     * RankMath's `rank_math_description`). Detecting the installed plugin and
     * mapping BlogPostSeo (meta_title/description, canonical_url, og_*, noindex)
     * into it is a later slice. Until then we intentionally do nothing here and
     * do NOT fail — slug + excerpt (WP core) are already set in buildPayload().
     */
    private function applySeoPluginFields(array &$payload, BlogPost $post): void
    {
        // Intentionally empty. Wire Yoast/RankMath meta here when a SEO plugin
        // is detected on the connected site. Must remain non-fatal.
    }
}
