<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AuthController (API)
 * --------------------
 * Token-based login for the mobile app / Tulip / any future client.
 *
 *   POST /api/v1/auth/login   -> returns a Bearer token + user info
 *   GET  /api/v1/auth/me      -> returns the logged-in user (token required)
 *   POST /api/v1/auth/logout  -> revokes the token for THIS device
 *
 * This reuses your existing User model and roles — it does NOT create a new
 * auth system. The web app's session login keeps working exactly as before.
 */
class AuthController extends ApiController
{
    /**
     * POST /api/v1/auth/login
     * Verify email + password, then issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string'], // optional label, e.g. "Pixel 7"
        ]);

        $user = User::where('email', $data['email'])->first();

        // Same answer for "no such user" and "wrong password" — don't leak which.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLog::event('login_failed', null, ['email' => $data['email']], ['module' => 'auth']);
            return $this->error('Invalid email or password.', [], 401);
        }

        if (! $user->is_active) {
            AuditLog::event('login_failed', $user->id, ['email' => $data['email'], 'reason' => 'inactive'], ['module' => 'auth']);
            return $this->error('This account is inactive. Please contact your admin.', [], 403);
        }

        $user->recordLogin(); // updates last_login_at, same as the web login

        $deviceName = $data['device'] ?? 'api';

        // Security (Phase A): issue a token with role-scoped abilities (not a
        // blanket "*") and an expiry (driven by config/sanctum.php 'expiration').
        // Sanctum applies the config expiry automatically when no expires_at is
        // passed, so we only need to scope the abilities here.
        $token = $user->createToken($deviceName, $this->abilitiesFor($user))->plainTextToken;

        AuditLog::event('login', $user->id, ['device' => $deviceName], ['module' => 'auth']);

        return $this->success([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->userPayload($user),
        ], 'Logged in successfully.');
    }

    /**
     * Token abilities (scopes) granted to a user.
     *
     * Admin / clinic owner gets full access ("*"). Everyone else gets a scoped
     * set based on their role. Today the app is single-login (admin only), so
     * this mostly future-proofs Phase 12 multi-login — but it means a stolen
     * non-admin token can't silently do everything once roles go live.
     */
    private function abilitiesFor(User $user): array
    {
        if ($user->isAdminRole()) {
            return ['*'];
        }

        // Clinical roles may read + write clinical records.
        if ($user->isDoctor()) {
            return ['patient:read', 'patient:write', 'clinical:read', 'clinical:write', 'billing:read'];
        }

        // Front desk / others: patients + billing, no clinical writes.
        if ($user->hasRole(User::ROLE_FRONT_DESK)) {
            return ['patient:read', 'patient:write', 'clinical:read', 'billing:read', 'billing:write'];
        }

        // Safe default — read-only.
        return ['patient:read', 'clinical:read', 'billing:read'];
    }

    /**
     * GET /api/v1/auth/me
     * Return the currently authenticated user (requires a valid token).
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            $this->userPayload($request->user()),
            'Current user.'
        );
    }

    /**
     * PUT /api/v1/auth/me
     * Update the logged-in user's name, email, and optionally password.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        AuditLog::event('profile_updated', $user->id, ['name' => $user->name, 'email' => $user->email], ['module' => 'auth']);

        return $this->success($this->userPayload($user), 'Profile updated.');
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke ONLY the token used for this request (logs out this one device).
     */
    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $request->user()->currentAccessToken()->delete();

        AuditLog::event('logout', $userId, [], ['module' => 'auth']);

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * POST /api/v1/auth/logout-all
     * Revoke EVERY token for this user (logs them out of all devices).
     * Use after a lost/stolen phone or a password change.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $request->user()->tokens()->delete();

        AuditLog::event('logout_all', $userId, [], ['module' => 'auth']);

        return $this->success(null, 'Logged out of all devices.');
    }

    /**
     * Shape the user object the API hands back. Keep it small — only what a
     * client needs — and never expose the password hash.
     */
    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'role_label' => $user->role_label,
            'is_admin'   => $user->isAdminRole(),
            'branch_id'  => $user->branch_id,
            'avatar'     => $user->avatar,
        ];
    }
}
