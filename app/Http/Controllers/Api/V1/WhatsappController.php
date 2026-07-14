<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Patient;
use App\Services\Relationship\ActivityEngine;
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
}
