<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1 — Lab Master: service/rate catalog entry for a lab vendor.
 * Used as financial defaults when creating lab cases and for Phase 2 reconciliation.
 */
class LabVendorService extends Model
{
    protected $fillable = [
        'lab_vendor_id', 'service_name', 'category',
        'default_rate', 'unit', 'turnaround_days',
        'notes', 'is_active',
    ];

    protected $casts = [
        'default_rate'    => 'decimal:2',
        'turnaround_days' => 'integer',
        'is_active'       => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
