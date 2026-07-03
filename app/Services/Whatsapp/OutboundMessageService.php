<?php

namespace App\Services\Whatsapp;

use App\Integration\IntegrationEngine;
use App\Models\AuditLog;
use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\WaMessage;
use App\Models\WaThread;
use App\Support\Features\Feature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * OutboundMessageService — the "send a WhatsApp message the right way" brain
 * (Phase B item 1.2, Chunk 2).
 * ----------------------------------------------------------------------------
 * This is the ONLY place the app should call to send a WhatsApp message. It:
 *
 *   1. Finds (or opens) the conversation thread for the phone number.
 *   2. Enforces the DPDP CONSENT GATE — a message is only sent if it's allowed.
 *   3. Records the outbound message in wa_messages (so the inbox has history).
 *   4. Calls the provider client (dry-run aware — nothing leaves in dry-run).
 *   5. Writes a tamper-evident AUDIT entry (sent or blocked).
 *
 * The low-level Graph API call lives in WhatsAppCloudService; this class never
 * touches HTTP directly.
 *
 * Returns a structured result so callers (the test command now, the inbox UI in
 * Chunk 3) can show what happened:
 *   ['ok' => bool, 'reason' => ?string, 'thread' => WaThread, 'message' => ?WaMessage]
 */
class OutboundMessageService
{
    public function __construct(
        protected WhatsAppCloudService $client = new WhatsAppCloudService(),
    ) {}

    /**
     * Send a plain-text WhatsApp message.
     *
     * @param string $phone  any format — it gets normalized.
     * @param array  $opts   category: 'service'|'marketing' (default service),
     *                        patient_id, lead_id, contact_name, sent_by_id.
     */
    public function sendText(string $phone, string $body, array $opts = []): array
    {
        $category = ($opts['category'] ?? 'service') === 'marketing' ? 'marketing' : 'service';
        $actorId  = $opts['sent_by_id'] ?? Auth::id();

        $thread = $this->resolveThread($phone, $opts);

        // ── DPDP CONSENT GATE ──────────────────────────────────────────────────
        $gate = $this->consentGate($thread, $category);
        if (! $gate['allowed']) {
            AuditLog::event('whatsapp_send_blocked', $actorId, [
                'thread_id' => $thread->id,
                'phone'     => $thread->contact_phone,
                'category'  => $category,
                'reason'    => $gate['reason'],
            ], [
                'module'         => 'communication',
                'auditable_type' => WaThread::class,
                'auditable_id'   => $thread->id,
            ]);

            return ['ok' => false, 'reason' => $gate['reason'], 'thread' => $thread, 'message' => null];
        }

        // ── RECORD the outbound message (queued) ───────────────────────────────
        $msg = $thread->messages()->create([
            'channel'    => 'whatsapp',
            'direction'  => WaMessage::OUTBOUND,
            'from_phone' => null, // our own number isn't stored as a string (only its Meta id)
            'to_phone'   => $thread->contact_phone,
            'type'       => 'text',
            'body'       => $body,
            'status'     => WaMessage::STATUS_QUEUED,
            'sent_by_id' => $actorId,
        ]);

        // ── SEND via provider (dry-run aware) ──────────────────────────────────
        // Phase 7 (Integration boundary): routed through the connector once
        // `integration.whatsapp` is on; the legacy direct call otherwise —
        // default OFF means this line behaves EXACTLY as before. Either way,
        // IntegrationEngine shadow-logs a comparison for the parity report;
        // that logging is side-effect-free and never causes a second real send.
        $viaConnector = Feature::enabled('integration.whatsapp');
        $res = $viaConnector
            ? app(IntegrationEngine::class)->whatsapp()->sendText($thread->contact_phone, $body)
            : $this->client->sendText($thread->contact_phone, $body);

        app(IntegrationEngine::class)->logWhatsAppText($thread->contact_phone, $body, $res, $viaConnector);

        $finalStatus = $res['success']
            ? ($res['status'] === 'dry_run' ? 'dry_run' : WaMessage::STATUS_SENT)
            : WaMessage::STATUS_FAILED;

        $msg->update([
            'wa_message_id' => $res['wa_message_id'],
            'status'        => $finalStatus,
            'error'         => $res['error'],
            'payload'       => $res['raw'],
        ]);

        // ── UPDATE thread activity (for inbox sorting / preview) ────────────────
        $now = Carbon::now();
        $thread->update([
            'last_message_at'  => $now,
            'last_outbound_at' => $now,
            'last_direction'   => 'outbound',
            'last_preview'     => mb_substr($body, 0, 160),
        ]);

        // ── AUDIT ──────────────────────────────────────────────────────────────
        AuditLog::event('whatsapp_sent', $actorId, [
            'thread_id'  => $thread->id,
            'message_id' => $msg->id,
            'phone'      => $thread->contact_phone,
            'category'   => $category,
            'status'     => $finalStatus,
            'dry_run'    => (bool) config('whatsapp.dry_run'),
        ], [
            'module'         => 'communication',
            'auditable_type' => WaMessage::class,
            'auditable_id'   => $msg->id,
        ]);

        return [
            'ok'      => $res['success'],
            'reason'  => $res['error'],
            'thread'  => $thread,
            'message' => $msg->fresh(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CONSENT GATE (DPDP)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Decide whether we're allowed to message this thread for this category.
     *
     *  - Known patient → must have GRANTED the matching consent purpose
     *    (service = whatsapp_comms, marketing = marketing_promotions).
     *  - Unknown number, marketing → always blocked (no lawful basis).
     *  - Unknown number, service → allowed ONLY as a reply inside the 24-hour
     *    window (the patient messaged us first = implied basis to reply).
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function consentGate(WaThread $thread, string $category, bool $isTemplate = false): array
    {
        $patient = $thread->patient;

        $key = $category === 'marketing'
            ? config('whatsapp.consent.marketing_purpose_key', 'marketing_promotions')
            : config('whatsapp.consent.service_purpose_key', 'whatsapp_comms');

        if ($patient) {
            $purpose = ConsentPurpose::where('key', $key)->first();
            if (! $purpose) {
                return ['allowed' => false, 'reason' => "Consent purpose '{$key}' is missing — run ConsentPurposeSeeder."];
            }

            $consent = PatientConsent::where('patient_id', $patient->id)
                ->where('consent_purpose_id', $purpose->id)
                ->first();

            if ($consent && $consent->isGranted()) {
                return ['allowed' => true, 'reason' => null];
            }

            return ['allowed' => false, 'reason' => "Patient has not granted '{$purpose->name}' consent (DPDP)."];
        }

        // No patient record on this number.
        // Templates are business-initiated, so they always need a consented
        // patient on file — we can't establish a lawful basis otherwise.
        if ($isTemplate) {
            return ['allowed' => false, 'reason' => 'Templates need a known, consented patient. Link this number to a patient first.'];
        }

        if ($category === 'marketing') {
            return ['allowed' => false, 'reason' => 'Marketing needs an explicit patient consent record; this number is not a known patient.'];
        }

        if ($thread->isWindowOpen()) {
            return ['allowed' => true, 'reason' => null];
        }

        return ['allowed' => false, 'reason' => 'No patient consent on file and the 24-hour reply window is closed. Link this number to a consented patient, or send an approved template.'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  TEMPLATES (business-initiated — allowed OUTSIDE the 24h window)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send a pre-approved template message (e.g. a reminder or recall).
     *
     * @param string $phone        recipient (any format).
     * @param string $templateKey  a key from config('whatsapp.templates').
     * @param array  $vars         variable values, keyed by the template's body_vars
     *                             names, e.g. ['name'=>'Asha','date'=>'1 Jul','time'=>'4 PM'].
     * @param array  $opts         patient_id, lead_id, sent_by_id.
     */
    public function sendTemplate(string $phone, string $templateKey, array $vars = [], array $opts = []): array
    {
        $def = config('whatsapp.templates.' . $templateKey);
        if (! $def) {
            return ['ok' => false, 'reason' => "Unknown template '{$templateKey}'.", 'thread' => null, 'message' => null];
        }

        $category = ($def['category'] ?? 'service') === 'marketing' ? 'marketing' : 'service';
        $actorId  = $opts['sent_by_id'] ?? Auth::id();

        // Idempotency: if a dedup_key was supplied and a non-failed message with
        // that key already exists, skip — so a daily reminder job never sends the
        // same reminder twice.
        if (! empty($opts['dedup_key'])) {
            $already = WaMessage::where('template_payload->dedup_key', $opts['dedup_key'])
                ->whereIn('status', ['queued', 'sent', 'delivered', 'read', 'dry_run'])
                ->exists();
            if ($already) {
                return ['ok' => true, 'reason' => 'already_sent', 'skipped' => true, 'thread' => null, 'message' => null];
            }
        }

        $thread = $this->resolveThread($phone, $opts);

        // ── DPDP CONSENT GATE (template mode — window does not apply) ───────────
        $gate = $this->consentGate($thread, $category, isTemplate: true);
        if (! $gate['allowed']) {
            AuditLog::event('whatsapp_send_blocked', $actorId, [
                'thread_id' => $thread->id,
                'phone'     => $thread->contact_phone,
                'category'  => $category,
                'template'  => $templateKey,
                'reason'    => $gate['reason'],
            ], [
                'module'         => 'communication',
                'auditable_type' => WaThread::class,
                'auditable_id'   => $thread->id,
            ]);

            return ['ok' => false, 'reason' => $gate['reason'], 'thread' => $thread, 'message' => null];
        }

        // Order the variables as the approved template expects ({{1}}, {{2}}, …).
        $ordered    = $this->orderedVars($def, $vars);
        $components = $this->buildBodyComponents($ordered);
        $preview    = $this->fillSample($def['sample'] ?? '', $ordered);

        // ── RECORD ─────────────────────────────────────────────────────────────
        $msg = $thread->messages()->create([
            'channel'          => 'whatsapp',
            'direction'        => WaMessage::OUTBOUND,
            'to_phone'         => $thread->contact_phone,
            'type'             => 'template',
            'body'             => $preview, // human-readable preview for the inbox
            'template_name'    => $def['meta_name'] ?? $templateKey,
            'template_payload' => [
                'key'        => $templateKey,
                'vars'       => $ordered,
                'components' => $components,
                'dedup_key'  => $opts['dedup_key'] ?? null,
            ],
            'status'           => WaMessage::STATUS_QUEUED,
            'sent_by_id'       => $actorId,
        ]);

        // ── SEND ─────────────────────────────────────────────────────────────
        // Phase 7: same Integration Engine routing as sendText() above.
        $viaConnector = Feature::enabled('integration.whatsapp');
        $metaName     = $def['meta_name'] ?? $templateKey;
        $language     = $def['language'] ?? 'en';
        $res = $viaConnector
            ? app(IntegrationEngine::class)->whatsapp()->sendTemplate($thread->contact_phone, $metaName, $language, $components)
            : $this->client->sendTemplate($thread->contact_phone, $metaName, $language, $components);

        app(IntegrationEngine::class)->logWhatsAppTemplate($thread->contact_phone, $metaName, $language, $components, $res, $viaConnector);

        $finalStatus = $res['success']
            ? ($res['status'] === 'dry_run' ? 'dry_run' : WaMessage::STATUS_SENT)
            : WaMessage::STATUS_FAILED;

        $msg->update([
            'wa_message_id' => $res['wa_message_id'],
            'status'        => $finalStatus,
            'error'         => $res['error'],
            'payload'       => $res['raw'],
        ]);

        // ── THREAD ACTIVITY (a template does NOT open the 24h window) ───────────
        $now = Carbon::now();
        $thread->update([
            'last_message_at'  => $now,
            'last_outbound_at' => $now,
            'last_direction'   => 'outbound',
            'last_preview'     => mb_substr($preview, 0, 160),
        ]);

        // ── AUDIT ──────────────────────────────────────────────────────────────
        AuditLog::event('whatsapp_template_sent', $actorId, [
            'thread_id'  => $thread->id,
            'message_id' => $msg->id,
            'phone'      => $thread->contact_phone,
            'template'   => $templateKey,
            'category'   => $category,
            'status'     => $finalStatus,
            'dry_run'    => (bool) config('whatsapp.dry_run'),
        ], [
            'module'         => 'communication',
            'auditable_type' => WaMessage::class,
            'auditable_id'   => $msg->id,
        ]);

        return ['ok' => $res['success'], 'reason' => $res['error'], 'thread' => $thread, 'message' => $msg->fresh()];
    }

    /** Put the supplied vars into the order the template body expects. */
    protected function orderedVars(array $def, array $vars): array
    {
        $names = $def['body_vars'] ?? [];
        $out   = [];
        foreach ($names as $name) {
            $out[$name] = (string) ($vars[$name] ?? '');
        }
        return $out;
    }

    /** Build Meta's "components" payload from ordered body variables. */
    protected function buildBodyComponents(array $orderedVars): array
    {
        if (empty($orderedVars)) {
            return [];
        }

        $parameters = [];
        foreach ($orderedVars as $value) {
            $parameters[] = ['type' => 'text', 'text' => $value];
        }

        return [[
            'type'       => 'body',
            'parameters' => $parameters,
        ]];
    }

    /** Replace {{1}}, {{2}} … in the local sample with the ordered values. */
    protected function fillSample(string $sample, array $orderedVars): string
    {
        $i = 1;
        foreach ($orderedVars as $value) {
            $sample = str_replace('{{' . $i . '}}', $value, $sample);
            $i++;
        }
        return $sample;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  THREAD RESOLUTION
    // ──────────────────────────────────────────────────────────────────────────

    /** Find the existing thread for this phone, or open a new one. */
    protected function resolveThread(string $phone, array $opts): WaThread
    {
        $normalized = $this->client->normalizePhone($phone);

        $thread = WaThread::where('channel', 'whatsapp')
            ->where('contact_phone', $normalized)
            ->first();

        if ($thread) {
            // Backfill a patient/lead link if the caller supplied one.
            $patch = [];
            if (! empty($opts['patient_id']) && ! $thread->patient_id) {
                $patch['patient_id'] = $opts['patient_id'];
            }
            if (! empty($opts['lead_id']) && ! $thread->lead_id) {
                $patch['lead_id'] = $opts['lead_id'];
            }
            if ($patch) {
                $thread->update($patch);
            }
            return $thread;
        }

        $patient = $this->matchPatient($normalized, $opts);

        return WaThread::create([
            'channel'       => 'whatsapp',
            'contact_phone' => $normalized,
            'patient_id'    => $opts['patient_id'] ?? $patient?->id,
            'lead_id'       => $opts['lead_id'] ?? null,
            'contact_name'  => $opts['contact_name'] ?? $patient?->name,
            'status'        => 'open',
        ]);
    }

    /**
     * Try to match a known patient by phone. `phone` is intentionally NOT
     * encrypted (Phase A), so we can query it. Stored formats vary, so we match
     * on the last 10 digits.
     */
    protected function matchPatient(string $normalized, array $opts): ?Patient
    {
        if (! empty($opts['patient_id'])) {
            return Patient::find($opts['patient_id']);
        }

        $last10 = substr($normalized, -10);
        if (strlen($last10) < 10) {
            return null;
        }

        return Patient::where('phone', 'like', '%' . $last10)->first();
    }
}
