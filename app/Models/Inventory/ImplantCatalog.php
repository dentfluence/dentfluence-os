<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ImplantCatalog extends Model
{
    use SoftDeletes;

    protected $table = 'implant_catalog';

    protected $fillable = [
        'brand', 'system', 'component_type', 'inventory_item_id', 'product_code',
        'description', 'diameter_mm', 'length_mm', 'platform',
        'material', 'photo_path', 'unit_price', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'unit_price' => 'decimal:2',
    ];

    /* ── Relationships ── */

    public function placements(): HasMany
    {
        return $this->hasMany(ImplantPlacement::class, 'implant_catalog_id');
    }

    /**
     * The stock-tracked inventory_items row backing this catalog entry.
     * Nullable — legacy/demo rows may not have one yet; the controller
     * backfills it the next time the catalog entry is edited.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Helpers ── */

    public function getComponentTypeLabel(): string
    {
        return match($this->component_type) {
            'fixture'          => 'Fixture',
            'abutment'         => 'Abutment',
            'healing_abutment' => 'Healing Abutment',
            'analogue'         => 'Analogue',
            'scan_body'        => 'Scan Body',
            'coping'           => 'Coping',
            'cover_screw'      => 'Cover Screw',
            'graft'            => 'Bone Graft',
            default            => 'Other',
        };
    }

    public function getFullName(): string
    {
        $parts = array_filter([
            $this->brand,
            $this->system,
            $this->getComponentTypeLabel(),
            $this->diameter_mm ? $this->diameter_mm . 'mm ø' : null,
            $this->length_mm   ? $this->length_mm . 'mm L'  : null,
        ]);
        return implode(' — ', $parts);
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
