<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabReconciliationItem — one lab case within a monthly reconciliation.
 *
 * match_status:
 *   matched  = our amount and vendor amount agree (within ₹1 tolerance)
 *   conflict = amounts differ
 *   disputed = flagged for dispute follow-up
 *   accepted = conflict accepted by approver (difference accepted)
 */
class LabReconciliationItem extends Model
{
    protected $table = 'lab_reconciliation_items';

    public const MATCH_STATUSES = ['matched', 'conflict', 'disputed', 'accepted'];

    protected $fillable = [
        'reconciliation_id',
        'lab_case_id',
        'our_amount',
        'vendor_amount',
        'difference',
        'match_status',
        'remarks',
        'auto_selected',
    ];

    protected $casts = [
        'our_amount'    => 'decimal:2',
        'vendor_amount' => 'decimal:2',
        'difference'    => 'decimal:2',
        'auto_selected' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(LabMonthlyReconciliation::class, 'reconciliation_id');
    }

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class, 'lab_case_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Determine match status based on amounts (tolerance ₹1) */
    public function computeMatchStatus(): string
    {
        return abs($this->our_amount - $this->vendor_amount) <= 1.0
            ? 'matched'
            : 'conflict';
    }
}
