<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentVisitItem extends Model
{
    /**
     * Slice 2.4b — what happened to this planned treatment TODAY.
     * A fact about one visit, never a running status. NULL is legitimate
     * (ad-hoc work, or items recorded before this slice).
     */
    public const WORK_STARTED         = 'started';
    public const WORK_WORKED_ON       = 'worked_on';
    public const WORK_COMPLETED_TODAY = 'completed_today';

    /** Dentist-facing wording. Internal keys are never shown. */
    public const WORK_OUTCOMES = [
        self::WORK_STARTED         => 'Started',
        self::WORK_WORKED_ON       => 'Worked On',
        self::WORK_COMPLETED_TODAY => 'Completed Today',
    ];

    protected $fillable = [
        'treatment_visit_id',
        'patient_id',
        'treatment_name',
        'material_option',
        'tooth_number',
        'suggested_price',
        'treatment_plan_item_id',
        'work_outcome',
        'billing_status',
        'invoice_item_id',
        'notes',
        // Repeat-work tracking
        'is_repeat',
        'repeat_reason',
        'repeat_of_visit_item_id',
    ];

    protected $casts = [
        'suggested_price' => 'decimal:2',
        'is_repeat'       => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function visit(): BelongsTo
    {
        return $this->belongsTo(TreatmentVisit::class, 'treatment_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /** The original item this one repeats (if flagged as repeat work). */
    public function repeatOf(): BelongsTo
    {
        return $this->belongsTo(TreatmentVisitItem::class, 'repeat_of_visit_item_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Human-readable label: "RCT — Ceramic — Tooth 26" */
    public function label(): string
    {
        $parts = [$this->treatment_name];
        if ($this->material_option) $parts[] = $this->material_option;
        if ($this->tooth_number)    $parts[] = 'Tooth ' . $this->tooth_number;
        return implode(' — ', $parts);
    }

    public function isPending(): bool
    {
        return $this->billing_status === 'pending';
    }
}
