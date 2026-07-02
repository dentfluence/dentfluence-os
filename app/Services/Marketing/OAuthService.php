<?php

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\PlatformConnection;
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

        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirect,
            'scope'         => $scopes,
            'response_type' => 'code',
            'state'         => $state,
        ]);
    }

    private function handleMetaCallback(string $platform, Request $request, int $clinicId): PlatformConnection
    {
        $code    = $request->input('code');
        $appId   = config('services.meta.app_id');
        $secret  = config('services.meta.app_secret');
        $redirect = route('marketing.integrations.callback', ['platform' => $platform]);

        // Exchange code for access token
        $tokenResponse = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id'     => $appId,
            'client_secret' => $secret,
            'redirect_uri'  => $redirect,
            'code'          => $code,
        ])->throw()->json();

        $accessToken = $tokenResponse['access_token'];
        $expiresIn   = $tokenResponse['expires_in'] ?? null; // seconds

        // Get user/page info
        $meResponse = Http::withToken($accessToken)
            ->get('https://graph.facebook.com/v19.0/me', ['fields' => 'id,name,picture'])
            ->throw()->json();

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
        $r = Http::withToken($conn->access_token)
            ->get('https://graph.facebook.com/v19.0/me', ['fields' => 'id']);
        return $r->successful() && isset($r->json()['id']);
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

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'             => $clientId,
            'redirect_uri'          => $redirect,
            'response_type'         => 'code',
            'scope'                 => $scopes,
            'access_type'           => 'offline',  // get refresh_token
            'prompt'                => 'consent',
            'state'                 => $state,
        ]);
    }

    private function handleGoogleCallback(string $platform, Request $request, int $clinicId): PlatformConnection
    {
        $code       = $request->input('code');
        $clientId   = config('services.google.client_id');
        $secret     = config('services.google.client_secret');
        $redirect   = route('marketing.integrations.callback', ['platform' => $platform]);

        // Exchange code for tokens
        $tokenResponse = Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $secret,
            'redirect_uri'  => $redirect,
            'grant_type'    => 'authorization_code',
        ])->throw()->json();

        $accessToken  = $tokenResponse['access_token'];
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresIn    = $tokenResponse['expires_in'] ?? 3600;

        // Get user info
        $userInfo = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo')
            ->throw()->json();

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
        $r = Http::withToken($conn->access_token)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');
        return $r->successful() && isset($r->json()['id']);
    }

    // -----------------------------------------------------------------------
    // Token revocation (best-effort)
    // -----------------------------------------------------------------------

    private function revokeToken(string $platform, PlatformConnection $conn): void
    {
        $token = $conn->access_token;
        if (! $token) return;

        if (in_array($platform, self::GOOGLE_PLATFORMS)) {
            Http::post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
        }
        // Meta doesn't have a revoke endpoint — token expires naturally
    }
}
