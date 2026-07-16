<?php

namespace App\Services\CaseAcceptance;

use App\Models\CaseConsentSnapshot;
use App\Models\JourneySentSnapshot;
use App\Models\PatientJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * JourneySnapshotService — writes the two IMMUTABLE snapshots and enforces
 * supersede-on-edit (frozen §6/§8):
 *   • sent snapshot   (pinned at SEND)   — the exact assembled DTO + prices
 *   • consent snapshot (pinned at ACCEPT) — what the patient confirmed
 * A sent journey is never mutated in place; editing it creates a NEW journey
 * (new token) and marks the old one superseded.
 */
class JourneySnapshotService
{
    public function __construct(
        private JourneyAssembler $assembler,
        private CaseSelectionService $selections,
    ) {}

    /**
     * Pin the sent snapshot, issue a public token, and move the journey to
     * "sent". Curations are frozen from this point (part of the pinned set).
     */
    public function send(PatientJourney $journey, int $expiryDays = 30): PatientJourney
    {
        return DB::transaction(function () use ($journey, $expiryDays) {
            $dto      = $this->assembler->assemble($journey);
            $estimate = $dto['estimate']['total'] ?? null;

            $journey->forceFill([
                'token'               => $journey->token ?: $this->uniqueToken(),
                'status'              => 'sent',
                'sent_at'             => now(),
                'expires_at'          => now()->addDays($expiryDays),
                'pinned_kb_version'   => $journey->decisionTree ? null : $journey->pinned_kb_version,
                'pinned_tree_version' => $journey->decisionTree?->version,
            ])->save();

            JourneySentSnapshot::updateOrCreate(
                ['patient_journey_id' => $journey->id],
                ['snapshot' => $dto, 'estimate_total' => $estimate, 'pinned_at' => now()]
            );

            return $journey->refresh();
        });
    }

    /**
     * Pin the consent snapshot at accept. Immutable record of shown + chosen +
     * prices, with request metadata for auditability.
     */
    public function consent(PatientJourney $journey, ?string $ip = null, ?string $userAgent = null): CaseConsentSnapshot
    {
        $dto      = $this->assembler->assemble($journey);
        $estimate = $this->selections->runningEstimate($journey);

        return CaseConsentSnapshot::create([
            'patient_journey_id' => $journey->id,
            'snapshot'           => $dto,
            'estimate_total'     => $estimate,
            'taken_at'           => now(),
            'ip'                 => $ip,
            'user_agent'         => $userAgent ? Str::limit($userAgent, 250, '') : null,
        ]);
    }

    /**
     * Edit-after-send = supersede, not mutate. Clone the sent journey into a new
     * draft (copying curations), link the old one via superseded_by, and expire
     * it. The caller re-curates/re-sends the returned draft.
     */
    public function supersede(PatientJourney $journey): PatientJourney
    {
        return DB::transaction(function () use ($journey) {
            $clone = $journey->replicate([
                'token', 'status', 'sent_at', 'expires_at', 'view_count',
                'last_viewed_at', 'superseded_by',
            ]);
            $clone->status = 'draft';
            $clone->token = null;
            $clone->save();

            foreach ($journey->curations as $curation) {
                $copy = $curation->replicate(['patient_journey_id']);
                $copy->patient_journey_id = $clone->id;
                $copy->save();
            }

            $journey->forceFill([
                'superseded_by' => $clone->id,
                'expires_at'    => now(),
            ])->save();

            return $clone;
        });
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (PatientJourney::where('token', $token)->exists());

        return $token;
    }
}
