<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prescription\{RxDrug, RxDrugCategory, RxGeneric, RxRouteOfAdmin, RxFoodInstruction};

/**
 * Drug Master — full CRUD with search.
 */
class RxDrugController extends Controller
{
    public function index(Request $request)
    {
        $query = RxDrug::with(['generic', 'category'])
                       ->withTrashed();

        if ($search = $request->get('search')) {
            $query->search($search);
        }
        if ($cat = $request->get('category_id')) {
            $query->where('category_id', $cat);
        }
        if ($request->get('active_only')) {
            $query->active();
        }

        $drugs      = $query->orderBy('brand_name')->paginate(30)->withQueryString();
        $categories = RxDrugCategory::where('is_active', true)->orderBy('name')->get();

        return view('prescriptions.settings.drugs.index', compact('drugs', 'categories'));
    }

    public function create()
    {
        return view('prescriptions.settings.drugs.form', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        RxDrug::create($data);
        return redirect()->route('rx.drugs.index')->with('success', 'Drug added to master.');
    }

    public function edit(RxDrug $drug)
    {
        return view('prescriptions.settings.drugs.form', array_merge($this->formData(), ['drug' => $drug]));
    }

    public function update(Request $request, RxDrug $drug)
    {
        $drug->update($this->validated($request, $drug->id));
        return redirect()->route('rx.drugs.index')->with('success', 'Drug updated.');
    }

    public function destroy(RxDrug $drug)
    {
        $drug->delete();
        return back()->with('success', 'Drug deactivated.');
    }

    public function restore($id)
    {
        RxDrug::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Drug restored.');
    }

    /**
     * AJAX drug search — returns JSON for prescription form autocomplete.
     */
    public function search(Request $request)
    {
        $term  = $request->get('q', '');
        $drugs = RxDrug::active()
                       ->search($term)
                       ->with(['generic', 'category', 'defaultFoodInstruction'])
                       ->limit(15)
                       ->get()
                       ->map(fn($d) => [
                           'id'                      => $d->id,
                           'brand_name'              => $d->brand_name,
                           'generic_name'            => $d->generic?->name,
                           'strength'                => $d->strength,
                           'dosage_form'             => $d->dosage_form,
                           'route'                   => $d->route?->abbreviation,
                           // Dispensing
                           'dispensing_type'         => $d->dispensing_type ?? RxDrug::DISPENSING_UNIT,
                           'unit_label'              => $d->unit_label ?? 'tab',
                           // Dose defaults
                           'default_dose'            => $d->default_dose,
                           'default_duration'        => $d->default_duration,
                           'default_duration_unit'   => $d->default_duration_unit,
                           // Instructions
                           'food_advice'             => $d->defaultFoodInstruction?->label,
                           'food_instruction_id'     => $d->default_food_instruction_id,
                           'default_instructions'    => $d->default_instructions,
                           // Safety / CDSS
                           'duplicate_molecule_group' => $d->duplicate_molecule_group,
                           'antibiotic_class'         => $d->antibiotic_class,
                           'max_daily_dose'           => $d->max_daily_dose,
                           'contraindications'        => $d->contraindications,
                           'pregnancy_category'       => $d->pregnancy_category,
                           'is_controlled'            => $d->is_controlled,
                       ]);

        return response()->json($drugs);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'drug'         => null,
            'categories'   => RxDrugCategory::where('is_active', true)->orderBy('name')->get(),
            'generics'     => RxGeneric::where('is_active', true)->orderBy('name')->get(),
            'routes'       => RxRouteOfAdmin::where('is_active', true)->orderBy('name')->get(),
            'foodInst'     => RxFoodInstruction::where('is_active', true)->get(),
        ];
    }

    private function validated(Request $request, $excludeId = null): array
    {
        return $request->validate([
            'drug_code'                    => 'nullable|string|max:30|unique:rx_drugs,drug_code,' . ($excludeId ?? 'NULL'),
            'brand_name'                   => 'required|string|max:200',
            'generic_id'                   => 'nullable|exists:rx_generics,id',
            'category_id'                  => 'nullable|exists:rx_drug_categories,id',
            'strength'                     => 'nullable|string|max:50',
            'dosage_form'                  => 'nullable|string|max:100',
            'composition'                  => 'nullable|string|max:500',
            'route_id'                     => 'nullable|exists:rx_routes_of_admin,id',
            'default_dose'                 => 'nullable|string|max:100',
            'default_duration'             => 'nullable|integer|min:1',
            'default_duration_unit'        => 'nullable|in:days,weeks,months',
            'default_food_instruction_id'  => 'nullable|exists:rx_food_instructions,id',
            'default_instructions'         => 'nullable|string',
            'max_daily_dose'               => 'nullable|string|max:100',
            'duplicate_molecule_group'     => 'nullable|string|max:100',
            'antibiotic_class'             => 'nullable|string|max:100',
            'is_controlled'                => 'boolean',
            'pregnancy_category'           => 'nullable|in:A,B,C,D,X,N',
            'breastfeeding_safety'         => 'nullable|in:safe,caution,avoid,unknown',
            'pediatric_safety'             => 'nullable|in:safe,caution,avoid,unknown',
            'geriatric_caution'            => 'nullable|in:normal,caution,avoid',
            'renal_dose_adjustment'        => 'nullable|string|max:200',
            'hepatic_dose_adjustment'      => 'nullable|string|max:200',
            'contraindications'            => 'nullable|string',
            'drug_interactions_note'       => 'nullable|string',
            'common_dental_uses'           => 'nullable|string',
            'notes'                        => 'nullable|string',
            'is_active'                    => 'boolean',
        ]);
    }
}
