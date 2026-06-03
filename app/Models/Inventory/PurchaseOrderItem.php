<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'inventory_item_id',
        'qty_ordered', 'qty_received', 'unit_price', 'gst_rate', 'total_price',
        'batch_no', 'expiry_date', 'notes',
    ];

    protected $casts = [
        'qty_ordered'  => 'float',
        'qty_received' => 'float',
        'unit_price'   => 'float',
        'gst_rate'     => 'float',
        'total_price'  => 'float',
        'expiry_date'  => 'date',
    ];

    /* ── Relationships ── */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /* ── Helpers ── */

    public function getPendingQtyAttribute(): float
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_ordered;
    }
}
