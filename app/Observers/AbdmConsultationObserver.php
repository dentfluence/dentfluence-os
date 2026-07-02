<?php

namespace App\Observers;

use App\Abdm\Recording\AbdmRecorder;
use App\Models\Consultation;
use Illuminate\Support\Facades\Log;

/**
 * Auto-generates a FHIR document when a consultation is saved in 'completed' state.
 *
 * The actual work is gated inside AbdmRecorder (no-op while ABDM is disabled), and
 * everything here is wrapped in try/catch so an ABDM hiccup can NEVER break or roll
 * back a clinical save. While the flag is off this does essentially nothing.
 */
class AbdmConsultationObserver
{
    public function __construct(private AbdmRecorder $recorder) {}

    public function saved(Consultation $consultation): void
    {
        try {
            $this->recorder->recordConsultation($consultation);
        } catch (\Throwable $e) {
            Log::error('[ABDM] consultation FHIR record failed', [
                'consultation_id' => $consultation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
