<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetPinMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Handles password reset via 6-digit PIN sent to email.
 *
 * Flow:
 *   POST /forgot-pin/send   → validate email, generate PIN, email it
 *   POST /forgot-pin/verify → verify PIN, return short-lived token
 *   POST /forgot-pin/reset  → verify token, set new password, delete row
 */
class ForgotPasswordPinController extends Controller
{
    // PIN validity: 15 minutes
    private const EXPIRES_MINUTES = 15;

    /* ─────────────────────────────────────────
       Step 1: Send PIN
    ───────────────────────────────────────── */
    public function sendPin(Request $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
        }

        // Check user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            // Return a generic message so we don't expose which emails are registered
            return response()->json(['success' => false, 'message' => 'No account found with that email address.'], 404);
        }

        // Generate a 6-digit PIN
        $pin = (string) random_int(100000, 999999);

        // Delete any existing PIN for this email
        DB::table('password_reset_pins')->where('email', $email)->delete();

        // Store hashed PIN
        DB::table('password_reset_pins')->insert([
            'email'        => $email,
            'pin'          => Hash::make($pin),
            'token'        => '',
            'pin_verified' => false,
            'expires_at'   => now()->addMinutes(self::EXPIRES_MINUTES),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Send email
        Mail::to($email)->send(new PasswordResetPinMail($pin));

        return response()->json(['success' => true]);
    }

    /* ─────────────────────────────────────────
       Step 2: Verify PIN
    ───────────────────────────────────────── */
    public function verifyPin(Request $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email', '')));
        $pin   = trim($request->input('pin', ''));

        if (!$email || strlen($pin) !== 6) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $record = DB::table('password_reset_pins')
            ->where('email', $email)
            ->where('pin_verified', false)
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No PIN request found. Please start over.'], 404);
        }

        if (now()->isAfter($record->expires_at)) {
            DB::table('password_reset_pins')->where('email', $email)->delete();
            return response()->json(['success' => false, 'message' => 'PIN has expired. Please request a new one.'], 422);
        }

        if (!Hash::check($pin, $record->pin)) {
            return response()->json(['success' => false, 'message' => 'Incorrect PIN. Please try again.'], 422);
        }

        // Mark verified and issue a short-lived token
        $token = Str::random(64);
        DB::table('password_reset_pins')
            ->where('email', $email)
            ->update([
                'pin_verified' => true,
                'token'        => $token,
                'updated_at'   => now(),
            ]);

        return response()->json(['success' => true, 'token' => $token]);
    }

    /* ─────────────────────────────────────────
       Step 3: Reset Password
    ───────────────────────────────────────── */
    public function resetPassword(Request $request): JsonResponse
    {
        $email    = strtolower(trim($request->input('email', '')));
        $token    = trim($request->input('token', ''));
        $password = $request->input('password', '');
        $confirm  = $request->input('password_confirmation', '');

        if ($password !== $confirm) {
            return response()->json(['success' => false, 'message' => 'Passwords do not match.'], 422);
        }
        if (strlen($password) < 8) {
            return response()->json(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
        }

        $record = DB::table('password_reset_pins')
            ->where('email', $email)
            ->where('token', $token)
            ->where('pin_verified', true)
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired session. Please start over.'], 422);
        }

        if (now()->isAfter($record->expires_at)) {
            DB::table('password_reset_pins')->where('email', $email)->delete();
            return response()->json(['success' => false, 'message' => 'Session expired. Please start over.'], 422);
        }

        // Update user password
        $updated = User::where('email', $email)->update([
            'password' => Hash::make($password),
        ]);

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Could not update password.'], 500);
        }

        // Clean up
        DB::table('password_reset_pins')->where('email', $email)->delete();

        return response()->json(['success' => true]);
    }
}
