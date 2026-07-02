<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppCloudService — the low-level provider client (Phase B item 1.2).
 * ----------------------------------------------------------------------------
 * This class ONLY knows how to talk to Meta's WhatsApp Cloud (Graph) API. It does
 * NOT touch the database, consent, or audit — that orchestration lives in the
 * OutboundMessageService built in Chunk 2. Keeping the provider call isolated
 * means we could swap providers later without touching business logic.
 *
 * Every method returns a NORMALIZED result array:
 *   [
 *     'success'       => bool,
 *     'wa_message_id' => string|null,  // Meta's wamid... on success
 *     'status'        => string,       // 'sent' | 'dry_run' | 'disabled' | 'failed'
 *     'error'         => string|null,
 *     'raw'           => array,        // raw response (or built payload in dry-run)
 *   ]
 *
 * SAFETY: if WHATSAPP_ENABLED is false → 'disabled' (no-op). If WHATSAPP_DRY_RUN
 * is true → 'dry_run' (payload logged, nothing sent). Real sends happen only when
 * enabled AND dry_run is off.
 */
class WhatsAppCloudService
{
    /**
     * Send a plain text message. Only allowed inside the 24h customer-service
     * window — the caller (Chunk 2) is responsible for enforcing that rule.
     */
    public function sendText(string $toPhone, string $body): array
    {
        $to = $this->normalizePhone($toPhone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $body],
        ];

        return $this->dispatch($payload);
    }

    /**
     * Send a pre-approved template message (business-initiated; works outside the
     * 24h window). Fully wired into the app in Chunk 4 — included here so the
     * provider client is complete.
     *
     * @param array $components Meta "components" array (header/body variables, etc.)
     */
    public function sendTemplate(string $toPhone, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        $to = $this->normalizePhone($toPhone);

        $template = [
            'name'     => $templateName,
            'language' => ['code' => $languageCode],
        ];
        if (! empty($components)) {
            $template['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $template,
        ];

        return $this->dispatch($payload);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  INTERNAL
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send the built payload to Meta — or short-circuit for disabled / dry-run.
     */
    protected function dispatch(array $payload): array
    {
        // 1) Sending switched off entirely.
        if (! config('whatsapp.enabled')) {
            return $this->result(false, null, 'disabled', 'WhatsApp sending is disabled (WHATSAPP_ENABLED=false).', $payload);
        }

        // 2) Dry-run — build + log, send nothing. Checked BEFORE credentials so
        //    you can fully test the pipeline without real Meta keys.
        if (config('whatsapp.dry_run')) {
            Log::info('WhatsApp DRY-RUN (not sent)', ['payload' => $payload]);
            return $this->result(true, null, 'dry_run', null, $payload);
        }

        // 3) Real send needs credentials — fail loudly but safely if missing.
        $phoneNumberId = config('whatsapp.phone_number_id');
        $token         = config('whatsapp.access_token');
        if (! $phoneNumberId || ! $token) {
            return $this->result(false, null, 'failed', 'Missing WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_ACCESS_TOKEN.', $payload);
        }

        // 4) Real send.
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('whatsapp.graph_version', 'v21.0'),
            $phoneNumberId,
        );

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('whatsapp.timeout', 15))
                ->post($url, $payload);

            $json = $response->json() ?? [];

            if ($response->successful()) {
                $waId = $json['messages'][0]['id'] ?? null;
                return $this->result(true, $waId, 'sent', null, $json);
            }

            // Meta returns a structured error object on failure.
            $error = $json['error']['message'] ?? ('HTTP ' . $response->status());
            Log::warning('WhatsApp send failed', ['status' => $response->status(), 'body' => $json]);
            return $this->result(false, null, 'failed', $error, $json);

        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', ['message' => $e->getMessage()]);
            return $this->result(false, null, 'failed', $e->getMessage(), $payload);
        }
    }

    /**
     * Normalize a phone number to the digits-only E.164 form Meta expects
     * (no '+', no spaces). Adds the default country code when the number looks
     * like a local one without it.
     */
    public function normalizePhone(string $raw): string
    {
        // Keep digits only.
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return $digits;
        }

        $cc = (string) config('whatsapp.default_country_code', '91');

        // Strip a leading 0 (common local prefix) before deciding on country code.
        $digits = ltrim($digits, '0');

        // If it's a 10-digit local number, prepend the country code.
        if (strlen($digits) === 10) {
            $digits = $cc . $digits;
        }

        return $digits;
    }

    /** Build the normalized result array every method returns. */
    protected function result(bool $success, ?string $waId, string $status, ?string $error, array $raw): array
    {
        return [
            'success'       => $success,
            'wa_message_id' => $waId,
            'status'        => $status,
            'error'         => $error,
            'raw'           => $raw,
        ];
    }
}
