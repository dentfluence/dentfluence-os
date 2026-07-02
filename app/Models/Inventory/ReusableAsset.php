<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReusableAsset extends Model
{
    protected $table = 'reusable_assets';

    protected $fillable = [
        'inventory_item_id', 'asset_code', 'serial_number',
        'tracking_type', 'max_usage_count', 'current_usage_count', 'retirement_threshold',
        'sterilization_required', 'last_sterilized_at', 'sterilization_count',
        'maintenance_interval', 'last_maintained_at', 'next_maintenance_due',
        'status', 'purchase_date', 'notes', 'location_id',
    ];

    protected $casts = [
        'sterilization_required' => 'boolean',
        'last_sterilized_at'     => 'datetime',
        'last_maintained_at'     => 'datetime',
        'next_maintenance_due'   => 'datetime',
        'purchase_date'          => 'date',
    ];

    /* ── Relationships ── */

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /* ── Scopes ── */

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeDueForMaintenance($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('retirement_threshold')
              ->whereRaw('current_usage_count >= retirement_threshold');
        });
    }

    /* ── Helpers ── */

    public function getUsagePercentAttribute(): int
    {
        if (! $this->max_usage_count || $this->max_usage_count <= 0) return 0;
        return min(100, (int) round(($this->current_usage_count / $this->max_usage_count) * 100));
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'available'             => 'Available',
            'in_use'                => 'In Use',
            'sterilization_pending' => 'Sterilization Pending',
            'under_maintenance'     => 'Maintenance',
            'retired'               => 'Retired',
            default                 => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'available'             => '#1a7a45',
            'in_use'                => '#1a5ea8',
            'sterilization_pending' => '#a05c00',
            'under_maintenance'     => '#6a0f70',
            'retired'               => '#b52020',
            default                 => '#555',
        };
    }
}
