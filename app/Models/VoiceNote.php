<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * VoiceNote — a single recording + its AI-derived transcript and clinical notes.
 *
 * Attaches polymorphically to a Consultation, TreatmentVisit, or Patient via
 * noteable(). The patient_id column is always filled for fast per-patient lookup.
 */
class VoiceNote extends Model
{
    use SoftDeletes;

    /** Pipeline status constants — keep these in sync with the controller. */
    public const STATUS_UPLOADED     = 'uploaded';
    public const STATUS_TRANSCRIBING = 'transcribing';
    public const STATUS_TRANSCRIBED  = 'transcribed';
    public const STATUS_ANALYZING    = 'analyzing';
    public const STATUS_READY        = 'ready';   // transcript + notes ready for review
    public const STATUS_SAVED        = 'saved';   // committed into the parent record
    public const STATUS_FAILED       = 'failed';

    protected $fillable = [
        'noteable_id',
        'noteable_type',
        'patient_id',
        'disk',
        'audio_path',
        'original_filename',
        'mime_type',
        'file_size',
        'duration_seconds',
        'language',
        'transcript',
        'structured_notes',
        'transcribe_model',
        'analyze_model',
        'status',
        'error_message',
        'saved_to_record',
        'created_by',
    ];

    protected $casts = [
        'structured_notes' => 'array',
        'saved_to_record'  => 'boolean',
        'file_size'        => 'integer',
        'duration_seconds' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    /** The Consultation / TreatmentVisit / Patient this note belongs to. */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Full Storage instance for the disk this audio lives on. */
    public function audioDisk()
    {
        return Storage::disk($this->disk ?? 'local');
    }

    /** Does the audio file still physically exist on disk? */
    public function audioExists(): bool
    {
        return $this->audio_path && $this->audioDisk()->exists($this->audio_path);
    }

    /** Human-readable duration, e.g. "3:07". */
    public function getDurationLabelAttribute(): ?string
    {
        if (!$this->duration_seconds) return null;
        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;
        return sprintf('%d:%02d', $m, $s);
    }

    /** True once the AI pipeline has produced reviewable output. */
    public function isReady(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_SAVED], true);
    }
}
