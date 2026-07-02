<?php

namespace App\Abdm\Recording;

use App\Abdm\AbdmManager;
use App\Abdm\Fhir\Bundles\OpConsultationBundleAssembler;
use App\Abdm\Fhir\Bundles\PrescriptionBundleAssembler;
use App\Abdm\Fhir\FhirMappingEngine;
use App\Models\Consultation;
use App\Models\FhirDocument;
use App\Models\Prescription\Prescription;

/**
 * Turns a finalized clinical record into a stored FHIR document.
 *
 * This is the bridge from "preview command" to "real pipeline". It is called by the
 * model observers when a consultation/prescription is finalized. CRUCIALLY it is a
 * no-op unless ABDM is enabled (app_settings.abdm_enabled = '1'), so today — and
 * until you flip the switch — it changes nothing.
 *
 * NOTE: when ABDM is switched on, document generation should move to a queued job
 * (Phase 3 Sync Engine) so it never slows down a save. For now it runs inline only
 * because the flag is off.
 */
class AbdmRecorder
{
    public function __construct(
        private AbdmManager $manager,
        private FhirMappingEngine $engine,
        private OpConsultationBundleAssembler $consultationBundle,
        private PrescriptionBundleAssembler $prescriptionBundle,
    ) {}

    public function recordConsultation(Consultation $c): ?FhirDocument
    {
        if (! $this->manager->enabled())   return null;   // master kill switch
        if ($c->status !== 'completed')    return null;   // only finalized visits

        $bundle = $this->consultationBundle->assemble($c);
        return $this->engine->persistBundle($c, $bundle, 'op_consultation', 'final');
    }

    public function recordPrescription(Prescription $rx): ?FhirDocument
    {
        if (! $this->manager->enabled()) return null;

        $finalized = ['issued', 'printed', 'whatsapp_sent', 'email_sent', 'revised'];
        if (! in_array($rx->status, $finalized, true)) return null;

        $bundle = $this->prescriptionBundle->assemble($rx);
        return $this->engine->persistBundle($rx, $bundle, 'prescription', 'final');
    }
}
