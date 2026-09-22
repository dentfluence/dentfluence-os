<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a task's outcome trail. Append-only — see the migration.
 *
 * Deliberately NOT SoftDeletes and NOT Auditable: this table IS the audit
 * record. Wrapping an audit log in another audit log buys nothing and doubles
 * the writes on the hottest staff action in the app.
 */
class TaskOutcome extends Model
{
    public const UPDATED_AT = null; // append-only: created_at alone

    /** Actions that leave the task open for someone to keep working. */
    public const OPEN_ACTIONS = ['attempted', 'rescheduled', 'reopened'];

    /** Actions that close the task. */
    public const CLOSING_ACTIONS = ['done', 'cancelled'];

    protected $fillable = [
        'task_id',
        'branch_id',
        'user_id',
        'action',
        'outcome_key',
        'outcome_label',
        'note',
        'due_date_before',
        'due_date_after',
    ];

    protected $casts = [
        'due_date_before' => 'date',
        'due_date_after'  => 'date',
        'created_at'      => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What reception sees in the trail, e.g. "No answer — will try evening". */
    public function summary(): string
    {
        $head = $this->outcome_label ?: ucfirst($this->action);

        if ($this->action === 'rescheduled' && $this->due_date_after) {
            $head = 'Rescheduled to ' . $this->due_date_after->format('d M');
        }

        return $this->note ? "{$head} — {$this->note}" : $head;
    }
}
