<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalFinding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consultation_id',
        // Oral examination
        'oral_hygiene',
        'gingival_health',
        'periodontal_status',
        'occlusion',
        'tmj_status',
        'soft_tissue',
        'hard_tissue',
        // Chart
        'chart_data',
        // Free-text
        'notes',
    ];

    protected $casts = [
        'chart_data' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
