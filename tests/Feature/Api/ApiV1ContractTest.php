<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 · Workstream F (slice F2) — /api/v1 contract lock.
 *
 * Freezes the public API contract the Flutter app depends on: the response
 * envelope, the auth flow, the token + user payload, error shape, and the
 * pagination meta. If a future refactor changes any of these, one of these
 * tests fails BEFORE the mobile app breaks in the field.
 */
class ApiV1ContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'admin',
            'branch_id' => 1,
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $overrides));
    }

    public function test_ping_is_public_and_uses_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.api', 'v1');
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonStructure(['success', 'message', 'data' => ['app', 'api', 'status', 'time']]);
    }

    public function test_protected_route_rejects_a_missing_token(): void
    {
        // The Flutter app relies on a 401 to know its token expired.
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_returns_a_bearer_token_and_the_user_payload(): void
    {
        $user = $this->admin(['email' => 'contract@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'contract@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.token_type', 'Bearer');
        $response->assertJsonStructure([
            'success', 'message',
            'data' => [
                'token', 'token_type',
                'user' => ['id', 'name', 'email', 'role', 'role_label', 'is_admin', 'branch_id', 'avatar'],
            ],
        ]);
    }

    public function test_login_with_bad_credentials_returns_the_error_envelope(): void
    {
        $this->admin(['email' => 'contract2@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'contract2@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_me_returns_the_user_payload_for_a_valid_token(): void
    {
        $user = $this->admin(['email' => 'me@example.com']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'me@example.com');
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'role_label', 'is_admin', 'branch_id', 'avatar'],
        ]);
    }

    public function test_patients_list_returns_the_paginated_envelope(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $response = $this->getJson('/api/v1/patients');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success', 'message', 'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
    }
}
