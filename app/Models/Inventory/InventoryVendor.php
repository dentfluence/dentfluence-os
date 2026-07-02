<?php

namespace App\Models\Inventory;

use App\Models\Finance\FinanceVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryVendor extends Model
{
    protected $table = 'inventory_vendors';

    protected $fillable = [
        'vendor_name', 'contact_person', 'phone', 'whatsapp', 'email',
        'gst_no', 'address', 'city', 'state', 'pincode',
        'notes', 'credit_days', 'is_active',
        'finance_vendor_id',   // Phase 1: sync link to Finance
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

    /** Phase 1: linked Finance vendor (auto-synced on create/update) */
    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'finance_vendor_id');
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

    /* ── Phase 1: Finance Sync ── */

    /**
     * Creates or updates the mirrored FinanceVendor record.
     * Called automatically after an InventoryVendor is saved.
     *
     * Rules (from roadmap):
     *   - Inventory vendors auto-sync to Finance as 'dental_supplier'.
     *   - Finance-only vendors never appear in Inventory.
     *   - The Finance copy is the source-of-truth for payments/outstanding.
     */
    public function syncToFinance(): FinanceVendor
    {
        $data = [
            'vendor_name'   => $this->vendor_name,
            'company_name'  => $this->vendor_name,
            'vendor_type'   => 'dental_supplier',
            'phone'         => $this->phone,
            'email'         => $this->email,
            'address'       => $this->address,
            'city'          => $this->city,
            'state'         => $this->state,
            'pincode'       => $this->pincode,
            'gstin'         => $this->gst_no,
            'credit_days'   => $this->credit_days ?? 0,
            'is_active'     => $this->is_active,
            'notes'         => $this->notes,
        ];

        if ($this->finance_vendor_id) {
            // Update existing mirror
            $fv = FinanceVendor::find($this->finance_vendor_id);
            if ($fv) {
                $fv->update($data);
                return $fv;
            }
        }

        // Create new Finance vendor and store the link
        $fv = FinanceVendor::create($data);
        $this->updateQuietly(['finance_vendor_id' => $fv->id]);

        return $fv;
    }
}
