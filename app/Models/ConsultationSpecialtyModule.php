<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConsultationSpecialtyModule
 *
 * One row = one specialty activated for one consultation.
 * e.g. Consultation #42 has orthodontics + periodontics → 2 rows.
 *
 * The `findings` JSON column stores all structured field values
 * entered by the doctor in that specialty's module panel.
 *
 * A module can be "suggested → accepted" or "suggested → rejected".
 * Accepted: accepted_at is set, rejected_at is null.
 * Rejected: rejected_at is set, accepted_at is null.
 * Pending:  both null (suggested but not yet actioned).
 */
class ConsultationSpecialtyModule extends Model
{
    use HasFactory;

    protected $table = 'consultation_specialty_modules';

    protected $fillable = [
        'consultation_id',
        'specialty_tag',
        'findings',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'findings'     => 'array',
        'accepted_at'  => 'datetime',
        'rejected_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * The knowledge base entry for this specialty.
     * Uses specialty_tag to look up TreatmentKnowledge.
     */
    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(TreatmentKnowledge::class, 'specialty_tag', 'specialty_tag');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at')->whereNull('rejected_at');
    }

    public function scopeRejected($query)
    {
        return $query->whereNotNull('rejected_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')->whereNull('rejected_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null && $this->rejected_at === null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function accept(): void
    {
        $this->update(['accepted_at' => now(), 'rejected_at' => null]);
    }

    public function reject(): void
    {
        $this->update(['rejected_at' => now(), 'accepted_at' => null]);
    }
}
