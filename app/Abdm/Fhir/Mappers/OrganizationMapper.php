<?php

namespace App\Abdm\Fhir\Mappers;

use App\Abdm\Fhir\Contracts\Mapper;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Branch (clinic facility) → FHIR R4 Organization.
 *
 * Carries the Health Facility Registry (HFR) id as the primary identifier and the
 * internal branch code as a secondary. Pure mapper.
 */
class OrganizationMapper implements Mapper
{
    public function supports(): string
    {
        return Branch::class;
    }

    public function resourceType(): string
    {
        return 'Organization';
    }

    public function toFhir(Model $model): array
    {
        /** @var Branch $b */
        $b = $model;

        $resource = [
            'resourceType' => 'Organization',
            'id'           => $b->fhir_organization_id ?: (string) Str::uuid(),
            'identifier'   => $this->identifiers($b),
            'active'       => (bool) ($b->is_active ?? true),
            'type'         => $b->facility_type
                ? [['text' => $b->facility_type]]
                : [['coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/organization-type',
                    'code'    => 'prov',
                    'display' => 'Healthcare Provider',
                ]]]],
            'name'         => $b->name,
            'telecom'      => $this->telecom($b),
        ];

        if ($addr = $this->address($b)) {
            $resource['address'] = [$addr];
        }

        return array_filter($resource, fn ($v) => $v !== null && $v !== []);
    }

    /* ── helpers ── */

    private function identifiers(Branch $b): array
    {
        $out = [];
        if ($b->hfr_id) {
            $out[] = ['system' => 'https://facility.abdm.gov.in', 'value' => $b->hfr_id];
        }
        if ($b->code) {
            $out[] = ['system' => 'https://dentfluence.in/branch-code', 'value' => $b->code];
        }
        if (empty($out)) {
            $out[] = ['system' => 'https://dentfluence.in/branch-id', 'value' => (string) $b->id];
        }
        return $out;
    }

    private function telecom(Branch $b): array
    {
        $t = [];
        if ($b->phone) $t[] = ['system' => 'phone', 'value' => $b->phone, 'use' => 'work'];
        if ($b->email) $t[] = ['system' => 'email', 'value' => $b->email, 'use' => 'work'];
        return $t;
    }

    private function address(Branch $b): ?array
    {
        $addr = array_filter([
            'use'     => 'work',
            'line'    => $b->address ? [$b->address] : null,
            'city'    => $b->city ?: null,
            'state'   => $b->state ?: null,
            'country' => 'IN',
        ], fn ($v) => $v !== null && $v !== []);

        return count($addr) > 1 ? $addr : null;
    }
}
