<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Patient;
use App\Models\User;
use App\Models\TreatmentVisit;
use App\Models\Consultation;

class Prescription extends Model
{
    use SoftDeletes;

    protected $table = 'prescriptions';

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_DRAFT         = 'draft';
    const STATUS_ISSUED        = 'issued';
    const STATUS_PRINTED       = 'printed';
    const STATUS_WHATSAPP_SENT = 'whatsapp_sent';
    const STATUS_EMAIL_SENT    = 'email_sent';
    const STATUS_REVISED       = 'revised';
    const STATUS_CANCELLED     = 'cancelled';

    // ── Source constants ──────────────────────────────────────────────────────
    const SOURCE_CONSULTATION = 'consultation';
    const SOURCE_VISIT        = 'visit';
    const SOURCE_EMERGENCY    = 'emergency_consultation';
    const SOURCE_REVIEW       = 'review_visit';
    const SOURCE_POST_OP      = 'post_operative_visit';

    protected $fillable = [
        'prescription_number',
        'patient_id', 'visit_id', 'consultation_id',
        'prescribed_by',
        'diagnosis', 'chief_complaint', 'follow_up_date',
        'general_instructions', 'language',
        'source', 'status',
        'printed_at', 'print_count',
        'whatsapp_sent_at',
        'email_sent_at', 'email_sent_count',
        'template_id', 'repeated_from_id',
        'version', 'parent_id',
    ];

    protected $casts = [
        'printed_at'       => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'email_sent_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function patient()      { return $this->belongsTo(Patient::class); }
    public function prescribedBy() { return $this->belongsTo(User::class, 'prescribed_by'); }
    public function visit()        { return $this->belongsTo(TreatmentVisit::class, 'visit_id'); }
    public function consultation() { return $this->belongsTo(Consultation::class); }
    public function template()     { return $this->belongsTo(RxTemplate::class, 'template_id'); }
    public function repeatedFrom() { return $this->belongsTo(Prescription::class, 'repeated_from_id'); }
    public function parent()       { return $this->belongsTo(Prescription::class, 'parent_id'); }
    public function versions()     { return $this->hasMany(Prescription::class, 'parent_id'); }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order');
    }

    public function auditLogs()
    {
        return $this->hasMany(PrescriptionAuditLog::class)->latest();
    }

    public function overrides()
    {
        return $this->hasMany(PrescriptionOverride::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    /** Still in draft — editable in place. */
    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }

    /**
     * Locked = issued/printed/sent/revised.
     * Editing a locked prescription creates a new version instead of mutating it.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_ISSUED,
            self::STATUS_PRINTED,
            self::STATUS_WHATSAPP_SENT,
            self::STATUS_EMAIL_SENT,
            self::STATUS_REVISED,
        ]);
    }

    /**
     * Backward-compatible alias — any code calling isFinalized() still works.
     * Previously returned true only for 'finalized'; now returns true for any
     * post-draft locked status.
     */
    public function isFinalized(): bool { return $this->isLocked(); }

    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    public function isRevised(): bool   { return $this->status === self::STATUS_REVISED; }

    // ── Source helper ─────────────────────────────────────────────────────────

    public function sourceLabel(): string
    {
        return match($this->source) {
            self::SOURCE_CONSULTATION => 'Consultation',
            self::SOURCE_VISIT        => 'Treatment Visit',
            self::SOURCE_EMERGENCY    => 'Emergency',
            self::SOURCE_REVIEW       => 'Review Visit',
            self::SOURCE_POST_OP      => 'Post-Op',
            default                   => ucfirst(str_replace('_', ' ', $this->source ?? '—')),
        };
    }

    // ── Number generation ─────────────────────────────────────────────────────

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        // Include soft-deleted rows so numbers never repeat
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
        return 'RX-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForPatient($q, $patientId) { return $q->where('patient_id', $patientId); }

    /** Exclude cancelled and superseded (revised) prescriptions. */
    public function scopeActive($q)
    {
        return $q->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REVISED]);
    }

    public function scopeDraft($q)  { return $q->where('status', self::STATUS_DRAFT); }
    public function scopeIssued($q) { return $q->where('status', self::STATUS_ISSUED); }

    /** @deprecated — use scopeIssued(). Kept for backward compatibility. */
    public function scopeFinalized($q) { return $this->scopeIssued($q); }
}
