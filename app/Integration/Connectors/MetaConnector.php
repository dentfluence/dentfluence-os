<?php

namespace App\Integration\Connectors;

use App\Integration\Contracts\OAuthConnectorInterface;
use Illuminate\Support\Facades\Http;

/**
 * MetaConnector — Phase 7, Slice 3.
 * ----------------------------------------------------------------------------
 * Same reasoning as GoogleConnector (Slice 2): Meta's OAuth + publishing
 * calls have always lived inline — in OAuthService's private Meta methods,
 * ProcessScheduledPost's publishToInstagram()/publishToFacebook(), and
 * MetaLeadController's fetchLeadFields() — there was no pre-existing
 * vendor-client class to wrap. This is a FRESH, independent implementation,
 * used only once `integration.meta` is on; the inline legacy calls stay as
 * the flag-off fallback through the soak, same strangler-fig reasoning as
 * GoogleConnector's docblock explains.
 *
 * Covers: Meta OAuth (Instagram + Facebook share this flow), Instagram
 * Content Publishing (2-step container/publish), Facebook Page feed posts,
 * and the Meta Lead Ads webhook's leadgen field fetch.
 */
class MetaConnector implements OAuthConnectorInterface
{
    private function graphVersion(): string
    {
        return config('services.meta.graph_version', 'v23.0');
    }

    public function providerName(): string
    {
        return 'meta';
    }

    // ── OAuth (Instagram + Facebook share this flow) ────────────────────────

    public function authUrl(string $platform, int $clinicId): string
    {
        $appId = config('services.meta.app_id');
        if (! $appId) {
            throw new \RuntimeException('meta_not_configured');
        }

        $scopes = $platform === 'instagram'
            ? 'instagram_basic,instagram_content_publish,pages_read_engagement'
            : 'pages_manage_posts,pages_read_engagement,pages_show_list';

        $state = base64_encode(json_encode([
            'platform'  => $platform,
            'clinic_id' => $clinicId,
            'csrf'      => csrf_token(),
        ]));

        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        return 'https://www.facebook.com/' . $this->graphVersion() . '/dialog/oauth?' . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirect,
            'scope'         => $scopes,
            'response_type' => 'code',
            'state'         => $state,
        ]);
    }

    /** Single-use authorization code — the caller must only invoke this on exactly one path. */
    public function exchangeCode(string $platform, string $code, string $redirectUri): array
    {
        $appId  = config('services.meta.app_id');
        $secret = config('services.meta.app_secret');

        return Http::get('https://graph.facebook.com/' . $this->graphVersion() . '/oauth/access_token', [
            'client_id'     => $appId,
            'client_secret' => $secret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ])->throw()->json();
    }

    public function fetchAccountInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get('https://graph.facebook.com/' . $this->graphVersion() . '/me', ['fields' => 'id,name,picture'])
            ->throw()->json();
    }

    public function ping(string $accessToken): bool
    {
        $r = Http::withToken($accessToken)
            ->get('https://graph.facebook.com/' . $this->graphVersion() . '/me', ['fields' => 'id']);

        return $r->successful() && isset($r->json()['id']);
    }

    /** Meta has no revoke endpoint — token expires naturally (matches the legacy comment in OAuthService::revokeToken()). */
    public function revoke(string $accessToken): void
    {
        // Intentionally a no-op.
    }

    // ── Publishing ────────────────────────────────────────────────────────────
    // Each method returns a normalized ['success','id','error','raw'] shape —
    // same convention as WhatsAppCloudService — so ProcessScheduledPost's
    // connector branch can build the same success/error response it already
    // builds for the legacy branch.

    /** Instagram step 1: create a media container. */
    public function createInstagramContainer(string $igUserId, string $token, array $mediaPayload): array
    {
        $r    = Http::withToken($token)->timeout(20)
            ->post('https://graph.facebook.com/' . $this->graphVersion() . "/{$igUserId}/media", $mediaPayload);
        $json = $r->json() ?? [];

        if (! $r->successful() || ! ($json['id'] ?? null)) {
            return ['success' => false, 'id' => null, 'error' => $json['error']['message'] ?? ('HTTP ' . $r->status()), 'raw' => $json];
        }

        return ['success' => true, 'id' => $json['id'], 'error' => null, 'raw' => $json];
    }

    /** Instagram step 2: publish a previously created container. */
    public function publishInstagramContainer(string $igUserId, string $token, string $containerId): array
    {
        $r    = Http::withToken($token)->timeout(20)
            ->post('https://graph.facebook.com/' . $this->graphVersion() . "/{$igUserId}/media_publish", ['creation_id' => $containerId]);
        $json = $r->json() ?? [];

        if (! $r->successful() || ! ($json['id'] ?? null)) {
            return ['success' => false, 'id' => null, 'error' => $json['error']['message'] ?? ('HTTP ' . $r->status()), 'raw' => $json];
        }

        return ['success' => true, 'id' => $json['id'], 'error' => null, 'raw' => $json];
    }

    /** Facebook Page feed post. */
    public function publishFacebookFeed(string $pageId, string $token, array $payload): array
    {
        $r    = Http::withToken($token)->timeout(20)
            ->post('https://graph.facebook.com/' . $this->graphVersion() . "/{$pageId}/feed", $payload);
        $json = $r->json() ?? [];

        if (! $r->successful()) {
            return ['success' => false, 'id' => null, 'error' => $json['error']['message'] ?? ('HTTP ' . $r->status()), 'raw' => $json];
        }

        return ['success' => true, 'id' => $json['id'] ?? null, 'error' => null, 'raw' => $json];
    }

    // ── Lead Ads webhook ──────────────────────────────────────────────────────

    /** Fetch a lead's submitted answers by leadgen_id. Returns raw field_data array, or null on failure. Read-only/idempotent. */
    public function fetchLeadFields(string $leadgenId, string $token, ?string $version = null): ?array
    {
        $version ??= $this->graphVersion();

        $resp = Http::timeout(15)->get("https://graph.facebook.com/{$version}/{$leadgenId}", [
            'access_token' => $token,
            'fields'       => 'field_data',
        ]);

        return $resp->successful() ? $resp->json('field_data', []) : null;
    }
}
