<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * ConsentLog
 * ----------
 * The append-only, tamper-evident history of consent events (DPDP 5.6).
 *
 * IMPORTANT: this model is deliberately write-once. We block UPDATE and DELETE
 * at the application layer so the chain of records cannot be quietly altered.
 * Each row carries a `hash` linked to the previous row's `prev_hash`; the
 * ConsentService writes those hashes and can later verify the whole chain.
 *
 * It does NOT use the Auditable trait — this table IS the audit trail.
 */
class ConsentLog extends Model
{
    /** Append-only: only created_at is used, there is no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'patient_id', 'consent_purpose_id', 'purpose_key', 'event',
        'purpose_version', 'capture_method', 'captured_by',
        'ip_address', 'user_agent', 'snapshot', 'prev_hash', 'hash',
    ];

    protected $casts = [
        'snapshot'        => 'array',
        'purpose_version' => 'integer',
        'created_at'      => 'datetime',
    ];

    /**
     * Guard the table: once a log row exists it can never be edited or removed.
     * Any attempt throws, which is what makes the trail trustworthy.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Consent logs are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('Consent logs are append-only and cannot be deleted.');
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'consent_purpose_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
