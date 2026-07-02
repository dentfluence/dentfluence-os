<?php

namespace Database\Seeders;

use App\Models\HrDepartment;
use App\Models\HrShift;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    /**
     * Seed default HR departments and shifts.
     * Safe to run multiple times — uses firstOrCreate.
     */
    public function run(): void
    {
        // ── Departments ──────────────────────────────────────
        $departments = [
            ['name' => 'Dentistry',       'description' => 'Dental doctors and clinical staff'],
            ['name' => 'Reception',        'description' => 'Front desk and patient registration'],
            ['name' => 'Dental Nursing',   'description' => 'Chairside assistants and dental nurses'],
            ['name' => 'Lab',              'description' => 'Dental lab technicians'],
            ['name' => 'Accounts',         'description' => 'Billing and finance staff'],
            ['name' => 'Sterilization',    'description' => 'Sterilization and infection control'],
            ['name' => 'Administration',   'description' => 'Admin and management'],
        ];

        foreach ($departments as $dept) {
            HrDepartment::firstOrCreate(
                ['name' => $dept['name']],
                ['description' => $dept['description'], 'is_active' => true]
            );
        }

        $this->command->info('✓ ' . count($departments) . ' departments seeded.');

        // ── Shifts ───────────────────────────────────────────
        $shifts = [
            ['name' => 'Morning',   'start_time' => '09:00', 'end_time' => '14:00'],
            ['name' => 'Evening',   'start_time' => '14:00', 'end_time' => '20:00'],
            ['name' => 'Full Day',  'start_time' => '09:00', 'end_time' => '20:00'],
            ['name' => 'Half Day',  'start_time' => '10:00', 'end_time' => '14:00'],
        ];

        foreach ($shifts as $shift) {
            HrShift::firstOrCreate(
                ['name' => $shift['name']],
                ['start_time' => $shift['start_time'], 'end_time' => $shift['end_time'], 'branch_id' => 1, 'is_active' => true]
            );
        }

        $this->command->info('✓ ' . count($shifts) . ' shifts seeded.');
    }
}
