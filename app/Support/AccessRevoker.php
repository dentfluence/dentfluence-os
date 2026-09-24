<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every way a user is currently signed in: phone/API tokens, web
 * sessions and "remember me" cookies. Audit AUTH-02 / AUTH-03 (24 Sep 2026).
 *
 * Call it whenever an admin resets someone's password, deactivates them or
 * changes their access role, and after a self-service PIN reset. The acting
 * user's own current web session is spared so an admin editing themselves is
 * not thrown out mid-request.
 */
class AccessRevoker
{
    public static function revoke(User $user, string $reason): int
    {
        $tokens = $user->tokens()->delete();

        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();

        $sessions = 0;
        if (config('session.driver') === 'database') {
            $query = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id);
            if (request()->hasSession()) {
                $query->where('id', '!=', request()->session()->getId());
            }
            $sessions = $query->delete();
        }

        AuditLog::event('access_revoked', auth()->id(), [
            'target_user_id' => $user->id,
            'reason'         => $reason,
            'tokens_revoked' => $tokens,
            'sessions_ended' => $sessions,
        ], ['module' => 'auth']);

        return $tokens + $sessions;
    }
}
