<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\ReusableAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReusableAssetController (API v1)
 * ---------------------------------
 * Mobile mirror of App\Http\Controllers\InventoryController's
 * reusableAssets()/storeAsset()/updateAsset()/updateAssetStatus() methods —
 * same validation, same status/action semantics, same "one brain" as web.
 *
 * Each ReusableAsset row is one physical instrument (e.g. "Implant Drill
 * #001") backed by an inventory_item_id, with its own lifecycle: usage
 * count, sterilization history, maintenance schedule, retirement threshold.
 * This is distinct from the plain consumable/reusable flag on InventoryItem.
 *
 *   GET   /inventory/reusable-assets              paginated list (+ status_counts + meta)
 *   POST  /inventory/reusable-assets              create an asset
 *   PUT   /inventory/reusable-assets/{asset}       update an asset
 *   POST  /inventory/reusable-assets/{asset}/status  sterilized|maintained|retire|mark_available|mark_in_use
 *
 * Writes are role-gated in routes/api.php (admin, front_desk) — same as
 * every other inventory write endpoint.
 */
class ReusableAssetController extends ApiController
{
    /**
     * GET /api/v1/inventory/reusable-assets
     * Filters: search (asset_code/serial_number/product_name), status, location_id.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $locId  = $request->get('location_id');

        $query = ReusableAsset::with(['item', 'location'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%$search%")
                  ->orWhere('serial_number', 'like', "%$search%")
                  ->orWhereHas('item', fn ($qi) => $qi->where('product_name', 'like', "%$search%"));
            });
        }
        if ($status) $query->where('status', $status);
        if ($locId)  $query->where('location_id', $locId);

        $page = $this->paginate($query, $request);

        $statusCounts = ReusableAsset::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $locations = InventoryLocation::orderBy('name')->get(['id', 'name']);
        $items     = InventoryItem::orderBy('product_name')->get(['id', 'product_name']);

        return $this->respond($page, fn (ReusableAsset $a) => $this->mapAsset($a), '', [
            'status_counts' => $statusCounts,
            'meta' => [
                'locations' => $locations,
                'items'     => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/inventory/reusable-assets
     * Same validation as web storeAsset().
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id'       => 'required|exists:inventory_items,id',
            'asset_code'              => 'required|string|max:50|unique:reusable_assets,asset_code',
            'serial_number'           => 'nullable|string|max:80',
            'tracking_type'           => 'required|in:usage_based,sterilization_based,time_based',
            'max_usage_count'         => 'nullable|integer|min:1',
            'retirement_threshold'    => 'nullable|integer|min:1',
            'sterilization_required'  => 'boolean',
            'maintenance_interval'    => 'nullable|integer|min:1',
            'status'                  => 'required|in:available,in_use,sterilization_pending,under_maintenance,retired',
            'purchase_date'           => 'nullable|date',
            'location_id'             => 'nullable|exists:inventory_locations,id',
            'notes'                   => 'nullable|string',
        ]);

        $data['sterilization_required'] = $request->boolean('sterilization_required');
        $asset = ReusableAsset::create($data);
        $asset->load(['item', 'location']);

        return $this->success($this->mapAsset($asset), 'Asset added successfully.', 201);
    }

    /**
     * PUT /api/v1/inventory/reusable-assets/{asset}
     * Same validation as web updateAsset() (unique check excludes self).
     */
    public function update(Request $request, ReusableAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'asset_code'              => 'required|string|max:50|unique:reusable_assets,asset_code,' . $asset->id,
            'serial_number'           => 'nullable|string|max:80',
            'tracking_type'           => 'required|in:usage_based,sterilization_based,time_based',
            'max_usage_count'         => 'nullable|integer|min:1',
            'retirement_threshold'    => 'nullable|integer|min:1',
            'sterilization_required'  => 'boolean',
            'maintenance_interval'    => 'nullable|integer|min:1',
            'status'                  => 'required|in:available,in_use,sterilization_pending,under_maintenance,retired',
            'purchase_date'           => 'nullable|date',
            'location_id'             => 'nullable|exists:inventory_locations,id',
            'notes'                   => 'nullable|string',
        ]);

        $data['sterilization_required'] = $request->boolean('sterilization_required');
        $asset->update($data);
        $asset->load(['item', 'location']);

        return $this->success($this->mapAsset($asset), 'Asset updated.');
    }

    /**
     * POST /api/v1/inventory/reusable-assets/{asset}/status
     * Same action enum + match logic as web updateAssetStatus() exactly.
     */
    public function updateStatus(Request $request, ReusableAsset $asset): JsonResponse
    {
        $request->validate(['action' => 'required|in:sterilized,maintained,retire,mark_available,mark_in_use']);

        match ($request->action) {
            'sterilized'     => $asset->update([
                'status'              => 'available',
                'last_sterilized_at'  => now(),
                'sterilization_count' => $asset->sterilization_count + 1,
            ]),
            'maintained'     => $asset->update([
                'status'               => 'available',
                'last_maintained_at'   => now(),
                'next_maintenance_due' => $asset->maintenance_interval
                    ? now()->addDays($asset->maintenance_interval) : null,
            ]),
            'retire'         => $asset->update(['status' => 'retired']),
            'mark_available' => $asset->update(['status' => 'available']),
            'mark_in_use'    => $asset->update([
                'status'              => 'in_use',
                'current_usage_count' => $asset->current_usage_count + 1,
            ]),
        };

        $asset->load(['item', 'location']);

        return $this->success($this->mapAsset($asset), 'Asset status updated.');
    }

    /* ═══════════════════════════════════════════════════════════
       Helpers — pagination + response shaping
    ═══════════════════════════════════════════════════════════ */

    /** Paginate any query builder with clamped limit (same convention as Api\V1\InventoryController). */
    private function paginate($query, Request $request, int $default = 20): LengthAwarePaginator
    {
        $limit = (int) $request->query('limit', $default);
        $limit = max(1, min($limit, 100));

        return $query->paginate($limit)->appends($request->query());
    }

    /** Map a paginator through a callback and return the standard envelope + meta. */
    private function respond(LengthAwarePaginator $page, callable $map, string $message = '', array $extraMeta = []): JsonResponse
    {
        $items = collect($page->items())->map($map)->values();

        $meta = array_merge([
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ], $extraMeta);

        return $this->success($items, $message, 200, $meta);
    }

    private function mapAsset(ReusableAsset $a): array
    {
        return [
            'id'                     => $a->id,
            'asset_code'             => $a->asset_code,
            'serial_number'          => $a->serial_number,
            'tracking_type'          => $a->tracking_type,
            'max_usage_count'        => $a->max_usage_count,
            'current_usage_count'    => $a->current_usage_count,
            'retirement_threshold'   => $a->retirement_threshold,
            'usage_percent'          => $a->usage_percent,
            'sterilization_required' => (bool) $a->sterilization_required,
            'last_sterilized_at'     => $a->last_sterilized_at?->toIso8601String(),
            'sterilization_count'    => $a->sterilization_count,
            'maintenance_interval'   => $a->maintenance_interval,
            'last_maintained_at'     => $a->last_maintained_at?->toIso8601String(),
            'next_maintenance_due'   => $a->next_maintenance_due?->toIso8601String(),
            'status'                 => $a->status,
            'status_label'           => $a->getStatusLabel(),
            'purchase_date'          => $a->purchase_date ? (optional($a->purchase_date)->format('Y-m-d') ?? (string) $a->purchase_date) : null,
            'notes'                  => $a->notes,
            'item_id'                => $a->inventory_item_id,
            'product_name'           => $a->item?->product_name,
            'location_id'            => $a->location_id,
            'location_name'          => $a->location?->name,
        ];
    }
}
