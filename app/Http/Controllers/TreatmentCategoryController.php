<?php

namespace App\Http\Controllers;

use App\Models\TreatmentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentCategoryController extends Controller
{
    // ── API: list for dropdowns ───────────────────────────────────────────────

    public function index()
    {
        $categories = DB::table('treatment_categories')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'color', 'is_active']);

        return response()->json($categories);
    }

    public function treatments(int $category)
    {
        // Treatments live in the `treatments` table (the Treatment model / Treatment module),
        // NOT the legacy `treatment_types` table. We pull active, non-deleted treatments for
        // the given category so the appointment modal matches the Treatment module list.
        $treatments = DB::table('treatments')
            ->where('treatment_category_id', $category)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'default_duration_minutes', 'default_price']);

        return response()->json($treatments);
    }

    // ── Web: create category ──────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:treatment_categories,name',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:7',
            'is_active'   => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        TreatmentCategory::create($data);

        return redirect()->route('treatments.index')
            ->with('success', "Category \"{$data['name']}\" created.");
    }

    // ── Web: update category ──────────────────────────────────────────────────

    public function update(Request $request, TreatmentCategory $treatmentCategory)
    {
        $data = $request->validate([
            'name'        => "required|string|max:255|unique:treatment_categories,name,{$treatmentCategory->id}",
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:7',
            'is_active'   => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $treatmentCategory->update($data);

        return redirect()->route('treatments.index')
            ->with('success', "Category \"{$treatmentCategory->name}\" updated.");
    }

    // ── Web: delete category ──────────────────────────────────────────────────

    public function destroy(TreatmentCategory $treatmentCategory)
    {
        $count = $treatmentCategory->allTreatments()->count();

        if ($count > 0) {
            return redirect()->route('treatments.index')
                ->with('error', "Cannot delete \"{$treatmentCategory->name}\" — it has {$count} treatment(s). Move them first.");
        }

        $treatmentCategory->delete();

        return redirect()->route('treatments.index')
            ->with('success', "Category deleted.");
    }

    // ── Web: price list ───────────────────────────────────────────────────────

    public function priceList()
    {
        $categories = TreatmentCategory::with([
            'allTreatments' => fn($q) => $q->orderBy('sort_order')->orderBy('name'),
        ])
        ->active()
        ->orderBy('name')
        ->get();

        return view('treatments.price-list', compact('categories'));
    }
}
