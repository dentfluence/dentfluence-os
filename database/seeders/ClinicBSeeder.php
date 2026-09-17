<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ClinicBSeeder — Tenancy Phase 0: PROVE THE LEAK.
 * ─────────────────────────────────────────────────────────────────────────
 * Creates a second, clearly-labelled dental practice ("Clinic B — Dr. B-Patel")
 * inside the SAME database as Tulip Dental, so that logging in as a Clinic B
 * user shows, with screenshots, exactly which screens expose Tulip's data.
 *
 * This seeder does NOT fix anything. It is the failing test that Phases 1–5
 * exist to turn green.
 *
 * WHAT IT WRITES (all re-runnable — it wipes its own rows first)
 *   branches            1 row   code CLINICB            (branch_id = 2 normally)
 *   operatories         2 rows  "B-Chair 1", "B-Chair 2"
 *   users               3 rows  owner (admin) / doctor / front desk, all @clinicb.test
 *   patients           20 rows  every name prefixed "B-", phones 9000000201–220
 *   appointments       10 rows  past / today / upcoming, on Clinic B chairs
 *   finance_transactions + finance_income_entries   5 income entries, clinic_id = 2
 *
 * TENANT TAGGING — PROVISIONAL
 *   `organizations` / `organization_id` (docs/tenancy, Step 2a) are NOT in
 *   database/migrations yet. Until they land, Clinic B is identified by:
 *     • branch_id  = <the CLINICB branch id>   on patients / appointments / users
 *     • clinic_id  = 2                          on finance_* tables
 *   When Step 2a is applied, add organization_id = 2 here and nothing else changes.
 *   This seeder must NEVER touch the `clinics` table — that is the HQ sales CRM.
 *
 * RUN
 *   php artisan db:seed --class=ClinicBSeeder
 *
 * LOGINS (dummy)
 *   owner-b@clinicb.test      ClinicB@2026   admin      ← the realistic case: a buying dentist is admin
 *   doctor-b@clinicb.test     ClinicB@2026   doctor
 *   reception-b@clinicb.test  ClinicB@2026   front_desk ← non-admin: BranchScope actually filters here
 *
 * LEAK CHECK (screenshot each)
 *   1. Login owner-b → Patients, Appointments, Finance → expect: TULIP DATA VISIBLE (admin bypass)
 *   2. Login reception-b → Patients → expect: only B- patients (BranchScope works for non-admin
 *      on Patient/Appointment/Consultation/LabCase/Task only) → then Finance, Treatments,
 *      Lab vendors, Inventory, HR → expect: TULIP DATA VISIBLE (unscoped modules)
 *   Two failure modes, one seeder. Both must be gone before any second clinic goes live.
 */
class ClinicBSeeder extends Seeder
{
    private const BRANCH_CODE  = 'CLINICB';
    private const CLINIC_ID    = 2;                 // finance_* tenant tag (provisional)
    private const EMAIL_DOMAIN = 'clinicb.test';
    private const PASSWORD     = 'ClinicB@2026';
    private const PHONE_BASE   = 9000000200;        // patients get +1 … +20

    private int $branchId;
    private int $ownerId;
    private int $doctorId;
    private array $chairIds = [];

    public function run(): void
    {
        $this->command->info('🅱  ClinicBSeeder — Phase 0: seeding a second practice into the same DB');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->branchId = $this->ensureBranch();
        $this->wipePrevious();
        $this->seedChairs();
        $this->seedUsers();
        $patientIds = $this->seedPatients();
        $this->seedAppointments($patientIds);
        $this->seedIncome($patientIds);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->newLine();
        $this->command->info("✅ Clinic B ready. branch_id = {$this->branchId}, finance clinic_id = " . self::CLINIC_ID);
        $this->command->info('   owner-b@clinicb.test / doctor-b@clinicb.test / reception-b@clinicb.test — password ' . self::PASSWORD);
        $this->command->warn('   Now log in as owner-b and open Patients → Appointments → Finance. Screenshot what you see.');
        $this->command->warn('   Then log in as reception-b and open Finance / Treatments / Lab / Inventory / HR. Screenshot again.');
    }

    // ── 1. Branch (find or create — never duplicate) ─────────────────────
    private function ensureBranch(): int
    {
        $existing = DB::table('branches')->where('code', self::BRANCH_CODE)->first();
        if ($existing) {
            $this->command->info("  🏥 Branch exists (id {$existing->id}).");
            return $existing->id;
        }

        $id = DB::table('branches')->insertGetId([
            'name'       => 'Clinic B — Dr. B-Patel Dental Care',
            'code'       => self::BRANCH_CODE,
            'phone'      => '9000000200',
            'email'      => 'hello@' . self::EMAIL_DOMAIN,
            'address'    => 'B-Wing, Test Plaza, Station Road',
            'city'       => 'Kalyan',
            'state'      => 'Maharashtra',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("  🏥 Branch created (id {$id}).");
        return $id;
    }

    // ── 2. Wipe Clinic B's own rows only (children first) ────────────────
    private function wipePrevious(): void
    {
        $patientIds = DB::table('patients')->where('branch_id', $this->branchId)->pluck('id');

        DB::table('finance_income_entries')->where('clinic_id', self::CLINIC_ID)->delete();
        DB::table('finance_transactions')->where('clinic_id', self::CLINIC_ID)->delete();
        DB::table('appointments')->where('branch_id', $this->branchId)->delete();
        if ($patientIds->isNotEmpty()) {
            DB::table('patient_alerts')->whereIn('patient_id', $patientIds)->delete();
            DB::table('patient_notes')->whereIn('patient_id', $patientIds)->delete();
        }
        DB::table('patients')->where('branch_id', $this->branchId)->delete();
        DB::table('operatories')->where('branch_id', $this->branchId)->delete();
        DB::table('users')->where('email', 'like', '%@' . self::EMAIL_DOMAIN)->delete();

        $this->command->info('  🗑  Previous Clinic B rows wiped (Tulip untouched).');
    }

    // ── 3. Chairs ────────────────────────────────────────────────────────
    private function seedChairs(): void
    {
        foreach (['B-Chair 1', 'B-Chair 2'] as $i => $name) {
            $this->chairIds[] = DB::table('operatories')->insertGetId([
                'branch_id'     => $this->branchId,
                'name'          => $name,
                'display_order' => $i + 1,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
        $this->command->info('  🪑 2 chairs created.');
    }

    // ── 4. Users ─────────────────────────────────────────────────────────
    private function seedUsers(): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'doctor', 'front_desk'])->pluck('id', 'slug');

        $users = [
            ['name' => 'Dr. B-Patel (Owner)', 'email' => 'owner-b@' . self::EMAIL_DOMAIN,     'role' => 'admin',      'color' => '#B23A48'],
            ['name' => 'Dr. B-Shah',          'email' => 'doctor-b@' . self::EMAIL_DOMAIN,    'role' => 'doctor',     'color' => '#2A6F97'],
            ['name' => 'B-Reception Desai',   'email' => 'reception-b@' . self::EMAIL_DOMAIN, 'role' => 'front_desk', 'color' => null],
        ];

        foreach ($users as $u) {
            $id = DB::table('users')->insertGetId([
                'name'          => $u['name'],
                'email'         => $u['email'],
                'password'      => Hash::make(self::PASSWORD),
                'is_superadmin' => false,
                'role'          => $u['role'],
                'role_id'       => $roleIds[$u['role']] ?? null,
                'branch_id'     => $this->branchId,
                'is_active'     => true,
                'color'         => $u['color'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            if ($u['role'] === 'admin')  { $this->ownerId  = $id; }
            if ($u['role'] === 'doctor') { $this->doctorId = $id; }
            $this->command->info("  👤 {$u['name']} → {$u['email']} [{$u['role']}]");
        }
    }

    // ── 5. Patients — every name starts with "B-" ────────────────────────
    private function seedPatients(): array
    {
        $names = [
            ['B-Aarav Patel', 'male'],   ['B-Diya Shah', 'female'],    ['B-Kabir Mehta', 'male'],
            ['B-Ishita Joshi', 'female'],['B-Vivaan Desai', 'male'],   ['B-Anaya Rao', 'female'],
            ['B-Reyansh Nair', 'male'],  ['B-Myra Kulkarni', 'female'],['B-Arjun Bhatt', 'male'],
            ['B-Saanvi Iyer', 'female'], ['B-Dev Chauhan', 'male'],    ['B-Kiara Pillai', 'female'],
            ['B-Advait Gupta', 'male'],  ['B-Navya Menon', 'female'],  ['B-Rudra Jadhav', 'male'],
            ['B-Aadhya Pawar', 'female'],['B-Krish Gokhale', 'male'],  ['B-Pari Deshmukh', 'female'],
            ['B-Yash Kadam', 'male'],    ['B-Riya Sawant', 'female'],
        ];
        $complaints = ['Tooth pain', 'Routine check-up', 'Scaling', 'RCT required', 'Crown replacement',
                       'Implant consultation', 'Aligner consultation', 'Extraction', 'Whitening', 'Sensitivity'];
        $sources = ['google', 'referral', 'walk_in', 'instagram'];

        $ids = [];
        foreach ($names as $i => [$name, $gender]) {
            [$first, $last] = explode(' ', $name, 2);
            $ids[] = DB::table('patients')->insertGetId([
                'name'            => $name,
                'first_name'      => $first,
                'last_name'       => $last,
                'phone'           => (string) (self::PHONE_BASE + $i + 1),
                'email'           => strtolower(str_replace(['B-', ' '], ['b.', '.'], $name)) . '@' . self::EMAIL_DOMAIN,
                'gender'          => $gender,
                'date_of_birth'   => Carbon::create(1970 + ($i * 2) % 40, ($i % 12) + 1, ($i % 27) + 1)->toDateString(),
                'city'            => 'Kalyan',
                'chief_complaint' => $complaints[$i % count($complaints)],
                'source'          => $sources[$i % count($sources)],
                'branch_id'       => $this->branchId,
                'created_by'      => $this->ownerId,
                'created_at'      => now()->subDays(60 - $i * 2),
                'updated_at'      => now(),
            ]);
        }
        $this->command->info('  🧑‍🤝‍🧑 20 B- patients created.');
        return $ids;
    }

    // ── 6. Appointments — on Clinic B chairs, Clinic B doctor ────────────
    private function seedAppointments(array $pids): void
    {
        $today = Carbon::today();
        $plan = [
            [-7, '10:00:00', 'consultation', 'done'],
            [-5, '11:30:00', 'treatment',    'done'],
            [-3, '09:30:00', 'treatment',    'done'],
            [-1, '16:00:00', 'consultation', 'done'],
            [ 0, '09:30:00', 'consultation', 'scheduled'],
            [ 0, '11:00:00', 'treatment',    'scheduled'],
            [ 0, '15:00:00', 'treatment',    'scheduled'],
            [ 1, '10:00:00', 'consultation', 'scheduled'],
            [ 2, '12:00:00', 'treatment',    'scheduled'],
            [ 4, '17:00:00', 'consultation', 'scheduled'],
        ];

        foreach ($plan as $i => [$offset, $time, $type, $status]) {
            DB::table('appointments')->insert([
                'patient_id'       => $pids[$i],
                'doctor_id'        => $this->doctorId,
                'branch_id'        => $this->branchId,
                'operatory_id'     => $this->chairIds[$i % 2],
                'appointment_date' => $today->copy()->addDays($offset)->toDateString(),
                'appointment_time' => $time,
                'duration_minutes' => 30,
                'type'             => $type,
                'status'           => $status,
                'chief_complaint'  => 'B-' . ucfirst($type) . ' visit',
                'created_by'       => $this->ownerId,
                'created_at'       => now()->subDays(8),
                'updated_at'       => now(),
            ]);
        }
        $this->command->info('  📅 10 appointments created on B-Chair 1 / B-Chair 2.');
    }

    // ── 7. Income — finance_* tables tagged clinic_id = 2 ────────────────
    private function seedIncome(array $pids): void
    {
        $today = Carbon::today();
        $entries = [
            [$pids[0], 'consultation', 'upi',  800,   0,     -7],
            [$pids[1], 'rct',          'cash', 6500,  2000,  -5],
            [$pids[2], 'scaling',      'upi',  2000,  0,     -3],
            [$pids[3], 'crown',        'card', 12000, 6000,  -1],
            [$pids[4], 'implant',      'upi',  25000, 20000,  0],
        ];

        foreach ($entries as [$pid, $category, $mode, $gross, $outstanding, $offset]) {
            $date = $today->copy()->addDays($offset);
            $txId = DB::table('finance_transactions')->insertGetId([
                'clinic_id'        => self::CLINIC_ID,
                'type'             => 'income',
                'direction'        => 'credit',
                'source_type'      => 'App\\Models\\Finance\\IncomeEntry',
                'amount'           => $gross,
                'gst_amount'       => 0,
                'discount_amount'  => 0,
                'net_amount'       => $gross,
                'payment_mode'     => $mode,
                'status'           => 'active',
                'patient_id'       => $pid,
                'user_id'          => $this->ownerId,
                'transaction_date' => $date->toDateString(),
                'notes'            => "CLINIC B | {$category}",
                'created_at'       => $date,
                'updated_at'       => now(),
            ]);

            DB::table('finance_income_entries')->insert([
                'clinic_id'      => self::CLINIC_ID,
                'transaction_id' => $txId,
                'source'         => 'patient_billing',
                'patient_id'     => $pid,
                'category'       => $category,
                'gross_amount'   => $gross,
                'discount'       => 0,
                'net_amount'     => $gross,
                'outstanding'    => $outstanding,
                'income_date'    => $date->toDateString(),
                'doctor_name'    => 'Dr. B-Shah',
                'notes'          => 'CLINIC B',
                'status'         => 'active',
                'created_at'     => $date,
                'updated_at'     => now(),
            ]);
        }
        $this->command->info('  💰 5 income entries created (clinic_id = 2).');
    }
}
