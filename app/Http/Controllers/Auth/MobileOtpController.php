<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Mobile OTP Login Controller
 *
 * Flow:
 *   POST /auth/mobile/send-otp  → generate 6-digit OTP, send via SMS gateway
 *   POST /auth/mobile/verify    → verify OTP, log user in
 *
 * SMS GATEWAY CONFIG (add to .env):
 *   MOBILE_OTP_PROVIDER=msg91    # or twilio, textlocal, etc.
 *   MSG91_AUTH_KEY=your_key
 *   MSG91_SENDER_ID=DENTFL
 *   MSG91_TEMPLATE_ID=your_template_id
 *
 * Until an SMS gateway is configured, OTPs are logged to
 * storage/logs/laravel.log for testing (safe for dev only).
 */
class MobileOtpController extends Controller
{
    // OTP validity: 5 minutes
    private const EXPIRES_MINUTES = 5;

    /* ─────────────────────────────────────────
       Send OTP to mobile number
    ───────────────────────────────────────── */
    public function sendOtp(Request $request): JsonResponse
    {
        $phone = preg_replace('/[^0-9+]/', '', $request->input('phone', ''));

        if (strlen($phone) < 10) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid mobile number.'], 422);
        }

        // Check that a user with this phone exists
        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No account found with this mobile number.'], 404);
        }

        // Generate OTP
        $otp = (string) random_int(100000, 999999);

        // Delete any existing OTP for this phone
        DB::table('mobile_otps')->where('phone', $phone)->delete();

        // Store hashed OTP
        DB::table('mobile_otps')->insert([
            'phone'      => $phone,
            'otp'        => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send SMS via configured provider
        $sent = $this->dispatchSms($phone, $otp);

        if (!$sent) {
            // Log for dev / fallback
            Log::info('[MobileOTP] OTP for ' . $phone . ': ' . $otp . ' (SMS gateway not configured)');
        }

        return response()->json(['success' => true]);
    }

    /* ─────────────────────────────────────────
       Verify OTP and log in
    ───────────────────────────────────────── */
    public function verify(Request $request): JsonResponse
    {
        $phone = preg_replace('/[^0-9+]/', '', $request->input('phone', ''));
        $otp   = trim($request->input('otp', ''));

        if (!$phone || strlen($otp) !== 6) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $record = DB::table('mobile_otps')->where('phone', $phone)->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'OTP not found. Please request a new one.'], 404);
        }

        if (now()->isAfter($record->expires_at)) {
            DB::table('mobile_otps')->where('phone', $phone)->delete();
            return response()->json(['success' => false, 'message' => 'OTP has expired. Please request a new one.'], 422);
        }

        if (!Hash::check($otp, $record->otp)) {
            return response()->json(['success' => false, 'message' => 'Incorrect OTP. Please try again.'], 422);
        }

        // Clean up OTP record
        DB::table('mobile_otps')->where('phone', $phone)->delete();

        // Log the user in
        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if (method_exists($user, 'recordLogin')) {
            $user->recordLogin();
        }

        return response()->json(['success' => true, 'redirect' => '/dashboard']);
    }

    /* ─────────────────────────────────────────
       SMS dispatch (stub — wire up your gateway)
    ───────────────────────────────────────── */
    private function dispatchSms(string $phone, string $otp): bool
    {
        // ── MSG91 example (uncomment and configure) ──────────────────
        // $authKey    = config('services.msg91.auth_key');
        // $senderId   = config('services.msg91.sender_id', 'DENTFL');
        // $templateId = config('services.msg91.template_id');
        //
        // if (!$authKey || !$templateId) return false;
        //
        // $response = \Illuminate\Support\Facades\Http::withHeaders([
        //     'authkey'      => $authKey,
        //     'content-type' => 'application/json',
        // ])->post('https://api.msg91.com/api/v5/otp', [
        //     'template_id' => $templateId,
        //     'mobile'      => $phone,
        //     'authkey'     => $authKey,
        //     'otp'         => $otp,
        // ]);
        //
        // return $response->successful();
        // ────────────────────────────────────────────────────────────

        return false; // return true once SMS gateway is configured
    }
}
