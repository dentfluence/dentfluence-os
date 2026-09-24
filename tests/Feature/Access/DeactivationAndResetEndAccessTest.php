<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Audit 24 Sep 2026 — AUTH-02 / AUTH-03 / AUTH-04.
 *
 * A deactivated user must not get in by password, must lose an open session
 * and a live phone token on the next request, and an admin password reset,
 * deactivation or role change must end every existing sign-in. The forgot-PIN
 * flow must not reveal which emails are staff and must die after 5 wrong PINs.
 * Real tokens are minted (not Sanctum::actingAs) so revocation is observable.
 */
class DeactivationAndResetEndAccessTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private const PW = 'Str0ng!Passw0rd2026';

    private function staff(): User
    {
        $user = $this->userWithModulePerm('patients', true, false, false);
        $user->forceFill(['password' => Hash::make(self::PW), 'is_active' => true])->save();

        return $this->fresh($user);
    }

    public function test_a_deactivated_user_cannot_log_in_with_the_right_password(): void
    {
        $user = $this->staff();
        $user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => $user->email, 'password' => self::PW])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_live_phone_token_stops_working_once_the_user_is_deactivated(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        User::whereKey($user->id)->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_an_open_web_session_ends_on_the_next_request_after_deactivation(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get('/profile')->assertOk();

        User::whereKey($user->id)->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->fresh($user))->get('/profile')->assertRedirect(route('login'));
    }

    public function test_admin_deactivation_revokes_every_phone_token(): void
    {
        $admin = $this->legacyAdminUser();
        $admin->forceFill(['password' => Hash::make(self::PW)])->save();
        $user = $this->staff();
        $user->createToken('phone-1');
        $user->createToken('phone-2');

        $this->actingAs($admin)
            ->postJson(route('settings.staff.toggle', $user), ['password' => self::PW])
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse($this->fresh($user)->is_active);
    }

    public function test_admin_role_change_revokes_every_phone_token(): void
    {
        $admin = $this->legacyAdminUser();
        $user  = $this->staff();
        $user->createToken('phone');
        $otherRole = \App\Models\Role::where('id', '!=', $user->role_id)->value('id');
        $this->assertNotNull($otherRole, 'fixture needs a second role');

        $this->actingAs($admin)
            ->postJson(route('settings.staff.role', $user), ['role_id' => $otherRole])
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_broken_forgot_pin_routes_are_switched_off(): void
    {
        // AUTH-04: the flow never worked (pin column too short for its hash)
        // and nothing links to it, so it is off until a UI + migration exist.
        foreach (['/forgot-pin/send', '/forgot-pin/verify', '/forgot-pin/reset'] as $uri) {
            $this->postJson($uri, ['email' => 'someone@example.com'])->assertNotFound();
        }
    }
}
