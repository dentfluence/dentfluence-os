<?php

namespace App\Models\Procurement;

use App\Models\Inventory\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1 — Vendor Invoice line item.
 */
class VendorInvoiceItem extends Model
{
    protected $table = 'vendor_invoice_items';

    protected $fillable = [
        'vendor_invoice_id', 'inventory_item_id',
        'description', 'qty', 'unit_price', 'gst_rate', 'total_price',
    ];

    protected $casts = [
        'qty'         => 'float',
        'unit_price'  => 'float',
        'gst_rate'    => 'float',
        'total_price' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
