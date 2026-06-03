<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_no', 'vendor_id', 'order_date', 'expected_date',
        'status', 'total_amount', 'gst_amount', 'notes', 'created_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'total_amount'  => 'float',
        'gst_amount'    => 'float',
    ];

    /* ── Relationships ── */

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(InventoryVendor::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Scopes ── */

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'ordered', 'partially_received']);
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
}
