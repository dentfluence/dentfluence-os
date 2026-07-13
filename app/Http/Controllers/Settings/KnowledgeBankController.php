<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use App\Models\DiagnosisTreatmentOption;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings → Knowledge Bank. Lets a dentist rank Treatment options per
 * Diagnosis ahead of time (best/acceptable/alternative), once, so the
 * reasoning doesn't have to be re-typed into every consultation. See
 * docs/gap-analysis-treatment-planning-knowledge-bank.md Phase 1.
 *
 * The diagnosis list itself (add/rename/delete a Diagnosis) is still owned
 * by Settings\MastersController — this controller only manages the ranked
 * Treatment options hanging off one Diagnosis.
 */
class KnowledgeBankController extends Controller
{
    /** One diagnosis's ranked treatment options — add / edit / remove. */
    public function manage(Diagnosis $diagnosis)
    {
        $diagnosis->load(['treatmentOptions.treatment.category']);

        $treatments = Treatment::active()->orderBy('name')->get(['id', 'name']);

        return view('settings.knowledge-bank.manage', compact('diagnosis', 'treatments'));
    }

    public function store(Request $request, Diagnosis $diagnosis)
    {
        $data = $request->validate([
            'treatment_id' => [
                'required',
                'exists:treatments,id',
                Rule::unique('diagnosis_treatment_options')
                    ->where(fn ($q) => $q->where('diagnosis_id', $diagnosis->id)),
            ],
            'rank'       => ['required', Rule::in(array_keys(DiagnosisTreatmentOption::RANKS))],
            'notes'      => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'treatment_id.unique' => 'This treatment is already ranked for this diagnosis — edit its rank below instead of adding it again.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? ((int) $diagnosis->treatmentOptions()->max('sort_order') + 1);

        $diagnosis->treatmentOptions()->create($data);

        return redirect()->route('settings.knowledge-bank.manage', $diagnosis)->with('success', 'Treatment option added.');
    }

    public function update(Request $request, DiagnosisTreatmentOption $option)
    {
        $data = $request->validate([
            'rank'       => ['required', Rule::in(array_keys(DiagnosisTreatmentOption::RANKS))],
            'notes'      => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $option->update($data);

        return redirect()->route('settings.knowledge-bank.manage', $option->diagnosis_id)->with('success', 'Updated.');
    }

    public function destroy(DiagnosisTreatmentOption $option)
    {
        $diagnosisId = $option->diagnosis_id;
        $option->delete();

        return redirect()->route('settings.knowledge-bank.manage', $diagnosisId)->with('success', 'Removed.');
    }
}
