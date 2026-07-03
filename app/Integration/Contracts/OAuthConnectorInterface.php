<?php

namespace App\Integration\Contracts;

/**
 * OAuthConnectorInterface — Phase 7 (Integration boundary).
 * ----------------------------------------------------------------------------
 * The contract for providers whose integration is "connect this clinic's
 * account via OAuth, then act on their behalf" — Google today (Slice 2),
 * Meta next (Slice 3, since Instagram/Facebook share this exact shape in
 * OAuthService already). Kept separate from MessagingConnectorInterface
 * because the operations are genuinely different (auth/connect/ping/revoke,
 * not send).
 */
interface OAuthConnectorInterface
{
    /** Short provider key, e.g. 'google'. Matches the integration.<provider> flag suffix. */
    public function providerName(): string;

    /** Build the provider's OAuth authorization redirect URL. Side-effect-free. */
    public function authUrl(string $platform, int $clinicId): string;

    /** Exchange a one-time authorization code for tokens. Raw provider response (same shape as the vendor's JSON). */
    public function exchangeCode(string $platform, string $code, string $redirectUri): array;

    /** Fetch basic account/profile info for the connected account. Raw provider response. */
    public function fetchAccountInfo(string $accessToken): array;

    /** Lightweight liveness check for an existing connection. */
    public function ping(string $accessToken): bool;

    /** Best-effort revoke at the provider side. */
    public function revoke(string $accessToken): void;
}
