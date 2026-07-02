<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1 — Lab Master: individual contact person at a lab vendor.
 */
class LabVendorContact extends Model
{
    protected $fillable = [
        'lab_vendor_id', 'name', 'role',
        'phone', 'whatsapp', 'email',
        'is_primary', 'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }
}
