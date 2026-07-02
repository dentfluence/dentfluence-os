<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentVisitItem extends Model
{
    protected $fillable = [
        'treatment_visit_id',
        'patient_id',
        'treatment_name',
        'material_option',
        'tooth_number',
        'suggested_price',
        'treatment_plan_item_id',
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
