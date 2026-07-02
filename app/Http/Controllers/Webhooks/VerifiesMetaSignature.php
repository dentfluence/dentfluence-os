<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;

/**
 * VerifiesMetaSignature — shared logic for Meta-platform webhooks
 * (Meta Lead Ads + WhatsApp Cloud API both use the same scheme).
 * ----------------------------------------------------------------------------
 *  • GET  verification: Meta calls the URL once with hub.mode / hub.verify_token
 *    / hub.challenge. We echo the challenge if the token matches. (PHP turns the
 *    dots in those query keys into underscores, hence hub_mode etc.)
 *  • POST authenticity: every payload carries X-Hub-Signature-256, an HMAC-SHA256
 *    of the raw body keyed by the app secret. We verify it in constant time.
 */
trait VerifiesMetaSignature
{
    /**
     * Handle the GET subscription handshake. Returns the challenge (200) or 403.
     */
    protected function verifyChallenge(Request $request, ?string $verifyToken)
    {
        if (
            $verifyToken
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Validate the X-Hub-Signature-256 header against the raw request body.
     */
    protected function signatureValid(Request $request, ?string $appSecret): bool
    {
        if (! $appSecret) {
            return false; // no secret configured → reject (fail closed).
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $header);
    }
}
