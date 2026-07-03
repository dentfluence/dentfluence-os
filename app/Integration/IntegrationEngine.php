<?php

namespace App\Integration;

use App\Integration\Connectors\GoogleConnector;
use App\Integration\Connectors\MetaConnector;
use App\Integration\Connectors\WebsiteConnector;
use App\Integration\Connectors\WhatsAppConnector;
use App\Models\IntegrationShadowLog;
use App\Support\Features\Feature;
use Throwable;

/**
 * IntegrationEngine — Phase 7 (Integration boundary).
 * ----------------------------------------------------------------------------
 * Blueprint: docs/implementation-blueprint-v1.md, "Phase 7 — Integration".
 * "One anti-corruption boundary; business engines provider-agnostic."
 *
 * WHAT IT OWNS: the connectors (one per external provider) and the
 * per-provider `integration.<provider>` flag gate. Business code asks this
 * engine for "the WhatsApp connector" (or later, "the Meta connector",
 * "the Google connector") instead of instantiating a vendor client itself.
 *
 * WHAT IT NEVER DOES: hold business logic (consent, audit, thread bookkeeping
 * stay in OutboundMessageService — untouched by this phase), or decide
 * WHETHER to send (that's the Communication Guard). This engine only decides
 * HOW a send reaches the vendor, and records evidence for the cutover call.
 *
 * SLICE 1 added WhatsApp. SLICE 2 added Google. SLICE 3 (this update) adds
 * Meta and Website. ABDM/payments have no real vendor calls yet (see the
 * Phase 7 closing-sweep notes), so there is nothing to wrap there for now.
 *
 * STRANGLER-FIG SAFETY (why this never double-sends): a real send only ever
 * happens ONCE per call — either through the legacy client (flag off) or
 * through the connector (flag on), decided by the caller (see
 * OutboundMessageService::sendText()/sendTemplate()). This engine's logging
 * methods are side-effect-free: they build a preview payload (no HTTP) and
 * compare it against whichever real result already happened. That is what
 * makes "dual-run old vs new, compare" safe for a channel where dual-running
 * the ACTUAL vendor call would mean messaging a real patient twice.
 */
class IntegrationEngine
{
    public function __construct(
        protected WhatsAppConnector $whatsapp = new WhatsAppConnector(),
        protected GoogleConnector $google = new GoogleConnector(),
        protected MetaConnector $meta = new MetaConnector(),
        protected WebsiteConnector $website = new WebsiteConnector(),
    ) {}

    public function whatsapp(): WhatsAppConnector
    {
        return $this->whatsapp;
    }

    public function google(): GoogleConnector
    {
        return $this->google;
    }

    public function meta(): MetaConnector
    {
        return $this->meta;
    }

    public function website(): WebsiteConnector
    {
        return $this->website;
    }

    /** Is this provider cut over to the Integration boundary? Default off = legacy direct call. */
    public function enabled(string $provider, ?int $branchId = null): bool
    {
        return Feature::enabled("integration.{$provider}", $branchId);
    }

    /**
     * Shadow-compare a WhatsApp TEXT send that already happened via either
     * path, and log the result. Never throws — a logging bug must never
     * surface to whoever just sent a message to a patient.
     *
     * @param  array  $result       The normalized result returned by whichever path actually sent.
     * @param  bool   $viaConnector True if this send went through the connector (flag on); false = legacy client.
     */
    public function logWhatsAppText(string $to, string $body, array $result, bool $viaConnector): void
    {
        $this->log('text', fn () => $this->whatsapp->previewText($to, $body), $result, $viaConnector);
    }

    /** Template variant of logWhatsAppText(). */
    public function logWhatsAppTemplate(string $to, string $templateName, string $languageCode, array $components, array $result, bool $viaConnector): void
    {
        $this->log(
            'template',
            fn () => $this->whatsapp->previewTemplate($to, $templateName, $languageCode, $components),
            $result,
            $viaConnector,
        );
    }

    /**
     * Shared logging path for both send shapes.
     *
     * NOTE on `agreed`: when the real send was 'dry_run' or 'disabled',
     * WhatsAppCloudService's `raw` IS the outgoing payload it built, so we can
     * diff it against our own preview payload byte-for-byte — a real parity
     * check. When the real send was 'sent' (live Meta call), `raw` is Meta's
     * RESPONSE, not the outgoing payload, so a deep diff isn't apples-to-
     * apples; in that case `agreed` just confirms the preview payload built
     * successfully and the send reported success. This asymmetry is
     * documented, not hidden — see integration:parity's output.
     */
    protected function log(string $method, callable $buildPreview, array $result, bool $viaConnector): void
    {
        try {
            $preview = $buildPreview();
            $status  = $result['status'] ?? null;

            $agreed = in_array($status, ['dry_run', 'disabled'], true)
                ? $preview === ($result['raw'] ?? null)
                : (bool) ($result['success'] ?? false);

            IntegrationShadowLog::create([
                'provider'        => 'whatsapp',
                'method'          => $method,
                'action'          => $viaConnector ? 'cutover' : 'legacy',
                'agreed'          => $agreed,
                'preview_payload' => $preview,
                'result_payload'  => $result['raw'] ?? null,
                'notes'           => $result['error'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Google (Slice 2)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * authUrl-building has NO side effects on either path, so — unlike a real
     * send — we can genuinely compute both and diff them for real, rather
     * than a preview-only comparison. A mismatch here is an actual bug.
     */
    public function logGoogleAuthUrl(string $legacyUrl, string $connectorUrl, bool $viaConnector): void
    {
        try {
            IntegrationShadowLog::create([
                'provider'        => 'google',
                'method'          => 'auth_url',
                'action'          => $viaConnector ? 'cutover' : 'legacy',
                'agreed'          => $legacyUrl === $connectorUrl,
                'preview_payload' => ['legacy_url' => $legacyUrl, 'connector_url' => $connectorUrl],
                'result_payload'  => null,
                'notes'           => null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The authorization `code` is single-use, so exactly ONE path actually
     * calls Google here (see OAuthService::handleGoogleCallback). There is no
     * second payload to diff against — `agreed` just records whether the one
     * path that ran reported success.
     */
    public function logGoogleExchange(bool $viaConnector, bool $success, ?string $error = null): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'google',
                'method'   => 'exchange',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
                'notes'    => $error,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Same single-path reasoning as logGoogleExchange() — ping is a real read, but kept to one call per the uniform pattern. */
    public function logGooglePing(bool $viaConnector, bool $ok): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'google',
                'method'   => 'ping',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $ok,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Same single-path reasoning again — revoke has a real side effect (invalidates the token). */
    public function logGoogleRevoke(bool $viaConnector): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'google',
                'method'   => 'revoke',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => true,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Google Business Profile publish (Slice 3, discovered inside
     * ProcessScheduledPost — see GoogleConnector::publishBusinessPost()).
     * A live publish has a real side effect (creates a post), so — same as
     * Google's exchange/ping/revoke above — exactly one path runs; `agreed`
     * just records whether it reported success.
     */
    public function logGoogleBusinessPublish(bool $viaConnector, bool $success, ?string $error = null): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'google',
                'method'   => 'business_post',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
                'notes'    => $error,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Meta (Slice 3)
    // ─────────────────────────────────────────────────────────────────────────

    /** Same side-effect-free real-diff reasoning as logGoogleAuthUrl() above. */
    public function logMetaAuthUrl(string $legacyUrl, string $connectorUrl, bool $viaConnector): void
    {
        try {
            IntegrationShadowLog::create([
                'provider'        => 'meta',
                'method'          => 'auth_url',
                'action'          => $viaConnector ? 'cutover' : 'legacy',
                'agreed'          => $legacyUrl === $connectorUrl,
                'preview_payload' => ['legacy_url' => $legacyUrl, 'connector_url' => $connectorUrl],
                'result_payload'  => null,
                'notes'           => null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Same single-path reasoning as Google's exchange/ping/revoke — Meta's OAuth calls are real network calls with real effects. */
    public function logMetaExchange(bool $viaConnector, bool $success, ?string $error = null): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'meta',
                'method'   => 'exchange',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
                'notes'    => $error,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function logMetaPing(bool $viaConnector, bool $ok): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'meta',
                'method'   => 'ping',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $ok,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** $surface distinguishes 'instagram' vs 'facebook' — a real publish, single path per call, same reasoning as Google Business publish. */
    public function logMetaPublish(string $surface, bool $viaConnector, bool $success, ?string $error = null): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'meta',
                'method'   => "publish_{$surface}",
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
                'notes'    => $error,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Meta Lead Ads webhook field fetch — a read, but kept to the same single-path pattern as everything else for consistency. */
    public function logMetaLeadFetch(bool $viaConnector, bool $success): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'meta',
                'method'   => 'lead_fetch',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Website (Slice 3)
    // ─────────────────────────────────────────────────────────────────────────

    public function logWebsitePublish(bool $viaConnector, bool $success, ?string $error = null): void
    {
        try {
            IntegrationShadowLog::create([
                'provider' => 'website',
                'method'   => 'wordpress_post',
                'action'   => $viaConnector ? 'cutover' : 'legacy',
                'agreed'   => $success,
                'notes'    => $error,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
