<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local term/code → standard code (SNOMED/LOINC/ICD-10/ATC/FDI). FHIR ConceptMap.
 */
class TerminologyMap extends Model
{
    protected $fillable = [
        'domain', 'local_code', 'local_term',
        'standard_system', 'standard_code', 'standard_display', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }
}
