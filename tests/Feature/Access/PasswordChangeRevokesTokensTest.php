<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2A.1 — a password change must end every other session.
 *
 * MEASURED FIRST, and two thirds of the row was already done: updateMe has
 * required current_password since the 14 Jul parity fix, and
 * config/sanctum.php already sets 'expiration' (43,200 minutes). The third
 * item was genuinely open — NOTHING revoked the tokens a thief was already
 * holding. Changing the password left a stolen phone's token valid for its
 * full 30 days, on both surfaces. The API even documents logout-all as the
 * thing to "use after a lost/stolen phone or a password change", and nothing
 * called it.
 *
 * Real tokens are minted here rather than Sanctum::actingAs, because
 * actingAs installs a TransientToken with no database row — it cannot tell a
 * revoked token from a live one, which is the whole question.
 *
 * STILL OPEN, stated not hidden: there is no IDLE expiry (sanctum.expiration
 * is absolute age), and a web password change does not invalidate other
 * browser SESSIONS — that needs the AuthenticateSession middleware and is a
 * change of its own.
 */
class PasswordChangeRevokesTokensTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private const OLD = 'OldPassw0rd!2026';
    private const NEW = 'NewPassw0rd!2026';

    private function userWithTokens(int $count = 3): array
    {
        $user = $this->userWithModulePerm('patients', true, false, false);
        $user->forceFill(['password' => Hash::make(self::OLD)])->save();

        $plain = [];
        for ($i = 1; $i <= $count; $i++) {
            $plain[] = $user->createToken('device-' . $i)->plainTextToken;
        }

        return [$this->fresh($user), $plain];
    }

    private function profilePayload(User $user, array $extra = []): array
    {
        return array_merge([
            'name'  => $user->name,
            'email' => $user->email,
        ], $extra);
    }

    public function test_api_password_change_revokes_every_other_token(): void
    {
        [$user, $tokens] = $this->userWithTokens(3);
        $this->assertSame(3, $user->tokens()->count());

        // Change the password using device 1's own token.
        $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->putJson('/api/v1/auth/me', $this->profilePayload($user, [
                'password'         => self::NEW,
                'current_password' => self::OLD,
            ]))
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count(),
            'The other devices are still holding valid tokens after a password change.');

        // forgetGuards() between requests, or this proves nothing.
        //
        // The application is NOT rebooted between requests inside one test, so
        // once a guard has resolved a user it keeps returning that user for
        // every later request in the same test — a revoked Bearer token still
        // came back 200. The first run of this test failed exactly there, on
        // the assertion below, while the token count assertion above was
        // already green. Clearing the guards forces each request to resolve
        // its own token again, which is the thing under test.

        // The device that made the change is still signed in...
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->getJson('/api/v1/auth/me')->assertOk();

        // ...and the others are not.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $tokens[1])
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_an_ordinary_profile_edit_does_not_sign_anyone_out(): void
    {
        [$user, $tokens] = $this->userWithTokens(3);

        $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->putJson('/api/v1/auth/me', $this->profilePayload($user, ['name' => 'Renamed Person']))
            ->assertOk();

        $this->assertSame(3, $user->tokens()->count(),
            'Editing a name should not log the staff member out of their other devices.');
    }

    public function test_a_wrong_current_password_revokes_nothing(): void
    {
        [$user, $tokens] = $this->userWithTokens(3);

        $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->putJson('/api/v1/auth/me', $this->profilePayload($user, [
                'password'         => self::NEW,
                'current_password' => 'not-the-password',
            ]))
            ->assertStatus(422);

        $this->assertSame(3, $user->tokens()->count());
    }

    public function test_web_password_change_revokes_every_api_token(): void
    {
        [$user, ] = $this->userWithTokens(3);

        $this->actingAs($user)
            ->post(route('profile.password'), [
                'current_password'      => self::OLD,
                'password'              => self::NEW,
                'password_confirmation' => self::NEW,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $user->tokens()->count(),
            'A phone signed in before the web password change can still use its token.');
    }

    public function test_web_wrong_current_password_revokes_nothing(): void
    {
        [$user, ] = $this->userWithTokens(3);

        $this->actingAs($user)
            ->post(route('profile.password'), [
                'current_password'      => 'not-the-password',
                'password'              => self::NEW,
                'password_confirmation' => self::NEW,
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame(3, $user->tokens()->count());
    }
}
