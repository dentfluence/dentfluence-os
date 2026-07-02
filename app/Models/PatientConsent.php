<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PatientConsent
 * --------------
 * The CURRENT consent state for one patient + one purpose.
 * The append-only history lives in ConsentLog; this is just the latest position.
 */
class PatientConsent extends Model
{
    use Auditable;

    protected $auditModule = 'consent';

    /** Status values. */
    public const GRANTED   = 'granted';
    public const WITHDRAWN = 'withdrawn';
    public const PENDING   = 'pending';
    public const EXPIRED   = 'expired';

    protected $fillable = [
        'patient_id', 'consent_purpose_id', 'status', 'purpose_version',
        'granted_at', 'withdrawn_at', 'expires_at',
        'capture_method', 'captured_by', 'notes',
        'on_behalf_of', 'guardian_name', 'guardian_relationship',
    ];

    protected $casts = [
        'purpose_version' => 'integer',
        'granted_at'      => 'datetime',
        'withdrawn_at'    => 'datetime',
        'expires_at'      => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'consent_purpose_id');
    }

    /** The staff member who recorded this consent (if any). */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    /** True only if consent is granted and not past its expiry. */
    public function isGranted(): bool
    {
        if ($this->status !== self::GRANTED) {
            return false;
        }
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }
}
