<?php

namespace App\Services\Relationship;

use App\Models\Relationship;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Support\Facades\Log;

/**
 * CommunicationEngine — Phase 4 single send gateway.
 *
 * Blueprint spec (docs/target-architecture-engine-first.md, section C1):
 * "No module may ever message a patient except through this engine."
 *
 * BUILT AND TESTED, BUT NOT YET WIRED IN. No existing call site was migrated
 * to use send() in this pass — see docs/phase-4/README.md ("Piece 4") for
 * why: the best-fit candidates (scheduled appointment reminders, scheduled
 * review requests) both run unsupervised via cron, and routing a live daily
 * patient-facing send through a brand-new code path for the first time
 * felt like something that should be watched, not shipped while unattended.
 * `comm.single_gateway` (declared, default off) is a documentation flag for
 * now — nothing reads it, because nothing calls send() yet.
 *
 * send() ALWAYS evaluates CommunicationGuard::decide() first — shadow-logged
 * whether or not any Guard flag is on, only actually blocks if a flag is on
 * — then delegates real delivery to OutboundMessageService, which keeps its
 * own independent, ALWAYS-live consent gate untouched. That layering means
 * this engine can never be LESS safe than what exists today, even once
 * wired in: it can only add checks on top of the real gate, never remove it.
 *
 * Only WhatsApp has a real delivery path today (mirrors the rest of Phase 4
 * — SMS/email have no provider wired up). send() is intentionally narrow:
 * plain-text sends only. Template sends (used by the reminder scheduler)
 * aren't wrapped yet — that needs its own pass once a call site is actually
 * being migrated, so the dedup_key / template-approval semantics get the
 * same care this class got.
 */
class CommunicationEngine
{
    public function __construct(
        protected CommunicationGuard $guard,
        protected OutboundMessageService $outbound,
    ) {}

    /**
     * Send a plain-text WhatsApp message to a Relationship.
     *
     * @param  string  $intent  Guard 'type' — e.g. 'appointment_reminder', 'recall_6month', 'marketing'.
     * @param  array   $opts    channel (default whatsapp), urgent (bool), patient_id, sent_by_id.
     * @return array{ok: bool, reason: ?string, via: string}
     */
    public function send(Relationship $relationship, string $intent, string $body, array $opts = []): array
    {
        $channel = $opts['channel'] ?? 'whatsapp';

        $decision = $this->guard->decide($relationship->id, $channel, $intent, (bool) ($opts['urgent'] ?? false));
        if (! $decision->allowed()) {
            Log::info('CommunicationEngine: send blocked by Guard', [
                'relationship_id' => $relationship->id,
                'channel'         => $channel,
                'intent'          => $intent,
                'reason'          => $decision->reason(),
            ]);

            return ['ok' => false, 'reason' => $decision->reason(), 'via' => 'guard'];
        }

        if ($channel !== 'whatsapp') {
            return ['ok' => false, 'reason' => "channel '{$channel}' has no delivery path yet", 'via' => 'unsupported'];
        }

        if (empty($relationship->phone)) {
            return ['ok' => false, 'reason' => 'no phone on file', 'via' => 'unsupported'];
        }

        $promotionalTypes = config('relationship_rules.communication_guard.promotional_types', ['marketing', 'offer', 'recall_campaign', 'newsletter']);
        $category = in_array($intent, $promotionalTypes, true) ? 'marketing' : 'service';

        $result = $this->outbound->sendText($relationship->phone, $body, [
            'category'   => $category,
            'patient_id' => $opts['patient_id'] ?? $relationship->patient?->id,
            'sent_by_id' => $opts['sent_by_id'] ?? null,
        ]);

        if ($result['ok'] ?? false) {
            // Record the contact for the Guard's own frequency/cooldown checks.
            $this->guard->log($relationship->id, $channel, $intent);
        }

        return [
            'ok'     => (bool) ($result['ok'] ?? false),
            'reason' => $result['reason'] ?? null,
            'via'    => 'whatsapp',
        ];
    }
}
