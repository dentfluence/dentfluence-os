<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationCategory extends Model
{
    protected $table = 'education_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function treatments(): HasMany
    {
        return $this->hasMany(EducationTreatment::class, 'category_id');
    }

    public function activeTreatments(): HasMany
    {
        return $this->hasMany(EducationTreatment::class, 'category_id')->where('is_published', true);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
