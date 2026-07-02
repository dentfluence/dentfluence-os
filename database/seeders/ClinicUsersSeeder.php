<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

/**
 * ClinicUsersSeeder
 *
 * Seeds all clinic staff with their roles, dummy emails, and passwords.
 * Run with: php artisan db:seed --class=ClinicUsersSeeder
 *
 * ┌──────────────────────────┬──────────────────────────────┬──────────────────┬───────────────┐
 * │ Name                     │ Email                        │ Password         │ Role          │
 * ├──────────────────────────┼──────────────────────────────┼──────────────────┼───────────────┤
 * │ Dr. Sayli Firke          │ sayli@tulipdental.in         │ Sayli@2025       │ Admin (Owner) │
 * │ Dr. Sumit Firke          │ sumit@tulipdental.in         │ Tulip@2025       │ Admin (Owner) │
 * │ Samiksha Nindrojiya      │ samiksha@tulipdental.in      │ Samiksha@2025    │ Manager       │
 * │ Runali Gurav             │ runali@tulipdental.in        │ Runali@2025      │ Assistant     │
 * │ Ankita Jughare           │ ankita@tulipdental.in        │ Ankita@2025      │ Assistant     │
 * │ Ashwini Kada             │ ashwini@tulipdental.in       │ Ashwini@2025     │ Front Desk    │
 * └──────────────────────────┴──────────────────────────────┴──────────────────┴───────────────┘
 */
class ClinicUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch role IDs — roles must exist (run RolePermissionSeeder first)
        $roleIds = Role::whereIn('slug', ['admin', 'manager', 'assistant', 'front_desk'])
                       ->pluck('id', 'slug');

        $users = [
            // ── Owners (Admin) ─────────────────────────────────────────────────
            [
                'name'      => 'Dr. Sayli Firke',
                'email'     => 'sayli@tulipdental.in',
                'password'  => 'Sayli@2025',
                'role'      => User::ROLE_ADMIN,
                'role_slug' => 'admin',
            ],
            [
                'name'      => 'Dr. Sumit Firke',
                'email'     => 'sumit@tulipdental.in',
                'password'  => 'Tulip@2025',
                'role'      => User::ROLE_ADMIN,
                'role_slug' => 'admin',
            ],

            // ── Clinic Manager ─────────────────────────────────────────────────
            [
                'name'      => 'Samiksha Nindrojiya',
                'email'     => 'samiksha@tulipdental.in',
                'password'  => 'Samiksha@2025',
                'role'      => 'manager',   // no ROLE_ constant exists for manager; use string directly
                'role_slug' => 'manager',
            ],

            // ── Dental Assistants ──────────────────────────────────────────────
            [
                'name'      => 'Runali Gurav',
                'email'     => 'runali@tulipdental.in',
                'password'  => 'Runali@2025',
                'role'      => 'assistant',
                'role_slug' => 'assistant',
            ],
            [
                'name'      => 'Ankita Jughare',
                'email'     => 'ankita@tulipdental.in',
                'password'  => 'Ankita@2025',
                'role'      => 'assistant',
                'role_slug' => 'assistant',
            ],

            // ── Front Desk ─────────────────────────────────────────────────────
            [
                'name'      => 'Ashwini Kada',
                'email'     => 'ashwini@tulipdental.in',
                'password'  => 'Ashwini@2025',
                'role'      => 'front_desk',
                'role_slug' => 'front_desk',
            ],
        ];

        foreach ($users as $data) {
            $roleId = $roleIds[$data['role_slug']] ?? null;

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'password'  => Hash::make($data['password']),
                    'role'      => $data['role'],
                    'role_id'   => $roleId,
                    'branch_id' => 1,
                    'is_active' => true,
                ]
            );

            $this->command->info("✅ {$data['name']} → {$data['email']} / {$data['password']} [{$data['role_slug']}]");
        }

        $this->command->newLine();
        $this->command->warn('⚠  These are dummy passwords — remind staff to change after first login!');
    }
}
