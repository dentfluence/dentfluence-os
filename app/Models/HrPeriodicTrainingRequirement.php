<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPeriodicTrainingRequirement extends Model
{
    protected $table = 'hr_periodic_training_requirements';

    protected $fillable = [
        'name', 'description', 'applies_to', 'frequency_months', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(HrPeriodicTrainingRecord::class, 'requirement_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Human-readable frequency e.g. "Every 6 months", "Yearly"
    public function getFrequencyLabelAttribute(): string
    {
        $m = $this->frequency_months;
        if ($m === 12)  return 'Yearly';
        if ($m === 24)  return 'Every 2 years';
        if ($m % 12 === 0) return 'Every ' . ($m / 12) . ' years';
        return "Every {$m} months";
    }
}
