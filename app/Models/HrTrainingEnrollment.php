<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTrainingEnrollment extends Model
{
    protected $table = 'hr_training_enrollments';

    protected $fillable = [
        'training_session_id', 'user_id', 'attendance', 'completed', 'completed_at', 'feedback',
    ];

    protected $casts = [
        'completed'    => 'boolean',
        'completed_at' => 'date',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(HrTrainingSession::class, 'training_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendanceBadgeClass(): string
    {
        return match($this->attendance) {
            'present' => 'bg-green-100 text-green-700',
            'absent'  => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-500',
        };
    }
}
