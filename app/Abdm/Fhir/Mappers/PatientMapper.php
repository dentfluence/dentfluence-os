<?php

namespace App\Abdm\Fhir\Mappers;

use App\Abdm\Fhir\Contracts\Mapper;
use App\Abdm\Fhir\Terminology\TerminologyResolver;
use App\Models\Patient;
use App\Models\PatientIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Patient → FHIR R4 Patient.
 *
 * Pure mapper: reads the patient (and its identifier bundle) and returns a FHIR
 * Patient resource array. No writes, no network. The engine handles persistence.
 */
class PatientMapper implements Mapper
{
    /** Local identifier_type → FHIR identifier system URI. */
    private const SYSTEMS = [
        PatientIdentifier::TYPE_INTERNAL     => 'https://dentfluence.in/patient-id',
        PatientIdentifier::TYPE_ABHA_NUMBER  => 'https://healthid.ndhm.gov.in',
        PatientIdentifier::TYPE_ABHA_ADDRESS => 'https://healthid.ndhm.gov.in/address',
        PatientIdentifier::TYPE_INSURANCE    => 'https://dentfluence.in/insurance-id',
    ];

    public function __construct(private TerminologyResolver $terminology) {}

    public function supports(): string
    {
        return Patient::class;
    }

    public function resourceType(): string
    {
        return 'Patient';
    }

    public function toFhir(Model $model): array
    {
        /** @var Patient $p */
        $p = $model;

        $resource = [
            'resourceType' => 'Patient',
            'id'           => $p->fhir_resource_id ?: (string) Str::uuid(),
            'identifier'   => $this->identifiers($p),
            'active'       => (bool) ($p->is_active ?? true),
            'name'         => $this->name($p),
            'telecom'      => $this->telecom($p),
            'gender'       => $this->gender($p->gender),
        ];

        if ($p->date_of_birth && ! $p->dob_unknown) {
            $resource['birthDate'] = $p->date_of_birth->format('Y-m-d');
        }

        if ($addr = $this->address($p)) {
            $resource['address'] = [$addr];
        }

        if ($contact = $this->emergencyContact($p)) {
            $resource['contact'] = [$contact];
        }

        if ($p->preferred_language) {
            $resource['communication'] = [[
                'language'  => ['text' => $p->preferred_language],
                'preferred' => true,
            ]];
        }

        // Drop any null/empty top-level keys so the resource stays clean.
        return array_filter($resource, fn ($v) => $v !== null && $v !== []);
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    private function identifiers(Patient $p): array
    {
        $out = [];

        // Prefer the normalized identifier bundle when available.
        $bundle = $p->relationLoaded('identifiers') ? $p->identifiers : $p->identifiers()->get();

        foreach ($bundle as $id) {
            if (empty($id->value)) continue;
            $out[] = array_filter([
                'system' => self::SYSTEMS[$id->identifier_type] ?? 'https://dentfluence.in/' . $id->identifier_type,
                'value'  => $id->value,
            ]);
        }

        // Fallback: at minimum emit the internal patient_id so the resource is valid.
        if (empty($out) && $p->patient_id) {
            $out[] = ['system' => self::SYSTEMS[PatientIdentifier::TYPE_INTERNAL], 'value' => $p->patient_id];
        }

        return $out;
    }

    private function name(Patient $p): array
    {
        $given = array_values(array_filter([$p->first_name, $p->middle_name]));
        $name = [
            'use'    => 'official',
            'text'   => $p->name ?: trim("{$p->first_name} {$p->last_name}"),
            'family' => $p->last_name ?: null,
            'given'  => $given ?: null,
        ];
        if ($p->title) {
            $name['prefix'] = [$p->title];
        }
        return [array_filter($name, fn ($v) => $v !== null && $v !== [])];
    }

    private function telecom(Patient $p): array
    {
        $t = [];
        if ($p->phone)           $t[] = ['system' => 'phone', 'value' => $p->phone, 'use' => 'mobile'];
        if ($p->alternate_phone) $t[] = ['system' => 'phone', 'value' => $p->alternate_phone, 'use' => 'home'];
        if ($p->email)           $t[] = ['system' => 'email', 'value' => $p->email];
        return $t;
    }

    /** FHIR administrative-gender: male | female | other | unknown. */
    private function gender(?string $local): string
    {
        $resolved = $this->terminology->resolve('gender', $local);
        if ($resolved && in_array($resolved['code'], ['male', 'female', 'other', 'unknown'], true)) {
            return $resolved['code'];
        }
        return match (strtolower((string) $local)) {
            'male'   => 'male',
            'female' => 'female',
            'other'  => 'other',
            default  => 'unknown',
        };
    }

    private function address(Patient $p): ?array
    {
        $lines = array_values(array_filter([$p->address, $p->area]));
        $addr = array_filter([
            'use'        => 'home',
            'line'       => $lines ?: null,
            'city'       => $p->city ?: null,
            'state'      => $p->state ?: null,
            'postalCode' => $p->pincode ?: null,
            'country'    => 'IN',
        ], fn ($v) => $v !== null && $v !== []);

        // Only return if we have more than just country.
        return count($addr) > 1 ? $addr : null;
    }

    private function emergencyContact(Patient $p): ?array
    {
        if (! $p->emergency_contact_name && ! $p->emergency_contact_number) {
            return null;
        }
        return array_filter([
            'name'     => $p->emergency_contact_name ? ['text' => $p->emergency_contact_name] : null,
            'telecom'  => $p->emergency_contact_number
                ? [['system' => 'phone', 'value' => $p->emergency_contact_number]]
                : null,
            'relationship' => $p->emergency_contact_relationship
                ? [['text' => $p->emergency_contact_relationship]]
                : null,
        ], fn ($v) => $v !== null);
    }
}
