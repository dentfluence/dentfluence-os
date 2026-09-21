<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /* ─────────────────────────────────────────
       GET /profile
    ───────────────────────────────────────── */
    public function show()
    {
        $user = Auth::user();
        return view('profile.show', compact('user'));
    }

    /* ─────────────────────────────────────────
       POST /profile  — update name/email/phone/designation
    ───────────────────────────────────────── */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone'       => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    /* ─────────────────────────────────────────
       POST /profile/password  — change password
    ───────────────────────────────────────── */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->with('tab', 'security');
        }

        $user->update(['password' => Hash::make($request->password)]);

        // 2A.1 — the same rule as the API side: a password change ends every
        // API token this user holds. There is no sanctum token behind a web
        // session, so all of them go. A phone that was signed in before the
        // change must sign in again.
        $revoked = $user->tokens()->delete();

        \App\Models\AuditLog::event('password_changed', $user->id,
            ['tokens_revoked' => $revoked, 'surface' => 'web'], ['module' => 'auth']);

        return back()->with('success', 'Password changed successfully. Any phone signed in with this account must sign in again.')->with('tab', 'security');
    }

    /* ─────────────────────────────────────────
       POST /profile/avatar  — upload avatar photo
    ───────────────────────────────────────── */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = Auth::user();

        // Delete old avatar if it exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }

    /* ─────────────────────────────────────────
       DELETE /profile/avatar  — remove avatar
    ───────────────────────────────────────── */
    public function removeAvatar()
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return back()->with('success', 'Profile photo removed.');
    }
}
