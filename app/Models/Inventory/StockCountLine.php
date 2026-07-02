<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockCountLine
 * One line per inventory item within a stock count session.
 */
class StockCountLine extends Model
{
    protected $fillable = [
        'session_id', 'inventory_item_id', 'category_name', 'product_name',
        'system_qty', 'physical_qty', 'variance', 'stock_status',
        'consumption_unit', 'minimum_qty', 'reorder_level',
        'notes', 'stock_movement_id',
    ];

    protected $casts = [
        'system_qty'   => 'float',
        'physical_qty' => 'float',
        'variance'     => 'float',
        'minimum_qty'  => 'float',
        'reorder_level'=> 'float',
    ];

    // ── Relationships ────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockCountSession::class, 'session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Derive stock_status from a physical qty and the item's thresholds.
     * critical: at or below minimum_qty
     * low:      above minimum but at or below reorder_level
     * out:      zero
     * healthy:  above reorder_level (or above minimum if no reorder set)
     */
    public static function deriveStatus(float $qty, float $minimum, float $reorder): string
    {
        if ($qty <= 0)            return 'out';
        if ($qty <= $minimum)     return 'critical';
        $threshold = $reorder > $minimum ? $reorder : $minimum;
        if ($qty <= $threshold)   return 'low';
        return 'healthy';
    }
}
