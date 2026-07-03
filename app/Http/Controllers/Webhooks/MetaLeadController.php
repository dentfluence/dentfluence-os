<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Integration\IntegrationEngine;
use App\Services\Prm\LeadIngestService;
use App\Support\Features\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MetaLeadController — PRM Phase 4b (one-inbox: Meta Lead Ads).
 * ----------------------------------------------------------------------------
 * Facebook / Instagram lead-form submissions. Meta's webhook only sends a
 * leadgen_id — we then call the Graph API (with a page access token) to fetch
 * the actual answers, and create a Lead from them.
 *
 *   GET  /api/webhooks/prm/meta-lead   → subscription verification handshake
 *   POST /api/webhooks/prm/meta-lead   → lead notifications (signed)
 *
 * The created Lead flows through LeadObserver (auto-assign + follow-up + AI).
 */
class MetaLeadController extends Controller
{
    use VerifiesMetaSignature;

    /** GET — Meta calls this once to verify the endpoint. */
    public function verify(Request $request)
    {
        return $this->verifyChallenge($request, config('prm.webhooks.meta.verify_token'));
    }

    /** POST — incoming leadgen notifications. */
    public function receive(Request $request, LeadIngestService $ingest)
    {
        if (! config('prm.webhooks.meta.enabled')) {
            return response()->json(['success' => false], 404);
        }

        if (! $this->signatureValid($request, config('prm.webhooks.meta.app_secret'))) {
            Log::warning('PRM Meta webhook: bad signature', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $created = 0;

        // Payload: entry[].changes[] where field == 'leadgen', value.leadgen_id.
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }
                $leadgenId = $change['value']['leadgen_id'] ?? null;
                if (! $leadgenId) {
                    continue;
                }

                $fields = $this->fetchLeadFields($leadgenId);
                if ($fields === null) {
                    continue; // fetch failed / not configured — logged inside.
                }

                $result = $ingest->ingest(
                    $this->mapFields($fields),
                    config('prm.webhooks.meta.default_source', 'facebook'),
                    'Meta Lead Ads',
                    'Captured from a Meta (Facebook/Instagram) lead form.',
                );

                if (! $result['duplicate']) {
                    $created++;
                }
            }
        }

        // Always 200 so Meta doesn't retry an already-processed delivery.
        return response()->json(['success' => true, 'created' => $created]);
    }

    /**
     * Pull the lead's answers from the Graph API using the leadgen_id.
     * Returns the raw field_data array, or null if it couldn't be fetched.
     */
    protected function fetchLeadFields(string $leadgenId): ?array
    {
        $token = config('prm.webhooks.meta.access_token');
        if (! $token) {
            Log::warning('PRM Meta webhook: no page access token configured');
            return null;
        }

        // Phase 7 (Integration boundary): routed through MetaConnector once
        // `integration.meta` is on; legacy inline call otherwise — default
        // OFF means the block below behaves exactly as before this slice.
        // This is a read (GET), so re-fetching is harmless, but kept to the
        // same single-path pattern as every other Integration touchpoint for
        // consistency.
        $viaConnector = Feature::enabled('integration.meta');
        $version      = config('prm.webhooks.meta.graph_version', 'v19.0');

        try {
            if ($viaConnector) {
                $fields = app(IntegrationEngine::class)->meta()->fetchLeadFields($leadgenId, $token, $version);
                app(IntegrationEngine::class)->logMetaLeadFetch(true, $fields !== null);
                return $fields;
            }

            // ── legacy inline call (unchanged) ──────────────────────────────
            $resp = Http::timeout(15)->get("https://graph.facebook.com/{$version}/{$leadgenId}", [
                'access_token' => $token,
                'fields'       => 'field_data',
            ]);

            if (! $resp->successful()) {
                Log::warning('PRM Meta webhook: graph fetch failed', ['body' => $resp->body()]);
                app(IntegrationEngine::class)->logMetaLeadFetch(false, false);
                return null;
            }

            app(IntegrationEngine::class)->logMetaLeadFetch(false, true);
            return $resp->json('field_data', []);
        } catch (\Throwable $e) {
            Log::warning('PRM Meta webhook: graph fetch error', ['error' => $e->getMessage()]);
            app(IntegrationEngine::class)->logMetaLeadFetch($viaConnector, false);
            return null;
        }
    }

    /**
     * Map Meta's field_data ([{name, values:[...]}, ...]) to our lead fields.
     */
    protected function mapFields(array $fields): array
    {
        $out   = ['name' => null, 'phone' => null, 'email' => null, 'treatment' => null];
        $extra = [];

        foreach ($fields as $f) {
            $key = strtolower($f['name'] ?? '');
            $val = $f['values'][0] ?? null;
            if ($val === null) {
                continue;
            }

            match (true) {
                in_array($key, ['full_name', 'name'], true)               => $out['name'] = $val,
                in_array($key, ['phone_number', 'phone'], true)           => $out['phone'] = preg_replace('/\s+/', '', $val),
                $key === 'email'                                          => $out['email'] = $val,
                in_array($key, ['treatment', 'service', 'interest'], true)=> $out['treatment'] = $val,
                default                                                   => $extra[] = ucfirst($key) . ': ' . $val,
            };
        }

        $out['notes'] = $extra ? implode("\n", $extra) : null;

        return $out;
    }
}
