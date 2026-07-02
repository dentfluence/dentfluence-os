<?php

namespace App\Abdm\Fhir\Builders;

use App\Abdm\Fhir\Terminology\TerminologyResolver;
use App\Models\Prescription\Prescription;
use App\Models\Prescription\PrescriptionItem;

/**
 * Builds FHIR MedicationRequest resources from a prescription's items.
 *
 * Each prescription_item → one MedicationRequest, with the drug coded via terminology
 * (falling back to the snapshot drug_name/strength text) and dosing translated from
 * the morning/afternoon/night + duration + route + food-advice columns into a
 * FHIR Dosage. Pure builder.
 */
class MedicationRequestBuilder
{
    public function __construct(private TerminologyResolver $terminology) {}

    /**
     * @return array<int, array>  list of FHIR MedicationRequest resources
     */
    public function build(Prescription $rx, string $patientRef, string $requesterRef): array
    {
        $out    = [];
        $n      = 0;
        $status = $this->status($rx->status);
        $when   = optional($rx->created_at)->toIso8601String();

        $items = $rx->relationLoaded('items') ? $rx->items : $rx->items()->get();

        foreach ($items as $item) {
            $label = trim(($item->drug_name ?? '') . ' ' . ($item->strength ?? ''));
            $code  = $this->terminology->codeableConcept('drug', $item->generic_name ?: $item->drug_name, $label ?: 'Medication');

            $out[] = array_filter([
                'resourceType'            => 'MedicationRequest',
                'id'                      => 'medreq-' . $rx->id . '-' . (++$n),
                'status'                  => $status,
                'intent'                  => 'order',
                'medicationCodeableConcept' => $code ?: ['text' => $label],
                'subject'                 => ['reference' => $patientRef],
                'authoredOn'              => $when,
                'requester'               => ['reference' => $requesterRef],
                'dosageInstruction'       => [$this->dosage($item)],
            ], fn ($v) => $v !== null && $v !== []);
        }

        return $out;
    }

    /** Map prescription status → FHIR MedicationRequest.status. */
    private function status(?string $s): string
    {
        return match ($s) {
            'draft'                                       => 'draft',
            'cancelled'                                   => 'cancelled',
            'issued', 'printed', 'whatsapp_sent',
            'email_sent', 'revised'                       => 'active',
            default                                       => 'unknown',
        };
    }

    /** Translate the dosing columns into a FHIR Dosage. */
    private function dosage(PrescriptionItem $item): array
    {
        // Build a human "1-0-1" style text + words. As-needed meds with no fixed
        // schedule read as "SOS (as needed)" rather than a confusing "0-0-0".
        $m  = $this->num($item->morning);
        $a  = $this->num($item->afternoon);
        $ni = $this->num($item->night);
        $daily = (float) $item->morning + (float) $item->afternoon + (float) $item->night;

        $textBits = ($item->is_sos && $daily == 0.0)
            ? ['SOS (as needed)']
            : ["{$m}-{$a}-{$ni}"];
        if ($item->duration)    $textBits[] = 'for ' . $item->duration . ' ' . ($item->duration_unit ?: 'days');
        if ($item->food_advice) $textBits[] = $item->food_advice;

        $when = [];
        if ((float) $item->morning > 0)   $when[] = 'MORN';
        if ((float) $item->afternoon > 0) $when[] = 'NOON';
        if ((float) $item->night > 0)     $when[] = 'NIGHT';

        $timing = [];
        if ($when) {
            $timing['repeat']['when'] = $when;
        }
        if ($item->duration) {
            $timing['repeat']['boundsDuration'] = [
                'value' => (float) $item->duration,
                'unit'  => $item->duration_unit ?: 'd',
            ];
        }

        $dosage = [
            'text'              => implode(', ', array_filter($textBits)),
            'asNeededBoolean'   => (bool) $item->is_sos,
            'patientInstruction'=> $item->patient_instruction_en ?: $item->instructions,
        ];

        if ($item->route) {
            $dosage['route'] = ['text' => $item->route];
        }
        if ($timing) {
            $dosage['timing'] = $timing;
        }

        return array_filter($dosage, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function num($v): string
    {
        $f = (float) $v;
        return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.') ?: '0';
    }
}
