<?php

namespace App\Integration\Connectors;

use App\Integration\Contracts\OAuthConnectorInterface;
use Illuminate\Support\Facades\Http;

/**
 * GoogleConnector — Phase 7, Slice 2.
 * ----------------------------------------------------------------------------
 * Unlike WhatsApp (which already had WhatsAppCloudService as a separate
 * vendor client), Google's OAuth calls have always lived inline inside
 * OAuthService's private methods — there was no pre-existing vendor-client
 * class to wrap. So this connector is a FRESH, independent implementation of
 * the same calls, not a wrapper around one.
 *
 * That means, deliberately, THIS is now the single place Google's HTTP shape
 * lives going forward. OAuthService keeps its own existing inline calls as
 * the "legacy" fallback for the duration of the soak (strangler-fig requires
 * both to coexist so the flag is a real rollback, not a no-op) — see the
 * Google-specific methods in OAuthService for the flag branch. Once
 * `integration.google` has soaked clean, a later cleanup step (not this
 * slice) deletes OAuthService's inline Google code, leaving this class as
 * the sole implementation — the same end-state Phase 8 describes for PRM.
 */
class GoogleConnector implements OAuthConnectorInterface
{
    public function providerName(): string
    {
        return 'google';
    }

    public function authUrl(string $platform, int $clinicId): string
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) {
            throw new \RuntimeException('google_not_configured');
        }

        $scopes = $platform === 'google_business'
            ? 'https://www.googleapis.com/auth/business.manage'
            : 'https://www.googleapis.com/auth/analytics.readonly';

        $state = base64_encode(json_encode([
            'platform'  => $platform,
            'clinic_id' => $clinicId,
            'csrf'      => csrf_token(),
        ]));

        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => $scopes,
            'access_type'   => 'offline', // get refresh_token
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
    }

    /**
     * Exchange the one-time authorization code for tokens. NOTE: this is a
     * real network call with a side effect (the code is single-use) — never
     * call this AND the legacy path for the same incoming code. The caller
     * (OAuthService::handleGoogleCallback) picks exactly one, by flag.
     */
    public function exchangeCode(string $platform, string $code, string $redirectUri): array
    {
        $clientId = config('services.google.client_id');
        $secret   = config('services.google.client_secret');

        return Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $secret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ])->throw()->json();
    }

    public function fetchAccountInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo')
            ->throw()->json();
    }

    public function ping(string $accessToken): bool
    {
        $r = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');

        return $r->successful() && isset($r->json()['id']);
    }

    public function revoke(string $accessToken): void
    {
        Http::post('https://oauth2.googleapis.com/revoke', ['token' => $accessToken]);
    }

    // ── Publishing (Slice 3 — Google Business Profile localPosts) ───────────
    // Discovered while wrapping Meta's ProcessScheduledPost calls in the same
    // job: Google Business publish lives there too, alongside Instagram/
    // Facebook/WordPress. It's a Google vendor call, so it belongs on this
    // connector, not MetaConnector — same `integration.google` flag as the
    // OAuth methods above.

    /** Create a Google Business Profile localPost. Normalized ['success','id','error','raw']. */
    public function publishBusinessPost(string $locationName, string $accessToken, array $payload): array
    {
        $r = Http::withToken($accessToken)->timeout(30)
            ->post("https://mybusiness.googleapis.com/v4/{$locationName}/localPosts", $payload);

        if (! $r->successful()) {
            return ['success' => false, 'id' => null, 'error' => $r->json('error.message') ?? ('HTTP ' . $r->status()), 'raw' => $r->json() ?? []];
        }

        // GBP returns the resource name (not "id") as the post identifier.
        return ['success' => true, 'id' => $r->json('name'), 'error' => null, 'raw' => $r->json() ?? []];
    }
}
