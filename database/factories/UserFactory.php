<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Phase 1 · Slice 1.2 — test-infrastructure only.
     *
     * The legacy "role string 'admin' with no role_id ⇒ full access" bypass was
     * retired from User::canAccess(). Production users all carry a role_id (VPS
     * census: 12/12), and RolePermissionSeeder assigns the Admin role to any
     * `role = 'admin'` user. Older tests, however, build admins as
     * `User::factory()->create(['role' => 'admin'])` and relied on the bypass.
     *
     * This hook gives such users the REAL Admin role row instead, so those tests
     * keep exercising production authorization rather than a removed shortcut.
     * It never runs for non-admin roles and never touches production code paths.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->role !== 'admin' || $user->role_id !== null) {
                return;
            }

            $role = \App\Models\Role::firstOrCreate(
                ['slug' => \App\Models\Role::ADMIN],
                [
                    'name'      => 'Admin',
                    'category'  => \App\Models\Role::CATEGORY_STAFF,
                    'is_system' => true,
                ]
            );

            $user->forceFill(['role_id' => $role->id])->saveQuietly();
            $user->setRelation('roleModel', $role);
        });
    }
}
