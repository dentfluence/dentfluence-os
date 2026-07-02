<?php

namespace App\Models;

use App\Traits\HashChained;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog
 * --------
 * One row per Create / Update / Delete recorded by the Auditable trait, plus
 * security events (login/logout/failed-login/record-view/role-change) via
 * {@see AuditLog::event()}.
 *
 * Tamper-evident (Phase A): the table is hash-chained + append-only via the
 * HashChained trait — rows cannot be edited or deleted, and `audit:verify`
 * detects any tampering.
 */
class AuditLog extends Model
{
    use HashChained;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'module', 'old_values', 'new_values',
        'device_type', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /** The user who performed the action (if known). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a security/access EVENT (login, logout, failed login, record view,
     * role change, etc.). Distinct from the model CRUD entries written by the
     * Auditable trait. The row is hash-chained automatically (HashChained).
     *
     * @param  string    $action  e.g. 'login', 'login_failed', 'logout', 'viewed', 'role_updated'
     * @param  int|null  $userId  the acting user; pass null for anonymous (e.g. failed login)
     * @param  array     $meta    free-form detail stored in new_values (e.g. ['email' => ...])
     * @param  array     $extra   optional: auditable_type, auditable_id, module
     */
    public static function event(string $action, ?int $userId = null, array $meta = [], array $extra = []): self
    {
        return static::create([
            'user_id'        => $userId,
            'action'         => $action,
            'auditable_type' => $extra['auditable_type'] ?? null,
            'auditable_id'   => $extra['auditable_id'] ?? null,
            'module'         => $extra['module'] ?? null,
            'old_values'     => null,
            'new_values'     => $meta ?: null,
            'device_type'    => static::eventDevice(),
            'ip_address'     => request()->ip(),
            'user_agent'     => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    /** web | android | ios | api — honours an X-Client-Type header if sent. */
    protected static function eventDevice(): string
    {
        $client = request()->header('X-Client-Type');
        if ($client) {
            return strtolower($client);
        }
        return request()->is('api/*') ? 'api' : 'web';
    }
}
