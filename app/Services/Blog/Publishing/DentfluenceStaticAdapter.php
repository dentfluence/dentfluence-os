<?php

namespace App\Services\Blog\Publishing;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogPublication;
use App\Services\Blog\Publishing\Contracts\WebsitePublishAdapter;

/**
 * STUB — the Dentfluence-hosted "seamless publishing" tier (masterplan §6,
 * Wave 4). The hand-coded static clinic sites have no CMS/API yet, so the
 * Growth-Division static-publishing backend this adapter would call does not
 * exist. It is present only so the resolver/factory is complete; every method
 * fails honestly with a "not implemented" result — it NEVER fakes success.
 */
class DentfluenceStaticAdapter implements WebsitePublishAdapter
{
    private const NOT_READY = 'Dentfluence-hosted publishing is not available yet (Wave 4) — nothing was sent.';

    public function targetType(): string
    {
        return BlogPublication::TARGET_DENTFLUENCE_STATIC;
    }

    public function connectionId(): ?int
    {
        return null;
    }

    public function publish(BlogPost $post): PublicationResult
    {
        return PublicationResult::fail(self::NOT_READY);
    }

    public function update(BlogPost $post, BlogPublication $publication): PublicationResult
    {
        return PublicationResult::fail(self::NOT_READY);
    }

    public function delete(BlogPublication $publication): PublicationResult
    {
        return PublicationResult::fail(self::NOT_READY);
    }

    public function status(BlogPublication $publication): PublicationResult
    {
        return PublicationResult::fail(self::NOT_READY);
    }
}
