<?php

namespace App\Models\Procurement;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceVendor;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 1 — Vendor Invoice.
 * Multiple invoices can be raised against one PO (partial shipments, split billing).
 *
 * On creation, automatically creates a FinanceExpense (Accounts Payable / unpaid bill)
 * so Finance has visibility without double entry.
 */
class VendorInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'vendor_invoices';

    protected $fillable = [
        'invoice_ref', 'purchase_order_id',
        'finance_vendor_id', 'inventory_vendor_id',
        'invoice_number', 'invoice_date', 'due_date', 'payment_terms',
        'invoice_amount', 'gst_amount', 'total_amount',
        'bill_attachment', 'notes', 'status',
        'finance_expense_id', 'created_by',
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'invoice_amount' => 'decimal:2',
        'gst_amount'     => 'decimal:2',
        'total_amount'   => 'decimal:2',
    ];

    /**
     * Statuses that mean "this bill still owes money".
     *
     * The DB enum is (draft|pending|approved|paid|cancelled) — there is NO
     * 'unpaid' value, yet the procurement analytics filtered on exactly that,
     * so every "outstanding vendor bill" figure silently returned zero.
     * Defined here so the enum and the queries can't drift apart again.
     */
    public const UNPAID_STATUSES = ['draft', 'pending', 'approved'];

    /* ── Relationships ── */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'finance_vendor_id');
    }

    public function inventoryVendor(): BelongsTo
    {
        return $this->belongsTo(InventoryVendor::class, 'inventory_vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorInvoiceItem::class, 'vendor_invoice_id');
    }

    /** The auto-created Accounts Payable / Finance expense entry */
    public function financeExpense(): BelongsTo
    {
        return $this->belongsTo(FinanceExpense::class, 'finance_expense_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Helpers ── */

    public static function generateRef(): string
    {
        $year  = now()->year;
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
        return 'VI-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'draft'     => 'Draft',
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'paid'      => 'Paid',
            'cancelled' => 'Cancelled',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'draft'     => '#a05c00',
            'pending'   => '#1a5ea8',
            'approved'  => '#5a6a00',
            'paid'      => '#1a7a45',
            'cancelled' => '#b52020',
            default     => '#555',
        };
    }
}
