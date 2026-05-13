<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $fillable = [
        'treatment_category_id',
        'name',
        'description',
        'default_duration_minutes',
        'default_price',
        'is_active',
    ];

    protected $casts = [
        'default_price'            => 'decimal:2',
        'default_duration_minutes' => 'integer',
        'is_active'                => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(TreatmentCategory::class, 'treatment_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
