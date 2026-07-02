<?php

namespace App\Models\Inventory;

use App\Models\Finance\FinanceVendor;
use App\Models\Procurement\GoodsReceiptNote;
use App\Models\Procurement\VendorInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_no', 'vendor_id', 'finance_vendor_id',
        'order_date', 'expected_date',
        'status', 'invoice_status', 'invoiced_amount',
        'total_amount', 'gst_amount', 'notes',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'order_date'      => 'date',
        'expected_date'   => 'date',
        'approved_at'     => 'datetime',
        'total_amount'    => 'float',
        'gst_amount'      => 'float',
        'invoiced_amount' => 'float',
    ];

    /* ── Relationships ── */

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(InventoryVendor::class, 'vendor_id');
    }

    /** Phase 1: direct Finance vendor link */
    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'finance_vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Phase 1: GRN records raised against this PO */
    public function grns(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class, 'purchase_order_id');
    }

    /** Phase 1: Vendor invoices raised against this PO */
    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'purchase_order_id');
    }

    /* ── Scopes ── */

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'ordered', 'partially_received']);
    }

    public function scopeUninvoiced($query)
    {
        return $query->where('invoice_status', '!=', 'fully_invoiced')
                     ->where('status', 'completed');
    }

    /* ── Helpers ── */

    public static function generateOrderNo(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'PO-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'draft'               => 'Draft',
            'ordered'             => 'Ordered',
            'partially_received'  => 'Partial',
            'completed'           => 'Completed',
            'cancelled'           => 'Cancelled',
            default               => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'draft'               => '#a05c00',
            'ordered'             => '#1a5ea8',
            'partially_received'  => '#7a4a00',
            'completed'           => '#1a7a45',
            'cancelled'           => '#b52020',
            default               => '#555',
        };
    }

    /** Phase 1: label for invoice status badge */
    public function getInvoiceStatusLabel(): string
    {
        return match($this->invoice_status) {
            'none'            => 'Not Invoiced',
            'partial'         => 'Partially Invoiced',
            'fully_invoiced'  => 'Fully Invoiced',
            default           => '—',
        };
    }

    /**
     * Phase 1: recalculate invoice_status after a vendor invoice is created/deleted.
     * Called by VendorInvoiceController after saving/deleting an invoice.
     */
    public function recalculateInvoiceStatus(): void
    {
        $invoiced = (float) $this->vendorInvoices()->sum('invoice_amount');
        $total    = (float) $this->total_amount;

        $status = match(true) {
            $invoiced <= 0        => 'none',
            $invoiced < $total    => 'partial',
            default               => 'fully_invoiced',
        };

        $this->update([
            'invoiced_amount' => $invoiced,
            'invoice_status'  => $status,
        ]);
    }

    /**
     * Phase 1: resolve the Finance vendor for this PO.
     * Uses the direct finance_vendor_id link if available,
     * falls back to name-match (legacy behaviour).
     */
    public function resolveFinanceVendor(): ?FinanceVendor
    {
        if ($this->finance_vendor_id) {
            return $this->financeVendor;
        }

        if ($this->vendor) {
            return FinanceVendor::where('vendor_name', 'like', '%' . $this->vendor->vendor_name . '%')->first();
        }

        return null;
    }
}
