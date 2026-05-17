<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────────────────────────────────
    // ↓↓↓  MERGE YOUR EXISTING $fillable / $casts / scopes / accessors BELOW ↓↓↓
    // ──────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        // TODO: paste your existing $fillable array here
    ];

    protected $casts = [
        // TODO: paste your existing $casts array here
        // e.g. 'photographs' => 'array',
        //      'scan_files'  => 'array',
        //      'clinical_data' => 'array',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships — child models
    // ──────────────────────────────────────────────────────────────────────────

    public function photographs(): HasMany
    {
        return $this->hasMany(ConsultationPhotograph::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ConsultationScan::class);
    }

    public function clinicalFindings(): HasMany
    {
        return $this->hasMany(ClinicalFinding::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ↑↑↑  Paste any remaining existing methods / scopes / accessors below  ↑↑↑
    // ──────────────────────────────────────────────────────────────────────────
}
