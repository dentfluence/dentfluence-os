<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class HrPeriodicTrainingRecord extends Model
{
    protected $table = 'hr_periodic_training_records';

    protected $fillable = [
        'requirement_id', 'user_id', 'completed_on', 'next_due_on',
        'training_session_id', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'completed_on' => 'date',
        'next_due_on'  => 'date',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(HrPeriodicTrainingRequirement::class, 'requirement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(HrTrainingSession::class, 'training_session_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Status: overdue / due_soon (within 30 days) / ok
    public function getComplianceStatusAttribute(): string
    {
        $due = $this->next_due_on;
        if ($due->isPast())                         return 'overdue';
        if ($due->diffInDays(now()) <= 30)          return 'due_soon';
        return 'ok';
    }

    public function complianceBadgeClass(): string
    {
        return match($this->compliance_status) {
            'overdue'   => 'bg-red-100 text-red-700',
            'due_soon'  => 'bg-yellow-100 text-yellow-700',
            default     => 'bg-green-100 text-green-700',
        };
    }
}
