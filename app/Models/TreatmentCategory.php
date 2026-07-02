<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'billing_basis',
        'is_phased',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_phased'  => 'boolean',
    ];

    public function treatments()
    {
        return $this->hasMany(Treatment::class)->where('is_active', true)->orderBy('name');
    }

    public function allTreatments()
    {
        return $this->hasMany(Treatment::class)->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
