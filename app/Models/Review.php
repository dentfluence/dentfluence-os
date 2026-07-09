<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review — a single review request + its outcome (Phase B item 2.4).
 */
class Review extends Model
{
    protected $fillable = [
        'patient_id', 'appointment_id', 'token', 'channel', 'status',
        'rating', 'comment', 'routed_to_google',
        'requested_by_id', 'requested_at', 'responded_at',
        'clinic_reply', 'replied_at', 'replied_by_id',
    ];

    protected $casts = [
        'rating'           => 'integer',
        'routed_to_google' => 'boolean',
        'requested_at'     => 'datetime',
        'responded_at'     => 'datetime',
        'replied_at'       => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_id');
    }

    /** Rated but no internal reply logged yet — this is what the Reply screen surfaces. */
    public function needsReply(): bool
    {
        return $this->status === 'rated' && $this->clinic_reply === null;
    }

    /** A "happy" rating at or above the configured threshold. */
    public function isPositive(): bool
    {
        return $this->rating !== null
            && $this->rating >= (int) config('reviews.positive_threshold', 4);
    }

    public function scopeRated($query)
    {
        return $query->where('status', 'rated');
    }
}
