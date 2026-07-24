<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\Communication\WhatsAppLinkService;
use App\Services\Relationship\CommunicationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single consent-gated endpoint that builds a click-to-chat (wa.me) link for any
 * text send point in the app. Every module — appointments, patient profile,
 * reviews, recalls — posts here instead of building wa.me URLs itself, so consent
 * gating, phone normalization, message copy and contact logging live in one place.
 *
 * The endpoint is model-agnostic: display values (date, time, doctor, review URL)
 * are passed in `params` by the caller, which already has them on screen. Only the
 * server-side concerns (consent, normalization, logging) are resolved here.
 */
class WhatsAppLinkController extends Controller
{
    public function build(Request $request, WhatsAppLinkService $service): JsonResponse
    {
        $data = $request->validate([
            'context'    => 'required|string|max:50',
            'patient_id' => 'nullable|integer|exists:patients,id',
            'phone'      => 'nullable|string|max:20',
            'message'    => 'nullable|string|max:2000',
            'params'     => 'nullable|array',
            'type'       => 'nullable|string|max:30', // 'service' (default) | 'marketing' etc.
        ]);

        $patient = ! empty($data['patient_id']) ? Patient::find($data['patient_id']) : null;
        $context = $data['context'];
        $type    = $data['type'] ?? 'service';

        $templateData = $service->prepareParams($context, $patient, $data['params'] ?? [], $data['message'] ?? null);
        $phone        = $data['phone'] ?? $patient?->phone;

        $result = $service->resolve($patient, $phone, $context, $templateData, $type);

        if (! $result['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $result['reason'] ?? 'This message was blocked by the communication guard.',
            ], 422);
        }

        // Record the contact against the relationship (used by frequency/quiet-hour
        // rules). Only when linked — unlinked patients have nothing to log against.
        if ($patient && $patient->relationship_id) {
            app(CommunicationGuard::class)->log($patient->relationship_id, 'whatsapp', $context);
        }

        return response()->json([
            'success' => true,
            'url'     => $result['url'],
            'phone'   => $result['phone'],
        ]);
    }
}
