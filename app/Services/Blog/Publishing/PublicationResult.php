<?php

namespace App\Services\Blog\Publishing;

/**
 * Immutable value object returned by every WebsitePublishAdapter call.
 * ----------------------------------------------------------------------------
 * Deliberately tiny and transport-agnostic: it carries only what the ledger
 * (blog_publications) needs — did it work, the remote id/url, and an honest
 * error string on failure. Adapters NEVER write the ledger themselves; they
 * return one of these and let ProcessBlogPublication / BlogPublishingService
 * persist the outcome. That keeps adapters pure ("talk to a website, report
 * back") and makes the no-website / failure paths impossible to fake.
 */
class PublicationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $error = null,
        /** Optional non-fatal context (e.g. remote status on a status() call). */
        public readonly ?string $note = null,
    ) {
    }

    public static function ok(?string $externalId = null, ?string $externalUrl = null, ?string $note = null): self
    {
        return new self(true, $externalId, $externalUrl, null, $note);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, null, $error);
    }
}
