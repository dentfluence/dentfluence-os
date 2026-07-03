<?php

namespace App\Services\Marketing;

use App\Integration\IntegrationEngine;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\PlatformConnection;
use App\Support\Features\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OAuthService
 *
 * Handles OAuth2 flows for marketing platform connections.
 * Each platform has its own connect() and handleCallback() method.
 *
 * Credentials come from .env:
 *   META_APP_ID, META_APP_SECRET
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
 *   WHATSAPP_ACCESS_TOKEN (static — no OAuth needed)
 *   WORDPRESS_SITE_URL, WORDPRESS_APP_PASSWORD (static — no OAuth)
 *
 * Phase 5 builds the real OAuth layer.
 * Phase 6 will add real API calls for posting content.
 */
class OAuthService
{
    // -----------------------------------------------------------------------
    // Platform registry — all supported platforms
    // -----------------------------------------------------------------------

    public const PLATFORMS = [
        'instagram'        => 'Instagram',
        'facebook'         => 'Facebook',
        'google_business'  => 'Google Business',
        'whatsapp'         => 'WhatsApp Business',
        'wordpress'        => 'WordPress',
        'google_analytics' => 'Google Analytics',
    ];

    // Platforms that use Meta's shared OAuth flow
    private const META_PLATFORMS = ['instagram', 'facebook'];

    // Platforms that use Google's OAuth flow
    private const GOOGLE_PLATFORMS = ['google_business', 'google_analytics'];

    // -----------------------------------------------------------------------
    // Connect — returns the redirect URL for starting OAuth
    // -----------------------------------------------------------------------

    /**
     * Returns the OAuth authorization URL for the given platform.
     * Throws \RuntimeException if credentials are not configured.
     */
    public function getConnectUrl(string $platform, int $clinicId): string
    {
        return match (true) {
            in_array($platform, self::META_PLATFORMS)    => $this->metaAuthUrl($platform, $clinicId),
            in_array($platform, self::GOOGLE_PLATFORMS)  => $this->googleAuthUrl($platform, $clinicId),
            $platform === 'whatsapp'                      => throw new \RuntimeException('whatsapp_static'),
            $platform === 'wordpress'                     => throw new \RuntimeException('wordpress_static'),
            default                                       => throw new \RuntimeException("Unknown platform: {$platform}"),
        };
    }

    // -----------------------------------------------------------------------
    // Callback — handle the OAuth return from the platform
    // -----------------------------------------------------------------------

    /**
     * Handle the OAuth callback. Returns the PlatformConnection on success.
     * Throws on any error.
     */
    public function handleCallback(string $platform, Request $request, int $clinicId): PlatformConnection
    {
        return match (true) {
            in_array($platform, self::META_PLATFORMS)   => $this->handleMetaCallback($platform, $request, $clinicId),
            in_array($platform, self::GOOGLE_PLATFORMS) => $this->handleGoogleCallback($platform, $request, $clinicId),
            default                                     => throw new \RuntimeException("Callback not supported for: {$platform}"),
        };
    }

    // -----------------------------------------------------------------------
    // Disconnect
    // -----------------------------------------------------------------------

    public function disconnect(string $platform, int $clinicId, int $userId): void
    {
        $conn = PlatformConnection::where('clinic_id', $clinicId)
            ->where('platform', $platform)
            ->first();

        if (! $conn) return;

        // Optionally revoke token at the platform side (best-effort, don't fail)
        try {
            $this->revokeToken($platform, $conn);
        } catch (\Throwable $e) {
            Log::warning("OAuthService: revoke failed for {$platform}: " . $e->getMessage());
        }

        MarketingActivityLog::log(
            $clinicId,
            'integration_disconnected',
            $conn,
            self::PLATFORMS[$platform] . " disconnected",
            ['platform' => $platform],
            $userId
        );

        $conn->delete();
    }

    // -----------------------------------------------------------------------
    // Health check — re-verify a connection is still valid
    // -----------------------------------------------------------------------

    public function checkHealth(string $platform, int $clinicId): array
    {
        $conn = PlatformConnection::where('clinic_id', $clinicId)
            ->where('platform', $platform)
            ->first();

        if (! $conn) {
            return ['status' => 'not_connected'];
        }

        // Check token expiry
        if ($conn->isTokenExpired()) {
            $conn->update(['status' => 'expired', 'last_checked_at' => now()]);
            return ['status' => 'expired', 'message' => 'Token has expired. Please reconnect.'];
        }

        // Platform-specific live ping
        try {
            $ok = match (true) {
                in_array($platform, self::META_PLATFORMS)   => $this->pingMeta($conn),
                in_array($platform, self::GOOGLE_PLATFORMS) => $this->pingGoogle($conn),
                default                                     => true,
            };

            $status = $ok ? 'connected' : 'error';
            $conn->update(['status' => $status, 'last_checked_at' => now()]);
            return ['status' => $status];

        } catch (\Throwable $e) {
            $conn->update([
                'status'         => 'error',
                'error_message'  => $e->getMessage(),
                'last_checked_at'=> now(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // META OAuth (Instagram + Facebook share the same flow)
    // -----------------------------------------------------------------------

    private function metaAuthUrl(string $platform, int $clinicId): string
    {
        $appId = config('services.meta.app_id');
        if (! $appId) throw new \RuntimeException('meta_not_configured');

        $scopes = $platform === 'instagram'
            ? 'instagram_basic,instagram_content_publish,pages_read_engagement'
            : 'pages_manage_posts,pages_read_engagement,pages_show_list';

        $state = base64_encode(json_encode([
            'platform'  => $platform,
            'clinic_id' => $clinicId,
            'csrf'      => csrf_token(),
        ]));

        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        $legacyUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirect,
            'scope'         => $scopes,
            'response_type' => 'code',
            'state'         => $state,
        ]);

        // Phase 7: same side-effect-free dual-compute as googleAuthUrl() above.
        $viaConnector = Feature::enabled('integration.meta');
        $connectorUrl = app(IntegrationEngine::class)->meta()->authUrl($platform, $clinicId);

        app(IntegrationEngine::class)->logMetaAuthUrl($legacyUrl, $connectorUrl, $viaConnector);

        return $viaConnector ? $connectorUrl : $legacyUrl;
    }

    private function handleMetaCallback(string $platform, Request $request, int $clinicId): PlatformConnection
    {
        $code     = $request->input('code');
        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        // Phase 7: same single-path flag branch as handleGoogleCallback() above.
        $viaConnector = Feature::enabled('integration.meta');

        try {
            if ($viaConnector) {
                $tokenResponse = app(IntegrationEngine::class)->meta()->exchangeCode($platform, $code, $redirect);
                $meResponse    = app(IntegrationEngine::class)->meta()->fetchAccountInfo($tokenResponse['access_token']);
            } else {
                $appId  = config('services.meta.app_id');
                $secret = config('services.meta.app_secret');

                // Exchange code for access token
                $tokenResponse = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                    'client_id'     => $appId,
                    'client_secret' => $secret,
                    'redirect_uri'  => $redirect,
                    'code'          => $code,
                ])->throw()->json();

                // Get user/page info
                $meResponse = Http::withToken($tokenResponse['access_token'])
                    ->get('https://graph.facebook.com/v19.0/me', ['fields' => 'id,name,picture'])
                    ->throw()->json();
            }
        } catch (\Throwable $e) {
            app(IntegrationEngine::class)->logMetaExchange($viaConnector, false, $e->getMessage());
            throw $e;
        }

        app(IntegrationEngine::class)->logMetaExchange($viaConnector, true);

        $accessToken = $tokenResponse['access_token'];
        $expiresIn   = $tokenResponse['expires_in'] ?? null; // seconds

        return PlatformConnection::updateOrCreate(
            ['clinic_id' => $clinicId, 'platform' => $platform],
            [
                'access_token'          => $accessToken,
                'token_expires_at'      => $expiresIn ? now()->addSeconds($expiresIn) : null,
                'scopes'                => $platform === 'instagram'
                    ? 'instagram_basic,instagram_content_publish'
                    : 'pages_manage_posts,pages_read_engagement',
                'external_account_id'   => $meResponse['id']   ?? null,
                'external_account_name' => $meResponse['name'] ?? null,
                'external_account_avatar' => data_get($meResponse, 'picture.data.url'),
                'status'                => 'connected',
                'error_message'         => null,
                'last_checked_at'       => now(),
                'connected_by'          => auth()->id(),
                'created_by'            => auth()->id(),
                'updated_by'            => auth()->id(),
            ]
        );
    }

    private function pingMeta(PlatformConnection $conn): bool
    {
        // Phase 7: same single-path flag branch as pingGoogle() above.
        $viaConnector = Feature::enabled('integration.meta');

        if ($viaConnector) {
            $ok = app(IntegrationEngine::class)->meta()->ping($conn->access_token);
        } else {
            $r  = Http::withToken($conn->access_token)
                ->get('https://graph.facebook.com/v19.0/me', ['fields' => 'id']);
            $ok = $r->successful() && isset($r->json()['id']);
        }

        app(IntegrationEngine::class)->logMetaPing($viaConnector, $ok);

        return $ok;
    }

    // -----------------------------------------------------------------------
    // GOOGLE OAuth (Google Business Profile + Google Analytics share flow)
    // -----------------------------------------------------------------------

    private function googleAuthUrl(string $platform, int $clinicId): string
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) throw new \RuntimeException('google_not_configured');

        $scopes = $platform === 'google_business'
            ? 'https://www.googleapis.com/auth/business.manage'
            : 'https://www.googleapis.com/auth/analytics.readonly';

        $state = base64_encode(json_encode([
            'platform'  => $platform,
            'clinic_id' => $clinicId,
            'csrf'      => csrf_token(),
        ]));

        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        $legacyUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'             => $clientId,
            'redirect_uri'          => $redirect,
            'response_type'         => 'code',
            'scope'                 => $scopes,
            'access_type'           => 'offline',  // get refresh_token
            'prompt'                => 'consent',
            'state'                 => $state,
        ]);

        // Phase 7 (Integration boundary): authUrl-building has no side effects
        // on either path, so we can genuinely compute both and diff them for
        // real evidence — unlike the network calls below, which must run on
        // exactly one path. Default OFF (integration.google) returns
        // $legacyUrl unchanged, so behaviour is identical to before this slice.
        $viaConnector = Feature::enabled('integration.google');
        $connectorUrl = app(IntegrationEngine::class)->google()->authUrl($platform, $clinicId);

        app(IntegrationEngine::class)->logGoogleAuthUrl($legacyUrl, $connectorUrl, $viaConnector);

        return $viaConnector ? $connectorUrl : $legacyUrl;
    }

    private function handleGoogleCallback(string $platform, Request $request, int $clinicId): PlatformConnection
    {
        $code     = $request->input('code');
        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        // Phase 7 (Integration boundary): the authorization `code` is
        // single-use, so EXACTLY ONE of these two branches may call Google —
        // never both for the same code. Default OFF (integration.google)
        // takes the legacy branch, unchanged from before this slice.
        $viaConnector = Feature::enabled('integration.google');
        $exchangeError = null;

        try {
            if ($viaConnector) {
                $tokenResponse = app(IntegrationEngine::class)->google()->exchangeCode($platform, $code, $redirect);
                $userInfo      = app(IntegrationEngine::class)->google()->fetchAccountInfo($tokenResponse['access_token']);
            } else {
                $clientId = config('services.google.client_id');
                $secret   = config('services.google.client_secret');

                // Exchange code for tokens
                $tokenResponse = Http::post('https://oauth2.googleapis.com/token', [
                    'code'          => $code,
                    'client_id'     => $clientId,
                    'client_secret' => $secret,
                    'redirect_uri'  => $redirect,
                    'grant_type'    => 'authorization_code',
                ])->throw()->json();

                // Get user info
                $userInfo = Http::withToken($tokenResponse['access_token'])
                    ->get('https://www.googleapis.com/oauth2/v2/userinfo')
                    ->throw()->json();
            }
        } catch (\Throwable $e) {
            $exchangeError = $e->getMessage();
            app(IntegrationEngine::class)->logGoogleExchange($viaConnector, false, $exchangeError);
            throw $e;
        }

        app(IntegrationEngine::class)->logGoogleExchange($viaConnector, true);

        $accessToken  = $tokenResponse['access_token'];
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresIn    = $tokenResponse['expires_in'] ?? 3600;

        return PlatformConnection::updateOrCreate(
            ['clinic_id' => $clinicId, 'platform' => $platform],
            [
                'access_token'          => $accessToken,
                'refresh_token'         => $refreshToken,
                'token_expires_at'      => now()->addSeconds($expiresIn),
                'scopes'                => $platform === 'google_business'
                    ? 'business.manage'
                    : 'analytics.readonly',
                'external_account_id'   => $userInfo['id']      ?? null,
                'external_account_name' => $userInfo['name']    ?? null,
                'external_account_avatar' => $userInfo['picture'] ?? null,
                'status'                => 'connected',
                'error_message'         => null,
                'last_checked_at'       => now(),
                'connected_by'          => auth()->id(),
                'created_by'            => auth()->id(),
                'updated_by'            => auth()->id(),
            ]
        );
    }

    private function pingGoogle(PlatformConnection $conn): bool
    {
        // Phase 7: same single-path flag branch as handleGoogleCallback() above.
        $viaConnector = Feature::enabled('integration.google');

        if ($viaConnector) {
            $ok = app(IntegrationEngine::class)->google()->ping($conn->access_token);
        } else {
            $r  = Http::withToken($conn->access_token)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');
            $ok = $r->successful() && isset($r->json()['id']);
        }

        app(IntegrationEngine::class)->logGooglePing($viaConnector, $ok);

        return $ok;
    }

    // -----------------------------------------------------------------------
    // Token revocation (best-effort)
    // -----------------------------------------------------------------------

    private function revokeToken(string $platform, PlatformConnection $conn): void
    {
        $token = $conn->access_token;
        if (! $token) return;

        if (in_array($platform, self::GOOGLE_PLATFORMS)) {
            // Phase 7: same single-path flag branch — revoke has a real side
            // effect (invalidates the token), so only one path ever calls it.
            $viaConnector = Feature::enabled('integration.google');

            if ($viaConnector) {
                app(IntegrationEngine::class)->google()->revoke($token);
            } else {
                Http::post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
            }

            app(IntegrationEngine::class)->logGoogleRevoke($viaConnector);
        }
        // Meta doesn't have a revoke endpoint — token expires naturally
    }
}
