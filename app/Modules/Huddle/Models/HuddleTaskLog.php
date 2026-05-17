<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HuddleTaskLog extends Model
{
    protected $fillable = [
        'task_id',
        'huddle_card_id',
        'performed_by',
        'action',
        'proof_path',
        'proof_uploaded_at',
        'note',
        'meta',
        'performed_at',
    ];

    protected $casts = [
        'meta'               => 'array',
        'proof_uploaded_at'  => 'datetime',
        'performed_at'       => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function card(): BelongsTo
    {
        return $this->belongsTo(HuddleCard::class, 'huddle_card_id');
    }

    // Note: task() intentionally references the existing tasks table
    // We don't define the model here — use DB or the Task model from Task module
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function hasProof(): bool
    {
        return $this->proof_path !== null;
    }

    public function isAutoCompleted(): bool
    {
        return $this->action === 'auto_completed';
    }
}
