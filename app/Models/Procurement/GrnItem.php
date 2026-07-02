<?php

namespace App\Models\Procurement;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1 — GRN: individual line item received in a GRN.
 */
class GrnItem extends Model
{
    protected $table = 'grn_items';

    protected $fillable = [
        'grn_id', 'purchase_order_item_id', 'inventory_item_id',
        'qty_received', 'unit_price', 'total_price',
        'batch_no', 'expiry_date', 'notes', 'stock_movement_id',
    ];

    protected $casts = [
        'expiry_date'  => 'date',
        'qty_received' => 'float',
        'unit_price'   => 'float',
        'total_price'  => 'float',
    ];

    /* ── Relationships ── */

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
