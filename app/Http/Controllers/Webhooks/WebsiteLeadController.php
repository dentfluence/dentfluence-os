<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Prm\LeadIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebsiteLeadController — PRM Phase 4a (one-inbox: website form).
 * ----------------------------------------------------------------------------
 * Public endpoint your website's contact/enquiry form POSTs to. It creates a
 * Lead, which then flows through the normal pipeline: LeadObserver auto-assigns
 * (2a), creates a follow-up reminder (2b) and runs AI enrichment (1). So a web
 * enquiry lands fully triaged with zero staff effort.
 *
 * SECURITY (this route is public — no login):
 *   • Shared secret — the site must send X-PRM-Token (or a `token` field) that
 *     matches config('prm.webhooks.website.secret'). No secret set = reject all.
 *   • Rate limited at the route (throttle middleware).
 *   • Strict validation + dedupe to stop spam / double-submits piling up leads.
 *
 * It never trusts the source field — source is always forced to website_form.
 */
class WebsiteLeadController extends Controller
{
    public function store(Request $request, LeadIngestService $ingest)
    {
        // 1) Feature switch.
        if (! config('prm.webhooks.website.enabled')) {
            return response()->json(['success' => false, 'message' => 'Endpoint disabled.'], 404);
        }

        // 2) Verify the shared secret (constant-time compare).
        $secret = (string) config('prm.webhooks.website.secret');
        $given  = (string) ($request->header('X-PRM-Token') ?? $request->input('token', ''));

        if ($secret === '' || ! hash_equals($secret, $given)) {
            Log::warning('PRM website webhook: bad/missing token', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // 3) Map flexible form field names → our fields, then validate.
        $data = $this->normalize($request);

        $validator = validator($data, [
            'name'  => 'required|string|max:120',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid submission.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 4) Create (with dedupe) via the shared ingest service. Source is
        //    forced to website_form — never trusted from the payload.
        $result = $ingest->ingest(
            $data,
            'website_form',
            'Website',
            trim('Captured from website form.' . ($data['page'] ? ' Page: ' . $data['page'] : '')),
        );

        return response()->json([
            'success'   => true,
            'message'   => $result['duplicate'] ? 'Duplicate ignored — recent lead already exists.' : 'Lead created.',
            'lead_id'   => $result['lead']->id,
            'duplicate' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }

    /**
     * Accept the common field names different form builders use.
     */
    protected function normalize(Request $request): array
    {
        return [
            'name'      => $request->input('name')
                ?? $request->input('full_name')
                ?? trim($request->input('first_name', '') . ' ' . $request->input('last_name', '')),
            'phone'     => $request->input('phone')
                ?? $request->input('mobile')
                ?? $request->input('phone_number'),
            'email'     => $request->input('email'),
            'treatment' => $request->input('treatment')
                ?? $request->input('service')
                ?? $request->input('interest'),
            'notes'     => $request->input('message')
                ?? $request->input('notes')
                ?? $request->input('comments'),
            'page'      => $request->input('page')
                ?? $request->input('source_url')
                ?? $request->header('Referer'),
        ];
    }
}
