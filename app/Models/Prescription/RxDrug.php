<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RxDrug extends Model
{
    use SoftDeletes;

    protected $table = 'rx_drugs';

    // ── Dispensing type constants ─────────────────────────────────────────────
    const DISPENSING_UNIT   = 'unit';   // Tablet / Capsule → qty = freq × days
    const DISPENSING_PACK   = 'pack';   // Gel / Mouthwash / Tube → qty defaults to 1
    const DISPENSING_MANUAL = 'manual'; // Injection / LA Cartridge → qty entered manually
    const DISPENSING_VOLUME = 'volume'; // Syrup / Suspension → volume-based

    protected $fillable = [
        'drug_code', 'brand_name', 'generic_id', 'category_id', 'strength',
        'dosage_form', 'composition', 'route_id',
        // Dispensing logic
        'dispensing_type', 'unit_label', 'pack_size',
        // Dose defaults
        'default_dose', 'adult_dose', 'pediatric_dose',
        'default_duration', 'default_duration_unit',
        'default_food_instruction_id', 'default_instructions',
        // Safety
        'max_daily_dose', 'duplicate_molecule_group', 'antibiotic_class',
        'is_controlled', 'pregnancy_category',
        'breastfeeding_safety', 'pediatric_safety', 'geriatric_caution',
        'renal_dose_adjustment', 'hepatic_dose_adjustment',
        'contraindications', 'drug_interactions_note',
        // JSON tag arrays for safety matching
        'allergy_tags', 'interaction_tags',
        // Dental context
        'common_dental_uses', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_controlled'    => 'boolean',
        'is_active'        => 'boolean',
        'allergy_tags'     => 'array',
        'interaction_tags' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function generic()
    {
        return $this->belongsTo(RxGeneric::class, 'generic_id');
    }

    public function category()
    {
        return $this->belongsTo(RxDrugCategory::class, 'category_id');
    }

    public function route()
    {
        return $this->belongsTo(RxRouteOfAdmin::class, 'route_id');
    }

    public function defaultFoodInstruction()
    {
        return $this->belongsTo(RxFoodInstruction::class, 'default_food_instruction_id');
    }

    public function warningRules()
    {
        return $this->hasMany(RxWarningRule::class, 'drug_id');
    }

    // ── Dispensing helpers ────────────────────────────────────────────────────

    public function isUnitBased(): bool   { return ($this->dispensing_type ?? self::DISPENSING_UNIT) === self::DISPENSING_UNIT; }
    public function isPackBased(): bool   { return $this->dispensing_type === self::DISPENSING_PACK; }
    public function isManual(): bool      { return $this->dispensing_type === self::DISPENSING_MANUAL; }
    public function isVolumeBased(): bool { return $this->dispensing_type === self::DISPENSING_VOLUME; }

    /**
     * Default quantity hint for the prescription form when this drug is added.
     * - pack   → 1 (e.g. 1 tube, 1 bottle)
     * - unit   → null (JS calculates freq × duration on the form)
     * - manual → null (dentist must enter)
     * - volume → null (JS or manual)
     */
    public function defaultQuantityForForm(): ?int
    {
        return $this->isPackBased() ? 1 : null;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($q) { return $q->where('is_active', true); }

    /**
     * Smart search across brand name, generic name, drug category, and composition.
     * Partial match — e.g. "amo" → Amoxicillin, Amoxiclav, Amoxycillin Suspension.
     */
    public function scopeSearch($q, string $term)
    {
        return $q->where(function ($q) use ($term) {
            $q->where('brand_name',   'like', "%{$term}%")
              ->orWhere('composition', 'like', "%{$term}%")
              ->orWhereHas('generic',  fn($g) => $g->where('name',  'like', "%{$term}%"))
              ->orWhereHas('category', fn($c) => $c->where('name',  'like', "%{$term}%"));
        });
    }
}
