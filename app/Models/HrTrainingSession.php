<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrTrainingSession extends Model
{
    protected $table = 'hr_training_sessions';

    protected $fillable = [
        'title', 'description', 'type', 'trainer_name', 'trainer_user_id',
        'venue', 'scheduled_date', 'start_time', 'end_time', 'duration_minutes',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    // Status helpers
    public function isUpcoming(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_date->isFuture();
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'scheduled'  => 'bg-blue-100 text-blue-700',
            'completed'  => 'bg-green-100 text-green-700',
            'cancelled'  => 'bg-red-100 text-red-700',
            default      => 'bg-gray-100 text-gray-600',
        };
    }

    // Relationships
    public function enrollments(): HasMany
    {
        return $this->hasMany(HrTrainingEnrollment::class, 'training_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function internalTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    // Count enrolled staff
    public function getEnrolledCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function getAttendedCountAttribute(): int
    {
        return $this->enrollments()->where('attendance', 'present')->count();
    }
}
