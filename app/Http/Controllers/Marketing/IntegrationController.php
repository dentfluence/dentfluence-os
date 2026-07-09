<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\MarketingActivityLog;
use App\Models\Marketing\PlatformConnection;
use App\Services\Marketing\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * IntegrationController
 *
 * Phase 5 — real OAuth connect/disconnect/callback/health-check.
 *
 * Routes (see routes/marketing.php):
 *   GET  /marketing/integrations                        → index()
 *   GET  /marketing/integrations/{platform}/connect     → connect()
 *   GET  /marketing/integrations/{platform}/callback    → callback()
 *   POST /marketing/integrations/{platform}/disconnect  → disconnect()
 *   POST /marketing/integrations/{platform}/health-check→ healthCheck()
 *   GET  /marketing/integrations/whatsapp/setup         → showWhatsappForm()
 *   POST /marketing/integrations/whatsapp/save          → saveWhatsapp()
 *   GET  /marketing/integrations/wordpress/setup        → showWordpressForm()
 *   POST /marketing/integrations/wordpress/save         → saveWordpress()
 */
class IntegrationController extends Controller
{
    use ResolvesClinicId;

    public function __construct(private readonly OAuthService $oauth) {}

    // -----------------------------------------------------------------------
    // Index — load all platform connections for this clinic
    // -----------------------------------------------------------------------

    public function index(): View
    {
        $clinicId = $this->currentClinicId();

        // Keyed by platform for easy access in the view
        $connections = PlatformConnection::where('clinic_id', $clinicId)
            ->get()
            ->keyBy('platform');

        return view('marketing.integrations.index', compact('connections'));
    }

    // -----------------------------------------------------------------------
    // Connect — initiate OAuth redirect
    // -----------------------------------------------------------------------

    public function connect(string $platform): RedirectResponse
    {
        $clinicId = $this->currentClinicId();

        if (! array_key_exists($platform, OAuthService::PLATFORMS)) {
            return back()->with('error', "Unknown platform: {$platform}");
        }

        try {
            $url = $this->oauth->getConnectUrl($platform, $clinicId);
            return redirect()->away($url);

        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'meta_not_configured'   => back()->with('error', 'Meta App credentials not configured. Add META_APP_ID and META_APP_SECRET to your .env file.'),
                'google_not_configured' => back()->with('error', 'Google credentials not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to your .env file.'),
                'whatsapp_static'       => redirect()->route('marketing.integrations.whatsapp-setup'),
                'wordpress_static'      => redirect()->route('marketing.integrations.wordpress-setup'),
                default                 => back()->with('error', $e->getMessage()),
            };
        }
    }

    // -----------------------------------------------------------------------
    // Callback — handle OAuth return from platform
    // -----------------------------------------------------------------------

    public function callback(string $platform, Request $request): RedirectResponse
    {
        $clinicId = $this->currentClinicId();

        if ($request->has('error')) {
            $msg = $request->input('error_description', $request->input('error'));
            return redirect()->route('marketing.integrations')->with('error', "Connection failed: {$msg}");
        }

        try {
            $conn = $this->oauth->handleCallback($platform, $request, $clinicId);

            MarketingActivityLog::log(
                $clinicId,
                'integration_connected',
                $conn,
                OAuthService::PLATFORMS[$platform] . " connected successfully",
                ['platform' => $platform, 'account' => $conn->external_account_name],
                auth()->id()
            );

            return redirect()->route('marketing.integrations')
                ->with('success', OAuthService::PLATFORMS[$platform] . " connected successfully!");

        } catch (\Throwable $e) {
            return redirect()->route('marketing.integrations')
                ->with('error', "Failed to connect: " . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Disconnect
    // -----------------------------------------------------------------------

    public function disconnect(string $platform): RedirectResponse
    {
        $clinicId = $this->currentClinicId();

        try {
            $this->oauth->disconnect($platform, $clinicId, auth()->id());
            return back()->with('success', OAuthService::PLATFORMS[$platform] . " disconnected.");
        } catch (\Throwable $e) {
            return back()->with('error', "Could not disconnect: " . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Health check (AJAX)
    // -----------------------------------------------------------------------

    public function healthCheck(string $platform): JsonResponse
    {
        $clinicId = $this->currentClinicId();
        $result   = $this->oauth->checkHealth($platform, $clinicId);

        return response()->json($result, $result['status'] === 'connected' ? 200 : 422);
    }

    // -----------------------------------------------------------------------
    // WhatsApp — static token form (no OAuth needed)
    // -----------------------------------------------------------------------

    public function showWhatsappForm(): View
    {
        $clinicId = $this->currentClinicId();
        $conn     = PlatformConnection::where('clinic_id', $clinicId)
            ->where('platform', 'whatsapp')->first();

        return view('marketing.integrations.whatsapp', compact('conn'));
    }

    public function saveWhatsapp(Request $request): RedirectResponse
    {
        $clinicId = $this->currentClinicId();

        $request->validate([
            'access_token'    => 'required|string',
            'phone_number_id' => 'required|string',
            'display_name'    => 'nullable|string|max:120',
        ]);

        $conn = PlatformConnection::updateOrCreate(
            ['clinic_id' => $clinicId, 'platform' => 'whatsapp'],
            [
                'access_token'          => $request->access_token,
                'external_account_id'   => $request->phone_number_id,
                'external_account_name' => $request->display_name ?? 'WhatsApp Business',
                'status'                => 'connected',
                'error_message'         => null,
                'last_checked_at'       => now(),
                'connected_by'          => auth()->id(),
                'updated_by'            => auth()->id(),
            ]
        );

        MarketingActivityLog::log($clinicId, 'integration_connected', $conn, "WhatsApp Business connected", [], auth()->id());

        return redirect()->route('marketing.integrations')->with('success', 'WhatsApp Business connected.');
    }

    // -----------------------------------------------------------------------
    // WordPress — app-password form (no OAuth)
    // -----------------------------------------------------------------------

    public function showWordpressForm(): View
    {
        $clinicId = $this->currentClinicId();
        $conn     = PlatformConnection::where('clinic_id', $clinicId)
            ->where('platform', 'wordpress')->first();

        return view('marketing.integrations.wordpress', compact('conn'));
    }

    public function saveWordpress(Request $request): RedirectResponse
    {
        $clinicId = $this->currentClinicId();

        $request->validate([
            'site_url'     => 'required|url',
            'username'     => 'required|string',
            'app_password' => 'required|string',
        ]);

        $siteUrl = rtrim($request->site_url, '/');
        $wpUser  = [];
        $testOk  = false;

        try {
            $r = \Illuminate\Support\Facades\Http::withBasicAuth($request->username, $request->app_password)
                ->timeout(8)
                ->get("{$siteUrl}/wp-json/wp/v2/users/me");
            $testOk = $r->successful();
            $wpUser = $r->json() ?? [];
        } catch (\Throwable) {}

        $conn = PlatformConnection::updateOrCreate(
            ['clinic_id' => $clinicId, 'platform' => 'wordpress'],
            [
                'access_token'          => $request->app_password,
                'external_account_id'   => $siteUrl,
                'external_account_name' => $wpUser['name'] ?? $request->username,
                'meta'                  => ['site_url' => $siteUrl, 'username' => $request->username],
                'status'                => $testOk ? 'connected' : 'error',
                'error_message'         => $testOk ? null : 'Could not reach the WordPress site. Check URL and credentials.',
                'last_checked_at'       => now(),
                'connected_by'          => auth()->id(),
                'updated_by'            => auth()->id(),
            ]
        );

        if (! $testOk) {
            return back()->with('error', 'WordPress connection failed — check the site URL and app password.');
        }

        MarketingActivityLog::log($clinicId, 'integration_connected', $conn, "WordPress connected ({$siteUrl})", [], auth()->id());

        return redirect()->route('marketing.integrations')->with('success', "WordPress connected ({$siteUrl}).");
    }
}
