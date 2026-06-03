<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    protected $table = 'inventory_stocks';

    protected $fillable = [
        'inventory_item_id', 'location_id', 'available_qty', 'reserved_qty',
    ];

    protected $casts = [
        'available_qty' => 'float',
        'reserved_qty'  => 'float',
    ];

    /* ── Relationships ── */

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /* ── Helpers ── */

    /**
     * Net usable quantity (available minus reserved).
     */
    public function getNetQtyAttribute(): float
    {
        return max(0, $this->available_qty - $this->reserved_qty);
    }

    /**
     * Adjust stock quantity (used by StockMovement observer).
     * Never call this directly from a controller — only via StockMovement.
     */
    public function adjustQty(float $delta): void
    {
        $this->available_qty = max(0, $this->available_qty + $delta);
        $this->save();
    }
}
