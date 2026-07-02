<?php

namespace App\Abdm\Fhir\Mappers;

use App\Abdm\Fhir\Contracts\Mapper;
use App\Abdm\Fhir\Support\FhirRef;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Consultation → FHIR R4 Encounter (the ABDM "Care Context" — the linkable visit).
 *
 * Produces RELATIVE references (Patient/{id}, Practitioner/{id}, Organization/{id}).
 * When used inside a document Bundle, the assembler rewrites these to urn:uuid so
 * everything resolves; standalone, the relative refs are still valid FHIR.
 */
class EncounterMapper implements Mapper
{
    public function supports(): string
    {
        return Consultation::class;
    }

    public function resourceType(): string
    {
        return 'Encounter';
    }

    public function toFhir(Model $model): array
    {
        /** @var Consultation $c */
        $c = $model;

        $resource = [
            'resourceType' => 'Encounter',
            'id'           => $c->fhir_encounter_id ?: (string) Str::uuid(),
            'status'       => $this->status($c),
            'class'        => $this->class($c),
            'subject'      => FhirRef::patient($c->patient),
        ];

        if ($c->consultation_type) {
            $resource['type'] = [['text' => $c->consultation_type]];
        }

        if ($c->doctor) {
            $resource['participant'] = [[
                'individual' => FhirRef::practitioner($c->doctor),
            ]];
        }

        if ($c->consultation_date) {
            $resource['period'] = ['start' => $c->consultation_date->toIso8601String()];
        }

        if ($c->chief_complaint) {
            $resource['reasonCode'] = [['text' => $c->chief_complaint]];
        }

        if ($c->branch_id) {
            $resource['serviceProvider'] = ['reference' => 'Organization/' . $c->branch_id];
        }

        return array_filter($resource, fn ($v) => $v !== null && $v !== []);
    }

    /** Map internal draft/completed → FHIR Encounter.status. */
    private function status(Consultation $c): string
    {
        return match ($c->status) {
            'completed' => 'finished',
            'cancelled' => 'cancelled',
            'draft'     => 'in-progress',
            default     => 'unknown',
        };
    }

    /** Ambulatory by default; emergency consultations → EMER. */
    private function class(Consultation $c): array
    {
        $isEmergency = str_contains(strtolower((string) $c->consultation_type), 'emergency')
            || str_contains(strtolower((string) $c->visit_type), 'emergency');

        return [
            'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
            'code'    => $isEmergency ? 'EMER' : 'AMB',
            'display' => $isEmergency ? 'emergency' : 'ambulatory',
        ];
    }
}
