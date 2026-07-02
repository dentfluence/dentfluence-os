<?php

namespace App\Console\Commands;

use App\Abdm\Fhir\FhirMappingEngine;
use App\Abdm\Fhir\Bundles\OpConsultationBundleAssembler;
use App\Abdm\Fhir\Bundles\PrescriptionBundleAssembler;
use App\Models\Branch;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Prescription\Prescription;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * FhirShow — ABDM Phase 2
 *
 * Generic FHIR previewer for any mapped resource type. Generates and prints the
 * FHIR R4 resource for a patient, practitioner (doctor/user) or organization (branch).
 * Read-only by default; --save persists to fhir_documents.
 *
 * Usage:
 *   php artisan abdm:fhir patient 1
 *   php artisan abdm:fhir patient DF-00142
 *   php artisan abdm:fhir practitioner 3
 *   php artisan abdm:fhir organization 1
 *   php artisan abdm:fhir patient 1 --save
 */
class FhirShow extends Command
{
    protected $signature = 'abdm:fhir {type : patient|practitioner|organization|encounter|consultation|prescription} {id} {--save : Persist to fhir_documents} {--validate : Run the FHIR validator and report issues}';

    protected $description = 'Generate and display a FHIR resource (or full consultation/prescription Bundle).';

    private const TYPES = ['patient', 'practitioner', 'organization', 'encounter', 'consultation', 'prescription'];

    public function handle(FhirMappingEngine $engine, OpConsultationBundleAssembler $assembler, PrescriptionBundleAssembler $rxAssembler): int
    {
        $type = strtolower($this->argument('type'));
        $id   = $this->argument('id');

        if (! in_array($type, self::TYPES, true)) {
            $this->error("Unknown type '{$type}'. Use: " . implode(' | ', self::TYPES));
            return self::FAILURE;
        }

        $model = match ($type) {
            'patient'      => Patient::with('identifiers')->where('id', $id)->orWhere('patient_id', $id)->first(),
            'practitioner' => User::with(['hrProfile', 'practitionerIdentifiers', 'qualifications'])->find($id),
            'organization' => Branch::find($id),
            'encounter',
            'consultation' => Consultation::with(['patient', 'doctor'])->find($id),
            'prescription' => Prescription::with(['patient', 'prescribedBy'])->find($id),
            default        => null,
        };

        if (! $model) {
            $this->error("No {$type} found for id: {$id}");
            return self::FAILURE;
        }

        // Full document Bundles use their assemblers; everything else is a single
        // resource via the engine.
        $fhir = match ($type) {
            'consultation' => $assembler->assemble($model),
            'prescription' => $rxAssembler->assemble($model),
            default        => $engine->map($model),
        };

        $this->line(json_encode($fhir, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($this->option('validate')) {
            $result = $engine->validator()->validate($fhir);
            $this->newLine();
            if ($result['ok'] && empty($result['warnings'])) {
                $this->info('✓ FHIR valid — no issues.');
            } else {
                if ($result['ok']) {
                    $this->info('✓ FHIR valid (with warnings).');
                } else {
                    $this->error('✗ FHIR INVALID — ' . count($result['errors']) . ' error(s):');
                    foreach ($result['errors'] as $e) $this->error('   • ' . $e);
                }
                foreach ($result['warnings'] as $w) $this->warn('   ! ' . $w);
            }
        }

        if ($this->option('save') && ! in_array($type, ['consultation', 'prescription'], true)) {
            $doc = $engine->persist($model, 'final');
            $this->info("Saved fhir_documents #{$doc->id} ({$doc->resource_type}, v{$doc->version}, fhir_id {$doc->fhir_id}).");
        }

        return self::SUCCESS;
    }
}
