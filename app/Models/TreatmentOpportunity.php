<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentOpportunity extends Model
{
    protected $fillable = [
        'patient_id',
        'relationship_id',   // Phase 4 — linked Relationship record (nullable)
        'treatment_plan_id',
        'type',
        'label',
        'status',
        'priority',
        'follow_up_date',
        'follow_up_time',
        'estimated_value',
        'notes',
        'declined_reason',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'follow_up_date'  => 'date',
        'estimated_value' => 'decimal:2',
    ];

    // ── Stage definitions ──────────────────────────────────────────────────────
    // DB status values mapped to display labels and colours
    const STAGES = [
        'prospect'  => ['label' => 'Identified',     'color' => '#6366f1', 'bg' => '#eef2ff'],
        'discussed' => ['label' => 'Nurturing',       'color' => '#f59e0b', 'bg' => '#fffbeb'],
        'quoted'    => ['label' => 'Estimate Given',  'color' => '#3b82f6', 'bg' => '#eff6ff'],
        'accepted'  => ['label' => 'Committed',       'color' => '#10b981', 'bg' => '#ecfdf5'],
        'completed' => ['label' => 'Converted',       'color' => '#22c55e', 'bg' => '#f0fdf4'],
        'declined'  => ['label' => 'Declined',        'color' => '#ef4444', 'bg' => '#fef2f2'],
    ];

    // Priority display labels (maps model enum → human label)
    const PRIORITY_LABELS = [
        'high'   => 'High Priority',
        'medium' => 'Warm',
        'low'    => 'Long Term',
    ];

    // Priority badge colours
    const PRIORITY_COLORS = [
        'high'   => ['bg' => '#fee2e2', 'text' => '#ef4444'],
        'medium' => ['bg' => '#fef9c3', 'text' => '#ca8a04'],
        'low'    => ['bg' => '#ede9fe', 'text' => '#7c3aed'],
    ];

    // Common treatment types (used in dropdowns)
    const TREATMENT_TYPES = [
        'implant'          => 'Dental Implant',
        'aligners'         => 'Orthodontics / Aligners',
        'whitening'        => 'Teeth Whitening',
        'veneers'          => 'Veneers',
        'rct'              => 'Root Canal',
        'crown_bridge'     => 'Crown / Bridge',
        'full_mouth_rehab' => 'Full Mouth Rehab',
        'gum_treatment'    => 'Gum Treatment',
        'other'            => 'Other',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Phase 4 — linked Relationship record (nullable). */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) return $this->label;
        return self::TREATMENT_TYPES[$this->type] ?? ucwords(str_replace('_', ' ', $this->type));
    }

    public function getStageLabelAttribute(): string
    {
        return self::STAGES[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? ucfirst($this->priority);
    }

    /** Is follow-up overdue? */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->follow_up_date) return false;
        return $this->follow_up_date->isPast() && !in_array($this->status, ['completed', 'declined']);
    }

    /** Is follow-up due today? */
    public function getDueTodayAttribute(): bool
    {
        if (!$this->follow_up_date) return false;
        return $this->follow_up_date->isToday() && !in_array($this->status, ['completed', 'declined']);
    }

    // ── Legacy helper (kept for backward compat) ───────────────────────────────

    public static function statusColor(string $status): string
    {
        return match($status) {
            'prospect'  => 'bg-indigo-50 text-indigo-600',
            'discussed' => 'bg-amber-50 text-amber-600',
            'quoted'    => 'bg-blue-50 text-blue-600',
            'accepted'  => 'bg-green-50 text-green-700',
            'declined'  => 'bg-red-50 text-red-500',
            'completed' => 'bg-green-50 text-green-700',
            default     => 'bg-gray-100 text-gray-500',
        };
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['completed', 'declined']);
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('follow_up_date', today());
    }

    public function scopeOverdue($query)
    {
        return $query->whereDate('follow_up_date', '<', today())
                     ->whereNotIn('status', ['completed', 'declined']);
    }
}
