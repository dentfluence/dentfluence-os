<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Create the default Dentfluence OS admin user.
     *
     * Login:    sumit@tulipdental.in
     * Password: set below — change before going live
     *
     * Run with: php artisan db:seed --class=UserSeeder
     */
    public function run(): void
    {
        // ── Primary admin — Dr. Sumit ──────────────────────
        User::updateOrCreate(
            ['email' => 'sumit@tulipdental.in'],
            [
                'name'      => 'Dr. Sumit Firke',
                'email'     => 'sumit@tulipdental.in',
                'password'  => Hash::make('Tulip@2025'),
                'role'      => User::ROLE_ADMIN,
                'branch_id' => 1,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Admin user created: sumit@tulipdental.in / Tulip@2025');
        $this->command->warn('⚠  Change your password after first login!');
    }
}
