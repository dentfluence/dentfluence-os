<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Http\Requests\Blog\AutosaveBlogPostRequest;
use App\Http\Requests\Blog\StoreBlogPostRequest;
use App\Http\Requests\Blog\UpdateBlogPostRequest;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPostVersion;
use App\Models\Blog\BlogPublication;
use App\Models\Blog\BlogTag;
use App\Models\Marketing\MarketingAsset;
use App\Models\Marketing\PlatformConnection;
use App\Services\Blog\BlogBlockSchema;
use App\Services\Blog\BlogPostService;
use App\Services\Blog\Publishing\BlogPublishingService;
use App\Support\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Blog Marketing Hub — CRUD + save/autosave/versioning backend (Wave 1 Slice 2).
 * ----------------------------------------------------------------------------
 * Thin HTTP layer over BlogPostService, which owns the create/update/slug/
 * version/restore logic. Every action gates on the `blog.hub` feature flag
 * (default OFF) and is scoped to the caller's clinic via ResolvesClinicId.
 *
 * All route-model binding resolves posts by their permanent `uuid`
 * (BlogPost::getRouteKeyName) — never the slug or the sequential id.
 *
 * index() renders the real CMS list (Wave 1 Slice 5: status filter/search/
 * pagination/duplicate/archive/delete/version-restore) for browsers and JSON
 * for API callers — thin: the actual query logic lives in BlogPostService
 * (listForClinic/statusCounts). Scheduled/published posts surface on the
 * single Marketing calendar (CalendarController), not a blog-only calendar.
 */
class BlogController extends Controller
{
    use ResolvesClinicId;

    public function __construct(
        private BlogPostService $posts,
        private BlogPublishingService $publishing,
    ) {
    }

    // =======================================================================
    // Read
    // =======================================================================

    /**
     * Clinic-scoped list with status filter + title/slug search + pagination.
     * "All" (no/invalid status param) hides archived posts by default — see
     * BlogPostService::listForClinic().
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->guard();

        $clinicId = $this->currentClinicId();
        $status   = $request->query('status');
        $search   = (string) $request->query('q', '');

        $posts = $this->posts->listForClinic($clinicId, $status, $search);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'posts' => $posts]);
        }

        return view('marketing.blog.index', [
            'posts'        => $posts,
            'statusCounts' => $this->posts->statusCounts($clinicId),
            'activeStatus' => in_array($status, BlogPost::STATUSES, true) ? $status : 'all',
            'search'       => $search,
        ]);
    }

    /**
     * Single post with its SEO, tags, category and version list.
     */
    public function show(Request $request, BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $blog->load(['seo', 'tags', 'category', 'featuredAsset', 'versions']);

        return response()->json(['success' => true, 'post' => $blog]);
    }

    // =======================================================================
    // Editor pages (Slice 3)
    // =======================================================================

    /**
     * Render the block editor for a brand-new post. No row is created yet — the
     * post is "materialised" on the first save/autosave (POST blog.store), after
     * which the client swaps to the uuid-bound update/autosave endpoints.
     */
    public function create(): View
    {
        $this->guard();

        return view('marketing.blog.editor', array_merge(
            ['post' => null],
            $this->editorViewData()
        ));
    }

    /**
     * Render the block editor for an existing post, hydrated from its
     * canonical body_json + meta.
     */
    public function edit(BlogPost $blog): View
    {
        $this->guard();
        $this->scope($blog);

        $blog->load(['seo', 'tags', 'category', 'featuredAsset', 'publications']);

        return view('marketing.blog.editor', array_merge(
            ['post' => $this->editorPostPayload($blog)],
            $this->editorViewData()
        ));
    }

    /**
     * DAM image list for the featured/inline image picker (clinic-scoped,
     * images only). Reuses the Marketing DAM — no new media store.
     */
    public function assetsIndex(): JsonResponse
    {
        $this->guard();

        return response()->json([
            'success' => true,
            'assets'  => $this->clinicImageAssets(),
        ]);
    }

    /**
     * Create-or-find a category by name (inline "create category" in the meta
     * panel). Idempotent per clinic on the derived slug.
     */
    public function categoriesStore(Request $request): JsonResponse
    {
        $this->guard();

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $slug = Str::slug($data['name']) ?: 'category';

        $category = BlogCategory::firstOrCreate(
            ['clinic_id' => $this->currentClinicId(), 'slug' => $slug],
            ['name' => $data['name']]
        );

        return response()->json([
            'success'  => true,
            'category' => ['id' => $category->id, 'name' => $category->name],
        ], 201);
    }

    /**
     * Create-or-find a tag by name (inline "add tag" in the meta panel).
     */
    public function tagsStore(Request $request): JsonResponse
    {
        $this->guard();

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $slug = Str::slug($data['name']) ?: 'tag';

        $tag = BlogTag::firstOrCreate(
            ['clinic_id' => $this->currentClinicId(), 'slug' => $slug],
            ['name' => $data['name']]
        );

        return response()->json([
            'success' => true,
            'tag'     => ['id' => $tag->id, 'name' => $tag->name],
        ], 201);
    }

    // =======================================================================
    // Write
    // =======================================================================

    /**
     * Create a new draft.
     */
    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $this->guard();

        $post = $this->posts->createDraft(
            $request->validated(),
            $this->currentClinicId(),
            auth()->id()
        );

        return response()->json(['success' => true, 'post' => $post], 201);
    }

    /**
     * Full manual save (permanent 'manual' snapshot). May change status,
     * including a first publish (which locks the slug).
     */
    public function update(UpdateBlogPostRequest $request, BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $post = $this->posts->updatePost(
            $blog,
            $request->validated(),
            auth()->id(),
            BlogPostVersion::LABEL_MANUAL
        );

        return response()->json(['success' => true, 'post' => $post]);
    }

    /**
     * Quick "Save draft" — a deliberate user save (permanent 'manual'
     * snapshot) that never publishes: any status change is dropped so the
     * button can't accidentally take a post live.
     */
    public function draft(UpdateBlogPostRequest $request, BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $data = $request->validated();
        unset($data['status']); // draft-save never changes publish state

        $post = $this->posts->updatePost(
            $blog,
            $data,
            auth()->id(),
            BlogPostVersion::LABEL_MANUAL
        );

        return response()->json(['success' => true, 'post' => $post]);
    }

    /**
     * Autosave the working content (writes an 'autosave' version, prunes to 20).
     */
    public function autosave(AutosaveBlogPostRequest $request, BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $data = $request->validated();
        unset($data['status']); // autosave never changes publish state

        $post = $this->posts->autosave($blog, $data, auth()->id());

        return response()->json([
            'success'    => true,
            'saved_at'   => now()->toIso8601String(),
            'reading_time' => $post->reading_time,
        ]);
    }

    /**
     * Duplicate into a fresh draft (new uuid + "-copy" slug; no publications).
     */
    public function duplicate(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $copy = $this->posts->duplicate($blog, auth()->id());

        return response()->json(['success' => true, 'post' => $copy], 201);
    }

    /**
     * Archive (status=archived). Reversible via unarchive.
     */
    public function archive(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $blog->update(['status' => 'archived', 'updated_by' => auth()->id()]);

        return response()->json(['success' => true, 'post' => $blog]);
    }

    /**
     * Unarchive back to draft (a previously-published post keeps its slug lock).
     */
    public function unarchive(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $blog->update(['status' => 'draft', 'updated_by' => auth()->id()]);

        return response()->json(['success' => true, 'post' => $blog]);
    }

    /**
     * Soft delete.
     */
    public function destroy(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $blog->delete();

        return response()->json(['success' => true]);
    }

    // =======================================================================
    // Versions
    // =======================================================================

    /**
     * List a post's version history (newest first — the relation is ordered).
     */
    public function versionsIndex(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $versions = $blog->versions()
            ->with('editor:id,name')
            ->get(['id', 'blog_post_id', 'label', 'editor_id', 'created_at']);

        return response()->json(['success' => true, 'versions' => $versions]);
    }

    /**
     * Restore a snapshot as the current working content (itself undoable).
     */
    public function versionsRestore(BlogPost $blog, BlogPostVersion $version): JsonResponse
    {
        $this->guard();
        $this->scope($blog);
        $this->scopeVersion($blog, $version);

        $post = $this->posts->restore($blog, $version, auth()->id());

        return response()->json(['success' => true, 'post' => $post]);
    }

    /**
     * Explicitly delete a single version (the only way a permanent
     * manual/scheduled/publish snapshot is ever removed).
     */
    public function versionsDestroy(BlogPost $blog, BlogPostVersion $version): JsonResponse
    {
        $this->guard();
        $this->scope($blog);
        $this->scopeVersion($blog, $version);

        $version->delete();

        return response()->json(['success' => true]);
    }

    // =======================================================================
    // Website publishing (Slice 6)
    // =======================================================================

    /**
     * The post's per-target publish ledger (drives the editor's status panel).
     */
    public function publicationsIndex(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        return response()->json([
            'success'      => true,
            'publications' => $this->publicationsPayload($blog),
        ]);
    }

    /**
     * Push the post to its resolved website target (create or sync). The
     * editorial status (published/scheduled) is set by the preceding save; this
     * only handles the website side. No website connected → the service records
     * a standalone publication, never a faked remote success.
     */
    public function publish(BlogPost $blog): JsonResponse
    {
        $this->guard();
        $this->scope($blog);

        $pub = $this->publishing->publish($blog, auth()->id());

        return response()->json([
            'success'      => true,
            'publication'  => $this->onePublicationPayload($pub),
            'publications' => $this->publicationsPayload($blog->fresh()),
        ]);
    }

    /**
     * Re-attempt a failed publication.
     */
    public function publicationRetry(BlogPost $blog, BlogPublication $publication): JsonResponse
    {
        $this->guard();
        $this->scope($blog);
        $this->scopePublication($blog, $publication);

        $pub = $this->publishing->retry($publication, auth()->id());

        return response()->json([
            'success'     => true,
            'publication' => $this->onePublicationPayload($pub),
        ]);
    }

    /**
     * Delete the post from its website (adapter delete → ledger status=deleted).
     */
    public function publicationDestroy(BlogPost $blog, BlogPublication $publication): JsonResponse
    {
        $this->guard();
        $this->scope($blog);
        $this->scopePublication($blog, $publication);

        $result = $this->publishing->deleteFromSite($publication, auth()->id());

        return response()->json([
            'success'     => $result->success,
            'error'       => $result->error,
            'publication' => $this->onePublicationPayload($publication->fresh()),
        ]);
    }

    // =======================================================================
    // Editor data assembly
    // =======================================================================

    /**
     * Shared bootstrap data every editor page needs: taxonomy, the DAM image
     * list, the block-schema type list and the valid statuses. Kept server-side
     * so the client never has to discover any of it.
     */
    private function editorViewData(): array
    {
        $clinicId = $this->currentClinicId();

        return [
            'categories' => BlogCategory::forClinic($clinicId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->all(),
            'tags' => BlogTag::forClinic($clinicId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
                ->all(),
            'assets'     => $this->clinicImageAssets(),
            'blockTypes' => BlogBlockSchema::TYPES,
            'statuses'   => BlogPost::STATUSES,
            // Which website the publish action will target, so the editor can
            // label it honestly ("Publish to WordPress" vs "no site connected").
            'publishTarget' => $this->clinicPublishTarget($clinicId),
        ];
    }

    /**
     * The website target a fresh publish resolves to for this clinic:
     * 'wordpress' when a connected connection exists, else 'standalone'.
     */
    private function clinicPublishTarget(int $clinicId): string
    {
        $hasWordpress = PlatformConnection::query()
            ->forClinic($clinicId)
            ->where('platform', 'wordpress')
            ->connected()
            ->exists();

        return $hasWordpress ? BlogPublication::TARGET_WORDPRESS : BlogPublication::TARGET_STANDALONE;
    }

    /** Flatten a post's publication ledger for the editor status panel. */
    private function publicationsPayload(BlogPost $blog): array
    {
        return $blog->publications()
            ->latest('updated_at')
            ->get()
            ->map(fn (BlogPublication $p) => $this->onePublicationPayload($p))
            ->all();
    }

    private function onePublicationPayload(BlogPublication $pub): array
    {
        return [
            'id'             => $pub->id,
            'target_type'    => $pub->target_type,
            'status'         => $pub->status,
            'external_id'    => $pub->external_id,
            'external_url'   => $pub->external_url,
            'error'          => $pub->error,
            'retry_count'    => $pub->retry_count,
            'last_synced_at' => optional($pub->last_synced_at)->toIso8601String(),
        ];
    }

    /**
     * Flatten a post to the exact shape the block editor hydrates from. Sends
     * body_json (source of truth), meta and the slug-lock flag; never body_html
     * (a render cache the client must not treat as editable).
     */
    private function editorPostPayload(BlogPost $blog): array
    {
        $seo = $blog->relationLoaded('seo') ? $blog->seo : $blog->seo()->first();

        return [
            'uuid'              => $blog->uuid,
            'title'             => $blog->title,
            'slug'              => $blog->slug,
            'excerpt'           => $blog->excerpt,
            'status'            => $blog->status,
            'body_json'         => $blog->body_json ?: ['version' => BlogBlockSchema::VERSION, 'blocks' => []],
            'category_id'       => $blog->category_id,
            'featured_asset_id' => $blog->featured_asset_id,
            'tag_ids'           => $blog->tags->pluck('id')->all(),
            'reading_time'      => $blog->reading_time,
            'slug_locked'       => $blog->isSlugLocked(),
            'updated_at'        => optional($blog->updated_at)->toIso8601String(),
            'publications'      => $this->publicationsPayload($blog),
            'seo'               => $seo ? $seo->only([
                'focus_keyword', 'secondary_keywords', 'meta_title', 'meta_description',
                'canonical_url', 'og_title', 'og_description', 'og_image_asset_id', 'noindex',
            ]) : null,
        ];
    }

    /**
     * Clinic-scoped image assets from the Marketing DAM, resolved to public
     * URLs (same public disk the WordPress publish service reads).
     *
     * @return array<int, array{id:int,name:string,url:string,alt:?string}>
     */
    private function clinicImageAssets(): array
    {
        return MarketingAsset::forClinic($this->currentClinicId())
            ->where('asset_type', 'image')
            ->latest('id')
            ->limit(300)
            ->get(['id', 'name', 'file_path', 'alt_text'])
            ->map(fn ($a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'url'  => Storage::disk('public')->url($a->file_path),
                'alt'  => $a->alt_text,
            ])
            ->all();
    }

    // =======================================================================
    // Guards
    // =======================================================================

    /** 404 unless the Blog Hub feature flag is on. */
    private function guard(): void
    {
        abort_unless(Feature::enabled('blog.hub'), 404);
    }

    /** 404 if the bound post is not in the caller's clinic. */
    private function scope(BlogPost $blog): void
    {
        abort_unless($blog->clinic_id === $this->currentClinicId(), 404);
    }

    /** 404 if the version does not belong to the given post. */
    private function scopeVersion(BlogPost $blog, BlogPostVersion $version): void
    {
        abort_unless($version->blog_post_id === $blog->id, 404);
    }

    /** 404 if the publication does not belong to the given post. */
    private function scopePublication(BlogPost $blog, BlogPublication $publication): void
    {
        abort_unless($publication->blog_post_id === $blog->id, 404);
    }
}
