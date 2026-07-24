<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Patient;
use App\Services\Communication\WhatsAppLinkService;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\CommunicationGuard;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Api\V1\WhatsappController — the consent-gated WhatsApp send for mobile.
 *
 * Product decision 2026-07-14 (Sumit): mobile must send WhatsApp through
 * OutboundMessageService like web — DPDP consent checked, message recorded
 * in wa_threads/wa_messages, Timeline logged — instead of the old device
 * deep-link that bypassed the consent gate entirely and left no record.
 *
 *   POST /api/v1/patients/{patient}/whatsapp/send   { message }
 *
 * The service's consentGate() runs unconditionally inside sendText(); a
 * consent refusal comes back as ok=false and is surfaced as a 422 with the
 * reason, exactly what web staff see.
 */
class WhatsappController extends ApiController
{
    /**
     * GET /api/v1/patients/{patient}/whatsapp/thread
     * The patient's WhatsApp conversation — same wa_threads/wa_messages the
     * web inbox and the Relationship-profile Communication tab read, plus
     * the consent gate so the client knows whether a free-text reply is
     * currently allowed (consent + 24h window). Read marks the thread's
     * unread counter zero, same as opening it on web.
     */
    public function thread(
        Request $request,
        $patient,
        OutboundMessageService $outbound
    ): JsonResponse {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $thread = \App\Models\WaThread::where('patient_id', $pt->id)
            ->latest('updated_at')
            ->first();

        if (! $thread) {
            return $this->success([
                'thread'    => null,
                'messages'  => [],
                'can_reply' => true, // send() will still consent-gate the actual send
                'gate'      => null,
            ], '');
        }

        $messages = $thread->messages()
            ->with('sentBy:id,name')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'direction'     => $m->direction,   // inbound | outbound
                'type'          => $m->type,
                'body'          => $m->body,
                'template_name' => $m->template_name,
                'media_url'     => $m->media_url,
                'status'        => $m->status,
                'sent_by'       => $m->sentBy?->name,
                'at'            => $m->created_at?->toIso8601String(),
            ])
            ->values();

        if ($thread->unread_count > 0) {
            $thread->update(['unread_count' => 0]);
        }

        $gate = $outbound->consentGate($thread, 'service');

        return $this->success([
            'thread' => [
                'id'            => $thread->id,
                'contact_phone' => $thread->contact_phone,
                'contact_name'  => $thread->contact_name,
            ],
            'messages'  => $messages,
            'can_reply' => (bool) ($gate['allowed'] ?? false),
            'gate'      => ($gate['allowed'] ?? false) ? null : ($gate['reason'] ?? null),
        ], '');
    }

    public function send(
        Request $request,
        $patient,
        OutboundMessageService $outbound,
        ActivityEngine $activityEngine
    ): JsonResponse {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }
        if (! $pt->phone) {
            return $this->error('Patient has no phone number on file.', [], 422);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $outbound->sendText($pt->phone, $data['message'], [
            'category'     => 'service',
            'patient_id'   => $pt->id,
            'contact_name' => $pt->name,
        ]);

        if (! ($result['ok'] ?? false)) {
            return $this->error(
                $result['reason'] ?? 'Could not send WhatsApp message (consent not granted or send failed).',
                [], 422
            );
        }

        $activityEngine->log(
            subject:        $pt,
            event:          'whatsapp.sent',
            actor:          $request->user(),
            metadata:       [
                'category' => 'service',
                'source'   => 'mobile',
            ],
            relationshipId: $pt->relationship_id ?? null,
            description:    'WhatsApp message sent from mobile app',
        );

        return $this->success(null, 'WhatsApp message sent.');
    }

    /**
     * POST /api/v1/patients/{patient}/whatsapp/link
     *
     * Interim click-to-chat (wa.me) parity for mobile. Until the WhatsApp Cloud
     * API is approved, the app opens the returned URL with url_launcher and the
     * staff member sends from their own WhatsApp. Same consent gate, phone
     * normalization, templates and contact logging as the web endpoint — it
     * shares WhatsAppLinkService, so copy and rules never drift between web and
     * mobile.
     *
     * Body: { context, message?, params?, type? }
     * Returns: { url, phone }
     */
    public function link(
        Request $request,
        $patient,
        WhatsAppLinkService $link
    ): JsonResponse {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }
        if (! $pt->phone) {
            return $this->error('Patient has no phone number on file.', [], 422);
        }

        $data = $request->validate([
            'context' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'params'  => ['nullable', 'array'],
            'type'    => ['nullable', 'string', 'max:30'],
        ]);

        $params = $link->prepareParams($data['context'], $pt, $data['params'] ?? [], $data['message'] ?? null);

        $result = $link->resolve($pt, $pt->phone, $data['context'], $params, $data['type'] ?? 'service');

        if (! $result['allowed']) {
            return $this->error($result['reason'] ?? 'This message was blocked by the communication guard.', [], 422);
        }

        if ($pt->relationship_id) {
            app(CommunicationGuard::class)->log($pt->relationship_id, 'whatsapp', $data['context']);
        }

        return $this->success([
            'url'   => $result['url'],
            'phone' => $result['phone'],
        ], '');
    }
}
