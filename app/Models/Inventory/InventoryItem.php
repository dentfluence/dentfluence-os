<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        // Original fields
        'item_code', 'product_name', 'generic_name', 'brand', 'description', 'image',
        'category_id', 'subcategory_id',
        'inventory_behavior', 'usage_type',
        'purchase_unit', 'consumption_unit', 'pieces_per_unit',
        'last_purchase_price', 'average_purchase_price', 'cost_per_usage', 'mrp', 'gst_rate',
        'minimum_qty', 'minimum_order_qty',
        'has_expiry', 'expiry_alert_days',
        'is_reusable', 'tracking_type', 'max_usage_count', 'sterilization_required',
        'is_active', 'created_by',
        // Product Master fields
        'sub_type_id', 'variant_id',
        'packaging_type', 'qty_in_packaging', 'packaging_unit_label', 'pack_size_label',
        'shelf_life_months',
        'company_name', 'alternative_brands', 'preferred_brand',
        'last_purchase_date',
        'reorder_level',
        'treatment_tags',
        'product_notes',
    ];

    protected $casts = [
        'has_expiry'             => 'boolean',
        'is_reusable'            => 'boolean',
        'is_active'              => 'boolean',
        'sterilization_required' => 'boolean',
        'last_purchase_price'    => 'float',
        'average_purchase_price' => 'float',
        'cost_per_usage'         => 'float',
        'mrp'                    => 'float',
        'gst_rate'               => 'float',
        'minimum_qty'            => 'float',
        'minimum_order_qty'      => 'float',
        'qty_in_packaging'       => 'float',
        'reorder_level'          => 'float',
        'alternative_brands'     => 'array',
        'treatment_tags'         => 'array',
        'last_purchase_date'     => 'date',
    ];

    /* ── Relationships ── */

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'subcategory_id');
    }

    public function subType(): BelongsTo
    {
        return $this->belongsTo(InventorySubType::class, 'sub_type_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(InventoryVariant::class, 'variant_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_item_id')->latest();
    }

    public function reusableAssets(): HasMany
    {
        return $this->hasMany(ReusableAsset::class, 'inventory_item_id');
    }

    public function dealers(): BelongsToMany
    {
        return $this->belongsToMany(
            InventoryVendor::class,
            'product_dealers',
            'inventory_item_id',
            'vendor_id'
        )->withPivot('is_primary', 'is_alternate')->withTimestamps();
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('stocks', function ($q) {
            $q->whereRaw('available_qty <= (SELECT minimum_qty FROM inventory_items WHERE id = inventory_stocks.inventory_item_id)');
        });
    }

    /* ── Computed helpers ── */

    /**
     * Total stock across all locations.
     */
    public function getTotalStockAttribute(): float
    {
        return $this->stocks->sum('available_qty');
    }

    /**
     * Whether item is below minimum stock threshold.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->total_stock <= $this->minimum_qty;
    }

    /**
     * Whether item is completely out of stock.
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->total_stock <= 0;
    }

    /**
     * Total inventory value for this item.
     */
    public function getTotalValueAttribute(): float
    {
        return round($this->total_stock * $this->average_purchase_price, 2);
    }

    /**
     * Cost per unit — purchase price divided by qty_in_packaging.
     * e.g. ₹1850 / 4g = ₹462.50/g
     */
    public function getCostPerUnitAttribute(): ?string
    {
        if ($this->last_purchase_price && $this->qty_in_packaging && $this->qty_in_packaging > 0) {
            $val  = round($this->last_purchase_price / $this->qty_in_packaging, 2);
            $unit = $this->packaging_unit_label ?? 'unit';
            return '₹' . number_format($val, 2) . ' / ' . $unit;
        }
        return null;
    }

    /**
     * Stock health level for a given qty.
     * Returns: 'healthy', 'low', 'critical', 'out'
     */
    public function stockHealth(float $qty): string
    {
        if ($qty <= 0)                        return 'out';
        if ($qty <= ($this->minimum_qty / 2)) return 'critical';
        if ($qty <= $this->minimum_qty)       return 'low';
        return 'healthy';
    }
}
