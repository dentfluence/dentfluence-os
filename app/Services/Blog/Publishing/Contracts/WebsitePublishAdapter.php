<?php

namespace App\Services\Blog\Publishing\Contracts;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPublication;
use App\Services\Blog\Publishing\PublicationResult;

/**
 * The publishing seam of the Blog Marketing Hub (masterplan §4).
 * ----------------------------------------------------------------------------
 * Blog content lives ABOVE this interface — the editor, SEO panel and renderer
 * never know which kind of website (if any) is connected. A concrete adapter
 * (WordPress / Standalone / — later — Dentfluence-static) is resolved per
 * clinic by WebsitePublishAdapterFactory and is the ONLY code that talks to a
 * website.
 *
 * Every method returns a PublicationResult; the caller (job / service) records
 * it in the blog_publications ledger so status is always honest and retryable.
 * Adapters must NEVER report success for something they did not actually do
 * (the antidote to the social pipeline's silent fake "published").
 */
interface WebsitePublishAdapter
{
    /** The blog_publications.target_type this adapter writes (BlogPublication::TARGET_*). */
    public function targetType(): string;

    /** The mkt_platform_connections id backing this adapter, or null (standalone). */
    public function connectionId(): ?int;

    /** Create the post on the website. */
    public function publish(BlogPost $post): PublicationResult;

    /** Sync edits to an already-published post (using publication.external_id). */
    public function update(BlogPost $post, BlogPublication $publication): PublicationResult;

    /** Remove the post from the website. */
    public function delete(BlogPublication $publication): PublicationResult;

    /** Verify / read the remote state of a publication. */
    public function status(BlogPublication $publication): PublicationResult;
}
