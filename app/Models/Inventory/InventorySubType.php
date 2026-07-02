<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySubType extends Model
{
    protected $table = 'inventory_sub_types';

    protected $fillable = ['category_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'sub_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
