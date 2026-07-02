<?php

namespace App\Models\Finance;

use App\Traits\HashChained;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FinanceAuditLog — the immutable finance trail (Phase A).
 *
 * The `finance_audit_log` table existed but had no model and nothing wrote to
 * it. This model makes it usable AND tamper-evident from day one: it's
 * hash-chained + append-only via HashChained, so once finance code starts
 * writing through it (FinanceAuditLog::create([...])) the trail is verifiable
 * by `php artisan audit:verify`.
 *
 * Note: the table uses `performed_at` rather than Laravel timestamps.
 */
class FinanceAuditLog extends Model
{
    use HashChained;

    protected $table = 'finance_audit_log';

    public $timestamps = false; // uses performed_at, not created_at/updated_at

    protected $fillable = [
        'clinic_id', 'model_type', 'model_id', 'action',
        'old_values', 'new_values', 'reason',
        'performed_by', 'ip_address', 'user_agent', 'performed_at',
    ];

    protected $casts = [
        'old_values'   => 'array',
        'new_values'   => 'array',
        'performed_at' => 'datetime',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }
}
