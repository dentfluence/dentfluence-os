<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEntryExitLog extends Model
{
    protected $table = 'hr_entry_exit_logs';

    protected $fillable = [
        'user_id', 'type', 'logged_at', 'method', 'ip_address', 'notes',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
