<?php

namespace App\Services\Blog\Publishing;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPublication;
use App\Services\Blog\Publishing\Contracts\WebsitePublishAdapter;

/**
 * The tier-3 (no live website) publishing experience.
 * ----------------------------------------------------------------------------
 * There is no external site to call: the content already lives in Dentfluence.
 * "Publishing" here simply records a standalone ledger row marked published so
 * the Hub is fully usable with no website connected (manual export comes
 * later). This is the adapter every clinic without a connected WordPress site
 * resolves to — the safe, honest default (no faked remote success).
 *
 * external_url is null in Wave 1 — there is no public in-app blog view yet
 * (dentfluence_static hosting is Wave 4). When one exists, return its URL here.
 */
class StandaloneAdapter implements WebsitePublishAdapter
{
    public function targetType(): string
    {
        return BlogPublication::TARGET_STANDALONE;
    }

    public function connectionId(): ?int
    {
        return null; // no external connection
    }

    public function publish(BlogPost $post): PublicationResult
    {
        // No external id — the post's own uuid is its canonical Dentfluence
        // reference; content stays in-app until a real site is connected.
        return PublicationResult::ok(null, null, 'stored_in_dentfluence');
    }

    public function update(BlogPost $post, BlogPublication $publication): PublicationResult
    {
        return PublicationResult::ok(null, $publication->external_url, 'stored_in_dentfluence');
    }

    public function delete(BlogPublication $publication): PublicationResult
    {
        // Nothing external to remove; the ledger row is flagged deleted by the
        // caller so the post no longer shows as "published (standalone)".
        return PublicationResult::ok(null, null, 'removed_from_dentfluence');
    }

    public function status(BlogPublication $publication): PublicationResult
    {
        return PublicationResult::ok(null, $publication->external_url, 'standalone');
    }
}
