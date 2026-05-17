<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'task_id', 'escalated_by', 'escalated_to',
        'reason', 'branch_id', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function task(): BelongsTo    { return $this->belongsTo(Task::class); }
    public function escalatedBy(): BelongsTo { return $this->belongsTo(User::class, 'escalated_by'); }
    public function escalatedTo(): BelongsTo { return $this->belongsTo(User::class, 'escalated_to'); }
}