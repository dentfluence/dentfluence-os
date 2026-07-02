<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryVariant extends Model
{
    protected $table = 'inventory_variants';

    protected $fillable = ['sub_type_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** The sub-type this variant belongs to (e.g. Hand Files). */
    public function subType(): BelongsTo
    {
        return $this->belongsTo(InventorySubType::class, 'sub_type_id');
    }

    /** Products using this variant. */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'variant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
