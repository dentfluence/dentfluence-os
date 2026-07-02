<?php

namespace App\Console\Commands;

use App\Abdm\Fhir\FhirMappingEngine;
use App\Models\Patient;
use Illuminate\Console\Command;

/**
 * FhirPreviewPatient — ABDM Phase 2 · Slice 1
 *
 * Generates and shows the FHIR R4 Patient resource for a given patient, straight
 * from the mapping engine. This is the proof that the internal→FHIR pipeline works
 * end-to-end. Read-only by default; pass --save to also persist it to fhir_documents.
 *
 * Usage:
 *   php artisan abdm:fhir-preview 1            # by patients.id
 *   php artisan abdm:fhir-preview DF-00142     # by patients.patient_id
 *   php artisan abdm:fhir-preview 1 --save     # also store in fhir_documents
 */
class FhirPreviewPatient extends Command
{
    protected $signature = 'abdm:fhir-preview {patient : patients.id or patient_id} {--save : Persist to fhir_documents}';

    protected $description = 'Generate and display the FHIR Patient resource for a patient.';

    public function handle(FhirMappingEngine $engine): int
    {
        $key = $this->argument('patient');

        $patient = Patient::with('identifiers')
            ->where('id', $key)
            ->orWhere('patient_id', $key)
            ->first();

        if (! $patient) {
            $this->error("Patient not found: {$key}");
            return self::FAILURE;
        }

        $fhir = $engine->map($patient);

        $this->line(json_encode($fhir, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($this->option('save')) {
            $doc = $engine->persist($patient, 'final');
            $this->info("Saved fhir_documents #{$doc->id} (version {$doc->version}, fhir_id {$doc->fhir_id}).");
        }

        return self::SUCCESS;
    }
}
