<?php

namespace App\Observers;

use App\Abdm\Recording\AbdmRecorder;
use App\Models\Prescription\Prescription;
use Illuminate\Support\Facades\Log;

/**
 * Auto-generates a FHIR document when a prescription reaches a finalized status.
 *
 * Gated inside AbdmRecorder (no-op while ABDM is disabled) and wrapped in try/catch
 * so it can never break the prescription save.
 */
class AbdmPrescriptionObserver
{
    public function __construct(private AbdmRecorder $recorder) {}

    public function saved(Prescription $prescription): void
    {
        try {
            $this->recorder->recordPrescription($prescription);
        } catch (\Throwable $e) {
            Log::error('[ABDM] prescription FHIR record failed', [
                'prescription_id' => $prescription->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
