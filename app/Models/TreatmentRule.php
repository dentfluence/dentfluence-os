<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentRule extends Model
{
    protected $fillable = [
        'treatment_id',
        'rule_type',
        'value',
        'note',
        'is_active',
    ];

    protected $casts = [
        'value'     => 'array',
        'is_active' => 'boolean',
    ];

    // Human-readable labels for each rule type (used in UI)
    public const LABELS = [
        'xray_required'      => 'X-Ray Required',
        'consent_required'   => 'Consent Form Required',
        'lab_required'       => 'Lab Case Required',
        'min_visits'         => 'Minimum Visits',
        'max_visits'         => 'Maximum Visits',
        'anesthesia_required'=> 'Anesthesia Required',
        'referral_required'  => 'Referral Required',
        'max_discount_pct'   => 'Maximum Discount %',
        'age_restriction'    => 'Age Restriction',
        'medical_clearance'  => 'Medical Clearance Required',
        'follow_up_days'     => 'Follow-Up Within (Days)',
        'custom'             => 'Custom Rule',
    ];

    // Which rule types are simple boolean toggles (no value needed)
    public const BOOLEAN_RULES = [
        'xray_required',
        'consent_required',
        'lab_required',
        'anesthesia_required',
        'referral_required',
        'medical_clearance',
    ];

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->rule_type] ?? ucwords(str_replace('_', ' ', $this->rule_type));
    }

    public function isBooleanRule(): bool
    {
        return in_array($this->rule_type, self::BOOLEAN_RULES);
    }
}
