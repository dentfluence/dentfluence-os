<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2A.2 — 2FA must be enforceable on the API, not only the web.
 *
 * MEASURED AT HEAD FIRST: the fix is present (AuthController::login lines
 * 64-89) and has been since 14 Jul. The row was open because it had never
 * been RE-VERIFIED at HEAD and nothing in the suite pinned it — the original
 * bug was that /api/v1/auth/login issued a full Bearer token on the password
 * alone, which made 2FA unenforceable for as long as the API existed: any
 * 2FA-protected account was reachable with a password.
 *
 * So this row ships a test, not a change. That is the honest shape of it:
 * a fix nobody can regress by accident is worth more than a fix nobody
 * checked. These five cases fail the moment the challenge is removed,
 * weakened to TOTP-only, or allowed to accept a spent recovery code.
 */
class ApiLoginEnforcesTwoFactorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private const PASSWORD = 'CorrectPassw0rd!';

    private function twoFactorUser(array $recovery = ['AAAAA-BBBBB']): User
    {
        $user   = $this->userWithModulePerm('patients', true, false, false);
        $secret = (new Google2FA())->generateSecretKey();

        $user->forceFill([
            'password'                  => Hash::make(self::PASSWORD),
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => $recovery,
            'two_factor_confirmed_at'   => now(),
        ])->save();

        return $this->fresh($user);
    }

    private function login(User $user, array $extra = [])
    {
        return $this->postJson('/api/v1/auth/login', array_merge([
            'email'    => $user->email,
            'password' => self::PASSWORD,
            'device'   => 'test-phone',
        ], $extra));
    }

    public function test_a_password_alone_does_not_mint_a_token(): void
    {
        $user = $this->twoFactorUser();

        $this->login($user)
            ->assertStatus(401)
            ->assertJsonPath('errors.two_factor_required', true);

        $this->assertSame(0, $user->tokens()->count(),
            'A Bearer token was issued on the password alone.');
    }

    public function test_a_wrong_code_does_not_mint_a_token(): void
    {
        $user = $this->twoFactorUser();

        $this->login($user, ['code' => '000000'])->assertStatus(401);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_valid_totp_code_signs_in(): void
    {
        $user = $this->twoFactorUser();
        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);

        $this->login($user, ['code' => $code])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_a_recovery_code_signs_in_once_and_is_then_spent(): void
    {
        $user = $this->twoFactorUser(['AAAAA-BBBBB', 'CCCCC-DDDDD']);

        $this->login($user, ['code' => 'AAAAA-BBBBB'])->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        // The same code must not work a second time.
        $this->login($user, ['code' => 'AAAAA-BBBBB'])->assertStatus(401);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_a_user_without_two_factor_is_unaffected(): void
    {
        $user = $this->userWithModulePerm('patients', true, false, false);
        $user->forceFill(['password' => Hash::make(self::PASSWORD)])->save();

        $this->login($this->fresh($user))->assertOk();
    }
}
