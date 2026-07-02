<?php

namespace App\Models;

use App\Traits\HashChained;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillingAuditLog extends Model
{
    use HashChained; // tamper-evident: hash-chained + append-only (Phase A)

    protected $fillable = [
        'action',
        'auditable_type',
        'auditable_id',
        'reason',
        'display_ref',
        'performed_by',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Log an action against any billable model.
     */
    public static function record(
        string $action,
        Model  $model,
        string $reason,
        int    $userId,
        ?string $displayRef = null
    ): self {
        return self::create([
            'action'         => $action,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'reason'         => $reason,
            'display_ref'    => $displayRef,
            'performed_by'   => $userId,
            'snapshot'       => $model->toArray(),
        ]);
    }
}
