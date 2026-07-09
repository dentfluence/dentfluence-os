<?php

namespace App\Services\Presentations;

use App\Models\AppSetting;
use App\Models\Presentation;
use App\Models\TreatmentPlan;

/**
 * PresentationNarrativeService — builds the patient-facing case narrative
 * entirely from structured data already in Dentfluence. No AI call, no
 * network dependency, never unavailable. Every sentence traces back to a
 * real field — nothing here is invented or inferred beyond simple templating
 * and the deterministic tooth-location math in ToothLocationDescriber.
 *
 * The Ollama-based PresentationSummaryService still exists as an OPTIONAL
 * "warmer overview paragraph" a dentist can generate/edit on top of this —
 * but this service is what the module runs on by default, so the whole
 * module works even if the local AI is never turned on.
 */
class PresentationNarrativeService
{
    public function __construct(protected ToothLocationDescriber $teeth) {}

    /** @return array<string, mixed> Structured sections — render whichever the view needs, skip empty ones. */
    public function build(Presentation $presentation): array
    {
        $consultation = $presentation->consultation;
        $plan = $presentation->treatmentPlan;

        return [
            'complaint'    => $this->complaint($consultation),
            'hopi'         => $this->hopi($consultation),
            'diagnosis'    => $this->diagnosis($consultation),
            'treatment'    => $this->treatment($plan),
            'alternatives' => $this->alternatives($plan),
            'clinic'       => $this->clinicInfo(),
        ];
    }

    protected function complaint($consultation): ?string
    {
        if (! $consultation?->chief_complaint) {
            return null;
        }

        return $consultation->chief_complaint;
    }

    /** History of Presenting Illness — duration + severity + notes, only the parts that exist. */
    protected function hopi($consultation): ?string
    {
        if (! $consultation) {
            return null;
        }

        $parts = [];
        if ($consultation->complaint_duration) {
            $parts[] = "You've noticed this for {$consultation->complaint_duration}.";
        }
        if ($consultation->severity) {
            $parts[] = "You described it as {$consultation->severity}.";
        }
        if ($consultation->complaint_notes) {
            $parts[] = $consultation->complaint_notes;
        }

        return $parts ? implode(' ', $parts) : null;
    }

    protected function diagnosis($consultation): ?string
    {
        if (! $consultation?->primary_diagnosis) {
            return null;
        }

        $text = $consultation->primary_diagnosis;
        if ($consultation->secondary_diagnosis) {
            $text .= '. ' . $consultation->secondary_diagnosis;
        }

        return $text;
    }

    /** @return array<int, array{treatment_name:string, tooth_phrase:string, units:int, total:float}> */
    protected function treatment(?TreatmentPlan $plan): array
    {
        if (! $plan) {
            return [];
        }

        return $plan->items->map(fn ($item) => [
            'treatment_name' => $item->treatment_name,
            'tooth_phrase'   => $this->teeth->phraseFor($item->tooth_number),
            'units'          => (int) $item->units,
            'total'          => (float) $item->total,
        ])->all();
    }

    /**
     * Other Treatment Options recorded for the SAME consultation (Option A/B/C
     * in Dentfluence's own Treatment Plan UI) — real, already-modeled data,
     * not invented alternatives. Excludes the plan this presentation is for.
     */
    protected function alternatives(?TreatmentPlan $plan): array
    {
        if (! $plan?->consultation_id) {
            return [];
        }

        return TreatmentPlan::forConsultation($plan->consultation_id)
            ->where('id', '!=', $plan->id)
            ->with('items')
            ->get()
            ->map(fn (TreatmentPlan $alt) => [
                'plan_name'    => $alt->plan_name,
                'total'        => (float) ($alt->total ?? $alt->items->sum('total')),
                'item_count'   => $alt->items->count(),
                'summary'      => $alt->items->pluck('treatment_name')->implode(', '),
            ])->all();
    }

    protected function clinicInfo(): array
    {
        return [
            'name'    => AppSetting::get('clinic_name'),
            'phone'   => AppSetting::get('clinic_phone'),
            'address' => AppSetting::get('clinic_address'),
            'logo'    => AppSetting::get('clinic_logo'),
        ];
    }
}
