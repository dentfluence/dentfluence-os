<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryVendor extends Model
{
    protected $table = 'inventory_vendors';

    protected $fillable = [
        'vendor_name', 'contact_person', 'phone', 'whatsapp', 'email',
        'gst_no', 'address', 'city', 'state', 'pincode',
        'notes', 'credit_days', 'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'credit_days' => 'integer',
    ];

    /* ── Relationships ── */

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id');
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('vendor_name');
    }

    /* ── Helpers ── */

    /**
     * Alias so $vendor->name works everywhere (column is vendor_name).
     */
    public function getNameAttribute(): string
    {
        return $this->vendor_name ?? '';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->vendor_name . ($this->city ? " ({$this->city})" : '');
    }
}
