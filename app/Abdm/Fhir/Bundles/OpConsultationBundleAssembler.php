<?php

namespace App\Abdm\Fhir\Bundles;

use App\Abdm\Fhir\Builders\ConditionBuilder;
use App\Abdm\Fhir\Builders\ObservationBuilder;
use App\Abdm\Fhir\FhirMappingEngine;
use App\Abdm\Fhir\Support\FhirRef;
use App\Models\Branch;
use App\Models\Consultation;
use Illuminate\Support\Carbon;

/**
 * Assembles an ABDM "OP Consultation" FHIR document Bundle from one consultation.
 *
 * Bundle (type=document) = Composition (spine) + Patient + Practitioner +
 * Organization + Encounter + Condition[] + Observation[]. The assembler owns all
 * cross-references so they stay consistent, then rewrites every "Type/{id}" to
 * "urn:uuid:{id}" so the document is internally resolvable — the shape ABDM expects.
 *
 * This is the first real ABDM health-record document Dentfluence can produce.
 */
class OpConsultationBundleAssembler
{
    public function __construct(
        private FhirMappingEngine $engine,
        private ConditionBuilder $conditions,
        private ObservationBuilder $observations,
    ) {}

    public function assemble(Consultation $c): array
    {
        $c->loadMissing([
            'patient.identifiers',
            'doctor.hrProfile', 'doctor.practitionerIdentifiers', 'doctor.qualifications',
            'clinicalFindings',
        ]);

        $patient = $c->patient;
        $doctor  = $c->doctor;
        $branch  = $c->branch_id ? Branch::find($c->branch_id) : null;

        // Deterministic logical ids (shared by resources + references).
        $pid   = FhirRef::patientId($patient);
        $drid  = FhirRef::practitionerId($doctor);
        $orgid = FhirRef::organizationId($branch);
        $encid = $c->fhir_encounter_id ?: ('enc-' . $c->id);

        $patientRef = 'Patient/' . $pid;
        $encRef     = 'Encounter/' . $encid;

        $resources = [];

        // ── Actor resources (via the registered mappers) ──
        $patientRes       = $this->engine->map($patient);
        $patientRes['id'] = $pid;
        $resources[]      = $patientRes;

        if ($doctor) {
            $dr       = $this->engine->map($doctor);
            $dr['id'] = $drid;
            $resources[] = $dr;
        }

        if ($branch) {
            $org       = $this->engine->map($branch);
            $org['id'] = $orgid;
            $resources[] = $org;
        }

        // ── Encounter (override refs so they line up with the ids above) ──
        $enc           = $this->engine->map($c);
        $enc['id']     = $encid;
        $enc['subject'] = ['reference' => $patientRef];
        if ($doctor) {
            $enc['participant'] = [['individual' => ['reference' => 'Practitioner/' . $drid]]];
        }
        if ($branch) {
            $enc['serviceProvider'] = ['reference' => 'Organization/' . $orgid];
        }
        $resources[] = $enc;

        // ── Clinical content ──
        $conditions   = $this->conditions->build($c, $patientRef, $encRef);
        $observations = $this->observations->build($c, $patientRef, $encRef);
        foreach ($conditions as $cond)   $resources[] = $cond;
        foreach ($observations as $obs)  $resources[] = $obs;

        // ── Composition (the document spine) goes FIRST ──
        $composition = $this->composition($c, $pid, $drid, $encid, $conditions, $observations);
        array_unshift($resources, $composition);

        // ── Wrap as entries, rewrite refs to urn:uuid ──
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

    private function composition(Consultation $c, string $pid, string $drid, string $encid, array $conditions, array $observations): array
    {
        $sections = [];

        if ($c->chief_complaint) {
            $sections[] = [
                'title' => 'Chief Complaint',
                'text'  => $this->narrative($c->chief_complaint),
            ];
        }

        if (! empty($conditions)) {
            $sections[] = [
                'title' => 'Diagnosis',
                'entry' => array_map(fn ($r) => ['reference' => 'Condition/' . $r['id']], $conditions),
            ];
        }

        if (! empty($observations)) {
            $sections[] = [
                'title' => 'Examination Findings',
                'entry' => array_map(fn ($r) => ['reference' => 'Observation/' . $r['id']], $observations),
            ];
        }

        return array_filter([
            'resourceType' => 'Composition',
            'id'           => 'comp-' . $c->id,
            'status'       => 'final',
            'type'         => [
                'coding' => [['system' => 'http://loinc.org', 'code' => '11488-4', 'display' => 'Consultation note']],
                'text'   => 'OP Consultation Note',
            ],
            'subject'   => ['reference' => 'Patient/' . $pid],
            'encounter' => ['reference' => 'Encounter/' . $encid],
            'date'      => optional($c->consultation_date)->toIso8601String() ?: Carbon::now()->toIso8601String(),
            'author'    => [['reference' => 'Practitioner/' . $drid]],
            'title'     => 'OP Consultation',
            'section'   => $sections,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** FHIR Narrative from plain text. */
    private function narrative(string $text): array
    {
        return [
            'status' => 'generated',
            'div'    => '<div xmlns="http://www.w3.org/1999/xhtml">' . htmlspecialchars($text, ENT_QUOTES | ENT_XML1) . '</div>',
        ];
    }

    /**
     * Recursively rewrite every relative reference "Type/{id}" → "urn:uuid:{id}"
     * so the document Bundle is internally resolvable.
     */
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
