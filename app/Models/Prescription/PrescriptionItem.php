<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $table = 'prescription_items';

    protected $fillable = [
        'prescription_id', 'drug_id', 'drug_name', 'generic_name', 'strength',
        'dosage_form', 'route',
        // Dispensing snapshot — copied from drug master at time of prescribing
        'dispensing_type', 'unit_label',
        // Dosing
        'morning', 'afternoon', 'night', 'is_sos',
        'duration', 'duration_unit',
        'quantity', 'quantity_manual',
        // Instructions
        'food_advice', 'instructions',
        'patient_instruction_en', 'patient_instruction_mr', 'patient_instruction_hi',
        'sort_order',
    ];

    protected $casts = [
        'is_sos'          => 'boolean',
        'quantity_manual' => 'boolean',
        'morning'         => 'float',
        'afternoon'       => 'float',
        'night'           => 'float',
    ];

    public function drug()         { return $this->belongsTo(RxDrug::class, 'drug_id'); }
    public function prescription() { return $this->belongsTo(Prescription::class); }

    /**
     * Calculate dispensed quantity based on dispensing type.
     *
     * unit   (Tablet / Capsule)  → frequency × duration in days
     * pack   (Gel / Mouthwash)   → always 1; never auto-calculated (dentist edits if needed)
     * manual (Injection / LA)    → returns 0; quantity must be entered manually
     * volume (Syrup / Suspension)→ same as unit (frequency × duration), but in ml
     *
     * Returns 0 for manual/pack so the controller knows not to overwrite
     * a quantity the dentist has already entered.
     */
    public function calculateQuantity(): int
    {
        $type = $this->dispensing_type ?? RxDrug::DISPENSING_UNIT;

        // Pack-based drugs: default to 1, never recalculate
        if ($type === RxDrug::DISPENSING_PACK) {
            return $this->quantity ?: 1;
        }

        // Manual: return whatever is set; 0 signals "not yet entered"
        if ($type === RxDrug::DISPENSING_MANUAL) {
            return $this->quantity ?: 0;
        }

        // Unit / Volume: freq × duration (in days)
        $daily    = ($this->morning + $this->afternoon + $this->night);
        $duration = $this->duration ?? 1;

        if ($this->duration_unit === 'weeks')  $duration *= 7;
        if ($this->duration_unit === 'months') $duration *= 30;

        return (int) ceil($daily * $duration);
    }

    /**
     * Whether quantity should be shown as auto-calculated or manually entered
     * in the UI (used by blade / JS).
     */
    public function isQuantityAutoCalc(): bool
    {
        $type = $this->dispensing_type ?? RxDrug::DISPENSING_UNIT;
        return in_array($type, [RxDrug::DISPENSING_UNIT, RxDrug::DISPENSING_VOLUME]);
    }

    /** Liquid forms are dosed in millilitres (syrup / suspension / drops). */
    public function isLiquidDose(): bool
    {
        return in_array(strtolower((string) $this->dosage_form), ['syrup', 'suspension', 'drops'], true);
    }

    /**
     * Display a single time-of-day dose: "5 ml" for liquids, a plain trimmed
     * number for solids, or "—" when nothing is prescribed at that time.
     */
    public function doseCell($value): string
    {
        if (! (float) $value) {
            return '—';
        }
        $num = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        return $this->isLiquidDose() ? $num . ' ml' : $num;
    }

    /**
     * Total quantity label. Liquids show no total — the per-dose ml, frequency
     * and duration already describe the order, and a raw volume like "31.5 ml"
     * isn't a figure anyone dispenses against. Solids show the unit count.
     */
    public function quantityLabel(): string
    {
        if ($this->isLiquidDose() || ! $this->quantity) {
            return '—';
        }
        return (string) $this->quantity;
    }
}
