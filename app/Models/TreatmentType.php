<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'default_duration_minutes',
        'base_price',
        'color',
        'is_active',
        'sort_order',
        'recall_after_days', // Recall Settings — treatment-wise periodicity override
    ];

    protected $casts = [
        'base_price'        => 'decimal:2',
        'is_active'         => 'boolean',
        'recall_after_days' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
