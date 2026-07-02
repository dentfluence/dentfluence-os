<?php

namespace App\Models\Procurement;

use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 1 — GRN: Goods Receipt Note header.
 * Multiple GRNs can exist against one PO (partial deliveries).
 */
class GoodsReceiptNote extends Model
{
    protected $table = 'goods_receipt_notes';

    protected $fillable = [
        'grn_number', 'purchase_order_id', 'vendor_id',
        'received_date', 'location_id', 'notes',
        'vendor_invoice_id', 'status', 'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    /* ── Relationships ── */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(InventoryVendor::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Helpers ── */

    public static function generateGrnNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'GRN-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /** Total value of all received items in this GRN */
    public function totalValue(): float
    {
        return (float) $this->items()->sum('total_price');
    }
}
