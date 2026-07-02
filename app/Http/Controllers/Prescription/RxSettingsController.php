<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prescription\{
    RxDrugCategory, RxGeneric, RxRouteOfAdmin, RxFoodInstruction,
    RxDoseTemplate, RxDurationTemplate, RxWarningRule,
    RxDrugInteractionRule, RxAllergyRule, RxTemplate, RxTemplateItem, RxDrug
};

/**
 * Manages all Prescription Settings masters (except Drug Master which has its own controller).
 * Route prefix: settings/prescription
 */
class RxSettingsController extends Controller
{
    // ── Settings Index ────────────────────────────────────────────────────────

    public function index()
    {
        return view('prescriptions.settings.index');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DRUG CATEGORIES
    // ══════════════════════════════════════════════════════════════════════════

    public function categories()
    {
        $items = RxDrugCategory::withTrashed()->orderBy('name')->get();
        return view('prescriptions.settings.categories', compact('items'));
    }

    public function categoriesStore(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string']);
        RxDrugCategory::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Category added.');
    }

    public function categoriesUpdate(Request $request, RxDrugCategory $category)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string', 'is_active' => 'boolean']);
        $category->update($data);
        return back()->with('success', 'Category updated.');
    }

    public function categoriesDestroy(RxDrugCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERICS
    // ══════════════════════════════════════════════════════════════════════════

    public function generics()
    {
        $items = RxGeneric::withTrashed()->orderBy('name')->get();
        return view('prescriptions.settings.generics', compact('items'));
    }

    public function genericsStore(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'drug_class' => 'nullable|string|max:100', 'notes' => 'nullable|string']);
        RxGeneric::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Generic added.');
    }

    public function genericsUpdate(Request $request, RxGeneric $generic)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'drug_class' => 'nullable|string|max:100', 'notes' => 'nullable|string', 'is_active' => 'boolean']);
        $generic->update($data);
        return back()->with('success', 'Generic updated.');
    }

    public function genericsDestroy(RxGeneric $generic)
    {
        $generic->delete();
        return back()->with('success', 'Generic deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ROUTES OF ADMINISTRATION
    // ══════════════════════════════════════════════════════════════════════════

    public function routes()
    {
        $items = RxRouteOfAdmin::orderBy('name')->get();
        return view('prescriptions.settings.routes', compact('items'));
    }

    public function routesStore(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'abbreviation' => 'nullable|string|max:20']);
        RxRouteOfAdmin::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Route added.');
    }

    public function routesUpdate(Request $request, RxRouteOfAdmin $route)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'abbreviation' => 'nullable|string|max:20', 'is_active' => 'boolean']);
        $route->update($data);
        return back()->with('success', 'Route updated.');
    }

    public function routesDestroy(RxRouteOfAdmin $route)
    {
        $route->delete();
        return back()->with('success', 'Route deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FOOD INSTRUCTIONS
    // ══════════════════════════════════════════════════════════════════════════

    public function foodInstructions()
    {
        $items = RxFoodInstruction::orderBy('label')->get();
        return view('prescriptions.settings.food-instructions', compact('items'));
    }

    public function foodInstructionsStore(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:30|unique:rx_food_instructions,code', 'label' => 'required|string|max:100', 'label_mr' => 'nullable|string|max:100', 'label_hi' => 'nullable|string|max:100']);
        RxFoodInstruction::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Instruction added.');
    }

    public function foodInstructionsUpdate(Request $request, RxFoodInstruction $instruction)
    {
        $data = $request->validate(['label' => 'required|string|max:100', 'label_mr' => 'nullable|string|max:100', 'label_hi' => 'nullable|string|max:100', 'is_active' => 'boolean']);
        $instruction->update($data);
        return back()->with('success', 'Instruction updated.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DOSE TEMPLATES
    // ══════════════════════════════════════════════════════════════════════════

    public function doseTemplates()
    {
        $items = RxDoseTemplate::orderBy('name')->get();
        return view('prescriptions.settings.dose-templates', compact('items'));
    }

    public function doseTemplatesStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'abbreviation' => 'required|string|max:20',
            'morning' => 'integer|min:0', 'afternoon' => 'integer|min:0', 'night' => 'integer|min:0',
            'is_sos' => 'boolean', 'description' => 'nullable|string',
        ]);
        RxDoseTemplate::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Dose template added.');
    }

    public function doseTemplatesUpdate(Request $request, RxDoseTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'abbreviation' => 'required|string|max:20',
            'morning' => 'integer|min:0', 'afternoon' => 'integer|min:0', 'night' => 'integer|min:0',
            'is_sos' => 'boolean', 'description' => 'nullable|string', 'is_active' => 'boolean',
        ]);
        $template->update($data);
        return back()->with('success', 'Template updated.');
    }

    public function doseTemplatesDestroy(RxDoseTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Template deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DURATION TEMPLATES
    // ══════════════════════════════════════════════════════════════════════════

    public function durationTemplates()
    {
        $items = RxDurationTemplate::orderBy('unit')->orderBy('value')->get();
        return view('prescriptions.settings.duration-templates', compact('items'));
    }

    public function durationTemplatesStore(Request $request)
    {
        $data = $request->validate(['label' => 'required|string|max:50', 'value' => 'required|integer|min:1', 'unit' => 'required|in:days,weeks,months']);
        RxDurationTemplate::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Duration added.');
    }

    public function durationTemplatesUpdate(Request $request, RxDurationTemplate $template)
    {
        $data = $request->validate(['label' => 'required|string|max:50', 'value' => 'required|integer|min:1', 'unit' => 'required|in:days,weeks,months', 'is_active' => 'boolean']);
        $template->update($data);
        return back()->with('success', 'Duration updated.');
    }

    public function durationTemplatesDestroy(RxDurationTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Duration deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WARNING RULES
    // ══════════════════════════════════════════════════════════════════════════

    public function warningRules()
    {
        $items = RxWarningRule::with('drug')->orderBy('condition_keyword')->get();
        return view('prescriptions.settings.warning-rules', compact('items'));
    }

    public function warningRulesStore(Request $request)
    {
        $data = $request->validate([
            'condition_keyword' => 'required|string|max:100',
            'drug_id'           => 'nullable|exists:rx_drugs,id',
            'molecule_group'    => 'nullable|string|max:100',
            'drug_class'        => 'nullable|string|max:100',
            'severity'          => 'required|in:info,warning,critical',
            'alert_message'     => 'required|string',
            'suggestion'        => 'nullable|string|max:200',
            'blockable'         => 'boolean',
        ]);
        RxWarningRule::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Warning rule added.');
    }

    public function warningRulesDestroy(RxWarningRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'Rule deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRESCRIPTION TEMPLATES
    // ══════════════════════════════════════════════════════════════════════════

    public function prescriptionTemplates()
    {
        $items = RxTemplate::withCount('items')->orderBy('name')->get();
        return view('prescriptions.settings.prescription-templates', compact('items'));
    }

    public function prescriptionTemplatesCreate()
    {
        $drugs    = RxDrug::active()->orderBy('brand_name')->get();
        $foodInst = RxFoodInstruction::where('is_active', true)->get();
        return view('prescriptions.settings.prescription-template-form', compact('drugs', 'foodInst'));
    }

    public function prescriptionTemplatesStore(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'category'     => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'instructions' => 'nullable|string',
            'items'        => 'array',
            'items.*.drug_id'             => 'required|exists:rx_drugs,id',
            'items.*.morning'             => 'nullable|numeric|min:0',
            'items.*.afternoon'           => 'nullable|numeric|min:0',
            'items.*.night'               => 'nullable|numeric|min:0',
            'items.*.duration'            => 'nullable|integer|min:1',
            'items.*.duration_unit'       => 'nullable|in:days,weeks,months',
            'items.*.food_instruction_id' => 'nullable|exists:rx_food_instructions,id',
            'items.*.instructions'        => 'nullable|string',
        ]);

        $template = RxTemplate::create([
            'name'         => $data['name'],
            'category'     => $data['category'] ?? null,
            'description'  => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'is_active'    => true,
            'created_by'   => auth()->id(),
        ]);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $i => $item) {
                $template->items()->create(array_merge($item, ['sort_order' => $i]));
            }
        }

        return redirect()->route('rx.settings.prescription-templates')
                         ->with('success', 'Template created.');
    }

    public function prescriptionTemplatesDestroy(RxTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Template deleted.');
    }
}
