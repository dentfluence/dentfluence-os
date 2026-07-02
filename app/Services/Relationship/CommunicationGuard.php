<?php

namespace App\Services\Relationship;

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
 *   - Quiet hours: optional window (config), relaxable by urgency.
 *   - Urgency: may relax FREQUENCY and QUIET-HOURS only — never consent.
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
    // Consent seam (Phase 0 stub — real lookup arrives in Phase 4)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Does the patient consent to this contact? Phase 0 is a foundation-only
     * seam that returns true (enforcement is disabled by default anyway). It is
     * `protected` so tests can force `false` and prove that urgency can NEVER
     * override consent. Phase 4 wires this to the DPDP consent domain.
     */
    protected function patientHasConsent(int $relationshipId, string $channel, string $type): bool
    {
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private guard checks
    // ─────────────────────────────────────────────────────────────────────────

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
