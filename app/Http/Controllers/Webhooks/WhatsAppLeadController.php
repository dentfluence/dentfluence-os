<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Prm\LeadIngestService;
use App\Services\Whatsapp\InboundMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppLeadController — PRM Phase 4c (one-inbox: WhatsApp Cloud API).
 * ----------------------------------------------------------------------------
 * Turns inbound WhatsApp messages into leads (first message from a new number
 * becomes a lead; further messages within the dedupe window are ignored so an
 * ongoing chat doesn't spawn duplicates).
 *
 *   GET  /api/webhooks/prm/whatsapp   → subscription verification handshake
 *   POST /api/webhooks/prm/whatsapp   → inbound messages (signed)
 *
 * Uses the same Meta signature scheme. Created leads flow through LeadObserver.
 */
class WhatsAppLeadController extends Controller
{
    use VerifiesMetaSignature;

    /** GET — verification handshake. */
    public function verify(Request $request)
    {
        return $this->verifyChallenge($request, config('prm.webhooks.whatsapp.verify_token'));
    }

    /** POST — inbound messages. */
    public function receive(Request $request, LeadIngestService $ingest, InboundMessageService $inbound)
    {
        if (! config('prm.webhooks.whatsapp.enabled')) {
            return response()->json(['success' => false], 404);
        }

        if (! $this->signatureValid($request, config('prm.webhooks.whatsapp.app_secret'))) {
            Log::warning('PRM WhatsApp webhook: bad signature', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $created = 0;

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value    = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];      // inbound msgs only
                if (empty($messages)) {
                    continue; // status/delivery updates carry no 'messages' — skip
                }

                // Build a phone → name map from the contacts block.
                $names = [];
                foreach ($value['contacts'] ?? [] as $c) {
                    if (! empty($c['wa_id'])) {
                        $names[$c['wa_id']] = $c['profile']['name'] ?? null;
                    }
                }

                foreach ($messages as $msg) {
                    $from = $msg['from'] ?? null;
                    if (! $from) {
                        continue;
                    }

                    $body = $msg['text']['body'] ?? ('[' . ($msg['type'] ?? 'message') . ']');

                    $result = $ingest->ingest(
                        [
                            'name'  => $names[$from] ?? 'WhatsApp Lead',
                            'phone' => $from,
                            'notes' => $body,
                        ],
                        'whatsapp',
                        'WhatsApp',
                        'First WhatsApp message: ' . $body,
                    );

                    if (! $result['duplicate']) {
                        $created++;
                    }

                    // Phase B 1.2 (Chunk 3a): thread EVERY inbound message into the
                    // unified inbox. The lead pipeline above only captures the first
                    // message; this records the full conversation and opens the 24h
                    // reply window so staff can respond with free text.
                    $inbound->record([
                        'from'          => $from,
                        'name'          => $names[$from] ?? null,
                        'body'          => $body,
                        'wa_message_id' => $msg['id'] ?? null,
                        'type'          => $msg['type'] ?? 'text',
                        'lead_id'       => $result['lead']->id ?? null,
                        'raw'           => $msg,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'created' => $created]);
    }
}
