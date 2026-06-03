<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    protected $table = 'inventory_locations';

    protected $fillable = [
        'name', 'code', 'type', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /* ── Relationships ── */

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'location_id');
    }

    public function reusableAssets(): HasMany
    {
        return $this->hasMany(ReusableAsset::class, 'location_id');
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /* ── Helpers ── */

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'main_store'     => 'Main Store',
            'operatory'      => 'Operatory',
            'sterilization'  => 'Sterilization',
            'lab'            => 'Lab',
            'implant_drawer' => 'Implant Drawer',
            'storage'        => 'Storage',
            default          => 'Other',
        };
    }
}
