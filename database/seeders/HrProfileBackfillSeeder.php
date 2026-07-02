<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\HrStaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Backfill HR profiles for all existing system users.
 * Safe to run multiple times — uses updateOrCreate.
 *
 * Run: php artisan db:seed --class=HrProfileBackfillSeeder
 */
class HrProfileBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('is_active', true)->get();

        $counter = HrStaffProfile::max('id') ?? 0; // for generating unique employee codes

        foreach ($users as $user) {
            $counter++;
            $code = 'DF-' . str_pad($counter, 3, '0', STR_PAD_LEFT);

            HrStaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code'  => $code,
                    'employment_type'=> 'full_time',
                    'salary_type'    => 'fixed',
                    // qr_token is auto-generated in model boot if missing
                ]
            );

            $this->command->line("  ✓ {$user->name} → {$code}");
        }

        $this->command->info("✅ HR profiles backfilled for {$users->count()} users.");
    }
}
