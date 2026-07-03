<?php

namespace App\Services\Relationship;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Relationship;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CommunicationGuard — Phase 5 (Relationship Engine), hardened in Phase 0.
 *
 * Silent gatekeeper for all outbound communications.
 * canContact() MUST be called before queuing any outbound message.
 *
 * ── Legacy rules (config/relationship_rules.php['communication_guard']) ──
 *   1. Same channel not used twice within 24h for the same relationship
 *   2. No more than 3 total contacts (any channel) in 7 days
 *   3. If a birthday message was sent today → block promotional types
 *
 * ── Phase 0 hardening (ALL DORMANT BY DEFAULT — no behaviour change) ──
 *   - Fail-closed: when feature flag 'guard.fail_closed' is ON, a guard error
 *     BLOCKS instead of failing open. Default OFF preserves current fail-open.
 *   - Consent gate: when 'guard.consent_required' is ON, no-consent BLOCKS.
 *     INVARIANT: consent is checked FIRST and is NEVER relaxed by urgency.
 *     Phase 4: wired to the real DPDP consent domain for WhatsApp
 *     (see hasWhatsAppConsent()) — still dormant until the flag flips.
 *   - Quiet hours: optional window (config), relaxable by urgency.
 *   - Urgency: may relax FREQUENCY and QUIET-HOURS only — never consent.
 *
 * ── Phase 4 (gated behind guard.full_8factor, default OFF) ──
 *   - Do-Not-Contact: hard block, all channels, NEVER relaxed by urgency
 *     (same tier as consent).
 *   - Channel eligibility: mechanical — blocks only if the relationship has
 *     no contact detail for that channel (no phone for whatsapp/call/sms, no
 *     email for email). Does not enforce per-channel opt-outs (none exist yet).
 *   - Preference (preferred_channel): informational only. Logged in the
 *     decision's factors for every call while the flag is on; never blocks.
 *   - Context: declared seam, always passes. No business rule exists for
 *     "relationship context" yet — see docs/phase-4/README.md before adding one.
 *
 * With every flag at its default (off) and $isUrgent = false, decide() reduces
 * EXACTLY to the original three rules + fail-open. This class does not change
 * user-facing behaviour in Phase 0.
 *
 * Design principles:
 *   - Never throws. canContact() returns bool; decide() returns a GuardDecision.
 *   - All guard checks are queryable via relationship_contact_log.
 *   - log() must be called AFTER a successful send to record the contact.
 */
class CommunicationGuard
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Legacy boolean API — unchanged signature and default behaviour.
     * TRUE = allowed, FALSE = blocked (silently).
     *
     * @param  string  $channel  call / whatsapp / sms / email
     * @param  string  $type     birthday / appointment_reminder / marketing / ...
     * @param  bool    $isUrgent Urgent messages may relax frequency & quiet-hours
     *                           (NEVER consent). Defaults false → current behaviour.
     */
    public function canContact(int $relationshipId, string $channel, string $type = 'general', bool $isUrgent = false): bool
    {
        return $this->decide($relationshipId, $channel, $type, $isUrgent)->allowed();
    }

    /**
     * Structured decision (Phase 0 foundation) — returns WHY, for the Decision
     * Log and future explainability. canContact() delegates here.
     */
    public function decide(int $relationshipId, string $channel, string $type = 'general', bool $isUrgent = false): GuardDecision
    {
        $config  = config('relationship_rules.communication_guard', []);
        $factors = ['channel' => $channel, 'type' => $type, 'urgent' => $isUrgent];

        try {
            // ── 1. CONSENT — always first, NEVER relaxed by urgency ──────────
            // Dormant unless the flag is on (default off → allow).
            if ($this->flagEnabled('guard.consent_required')
                && ! $this->patientHasConsent($relationshipId, $channel, $type)) {
                return GuardDecision::block('consent', $factors + ['blocked_by' => 'consent']);
            }

            // ── 1b. DO-NOT-CONTACT, CHANNEL ELIGIBILITY, PREFERENCE, CONTEXT ──
            // All gated behind guard.full_8factor (default off) — this whole
            // block is a no-op today. Do-Not-Contact is checked here (same
            // tier as consent, NEVER relaxed by urgency) because "do not
            // contact me" has only one reasonable reading. Preference is
            // logged only — it never blocks (see class docblock). Context is
            // a declared seam with no rule yet — see docs/phase-4/README.md.
            if ($this->flagEnabled('guard.full_8factor')) {
                $relationship = Relationship::find($relationshipId);

                if ($relationship?->do_not_contact) {
                    return GuardDecision::block('do_not_contact', $factors + ['blocked_by' => 'do_not_contact']);
                }

                if (! $this->isChannelEligible($relationship, $channel)) {
                    return GuardDecision::block('channel_ineligible', $factors + ['blocked_by' => 'channel']);
                }

                $factors['preferred_channel'] = $relationship?->preferred_channel;
                // Context: seam only, always passes — no business rule defined yet.
                $factors['context'] = 'not_evaluated';
            }

            // Whether urgency may relax the frequency / quiet-hours families.
            $relaxes         = (array) ($config['urgency']['relaxes'] ?? []);
            $relaxFrequency  = $isUrgent && in_array('frequency', $relaxes, true);
            $relaxQuietHours = $isUrgent && in_array('quiet_hours', $relaxes, true);

            // ── 2. Same-channel cooldown (frequency family) ──────────────────
            if (! $relaxFrequency && $this->isSameChannelBlocked($relationshipId, $channel, $config)) {
                return GuardDecision::block('same_channel_cooldown', $factors);
            }

            // ── 3. Total contacts window (frequency family) ──────────────────
            if (! $relaxFrequency && $this->isTotalContactsExceeded($relationshipId, $config)) {
                return GuardDecision::block('total_contacts_exceeded', $factors);
            }

            // ── 4. Quiet hours (foundation; disabled by default) ─────────────
            if (! $relaxQuietHours && $this->isQuietHoursBlocked($config)) {
                return GuardDecision::block('quiet_hours', $factors);
            }

            // ── 5. Birthday → block promotional (unchanged) ──────────────────
            if ($this->isBirthdayBlockActive($relationshipId, $type, $config)) {
                return GuardDecision::block('birthday_block', $factors);
            }

            return GuardDecision::allow($factors);

        } catch (\Throwable $e) {
            // Guard failure must never break the calling action.
            // Fail-closed only when explicitly enabled; otherwise fail open
            // (the current behaviour).
            $failClosed = $this->flagEnabled('guard.fail_closed');

            Log::warning('CommunicationGuard::decide failed', [
                'relationship_id' => $relationshipId,
                'channel'         => $channel,
                'fail_closed'     => $failClosed,
                'error'           => $e->getMessage(),
            ]);

            return $failClosed
                ? GuardDecision::block('guard_error_fail_closed', $factors)
                : GuardDecision::allow($factors + ['fail_open' => true]);
        }
    }

    /**
     * Record a contact attempt. Call AFTER a successful send — not before.
     *
     * @param  string  $channel  call / whatsapp / sms / email
     * @param  string  $type     appointment_reminder / recall / birthday / ...
     */
    public function log(int $relationshipId, string $channel, string $type): void
    {
        try {
            DB::table('relationship_contact_log')->insert([
                'relationship_id' => $relationshipId,
                'channel'         => $channel,
                'type'            => $type,
                'contacted_at'    => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CommunicationGuard::log failed', [
                'relationship_id' => $relationshipId,
                'channel'         => $channel,
                'type'            => $type,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consent (Phase 4 — wired to the real DPDP consent domain)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Does the patient consent to this contact? Only WhatsApp has a real
     * consent-purpose defined today (`whatsapp_comms` / `marketing_promotions` —
     * see `hasWhatsAppConsent()`). Other channels (call/sms/email) have no DPDP
     * consent-purpose infrastructure yet, so we don't invent a rule for them —
     * they pass through unaffected until that infra exists.
     *
     * Still `protected` so tests can force a result and prove urgency can NEVER
     * override consent — decide() checks this before anything urgency-relaxable.
     */
    protected function patientHasConsent(int $relationshipId, string $channel, string $type): bool
    {
        if ($channel !== 'whatsapp') {
            return true;
        }

        $patient = Relationship::find($relationshipId)?->patient; // hasOne primary patient (back-compat)

        return $this->hasWhatsAppConsent($patient, $type)['allowed'];
    }

    /**
     * Real DPDP WhatsApp consent check, patient-scoped (not thread-scoped), so
     * anything that needs "can we WhatsApp this patient a service/marketing
     * message" can call it directly — even before a WaThread exists (e.g. the
     * Prescription "Send WhatsApp" button). Mirrors the purpose-key mapping in
     * `OutboundMessageService::consentGate()`.
     *
     * Deliberately NOT wired INTO OutboundMessageService — that class already
     * has its own real, live, thread-aware consent gate (handles unknown
     * numbers + the 24h reply window, which this patient-only version can't).
     * Reconciling the *logic* (same PatientConsent/ConsentPurpose lookup) was
     * judged safer than merging the *code path* of an already-live send flow.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function hasWhatsAppConsent(?Patient $patient, string $type = 'service'): array
    {
        if (! $patient) {
            // No linked patient (e.g. lead-only relationship) — DPDP consent
            // infra here is patient-scoped only; nothing to check yet.
            return ['allowed' => true, 'reason' => null];
        }

        $config            = config('relationship_rules.communication_guard', []);
        $promotionalTypes  = $config['promotional_types'] ?? ['marketing', 'offer', 'recall_campaign', 'newsletter'];
        $key = in_array($type, $promotionalTypes, true)
            ? config('whatsapp.consent.marketing_purpose_key', 'marketing_promotions')
            : config('whatsapp.consent.service_purpose_key', 'whatsapp_comms');

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

    /**
     * Do-Not-Contact + channel eligibility ONLY — deliberately NOT the full
     * decide() pipeline, which also runs frequency/quiet-hours/birthday
     * checks. Those are scoped to batch/automated contact (recall, reminders,
     * marketing) and aren't appropriate to silently impose on a direct,
     * doctor-requested send like a prescription — a patient who got a recall
     * WhatsApp that morning should still be able to receive their
     * prescription the same day. Reused by any manually-triggered send that
     * wants the two hard/mechanical checks without the batch-messaging rules.
     *
     * Always evaluates (so callers can log/observe); callers decide whether
     * to enforce based on guard.full_8factor.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function checkDoNotContactAndChannel(int $relationshipId, string $channel): array
    {
        $relationship = Relationship::find($relationshipId);

        if ($relationship?->do_not_contact) {
            return ['allowed' => false, 'reason' => 'do_not_contact'];
        }

        if (! $this->isChannelEligible($relationship, $channel)) {
            return ['allowed' => false, 'reason' => 'channel_ineligible'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private guard checks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Channel eligibility (Phase 4): can this relationship even be reached on
     * this channel? Deliberately mechanical — "no phone number on file" isn't
     * a policy call, it's a fact. Only checks contact-detail presence; does
     * NOT check per-channel opt-outs (no such data exists yet — see
     * do_not_contact for the only opt-out signal implemented so far).
     */
    protected function isChannelEligible(?Relationship $relationship, string $channel): bool
    {
        if (! $relationship) {
            return true; // no relationship row to check against — don't invent a block
        }

        return match ($channel) {
            'whatsapp', 'call', 'sms' => ! empty($relationship->phone),
            'email'                   => ! empty($relationship->email),
            default                   => true, // unknown channel — don't block on ignorance
        };
    }

    /** Rule 1: same channel used for this relationship within N hours? */
    protected function isSameChannelBlocked(int $relationshipId, string $channel, array $config): bool
    {
        $cooldownHours = (int) ($config['same_channel_cooldown_hours'] ?? 24);
        $since = Carbon::now()->subHours($cooldownHours);

        return DB::table('relationship_contact_log')
            ->where('relationship_id', $relationshipId)
            ->where('channel', $channel)
            ->where('contacted_at', '>=', $since)
            ->exists();
    }

    /** Rule 2: contacted N+ times in the last M days? */
    protected function isTotalContactsExceeded(int $relationshipId, array $config): bool
    {
        $maxCount = (int) ($config['max_contacts_per_count'] ?? 3);
        $windowDays = (int) ($config['max_contacts_per_days'] ?? 7);
        $since = Carbon::now()->subDays($windowDays);

        $count = DB::table('relationship_contact_log')
            ->where('relationship_id', $relationshipId)
            ->where('contacted_at', '>=', $since)
            ->count();

        return $count >= $maxCount;
    }

    /**
     * Phase 0 foundation: quiet-hours window. Disabled by default
     * (config quiet_hours.enabled = false) → always returns false → no change.
     */
    protected function isQuietHoursBlocked(array $config): bool
    {
        $quiet = $config['quiet_hours'] ?? [];
        if (! ($quiet['enabled'] ?? false)) {
            return false;
        }

        $now   = Carbon::now();
        $start = Carbon::parse($quiet['start'] ?? '21:00');
        $end   = Carbon::parse($quiet['end'] ?? '08:00');

        // Overnight window (e.g. 21:00 → 08:00) wraps midnight.
        if ($start->greaterThan($end)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    /** Rule 3: birthday sent today → block promotional contact types. */
    protected function isBirthdayBlockActive(int $relationshipId, string $type, array $config): bool
    {
        if (!($config['birthday_blocks_promotional'] ?? true)) {
            return false;
        }

        $promotionalTypes = $config['promotional_types'] ?? ['marketing', 'offer', 'recall_campaign', 'newsletter'];
        if (!in_array($type, $promotionalTypes)) {
            return false;
        }

        return DB::table('relationship_contact_log')
            ->where('relationship_id', $relationshipId)
            ->where('type', 'birthday')
            ->whereDate('contacted_at', today())
            ->exists();
    }

    /** Resolve a feature flag defensively — never let flag resolution break the guard. */
    private function flagEnabled(string $key): bool
    {
        try {
            return Feature::enabled($key);
        } catch (\Throwable $e) {
            return false; // safest default: treat as legacy behaviour
        }
    }
}
