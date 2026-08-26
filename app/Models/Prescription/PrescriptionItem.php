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
        'morning', 'afternoon', 'night', 'is_sos', 'sos_dose',
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
        'sos_dose'        => 'float',
    ];

    public function drug()         { return $this->belongsTo(RxDrug::class, 'drug_id'); }
    public function prescription() { return $this->belongsTo(Prescription::class); }

    /**
     * Brand name only — never carries the strength.
     *
     * `drug_name` is a prescribe-time snapshot of the brand ("Zerodol P").
     * Older rows were saved as "brand + strength" and, because the edit form
     * re-appended the strength on every reload, some accumulated it more than
     * once ("Zerodol P 100+500mg 100+500mg"). Strength lives in `strength`
     * and is rendered separately, so any copy of it inside the name is
     * stripped here — which also cleans legacy rows at read time, without a
     * data migration.
     */
    public function displayName(): string
    {
        $name = trim((string) ($this->drug_name ?: ''));

        if ($name === '') {
            return $this->drug?->brand_name ?: '—';
        }

        foreach (array_filter([$this->strength, $this->drug?->strength]) as $strength) {
            $name = preg_replace('/\s*' . preg_quote(trim((string) $strength), '/') . '(?![\w])/i', '', $name);
        }

        $name = trim(preg_replace('/\s{2,}/', ' ', (string) $name));

        return $name !== '' ? $name : ($this->drug?->brand_name ?: '—');
    }

    /**
     * "Zerodol P 100+500mg" — brand plus strength exactly once. For single-line
     * contexts (WhatsApp text, ABDM payloads) that have no second line to put
     * the strength on. Screens and print use displayName() + strength column.
     */
    public function nameWithStrength(): string
    {
        $strength = trim((string) ($this->strength ?: ''));

        return trim($this->displayName() . ($strength !== '' ? ' ' . $strength : ''));
    }

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

    /**
     * Liquid forms are dosed in millilitres (syrup / suspension / drops).
     * `dosage_form` is a free-text snapshot from the drug master (e.g. "Oral
     * Suspension", "Paediatric Drops") rather than a clean enum, so this is a
     * keyword match — not an exact one — otherwise compound labels would
     * silently fall through as "solid" and print without the ml unit.
     */
    public function isLiquidDose(): bool
    {
        $form = strtolower((string) $this->dosage_form);
        foreach (['syrup', 'suspension', 'drop'] as $kw) {
            if (str_contains($form, $kw)) {
                return true;
            }
        }
        return false;
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
     * SOS cell for print: "SOS" alone when no amount was recorded (legacy
     * rows, or a doctor who left it blank), otherwise "SOS · 5 ml" /
     * "SOS · 1" so the dispensing amount for the as-needed dose is explicit.
     * Kept for any single-line/plain-text context; UI table cells should
     * prefer sosDoseLabel() rendered on its own line — "SOS · 10 ml" doesn't
     * fit an ~32-44px table column and wraps badly mid-word.
     */
    public function sosCell(): string
    {
        if (! $this->is_sos) {
            return '—';
        }
        $label = $this->sosDoseLabel();
        return $label ? 'SOS · ' . $label : 'SOS';
    }

    /**
     * Just the amount part of an SOS dose ("10 ml" / "1"), or null if SOS
     * isn't set or no amount was recorded. Meant to be rendered as its own
     * short line under a plain "SOS" badge so narrow table columns don't
     * wrap "SOS · 10 ml" across three lines.
     */
    public function sosDoseLabel(): ?string
    {
        if (! $this->is_sos || ! (float) $this->sos_dose) {
            return null;
        }
        $num = rtrim(rtrim(number_format((float) $this->sos_dose, 2, '.', ''), '0'), '.');
        return $num . ($this->isLiquidDose() ? ' ml' : '');
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
