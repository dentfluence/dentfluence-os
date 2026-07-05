<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventorySubType;
use App\Models\Inventory\InventoryVariant;
use App\Models\Inventory\InventoryVendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InventorySettingsController (API v1)
 * -------------------------------------
 * Mobile mirror of App\Http\Controllers\InventoryController's admin-only
 * settings() / updateSettings() / storeCategory.../storeSubType.../
 * storeVariant.../storeLocation.../updateVendor() methods — same validation,
 * same "can't delete if in use" guards, same slug generation.
 *
 * ADMIN ONLY. Every route in this controller is gated by the `api.role:admin`
 * middleware in routes/api.php (not by in-method checks) — same convention as
 * every other role-gated write in this codebase.
 *
 *   GET    /inventory/settings                    everything the settings screen needs
 *   POST   /inventory/settings                     bulk-update the key/value settings
 *
 *   POST   /inventory/settings/categories           create
 *   PUT    /inventory/settings/categories/{cat}      update
 *   DELETE /inventory/settings/categories/{cat}      delete (blocked if items assigned)
 *
 *   POST   /inventory/settings/sub-types             create
 *   PUT    /inventory/settings/sub-types/{st}         update
 *   DELETE /inventory/settings/sub-types/{st}         delete (blocked if products assigned)
 *
 *   POST   /inventory/settings/variants               create
 *   PUT    /inventory/settings/variants/{variant}      update
 *   DELETE /inventory/settings/variants/{variant}      delete (blocked if products assigned)
 *
 *   POST   /inventory/settings/locations              create
 *   PUT    /inventory/settings/locations/{loc}         update
 *   DELETE /inventory/settings/locations/{loc}         soft-deactivate (blocked if stock assigned)
 *
 *   PUT    /inventory/vendors/{vendor}                 update vendor + resync to Finance
 */
class InventorySettingsController extends ApiController
{
    /**
     * GET /api/v1/inventory/settings
     * Everything the admin settings screen needs in one call.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = InventoryCategory::withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryCategory $c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'color'        => $c->color,
                'description'  => $c->description,
                'is_active'    => (bool) $c->is_active,
                'sort_order'   => $c->sort_order,
                'items_count'  => $c->items_count,
            ]);

        $locations = InventoryLocation::orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryLocation $l) => [
                'id'          => $l->id,
                'name'        => $l->name,
                'code'        => $l->code,
                'type'        => $l->type,
                'description' => $l->description,
                'is_active'   => (bool) $l->is_active,
                'sort_order'  => $l->sort_order,
            ]);

        $subTypes = InventorySubType::with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (InventorySubType $st) => [
                'id'            => $st->id,
                'category_id'   => $st->category_id,
                'category_name' => $st->category?->name,
                'name'          => $st->name,
                'is_active'     => (bool) $st->is_active,
            ]);

        $variants = InventoryVariant::with('subType')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryVariant $v) => [
                'id'            => $v->id,
                'sub_type_id'   => $v->sub_type_id,
                'sub_type_name' => $v->subType?->name,
                'name'          => $v->name,
                'is_active'     => (bool) $v->is_active,
            ]);

        $settings = DB::table('inventory_settings')
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->keyBy('key')
            ->map(fn ($row) => [
                'value'       => $row->value,
                'label'       => $row->label,
                'type'        => $row->type,
                'group'       => $row->group,
                'description' => $row->description,
            ]);

        return $this->success([
            'categories' => $categories,
            'locations'  => $locations,
            'sub_types'  => $subTypes,
            'variants'   => $variants,
            'settings'   => $settings,
        ]);
    }

    /**
     * POST /api/v1/inventory/settings
     * Same logic as web updateSettings(), with one deliberate difference:
     * the web form is HTML checkboxes, which omit the field entirely when
     * unchecked — so web treats "key missing from submission" as boolean
     * false. Mobile has no such ambiguity: the client always sends explicit
     * true/false for every boolean setting. So here we simply write every
     * key present in the submitted map and do NOT zero out omitted boolean
     * keys — "omitted" on mobile just means "not being touched this call",
     * not "unchecked".
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable|string|max:255',
        ]);

        foreach ($data['settings'] as $key => $value) {
            DB::table('inventory_settings')
                ->where('key', $key)
                ->update(['value' => $value ?? '0', 'updated_at' => now()]);
        }

        return $this->success(null, 'Settings saved successfully.');
    }

    /* ─────────────────────────────────────────────────────────
       CATEGORIES CRUD
    ───────────────────────────────────────────────────────── */

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $data['slug']       = Str::slug($data['name']);
        $data['sort_order'] = InventoryCategory::max('sort_order') + 1;
        $data['is_active']  = true;

        $cat = InventoryCategory::create($data);

        return $this->success(['id' => $cat->id], 'Category "' . $data['name'] . '" added.', 201);
    }

    public function updateCategory(Request $request, InventoryCategory $cat): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $data['slug']      = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $cat->update($data);

        return $this->success(null, 'Category updated.');
    }

    public function destroyCategory(InventoryCategory $cat): JsonResponse
    {
        if ($cat->items()->count() > 0) {
            return $this->error('Cannot delete — this category has ' . $cat->items()->count() . ' item(s) assigned to it.', [], 422);
        }

        $cat->delete();

        return $this->success(null, 'Category deleted.');
    }

    /* ─────────────────────────────────────────────────────────
       SUB-TYPES CRUD
    ───────────────────────────────────────────────────────── */

    public function storeSubType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:100',
        ]);
        $data['is_active'] = true;

        $st = InventorySubType::create($data);

        return $this->success(['id' => $st->id], 'Sub-type "' . $data['name'] . '" added.', 201);
    }

    public function updateSubType(Request $request, InventorySubType $st): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:100',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $st->update($data);

        return $this->success(null, 'Sub-type updated.');
    }

    public function destroySubType(InventorySubType $st): JsonResponse
    {
        if ($st->items()->count() > 0) {
            return $this->error('Cannot delete — ' . $st->items()->count() . ' product(s) use this sub-type.', [], 422);
        }

        $st->delete(); // cascades to inventory_variants

        return $this->success(null, 'Sub-type deleted.');
    }

    /* ─────────────────────────────────────────────────────────
       VARIANTS CRUD  (4th tier: Category → Sub-type → Variant)
    ───────────────────────────────────────────────────────── */

    public function storeVariant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_type_id' => 'required|exists:inventory_sub_types,id',
            'name'        => 'required|string|max:100',
        ]);
        $data['is_active'] = true;

        $variant = InventoryVariant::create($data);

        return $this->success(['id' => $variant->id], 'Variant "' . $data['name'] . '" added.', 201);
    }

    public function updateVariant(Request $request, InventoryVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'sub_type_id' => 'required|exists:inventory_sub_types,id',
            'name'        => 'required|string|max:100',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $variant->update($data);

        return $this->success(null, 'Variant updated.');
    }

    public function destroyVariant(InventoryVariant $variant): JsonResponse
    {
        if ($variant->items()->count() > 0) {
            return $this->error('Cannot delete — ' . $variant->items()->count() . ' product(s) use this variant.', [], 422);
        }

        $variant->delete();

        return $this->success(null, 'Variant deleted.');
    }

    /* ─────────────────────────────────────────────────────────
       LOCATIONS CRUD
    ───────────────────────────────────────────────────────── */

    public function storeLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20|unique:inventory_locations,code',
            'type'        => 'required|in:main_store,operatory,sterilization,lab,implant_drawer,storage,other',
            'description' => 'nullable|string|max:255',
        ]);

        $data['sort_order'] = InventoryLocation::max('sort_order') + 1;
        $data['is_active']  = true;

        $loc = InventoryLocation::create($data);

        return $this->success(['id' => $loc->id], 'Location "' . $data['name'] . '" added.', 201);
    }

    public function updateLocation(Request $request, InventoryLocation $loc): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20|unique:inventory_locations,code,' . $loc->id,
            'type'        => 'required|in:main_store,operatory,sterilization,lab,implant_drawer,storage,other',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $loc->update($data);

        return $this->success(null, 'Location updated.');
    }

    public function destroyLocation(InventoryLocation $loc): JsonResponse
    {
        if ($loc->stocks()->where('available_qty', '>', 0)->count() > 0) {
            return $this->error('Cannot delete — this location has stock assigned to it.', [], 422);
        }

        // Same as web: soft-deactivate rather than hard-delete.
        $loc->update(['is_active' => false]);

        return $this->success(null, 'Location deactivated.');
    }

    /* ─────────────────────────────────────────────────────────
       VENDOR UPDATE (edit form)
    ───────────────────────────────────────────────────────── */

    public function updateVendor(Request $request, InventoryVendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'vendor_name'    => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'whatsapp'       => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'gst_no'         => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:80',
            'credit_days'    => 'nullable|integer|min:0',
        ]);

        $vendor->update($data);

        // Phase 1: keep Finance mirror in sync (same as web).
        $vendor->refresh()->syncToFinance();

        return $this->success(null, 'Vendor "' . $vendor->vendor_name . '" updated.');
    }
}
