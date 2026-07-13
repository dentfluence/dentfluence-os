<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabVendorPriceList — one uploaded price-list file for a lab vendor.
 * Append-only: every upload is kept so there's a dated history to point
 * back to if a lab disputes an old rate. The most recent row (by
 * created_at) is the vendor's "current" price list.
 */
class LabVendorPriceList extends Model
{
    protected $fillable = [
        'lab_vendor_id', 'file_path', 'original_name', 'file_size', 'uploaded_by',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
