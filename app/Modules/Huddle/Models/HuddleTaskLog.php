<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HuddleTaskLog extends Model
{
    protected $fillable = [
        'task_id',
        'huddle_board_id',    // FIX #8: was missing — repository writes this
        'huddle_card_id',
        'status',             // FIX #8: was missing — repository writes this
        'carried_forward',    // FIX #8: was missing — repository writes this
        'performed_by',
        'action',
        'proof_path',
        'proof_uploaded_at',
        'note',
        'meta',
        'performed_at',
    ];

    protected $casts = [
        'meta'              => 'array',
        'carried_forward'   => 'boolean',
        'proof_uploaded_at' => 'datetime',
        'performed_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * FIX #9: task() relationship was completely missing.
     * HuddleTaskController accesses $log->task->title, $log->task->assignedTo etc.
     * Adjust the class path if your Task model lives elsewhere.
     */
    public function task(): BelongsTo
    {
        // Try common locations — use whichever matches your app:
        // App\Modules\Task\Models\Task::class
        // App\Models\Task::class
        return $this->belongsTo(\App\Models\Task::class, 'task_id');
    }

    /**
     * FIX #10: huddleBoard() relationship was missing.
     * HuddleTaskRepository::overdueForBranch() uses whereHas('huddleBoard').
     */
    public function huddleBoard(): BelongsTo
    {
        return $this->belongsTo(HuddleBoard::class, 'huddle_board_id'); // FIX #10
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(HuddleCard::class, 'huddle_card_id');
    }

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
