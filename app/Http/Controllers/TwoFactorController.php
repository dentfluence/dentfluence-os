<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * TwoFactorController (Phase A — MFA)
 * ----------------------------------
 * Authenticator-app (TOTP) two-factor auth, opt-in per user.
 *
 *  Setup  : GET  /two-factor/setup     show QR + secret to scan
 *           POST /two-factor/enable    verify first code → turn it on, show recovery codes
 *           POST /two-factor/disable   verify password → turn it off
 *  Login  : GET  /two-factor/challenge prompt for the 6-digit code (after password)
 *           POST /two-factor/challenge verify code (or a recovery code) → finish login
 *
 * Session keys used during the login challenge:
 *   2fa:user_id   the id of the user who passed the password step
 *   2fa:remember  whether they ticked "remember me"
 */
class TwoFactorController extends Controller
{
    private function engine(): Google2FA
    {
        return new Google2FA();
    }

    // ── Setup (authenticated) ───────────────────────────────────────────────

    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return view('two-factor.setup', ['enabled' => true, 'qr' => null, 'secret' => null]);
        }

        // Hold a candidate secret in the session until the user confirms a code.
        $secret = $request->session()->get('2fa:setup_secret');
        if (! $secret) {
            $secret = $this->engine()->generateSecretKey();
            $request->session()->put('2fa:setup_secret', $secret);
        }

        $qr = $this->engine()->getQRCodeInline(
            (string) config('app.name', 'Dentfluence'),
            $user->email,
            $secret
        );

        return view('two-factor.setup', ['enabled' => false, 'qr' => $qr, 'secret' => $secret]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user   = $request->user();
        $secret = $request->session()->get('2fa:setup_secret');

        if (! $secret || ! $this->engine()->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'That code was not valid. Try the latest one from your app.']);
        }

        $recovery = User::generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => $recovery,
            'two_factor_confirmed_at'   => now(),
        ])->save();

        $request->session()->forget('2fa:setup_secret');
        AuditLog::event('2fa_enabled', $user->id, [], ['module' => 'auth']);

        // Show the recovery codes once.
        return redirect()->route('two-factor.setup')->with('recovery_codes', $recovery);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        AuditLog::event('2fa_disabled', $user->id, [], ['module' => 'auth']);

        return redirect()->route('two-factor.setup')->with('status', 'Two-factor authentication turned off.');
    }

    // ── Login challenge (not yet authenticated) ─────────────────────────────

    public function challenge(Request $request)
    {
        if (! $request->session()->has('2fa:user_id')) {
            return redirect()->route('login');
        }

        return view('two-factor.challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = $request->session()->get('2fa:user_id');
        $user   = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        $code        = trim($request->code);
        $passedTotp  = $user->two_factor_secret && $this->engine()->verifyKey($user->two_factor_secret, $code);
        $passedRecov = ! $passedTotp && $user->useRecoveryCode($code);

        if (! $passedTotp && ! $passedRecov) {
            AuditLog::event('login_failed', $user->id, ['reason' => '2fa'], ['module' => 'auth']);
            return back()->withErrors(['code' => 'Invalid code. Try again, or use a recovery code.']);
        }

        $remember = (bool) $request->session()->get('2fa:remember', false);

        Auth::login($user, $remember);
        $request->session()->forget(['2fa:user_id', '2fa:remember']);
        $request->session()->regenerate();

        $user->recordLogin();
        AuditLog::event('login', $user->id, ['via' => $passedRecov ? '2fa_recovery' : '2fa'], ['module' => 'auth']);

        return redirect()->intended('/dashboard');
    }
}
