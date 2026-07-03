<?php

namespace App\Integration\Contracts;

/**
 * MessagingConnectorInterface — Phase 7 (Integration boundary).
 * ----------------------------------------------------------------------------
 * The contract every messaging-provider connector must satisfy so the rest of
 * the app (Communication Engine, OutboundMessageService, and later Meta/SMS/
 * email connectors) can talk to "a messaging provider" without knowing which
 * vendor SDK sits behind it. This is the anti-corruption boundary described
 * in docs/implementation-blueprint-v1.md, Phase 7: "no business engine holds
 * a vendor SDK."
 *
 * Every method returns the SAME normalized result shape the app already uses
 * (see WhatsAppCloudService): ['success','wa_message_id','status','error','raw'].
 * Keeping that shape here (not redefining it) means today's WhatsApp cutover
 * needs zero changes to any caller's result-handling code.
 */
interface MessagingConnectorInterface
{
    /** Short provider key, e.g. 'whatsapp'. Matches the integration.<provider> flag suffix. */
    public function providerName(): string;

    /** Send a plain-text message. */
    public function sendText(string $to, string $body): array;

    /** Send a pre-approved template message. */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'en', array $components = []): array;

    /** Normalize a raw phone number to the form this provider expects. */
    public function normalizePhone(string $raw): string;
}
