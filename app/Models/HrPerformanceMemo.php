<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPerformanceMemo extends Model
{
    protected $table = 'hr_performance_memos';

    protected $fillable = [
        'staff_user_id', 'issued_by', 'type', 'subject', 'body',
        'memo_date', 'staff_acknowledged', 'acknowledged_at', 'is_confidential',
    ];

    protected $casts = [
        'memo_date'         => 'date',
        'acknowledged_at'   => 'datetime',
        'staff_acknowledged'=> 'boolean',
        'is_confidential'   => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function typeBadgeClass(): string
    {
        return match($this->type) {
            'praise'      => 'bg-green-100 text-green-700',
            'warning'     => 'bg-red-100 text-red-700',
            'improvement' => 'bg-yellow-100 text-yellow-700',
            'review'      => 'bg-blue-100 text-blue-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'praise'      => 'Praise',
            'warning'     => 'Warning',
            'improvement' => 'Improvement Plan',
            'review'      => 'Performance Review',
            default       => 'General Memo',
        };
    }
}
