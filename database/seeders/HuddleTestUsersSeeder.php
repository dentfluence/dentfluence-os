<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class HuddleTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = 1; // Single branch for now — multi-branch ready

        $users = [
            [
                'name'              => 'Dr. Test Doctor',
                'email'             => 'doctor@tulipdental.in',
                'password'          => Hash::make('password'),
                'role'              => 'doctor',
                'branch_id'         => $branchId,
                'is_active'         => true,
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Test Front Desk',
                'email'             => 'frontdesk@tulipdental.in',
                'password'          => Hash::make('password'),
                'role'              => 'front_desk',
                'branch_id'         => $branchId,
                'is_active'         => true,
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Test Assistant',
                'email'             => 'assistant@tulipdental.in',
                'password'          => Hash::make('password'),
                'role'              => 'assistant',
                'branch_id'         => $branchId,
                'is_active'         => true,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('✅ Huddle test users seeded:');
        $this->command->table(
            ['Name', 'Email', 'Role', 'Password'],
            collect($users)->map(fn($u) => [
                $u['name'],
                $u['email'],
                $u['role'],
                'password',
            ])->toArray()
        );
    }
}
