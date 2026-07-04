<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Auditable
 * ---------
 * Add `use Auditable;` to any model and every create / update / delete on it
 * is recorded in the audit_logs table — who, what changed (before/after),
 * and from which device. Sensitive and "noise" fields are never logged.
 *
 * Optional: set `protected $auditModule = 'patients';` on the model to tag
 * the log entries with a module name.
 */
trait Auditable
{
    /** Fields we never store, and that shouldn't trigger an audit on their own. */
    protected static array $auditIgnore = [
        'password', 'remember_token', 'updated_at', 'created_at', 'last_login_at',
    ];

    /** Laravel calls this automatically because of the trait name. */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAudit('created', null, $model->auditClean($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditClean($model->getChanges());
            if (empty($changes)) {
                return; // nothing meaningful changed (e.g. just a login timestamp)
            }
            $before = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit('updated', $model->auditClean($before), $changes);
        });

        static::deleted(function ($model) {
            $model->writeAudit('deleted', $model->auditClean($model->getOriginal()), null);
        });
    }

    /** Strip ignored / sensitive keys before logging. */
    protected function auditClean(array $values): array
    {
        return array_diff_key($values, array_flip(static::$auditIgnore));
    }

    public function writeAudit(string $action, ?array $old, ?array $new): void
    {
        // This runs synchronously inside the model's create/update/delete event,
        // i.e. AFTER the real write already happened. If audit logging itself
        // throws (bad column size, encoding issue, etc.) that exception must
        // never propagate back out and make the caller think the actual save
        // failed. Log it and move on instead.
        try {
            AuditLog::create([
                'user_id'        => auth()->id(),
                'action'         => $action,
                'auditable_type' => static::class,
                'auditable_id'   => $this->getKey(),
                'module'         => property_exists($this, 'auditModule') ? $this->auditModule : null,
                'old_values'     => $old ?: null,
                'new_values'     => $new ?: null,
                'device_type'    => static::auditDevice(),
                'ip_address'     => request()->ip(),
                'user_agent'     => static::auditTrim(request()->userAgent()),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** web | android | ios | api — the app can send an X-Client-Type header. */
    protected static function auditDevice(): string
    {
        $client = request()->header('X-Client-Type');
        if ($client) {
            return strtolower($client);
        }
        return request()->is('api/*') ? 'api' : 'web';
    }

    protected static function auditTrim(?string $value): ?string
    {
        return $value ? substr($value, 0, 255) : null;
    }
}
