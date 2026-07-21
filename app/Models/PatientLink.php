<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PatientLink — one interpersonal family link in the canonical relationship graph.
 *
 * Slice 1 (foundation): identity, relations, casts and audit only. No business
 * logic lives here — link creation, reciprocity, the inverse-label map and the
 * guardian workflow belong to FamilyLinkService (Slice 2).
 *
 * Fields:
 *   relationship_type — biological/social relationship ONLY
 *                       (mother, father, spouse, child, sibling, other).
 *   is_guardian       — the single representation of legal/consent guardian
 *                       authority (a capacity flag). Ward is the derived inverse.
 *   relationship      — LEGACY free-text column. Read-only: intentionally not
 *                       fillable; superseded by relationship_type.
 */
class PatientLink extends Model
{
    use Auditable;

    protected $table = 'patient_links';

    /** Tag audit-log entries with the owning module. */
    protected $auditModule = 'patients';

    protected $fillable = [
        'patient_id',
        'linked_patient_id',
        'relationship_type',
        'is_guardian',
        'notes',
        'added_by',
        // 'relationship' (legacy) is deliberately omitted — read-only.
    ];

    protected $casts = [
        'is_guardian' => 'boolean',
    ];

    /** The subject patient of this link. */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** The counterpart patient this link points to. */
    public function linkedPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'linked_patient_id');
    }

    /** Staff member who created the link. */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
