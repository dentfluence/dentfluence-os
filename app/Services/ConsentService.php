<?php

namespace App\Services;

use App\Models\ConsentLog;
use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ConsentService
 * --------------
 * The single "brain" for DPDP consent. Every grant / withdraw goes through
 * here so the web pages, the patient portal (later) and the API behave the
 * same way and every change is written to the tamper-evident consent_logs.
 *
 * Two things happen on every change:
 *   1. patient_consents is updated to the new CURRENT state.
 *   2. a new row is appended to consent_logs, hash-chained to the previous one
 *      so the history cannot be quietly altered (DPDP item 5.6).
 *
 * Controllers stay thin: they validate input and call these methods.
 */
class ConsentService
{
    // ──────────────────────────────────────────────────────────────────────
    //  READ
    // ──────────────────────────────────────────────────────────────────────

    /** All active purposes, in display order. */
    public function purposes(): Collection
    {
        return ConsentPurpose::active()->get();
    }

    /**
     * The patient's consent picture: every active purpose paired with the
     * patient's current row (or null if they've never been asked). Perfect for
     * rendering the capture screen.
     *
     * @return Collection<int, array{purpose: ConsentPurpose, consent: ?PatientConsent, granted: bool}>
     */
    public function stateFor(Patient $patient): Collection
    {
        $existing = PatientConsent::where('patient_id', $patient->id)
            ->get()
            ->keyBy('consent_purpose_id');

        return $this->purposes()->map(function (ConsentPurpose $purpose) use ($existing) {
            $consent = $existing->get($purpose->id);
            return [
                'purpose' => $purpose,
                'consent' => $consent,
                'granted' => $consent?->isGranted() ?? false,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    //  WRITE
    // ──────────────────────────────────────────────────────────────────────

    /** Record that a patient GRANTS consent for one purpose. */
    public function grant(Patient $patient, ConsentPurpose $purpose, array $opts = []): PatientConsent
    {
        return $this->apply($patient, $purpose, PatientConsent::GRANTED, $opts);
    }

    /** Record that a patient WITHDRAWS consent for one purpose. */
    public function withdraw(Patient $patient, ConsentPurpose $purpose, array $opts = []): PatientConsent
    {
        return $this->apply($patient, $purpose, PatientConsent::WITHDRAWN, $opts);
    }

    /**
     * Apply a whole form at once.
     *
     * @param array<int, bool> $decisions  map of consent_purpose_id => granted?
     * @return int number of purposes whose state actually changed
     */
    public function setMany(Patient $patient, array $decisions, array $opts = []): int
    {
        $purposes = $this->purposes()->keyBy('id');
        $changed  = 0;

        foreach ($decisions as $purposeId => $granted) {
            $purpose = $purposes->get((int) $purposeId);
            if (! $purpose) {
                continue; // ignore unknown / inactive purposes
            }

            $current = PatientConsent::where('patient_id', $patient->id)
                ->where('consent_purpose_id', $purpose->id)
                ->first();

            $targetStatus = $granted ? PatientConsent::GRANTED : PatientConsent::WITHDRAWN;

            // Skip if nothing actually changes — avoids noise in the log.
            if ($current && $current->status === $targetStatus && $current->isGranted() === (bool) $granted) {
                continue;
            }

            $this->apply($patient, $purpose, $targetStatus, $opts);
            $changed++;
        }

        return $changed;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  INTEGRITY (DPDP 5.6)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Recompute the hash chain for a patient and confirm nothing was tampered.
     * Returns true if every stored hash matches what it should be.
     */
    public function verifyChain(Patient $patient): bool
    {
        $prevHash = null;

        $logs = ConsentLog::where('patient_id', $patient->id)
            ->orderBy('id')
            ->get();

        foreach ($logs as $log) {
            $expected = $this->computeHash($prevHash, $this->canonical($log->getAttributes()));
            if (! hash_equals($expected, (string) $log->hash) || (string) $log->prev_hash !== (string) $prevHash) {
                return false;
            }
            $prevHash = $log->hash;
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  INTERNAL
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Core write: update current state + append a hash-chained log row,
     * inside one transaction so the two never drift apart.
     */
    protected function apply(Patient $patient, ConsentPurpose $purpose, string $status, array $opts): PatientConsent
    {
        $method = $opts['method'] ?? 'web';
        $userId = $opts['captured_by'] ?? Auth::id();
        $now    = Carbon::now();

        return DB::transaction(function () use ($patient, $purpose, $status, $method, $userId, $now, $opts) {

            // 1) Current state
            $consent = PatientConsent::firstOrNew([
                'patient_id'         => $patient->id,
                'consent_purpose_id' => $purpose->id,
            ]);

            $consent->status          = $status;
            $consent->purpose_version = $purpose->version;
            $consent->capture_method  = $method;
            $consent->captured_by     = $userId;
            $consent->notes           = $opts['notes'] ?? $consent->notes;
            // Parental / guardian consent for minors (DPDP 5.5)
            $consent->on_behalf_of          = $opts['on_behalf_of'] ?? 'self';
            $consent->guardian_name         = $opts['guardian_name'] ?? null;
            $consent->guardian_relationship = $opts['guardian_relationship'] ?? null;

            if ($status === PatientConsent::GRANTED) {
                $consent->granted_at   = $now;
                $consent->withdrawn_at = null;
                $consent->expires_at   = $opts['expires_at'] ?? null;
            } else { // withdrawn
                $consent->withdrawn_at = $now;
            }
            $consent->save();

            // 2) Append to the tamper-evident log
            $event = $status === PatientConsent::GRANTED ? 'granted' : 'withdrawn';
            $this->writeLog($patient, $purpose, $event, $method, $userId, $now, $opts);

            return $consent;
        });
    }

    /** Append one hash-chained row to consent_logs. */
    protected function writeLog(Patient $patient, ConsentPurpose $purpose, string $event, string $method, ?int $userId, Carbon $now, array $opts = []): ConsentLog
    {
        // Link to the previous row for THIS patient (per-patient chain).
        $prevHash = ConsentLog::where('patient_id', $patient->id)
            ->orderByDesc('id')
            ->value('hash');

        $attributes = [
            'patient_id'         => $patient->id,
            'consent_purpose_id' => $purpose->id,
            'purpose_key'        => $purpose->key,
            'event'              => $event,
            'purpose_version'    => $purpose->version,
            'capture_method'     => $method,
            'captured_by'        => $userId,
            'ip_address'         => request()->ip(),
            'user_agent'         => substr((string) request()->userAgent(), 0, 255),
            'snapshot'           => [
                'purpose_name'          => $purpose->name,
                'category'              => $purpose->category,
                'is_mandatory'          => $purpose->is_mandatory,
                'patient_code'          => $patient->patient_id ?? null,
                'on_behalf_of'          => $opts['on_behalf_of'] ?? 'self',
                'guardian_name'         => $opts['guardian_name'] ?? null,
                'guardian_relationship' => $opts['guardian_relationship'] ?? null,
            ],
            'created_at'         => $now->toDateTimeString(),
            'prev_hash'          => $prevHash,
        ];

        $attributes['hash'] = $this->computeHash($prevHash, $this->canonical($attributes));

        return ConsentLog::create($attributes);
    }

    /**
     * Build the exact, ordered set of fields that the hash is computed over.
     * Must be identical on write and on verify — so the field list is fixed
     * here and nowhere else.
     */
    protected function canonical(array $a): array
    {
        return [
            'patient_id'         => $a['patient_id'] ?? null,
            'consent_purpose_id' => $a['consent_purpose_id'] ?? null,
            'purpose_key'        => $a['purpose_key'] ?? null,
            'event'              => $a['event'] ?? null,
            'purpose_version'    => $a['purpose_version'] ?? null,
            'capture_method'     => $a['capture_method'] ?? null,
            'captured_by'        => $a['captured_by'] ?? null,
            'ip_address'         => $a['ip_address'] ?? null,
            'user_agent'         => $a['user_agent'] ?? null,
            'snapshot'           => is_array($a['snapshot'] ?? null) ? $a['snapshot'] : (json_decode($a['snapshot'] ?? 'null', true)),
            'created_at'         => $a['created_at'] instanceof Carbon ? $a['created_at']->toDateTimeString() : ($a['created_at'] ?? null),
        ];
    }

    /** sha256 of "prev_hash | canonical-json". */
    protected function computeHash(?string $prevHash, array $canonical): string
    {
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', ((string) $prevHash) . '|' . $json);
    }
}
