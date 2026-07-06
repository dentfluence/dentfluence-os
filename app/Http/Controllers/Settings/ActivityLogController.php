<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ActivityLogController
 * ----------------------
 * Admin-only viewer over the `audit_logs` table (written by App\Traits\Auditable
 * on model create/update/delete, and by AuditLog::event() for login/logout/2FA/
 * role-change/record-view). Until now this data was write-only — nothing in the
 * app displayed it. This is purely a read/view layer; no new tracking is added
 * here (see App\Services\Relationship\AppointmentActivityLogger for the
 * separate patient-facing Timeline, which is a different concern).
 *
 * Also surfaces `staff_activity_logs` (activate/deactivate/role-change on a
 * staff account) in a lightweight second panel — a narrower, pre-existing
 * write-only table for the same "who did what to which staff member" story.
 */
class ActivityLogController extends Controller
{
    private const IGNORED_DIFF_FIELDS = ['updated_at', 'created_at', 'password', 'remember_token'];

    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(40)->withQueryString();

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->summary = $this->describe($log);
            return $log;
        });

        $modules = AuditLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action');
        $users   = User::orderBy('name')->get(['id', 'name']);

        $staffChanges = StaffActivityLog::with(['user', 'performer'])
            ->latest('created_at')
            ->limit(25)
            ->get();

        return view('settings.activity-log', compact('logs', 'modules', 'actions', 'users', 'staffChanges'));
    }

    /** Turn a raw audit row into a one-line human-readable summary. */
    private function describe(AuditLog $log): string
    {
        if ($log->action === 'created') {
            return 'Created';
        }
        if ($log->action === 'deleted') {
            return 'Deleted';
        }
        if ($log->action === 'updated') {
            return $this->describeChanges($log->old_values, $log->new_values);
        }

        return match ($log->action) {
            'login'          => 'Logged in',
            'login_failed'   => 'Failed login attempt',
            'logout'         => 'Logged out',
            'logout_all'     => 'Logged out of all devices',
            'record_viewed', 'viewed' => 'Viewed the record',
            'role_updated'   => 'Updated role/permissions',
            'profile_updated'=> 'Updated profile',
            '2fa_enabled'    => 'Enabled two-factor authentication',
            '2fa_disabled'   => 'Disabled two-factor authentication',
            default          => ucfirst(str_replace('_', ' ', $log->action)),
        };
    }

    private function describeChanges(?array $old, ?array $new): string
    {
        if (! $new) {
            return 'Updated';
        }

        $parts = [];
        foreach ($new as $field => $newVal) {
            if (in_array($field, self::IGNORED_DIFF_FIELDS, true)) {
                continue;
            }
            $oldVal  = $old[$field] ?? null;
            $parts[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . $this->shorten($oldVal) . ' → ' . $this->shorten($newVal);
        }

        if (! $parts) {
            return 'Updated';
        }

        $shown = array_slice($parts, 0, 4);
        return implode('; ', $shown) . (count($parts) > 4 ? ' …' : '');
    }

    private function shorten(mixed $val): string
    {
        if (is_null($val)) {
            return '—';
        }
        if (is_bool($val)) {
            return $val ? 'Yes' : 'No';
        }
        if (is_array($val)) {
            return '[…]';
        }

        $s = (string) $val;
        return mb_strlen($s) > 40 ? mb_substr($s, 0, 40) . '…' : $s;
    }
}
