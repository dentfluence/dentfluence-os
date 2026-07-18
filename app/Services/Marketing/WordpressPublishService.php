<?php

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PlatformConnection;
use App\Models\Marketing\PostVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WordpressPublishService — single source of truth for turning a composed
 * marketing post into a WordPress blog post.
 *
 * Locked behaviour (docs/blog-publish-engine-brief.md):
 *   - The WP post is ALWAYS created as a DRAFT; a human reviews and publishes
 *     from wp-admin. We return the wp-admin edit-post URL.
 *   - Every image attached to the post is uploaded to the WP media library
 *     (raw bytes from Storage::disk('public'), app-password basic auth).
 *     The first becomes the featured image; all are embedded inline.
 *   - Composer hashtags map to WP tags (looked up by name, created if
 *     missing). The post is filed under the "Patient Education" category
 *     (created if missing).
 *   - Caption → HTML paragraphs; hashtags are stripped from the body (they
 *     live in tags); the CTA is appended as a trailing link.
 *
 * Called by BOTH publish paths so they cannot drift:
 *   - legacy inline: ProcessScheduledPost::publishToWordpress()
 *     (integration.website flag OFF — the current default)
 *   - connector:     WebsiteConnector::publishWordpressDraft()
 *     (integration.website flag ON)
 */
class WordpressPublishService
{
    private const DEFAULT_CATEGORY = 'Patient Education';

    /**
     * Create the post as a WP draft.
     *
     * Returns the normalized shape ProcessScheduledPost expects:
     * ['success' => bool, 'platform_post_id' => ?string, 'external_url' => ?string,
     *  'error' => ?string, 'note' => ?string, 'warnings' => ?array, 'wp' => ?array]
     */
    public function publishDraft(MarketingPost $post, PostVariant $variant, PlatformConnection $conn): array
    {
        $siteUrl  = rtrim((string) ($conn->meta['site_url'] ?? ''), '/');
        $username = $conn->meta['username'] ?? null;
        $password = $conn->access_token; // decrypted by the model accessor — do NOT re-decrypt

        if (! $siteUrl || ! $username || ! $password) {
            return $this->failure('WordPress credentials incomplete. Re-connect in Marketing → Integrations.');
        }

        $warnings = [];

        // 1. Upload every image to the WP media library (bytes, not URLs —
        //    the WP site cannot reach our local/private storage).
        $images = $this->uploadImages($post, $siteUrl, $username, $password, $warnings);

        // 2. Hashtags → tags, plus the default category.
        $tagIds      = $this->resolveTagIds($post->hashtags ?? [], $siteUrl, $username, $password, $warnings);
        $categoryIds = $this->resolveCategoryIds($siteUrl, $username, $password, $warnings);

        // 3. Body + title.
        $caption         = trim($variant->caption ?: (string) $post->caption);
        $strippedCaption = $this->stripHashtags($caption);

        $payload = [
            'title'   => $this->resolveTitle($post, $strippedCaption),
            'content' => $this->buildHtmlBody($strippedCaption, $images, $post),
            'status'  => 'draft', // locked: always draft, human publishes from wp-admin
        ];

        if ($images !== []) {
            $payload['featured_media'] = $images[0]['id'];
        }
        if ($tagIds !== []) {
            $payload['tags'] = $tagIds;
        }
        if ($categoryIds !== []) {
            $payload['categories'] = $categoryIds;
        }

        // 4. Create the draft.
        $r = Http::withBasicAuth($username, $password)
            ->timeout(30)
            ->post("{$siteUrl}/wp-json/wp/v2/posts", $payload);

        if (! $r->successful() || ! $r->json('id')) {
            $err = $r->json('message') ?? ('HTTP ' . $r->status());
            return $this->failure('WP API error: ' . $err);
        }

        $wpId    = $r->json('id');
        $editUrl = "{$siteUrl}/wp-admin/post.php?post={$wpId}&action=edit";

        return [
            'success'          => true,
            'platform_post_id' => (string) $wpId,
            'external_url'     => $editUrl,
            'error'            => null,
            'note'             => 'created_as_wp_draft',
            'warnings'         => $warnings ?: null,
            'wp'               => [
                'edit_url'     => $editUrl,
                'preview_link' => $r->json('link'),
                'media_ids'    => array_column($images, 'id'),
                'tag_ids'      => $tagIds,
                'category_ids' => $categoryIds,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Upload each image attached to the post. Videos are skipped (v1).
     * Per-image failures are recorded as warnings and do not abort the publish.
     *
     * @return array<int, array{id:int, source_url:string, alt:string}>
     */
    private function uploadImages(MarketingPost $post, string $siteUrl, string $username, string $password, array &$warnings): array
    {
        $uploaded = [];

        foreach ($post->media as $media) {
            $isImage = $media->media_type === 'image'
                || str_starts_with((string) $media->mime_type, 'image/');

            if (! $isImage) {
                continue; // images only in v1
            }

            $relPath = $this->storageRelativePath((string) $media->file_path);

            if ($relPath === '' || ! Storage::disk('public')->exists($relPath)) {
                $warnings[] = "Image not found in storage: {$media->file_path}";
                Log::warning("WordpressPublishService: image missing for post #{$post->id}: {$media->file_path}");
                continue;
            }

            $bytes    = Storage::disk('public')->get($relPath);
            $filename = $media->file_name ?: basename($relPath);
            $mime     = $media->mime_type ?: 'image/jpeg';

            $r = Http::withBasicAuth($username, $password)
                ->withHeaders(['Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"'])
                ->withBody($bytes, $mime)
                ->timeout(60)
                ->post("{$siteUrl}/wp-json/wp/v2/media");

            if (! $r->successful() || ! $r->json('id')) {
                $err        = $r->json('message') ?? ('HTTP ' . $r->status());
                $warnings[] = "Image upload failed ({$filename}): {$err}";
                Log::warning("WordpressPublishService: media upload failed for post #{$post->id} ({$filename}): {$err}");
                continue;
            }

            $uploaded[] = [
                'id'         => (int) $r->json('id'),
                'source_url' => (string) $r->json('source_url'),
                'alt'        => (string) ($media->alt_text ?: $filename),
            ];
        }

        return $uploaded;
    }

    /**
     * PostMedia.file_path may hold a plain disk-relative path, a "/storage/…"
     * public URL path, or a full URL — normalize all of them to the
     * Storage::disk('public') relative path.
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
    // Taxonomy
    // -----------------------------------------------------------------------

    /** @param array<int, string> $hashtags */
    private function resolveTagIds(array $hashtags, string $siteUrl, string $username, string $password, array &$warnings): array
    {
        $ids = [];

        foreach ($hashtags as $hashtag) {
            $name = trim(ltrim(trim((string) $hashtag), '#'));
            if ($name === '') {
                continue;
            }

            $id = $this->resolveTermId('tags', $name, $siteUrl, $username, $password, $warnings);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolveCategoryIds(string $siteUrl, string $username, string $password, array &$warnings): array
    {
        $id = $this->resolveTermId('categories', self::DEFAULT_CATEGORY, $siteUrl, $username, $password, $warnings);

        return $id !== null ? [$id] : [];
    }

    /**
     * Look a term up by exact (case-insensitive) name; create it if missing.
     * Failures are non-fatal — the draft still goes through, just untagged.
     */
    private function resolveTermId(string $taxonomy, string $name, string $siteUrl, string $username, string $password, array &$warnings): ?int
    {
        $search = Http::withBasicAuth($username, $password)
            ->timeout(20)
            ->get("{$siteUrl}/wp-json/wp/v2/{$taxonomy}", ['search' => $name, 'per_page' => 100]);

        if ($search->successful()) {
            foreach ($search->json() ?? [] as $term) {
                $termName = html_entity_decode((string) ($term['name'] ?? ''), ENT_QUOTES);
                if (mb_strtolower($termName) === mb_strtolower($name)) {
                    return (int) $term['id'];
                }
            }
        }

        $create = Http::withBasicAuth($username, $password)
            ->timeout(20)
            ->post("{$siteUrl}/wp-json/wp/v2/{$taxonomy}", ['name' => $name]);

        if ($create->successful() && $create->json('id')) {
            return (int) $create->json('id');
        }

        // WP returns 400 "term_exists" with the existing ID on create races /
        // slug collisions — use it instead of failing.
        if ($create->json('code') === 'term_exists' && $create->json('data.term_id')) {
            return (int) $create->json('data.term_id');
        }

        $err        = $create->json('message') ?? ('HTTP ' . $create->status());
        $warnings[] = ucfirst($taxonomy) . " lookup/create failed for \"{$name}\": {$err}";
        Log::warning("WordpressPublishService: {$taxonomy} resolve failed for \"{$name}\": {$err}");

        return null;
    }

    // -----------------------------------------------------------------------
    // Content
    // -----------------------------------------------------------------------

    /** Remove #hashtag tokens (they become WP tags) and tidy leftover spacing. */
    private function stripHashtags(string $caption): string
    {
        $text = preg_replace('/(?<!\S)#[\p{L}\p{N}_]+/u', '', $caption) ?? $caption;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;

        // Drop lines that held only hashtags and are now blank.
        $lines = array_map('rtrim', preg_split('/\R/u', $text) ?: []);

        return trim(implode("\n", $lines));
    }

    private function resolveTitle(MarketingPost $post, string $strippedCaption): string
    {
        if (filled($post->title)) {
            return $post->title;
        }

        foreach (preg_split('/\R/u', $strippedCaption) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return Str::limit($line, 90, '…');
            }
        }

        return 'Clinic blog post — ' . now()->format('j M Y');
    }

    /**
     * Caption → escaped HTML paragraphs (blank line = new paragraph, single
     * newline = <br>), with the uploaded images interleaved between paragraphs
     * (leftovers appended) and the CTA as a trailing link.
     *
     * @param array<int, array{id:int, source_url:string, alt:string}> $images
     */
    private function buildHtmlBody(string $strippedCaption, array $images, MarketingPost $post): string
    {
        $blocks     = [];
        $imageQueue = $images;

        $paragraphs = preg_split('/\R{2,}/u', $strippedCaption) ?: [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $blocks[] = '<p>' . str_replace("\n", '<br>', e($paragraph)) . '</p>';

            if ($imageQueue !== []) {
                $blocks[] = $this->imageFigure(array_shift($imageQueue));
            }
        }

        foreach ($imageQueue as $image) {
            $blocks[] = $this->imageFigure($image);
        }

        // CTA — label + link as a trailing paragraph.
        $ctaLabel = trim((string) ($post->cta_text ?: $post->cta_type));
        if (filled($post->cta_url)) {
            $ctaLabel = $ctaLabel !== '' ? $ctaLabel : 'Learn more';
            $blocks[] = '<p><strong><a href="' . e($post->cta_url) . '">' . e($ctaLabel) . '</a></strong></p>';
        } elseif ($ctaLabel !== '') {
            $blocks[] = '<p><strong>' . e($ctaLabel) . '</strong></p>';
        }

        return implode("\n", $blocks);
    }

    /** @param array{id:int, source_url:string, alt:string} $image */
    private function imageFigure(array $image): string
    {
        return '<figure class="wp-block-image size-large">'
            . '<img src="' . e($image['source_url']) . '" alt="' . e($image['alt']) . '" loading="lazy" />'
            . '</figure>';
    }

    private function failure(string $error): array
    {
        return [
            'success'          => false,
            'platform_post_id' => null,
            'external_url'     => null,
            'error'            => $error,
        ];
    }
}
