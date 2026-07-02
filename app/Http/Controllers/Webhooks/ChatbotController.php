<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Prm\LeadIngestService;
use Illuminate\Http\Request;

/**
 * ChatbotController — PRM Phase 6 (website chatbot).
 * ----------------------------------------------------------------------------
 * The website chat widget runs a short scripted qualification (treatment →
 * name → phone) entirely in the visitor's browser, then POSTs the result here.
 * We create a Lead via the shared ingest pipeline, so it immediately gets
 * auto-assigned, gets a follow-up, and is AI-enriched — the bot captures, the
 * AI does the smart work.
 *
 * PUBLIC endpoint (a chat widget can't safely hold a secret) — so it's rate
 * limited at the route and strictly validated here.
 */
class ChatbotController extends Controller
{
    /** POST /api/webhooks/prm/chatbot — create a lead from a chat session. */
    public function submit(Request $request, LeadIngestService $ingest)
    {
        if (! config('prm.chatbot.enabled')) {
            return response()->json(['success' => false, 'message' => 'Chatbot disabled.'], 404);
        }

        $validator = validator($request->all(), [
            'name'      => 'required|string|max:120',
            'phone'     => 'required|string|max:20',
            'treatment' => 'nullable|string|max:120',
            'message'   => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please share your name and phone so we can help.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $ingest->ingest(
            [
                'name'      => $request->input('name'),
                'phone'     => preg_replace('/\s+/', '', $request->input('phone')),
                'treatment' => $request->input('treatment'),
                'notes'     => $request->input('message'),
            ],
            'website_form',
            'Website Chatbot',
            'Captured via the website chatbot.',
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Thanks! Our team will reach out shortly.',
            'lead_id'   => $result['lead']->id,
            'duplicate' => $result['duplicate'],
        ]);
    }
}
