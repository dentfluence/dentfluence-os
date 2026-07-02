<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle a login request.
     *
     * Validates credentials, records a successful login against the user
     * model, and redirects to the dashboard — or bounces back with an
     * error on failure.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Verify the password WITHOUT logging in yet, so we can insert the 2FA
        // step in between when the account has it enabled.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            AuditLog::event('login_failed', null, ['email' => $credentials['email']], ['module' => 'auth']);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid credentials']);
        }

        // Password is correct. If 2FA is on, hold the user in a "pending" state
        // and send them to the code challenge — they are NOT logged in until it
        // passes (see TwoFactorController::verify).
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa:user_id', $user->id);
            $request->session()->put('2fa:remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        // No 2FA — log in normally.
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->recordLogin();
        AuditLog::event('login', $user->id, [], ['module' => 'auth']);

        return redirect()->intended('/dashboard');
    }

    /**
     * Log the current user out and redirect to the login page.
     */
    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::event('logout', $userId, [], ['module' => 'auth']);

        return redirect('/login');
    }
}
