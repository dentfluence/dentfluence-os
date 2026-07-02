<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPrompt extends Model
{
    protected $fillable = [
        'patient_id',
        'trigger_type',
        'trigger_id',
        'description',
        'status',
        'invoice_id',
        'created_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Mark as invoiced and link the invoice. */
    public function markInvoiced(Invoice $invoice, int $userId): void
    {
        $this->update([
            'status'      => 'invoiced',
            'invoice_id'  => $invoice->id,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }

    /** Mark as dismissed (no billing needed). */
    public function dismiss(int $userId): void
    {
        $this->update([
            'status'      => 'dismissed',
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Badge colour for the prompt status chip. */
    public function statusColor(): string
    {
        return match($this->status) {
            'pending'   => 'yellow',
            'invoiced'  => 'green',
            'dismissed' => 'gray',
            default     => 'gray',
        };
    }
}
