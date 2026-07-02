<?php

namespace App\Abdm\Fhir\Bundles;

use App\Abdm\Fhir\Builders\AllergyBuilder;
use App\Abdm\Fhir\Builders\MedicationRequestBuilder;
use App\Abdm\Fhir\FhirMappingEngine;
use App\Abdm\Fhir\Support\FhirRef;
use App\Models\Prescription\Prescription;
use Illuminate\Support\Carbon;

/**
 * Assembles an ABDM "Prescription" FHIR document Bundle from one prescription.
 *
 * Bundle (type=document) = Composition + Patient + Practitioner + MedicationRequest[]
 * + AllergyIntolerance[]. Mirrors OpConsultationBundleAssembler: the assembler owns
 * all references and rewrites "Type/{id}" → "urn:uuid:{id}" so the document resolves.
 */
class PrescriptionBundleAssembler
{
    public function __construct(
        private FhirMappingEngine $engine,
        private MedicationRequestBuilder $medications,
        private AllergyBuilder $allergies,
    ) {}

    public function assemble(Prescription $rx): array
    {
        $rx->loadMissing([
            'patient.identifiers', 'patient.allergyRecords',
            'prescribedBy.hrProfile', 'prescribedBy.practitionerIdentifiers', 'prescribedBy.qualifications',
            'items',
        ]);

        $patient = $rx->patient;
        $doctor  = $rx->prescribedBy;

        $pid  = FhirRef::patientId($patient);
        $drid = FhirRef::practitionerId($doctor);

        $patientRef   = 'Patient/' . $pid;
        $requesterRef = 'Practitioner/' . $drid;

        $resources = [];

        $patientRes       = $this->engine->map($patient);
        $patientRes['id'] = $pid;
        $resources[]      = $patientRes;

        if ($doctor) {
            $dr       = $this->engine->map($doctor);
            $dr['id'] = $drid;
            $resources[] = $dr;
        }

        $meds      = $this->medications->build($rx, $patientRef, $requesterRef);
        $allergies = $this->allergies->build($patient, $patientRef);
        foreach ($meds as $m)      $resources[] = $m;
        foreach ($allergies as $a) $resources[] = $a;

        $composition = $this->composition($rx, $pid, $drid, $meds, $allergies);
        array_unshift($resources, $composition);

        $entries = [];
        foreach ($resources as $res) {
            $id = $res['id'] ?? (string) \Illuminate\Support\Str::uuid();
            $entries[] = [
                'fullUrl'  => 'urn:uuid:' . $id,
                'resource' => $this->rewriteRefs($res),
            ];
        }

        return [
            'resourceType' => 'Bundle',
            'type'         => 'document',
            'timestamp'    => Carbon::now()->toIso8601String(),
            'entry'        => $entries,
        ];
    }

    /* ── helpers ── */

    private function composition(Prescription $rx, string $pid, string $drid, array $meds, array $allergies): array
    {
        $sections = [];

        if (! empty($meds)) {
            $sections[] = [
                'title' => 'Medications',
                'entry' => array_map(fn ($r) => ['reference' => 'MedicationRequest/' . $r['id']], $meds),
            ];
        }
        if (! empty($allergies)) {
            $sections[] = [
                'title' => 'Allergies',
                'entry' => array_map(fn ($r) => ['reference' => 'AllergyIntolerance/' . $r['id']], $allergies),
            ];
        }

        return array_filter([
            'resourceType' => 'Composition',
            'id'           => 'comp-rx-' . $rx->id,
            'status'       => 'final',
            'type'         => [
                'coding' => [['system' => 'http://loinc.org', 'code' => '57833-6', 'display' => 'Prescription for medication']],
                'text'   => 'Prescription',
            ],
            'subject' => ['reference' => 'Patient/' . $pid],
            'date'    => optional($rx->created_at)->toIso8601String() ?: Carbon::now()->toIso8601String(),
            'author'  => [['reference' => 'Practitioner/' . $drid]],
            'title'   => 'Prescription ' . ($rx->prescription_number ?? ''),
            'section' => $sections,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** Recursively rewrite "Type/{id}" references to "urn:uuid:{id}". */
    private function rewriteRefs(array $node): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'reference' && is_string($value) && str_contains($value, '/')) {
                $node[$key] = 'urn:uuid:' . substr($value, strpos($value, '/') + 1);
            } elseif (is_array($value)) {
                $node[$key] = $this->rewriteRefs($value);
            }
        }
        return $node;
    }
}
