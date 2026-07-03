<?php

namespace App\Integration\Connectors;

use App\Integration\Contracts\MessagingConnectorInterface;
use App\Services\Whatsapp\WhatsAppCloudService;

/**
 * WhatsAppConnector — Phase 7, Slice 1.
 * ----------------------------------------------------------------------------
 * The Integration Engine's wrapper around WhatsAppCloudService. Today this is
 * a thin passthrough — WhatsAppCloudService was already the ONLY class in the
 * app that touches Meta's Graph API directly (Phase B item 1.2), so there was
 * no vendor SDK loose inside a business engine to begin with. What this class
 * adds is the boundary itself: a stable, provider-agnostic contract
 * (MessagingConnectorInterface) that OutboundMessageService can depend on
 * instead of reaching for "the WhatsApp class" by name. That's what makes a
 * future provider swap (or adding SMS/email connectors) a new class here,
 * not a rewrite of the callers.
 *
 * Also exposes previewText()/previewTemplate() — side-effect-free payload
 * builders (no HTTP, no dispatch) used ONLY for the Slice 1 shadow-comparison
 * log in IntegrationEngine. They must never be used to actually send.
 */
class WhatsAppConnector implements MessagingConnectorInterface
{
    public function __construct(
        protected WhatsAppCloudService $client = new WhatsAppCloudService(),
    ) {}

    public function providerName(): string
    {
        return 'whatsapp';
    }

    public function sendText(string $to, string $body): array
    {
        return $this->client->sendText($to, $body);
    }

    public function sendTemplate(string $to, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        return $this->client->sendTemplate($to, $templateName, $languageCode, $components);
    }

    public function normalizePhone(string $raw): string
    {
        return $this->client->normalizePhone($raw);
    }

    /**
     * Build the outgoing text payload WITHOUT sending it. Used to shadow-compare
     * "what the connector would have built" against what the legacy path
     * actually sent, without risking a duplicate real message to a patient.
     */
    public function previewText(string $to, string $body): array
    {
        return $this->client->buildTextPayload($to, $body);
    }

    public function previewTemplate(string $to, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        return $this->client->buildTemplatePayload($to, $templateName, $languageCode, $components);
    }
}
