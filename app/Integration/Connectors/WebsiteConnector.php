<?php

namespace App\Integration\Connectors;

use Illuminate\Support\Facades\Http;

/**
 * WebsiteConnector — Phase 7, Slice 3.
 * ----------------------------------------------------------------------------
 * Covers the clinic's own self-hosted website (WordPress today, via
 * ProcessScheduledPost::publishToWordpress()) — named "website" in the
 * blueprint's Phase 7 deliverables. Recon (this phase's read-only pass)
 * found no OUTBOUND call the app makes to a clinic's website other than this
 * WordPress publish; WebsiteLeadController is inbound-only (a webhook
 * receiver) and has nothing to wrap here.
 *
 * `integration.website` was not yet declared in config/features.php — added
 * in this slice since the blueprint's own Phase 7 paragraph names "website"
 * as one of the six systems to wrap; the flag table just hadn't caught up.
 */
class WebsiteConnector
{
    public function providerName(): string
    {
        return 'website';
    }

    /** Publish a post to a WordPress site via its REST API (app-password auth). Normalized ['success','id','error','raw']. */
    public function publishWordpress(string $siteUrl, string $username, string $password, array $payload): array
    {
        $r = Http::withBasicAuth($username, $password)
            ->post("{$siteUrl}/wp-json/wp/v2/posts", $payload);

        if ($r->successful()) {
            return ['success' => true, 'id' => (string) $r->json('id'), 'error' => null, 'raw' => $r->json() ?? []];
        }

        return ['success' => false, 'id' => null, 'error' => $r->json('message') ?? ('HTTP ' . $r->status()), 'raw' => $r->json() ?? []];
    }
}
