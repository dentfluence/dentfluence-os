<?php

namespace App\Modules\Hq\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'clinic_id', 'subject', 'body', 'channel', 'priority',
        'status', 'resolution', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['resolved', 'closed']);
    }
}
