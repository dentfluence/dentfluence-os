<?php

namespace App\Abdm\Fhir\Mappers;

use App\Abdm\Fhir\Contracts\Mapper;
use App\Models\PractitionerIdentifier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * User (clinician) → FHIR R4 Practitioner.
 *
 * Pulls identity from the practitioner_identifiers bundle (internal id, HPR id,
 * council reg) and qualifications from practitioner_qualifications, falling back to
 * the single hr_staff_profiles.qualification/license_number when the richer rows
 * aren't present. Pure mapper.
 */
class PractitionerMapper implements Mapper
{
    private const SYSTEMS = [
        PractitionerIdentifier::TYPE_INTERNAL    => 'https://dentfluence.in/practitioner-id',
        PractitionerIdentifier::TYPE_HPR_ID      => 'https://hpr.abdm.gov.in',
        PractitionerIdentifier::TYPE_COUNCIL_REG => 'https://dentfluence.in/council-reg',
    ];

    public function supports(): string
    {
        return User::class;
    }

    public function resourceType(): string
    {
        return 'Practitioner';
    }

    public function toFhir(Model $model): array
    {
        /** @var User $u */
        $u       = $model;
        $profile = $u->hrProfile;   // may be null for non-clinical users

        $resource = [
            'resourceType' => 'Practitioner',
            'id'           => ($profile->fhir_practitioner_id ?? null) ?: (string) Str::uuid(),
            'identifier'   => $this->identifiers($u),
            'active'       => (bool) ($u->is_active ?? true),
            'name'         => [[
                'use'  => 'official',
                'text' => $u->name,
            ]],
            'telecom'      => $this->telecom($u),
        ];

        if ($profile && $profile->gender) {
            $resource['gender'] = strtolower($profile->gender);
        }

        if ($q = $this->qualifications($u, $profile)) {
            $resource['qualification'] = $q;
        }

        return array_filter($resource, fn ($v) => $v !== null && $v !== []);
    }

    /* ── helpers ── */

    private function identifiers(User $u): array
    {
        $out = [];
        $bundle = $u->relationLoaded('practitionerIdentifiers')
            ? $u->practitionerIdentifiers
            : $u->practitionerIdentifiers()->get();

        foreach ($bundle as $id) {
            if (empty($id->value)) continue;
            $out[] = [
                'system' => self::SYSTEMS[$id->identifier_type] ?? 'https://dentfluence.in/' . $id->identifier_type,
                'value'  => $id->value,
            ];
        }

        if (empty($out)) {
            $out[] = ['system' => self::SYSTEMS[PractitionerIdentifier::TYPE_INTERNAL], 'value' => (string) $u->id];
        }

        return $out;
    }

    private function telecom(User $u): array
    {
        $t = [];
        if ($u->phone) $t[] = ['system' => 'phone', 'value' => $u->phone, 'use' => 'work'];
        if ($u->email) $t[] = ['system' => 'email', 'value' => $u->email, 'use' => 'work'];
        return $t;
    }

    private function qualifications(User $u, $profile): array
    {
        $out = [];

        // Prefer the richer multi-row qualifications.
        $rows = $u->relationLoaded('qualifications') ? $u->qualifications : $u->qualifications()->get();
        foreach ($rows as $q) {
            $out[] = array_filter([
                'code'       => ['text' => $q->degree],
                'issuer'     => $q->institution ? ['display' => $q->institution] : null,
                'identifier' => $q->registration_number
                    ? [['value' => $q->registration_number]]
                    : null,
            ], fn ($v) => $v !== null);
        }

        // Fallback to the single-string qualification on the HR profile.
        if (empty($out) && $profile && $profile->qualification) {
            $out[] = ['code' => ['text' => $profile->qualification]];
        }

        return $out;
    }
}
