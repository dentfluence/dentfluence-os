<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MasterDemoSeeder
 * ─────────────────────────────────────────────────────────────
 * Creates ONE consistent set of demo data across ALL modules:
 *   • patients (10)
 *   • appointments (15 — mix of past/upcoming/today)
 *   • consultations (8 — linked to patients + doctor)
 *   • treatment_visits (12 — linked to appointments + consultations)
 *   • treatment_plans (8 — linked to consultations)
 *   • finance_income_entries + finance_transactions (10 income records)
 *
 * Run: php artisan db:seed --class=MasterDemoSeeder
 * Reset & run: php artisan db:seed --class=MasterDemoSeeder (safe — clears demo data first)
 */
class MasterDemoSeeder extends Seeder
{
    // Admin user ID (Dr. Sumit) — created by UserSeeder
    private int $adminId = 1;
    private int $branchId = 1;

    public function run(): void
    {
        $this->command->info('🌱 MasterDemoSeeder starting…');

        // ── 0. Wipe old demo data (safe order: children first) ──
        $this->wipeDemoData();

        // ── 1. Ensure doctor users exist ──
        $doctors = $this->ensureDoctors();

        // ── 2. Seed patients ──
        $patientIds = $this->seedPatients();

        // ── 3. Seed appointments ──
        $appointmentIds = $this->seedAppointments($patientIds, $doctors);

        // ── 4. Seed consultations ──
        $consultationIds = $this->seedConsultations($patientIds, $doctors, $appointmentIds);

        // ── 5. Seed treatment visits ──
        $this->seedVisits($patientIds, $doctors, $appointmentIds, $consultationIds);

        // ── 6. Seed treatment plans ──
        $this->seedTreatmentPlans($consultationIds);

        // ── 7. Seed finance income ──
        $this->seedFinanceIncome($patientIds, $doctors);

        $this->command->info('✅ MasterDemoSeeder complete!');
    }

    // ─────────────────────────────────────────────────────────
    // 0. WIPE
    // ─────────────────────────────────────────────────────────
    private function wipeDemoData(): void
    {
        // Disable FK checks for clean truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('finance_transactions')->where('clinic_id', 1)->delete();
        DB::table('finance_income_entries')->where('clinic_id', 1)->delete();
        DB::table('treatment_plans')->whereIn('consultation_id',
            DB::table('consultations')->where('branch_id', $this->branchId)->pluck('id')
        )->delete();
        DB::table('treatment_visits')->where('created_by', $this->adminId)->orWhere('doctor_id', $this->adminId)->delete();
        DB::table('consultations')->where('branch_id', $this->branchId)->delete();
        DB::table('appointments')->where('branch_id', $this->branchId)->delete();
        DB::table('patients')->where('branch_id', $this->branchId)->where('created_by', $this->adminId)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('  🗑  Old demo data wiped.');
    }

    // ─────────────────────────────────────────────────────────
    // 1. DOCTORS
    // ─────────────────────────────────────────────────────────
    private function ensureDoctors(): array
    {
        $doctors = [
            ['name' => 'Dr. Sumit Firke',  'email' => 'sumit@tulipdental.in'],
            ['name' => 'Dr. Priya Mehta',  'email' => 'priya@tulipdental.in'],
            ['name' => 'Dr. Arjun Sharma', 'email' => 'arjun@tulipdental.in'],
        ];

        $ids = [];
        foreach ($doctors as $d) {
            $user = DB::table('users')->where('email', $d['email'])->first();
            if ($user) {
                $ids[] = $user->id;
            } else {
                $ids[] = DB::table('users')->insertGetId([
                    'name'       => $d['name'],
                    'email'      => $d['email'],
                    'password'   => bcrypt('Tulip@2025'),
                    'role'       => 'doctor',
                    'branch_id'  => $this->branchId,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('  👨‍⚕️  Doctors ready: ' . implode(', ', array_column($doctors, 'name')));
        return $ids; // [sumitId, priyaId, arjunId]
    }

    // ─────────────────────────────────────────────────────────
    // 2. PATIENTS
    // ─────────────────────────────────────────────────────────
    private function seedPatients(): array
    {
        $patients = [
            ['name' => 'Priya Sharma',    'phone' => '9876543210', 'dob' => '1990-03-15', 'gender' => 'female', 'email' => 'priya.sharma@gmail.com',    'city' => 'Mumbai',    'chief_complaint' => 'Tooth pain upper right', 'source' => 'google'],
            ['name' => 'Rahul Mehta',     'phone' => '9123456789', 'dob' => '1985-07-22', 'gender' => 'male',   'email' => 'rahul.mehta@gmail.com',     'city' => 'Mumbai',    'chief_complaint' => 'Root canal required',    'source' => 'referral'],
            ['name' => 'Anita Desai',     'phone' => '8765432109', 'dob' => '1995-11-08', 'gender' => 'female', 'email' => 'anita.desai@gmail.com',     'city' => 'Thane',     'chief_complaint' => 'Crooked teeth, aligners', 'source' => 'instagram'],
            ['name' => 'Suresh Kumar',    'phone' => '7654321098', 'dob' => '1978-05-30', 'gender' => 'male',   'email' => 'suresh.kumar@gmail.com',    'city' => 'Mumbai',    'chief_complaint' => 'Broken crown to replace', 'source' => 'walk_in'],
            ['name' => 'Meera Patel',     'phone' => '6543210987', 'dob' => '2000-01-19', 'gender' => 'female', 'email' => 'meera.patel@gmail.com',     'city' => 'Navi Mumbai','chief_complaint' => 'Routine check-up',       'source' => 'google'],
            ['name' => 'Vijay Singh',     'phone' => '9988776655', 'dob' => '1975-09-12', 'gender' => 'male',   'email' => 'vijay.singh@gmail.com',     'city' => 'Mumbai',    'chief_complaint' => 'Dental membership',      'source' => 'referral'],
            ['name' => 'Kavita Rao',      'phone' => '9871234560', 'dob' => '1988-06-25', 'gender' => 'female', 'email' => 'kavita.rao@gmail.com',      'city' => 'Pune',      'chief_complaint' => 'Implant consultation',   'source' => 'google'],
            ['name' => 'Arun Joshi',      'phone' => '9765432108', 'dob' => '1992-12-03', 'gender' => 'male',   'email' => 'arun.joshi@gmail.com',      'city' => 'Mumbai',    'chief_complaint' => 'Teeth whitening',        'source' => 'instagram'],
            ['name' => 'Sneha Kulkarni',  'phone' => '8877665544', 'dob' => '1997-04-17', 'gender' => 'female', 'email' => 'sneha.kulkarni@gmail.com',  'city' => 'Thane',     'chief_complaint' => 'Scaling & cleaning',     'source' => 'walk_in'],
            ['name' => 'Deepak Nair',     'phone' => '9654321087', 'dob' => '1983-08-09', 'gender' => 'male',   'email' => 'deepak.nair@gmail.com',     'city' => 'Mumbai',    'chief_complaint' => 'Extraction lower molar',  'source' => 'google'],
        ];

        $ids = [];
        foreach ($patients as $p) {
            $ids[] = DB::table('patients')->insertGetId([
                'name'            => $p['name'],
                'phone'           => $p['phone'],
                'date_of_birth'   => $p['dob'],
                'gender'          => $p['gender'],
                'email'           => $p['email'],
                'city'            => $p['city'],
                'chief_complaint' => $p['chief_complaint'],
                'source'          => $p['source'],
                'branch_id'       => $this->branchId,
                'created_by'      => $this->adminId,
                'created_at'      => now()->subDays(rand(10, 60)),
                'updated_at'      => now(),
            ]);
        }

        $this->command->info('  🧑‍🤝‍🧑 10 patients created.');
        return $ids;
    }

    // ─────────────────────────────────────────────────────────
    // 3. APPOINTMENTS
    // ─────────────────────────────────────────────────────────
    private function seedAppointments(array $pids, array $dids): array
    {
        $today = Carbon::today();
        $appts = [
            // Past — done
            ['patient' => $pids[0], 'doctor' => $dids[0], 'date' => $today->copy()->subDays(10), 'time' => '10:00:00', 'type' => 'consultation', 'status' => 'done',      'complaint' => 'Tooth pain upper right'],
            ['patient' => $pids[1], 'doctor' => $dids[1], 'date' => $today->copy()->subDays(9),  'time' => '11:30:00', 'type' => 'treatment',    'status' => 'done',      'complaint' => 'RCT sitting 1'],
            ['patient' => $pids[2], 'doctor' => $dids[0], 'date' => $today->copy()->subDays(7),  'time' => '09:00:00', 'type' => 'consultation', 'status' => 'done',      'complaint' => 'Aligner consultation'],
            ['patient' => $pids[3], 'doctor' => $dids[2], 'date' => $today->copy()->subDays(6),  'time' => '14:00:00', 'type' => 'treatment',    'status' => 'done',      'complaint' => 'Crown fitting'],
            ['patient' => $pids[6], 'doctor' => $dids[0], 'date' => $today->copy()->subDays(5),  'time' => '10:30:00', 'type' => 'consultation', 'status' => 'done',      'complaint' => 'Implant evaluation'],
            ['patient' => $pids[1], 'doctor' => $dids[1], 'date' => $today->copy()->subDays(3),  'time' => '11:00:00', 'type' => 'treatment',    'status' => 'done',      'complaint' => 'RCT sitting 2'],
            ['patient' => $pids[7], 'doctor' => $dids[0], 'date' => $today->copy()->subDays(2),  'time' => '15:00:00', 'type' => 'consultation', 'status' => 'done',      'complaint' => 'Whitening consultation'],

            // Today
            ['patient' => $pids[4], 'doctor' => $dids[1], 'date' => $today,                      'time' => '09:30:00', 'type' => 'consultation', 'status' => 'checkin',   'complaint' => 'Routine check-up'],
            ['patient' => $pids[8], 'doctor' => $dids[2], 'date' => $today,                      'time' => '11:00:00', 'type' => 'treatment',    'status' => 'in_chair',  'complaint' => 'Scaling sitting 1'],
            ['patient' => $pids[9], 'doctor' => $dids[0], 'date' => $today,                      'time' => '12:30:00', 'type' => 'treatment',    'status' => 'scheduled', 'complaint' => 'Extraction lower 36'],

            // Upcoming
            ['patient' => $pids[5], 'doctor' => $dids[1], 'date' => $today->copy()->addDay(),    'time' => '10:00:00', 'type' => 'consultation', 'status' => 'scheduled', 'complaint' => 'Membership query'],
            ['patient' => $pids[0], 'doctor' => $dids[0], 'date' => $today->copy()->addDays(2),  'time' => '09:00:00', 'type' => 'treatment',    'status' => 'scheduled', 'complaint' => 'Implant surgery prep'],
            ['patient' => $pids[2], 'doctor' => $dids[0], 'date' => $today->copy()->addDays(3),  'time' => '14:30:00', 'type' => 'treatment',    'status' => 'scheduled', 'complaint' => 'Aligner review visit 2'],
            ['patient' => $pids[6], 'doctor' => $dids[0], 'date' => $today->copy()->addDays(5),  'time' => '11:00:00', 'type' => 'treatment',    'status' => 'scheduled', 'complaint' => 'Implant stage 1'],
            ['patient' => $pids[3], 'doctor' => $dids[2], 'date' => $today->copy()->addDays(7),  'time' => '10:00:00', 'type' => 'treatment',    'status' => 'scheduled', 'complaint' => 'Crown cementation follow-up'],
        ];

        $ids = [];
        foreach ($appts as $a) {
            $ids[] = DB::table('appointments')->insertGetId([
                'patient_id'       => $a['patient'],
                'doctor_id'        => $a['doctor'],
                'branch_id'        => $this->branchId,
                'appointment_date' => $a['date']->format('Y-m-d'),
                'appointment_time' => $a['time'],
                'duration_minutes' => 30,
                'type'             => $a['type'],
                'status'           => $a['status'],
                'chief_complaint'  => $a['complaint'],
                'created_by'       => $this->adminId,
                'created_at'       => now()->subDays(rand(1, 5)),
                'updated_at'       => now(),
            ]);
        }

        $this->command->info('  📅 15 appointments created (past / today / upcoming).');
        return $ids;
    }

    // ─────────────────────────────────────────────────────────
    // 4. CONSULTATIONS
    // ─────────────────────────────────────────────────────────
    private function seedConsultations(array $pids, array $dids, array $apptIds): array
    {
        $today = Carbon::today();

        $consultations = [
            [
                'patient_id'         => $pids[0],
                'doctor_id'          => $dids[0],
                'appt_idx'           => 0,    // maps to appointmentIds index
                'date'               => $today->copy()->subDays(10),
                'status'             => 'completed',
                'chief_complaint'    => 'Tooth pain upper right',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => 'Periapical abscess 16',
                'secondary_diagnosis'=> 'Mild generalised periodontitis',
                'risk_assessment'    => 'low',
                'tx_transformative'  => json_encode([['procedure' => 'Implant 16', 'tooth' => '16', 'visits' => 3, 'cost' => 45000]]),
                'next_visit_date'    => $today->copy()->addDays(2)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[1],
                'doctor_id'          => $dids[1],
                'appt_idx'           => 1,
                'date'               => $today->copy()->subDays(9),
                'status'             => 'completed',
                'chief_complaint'    => 'Severe pain lower left',
                'visit_type'         => 'emergency',
                'primary_diagnosis'  => 'Irreversible pulpitis 36',
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'medium',
                'tx_transformative'  => json_encode([['procedure' => 'RCT 36', 'tooth' => '36', 'visits' => 3, 'cost' => 12000]]),
                'next_visit_date'    => $today->copy()->subDays(3)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[2],
                'doctor_id'          => $dids[0],
                'appt_idx'           => 2,
                'date'               => $today->copy()->subDays(7),
                'status'             => 'completed',
                'chief_complaint'    => 'Crooked teeth, wants aligners',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => 'Mild-moderate crowding upper arch',
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'low',
                'tx_transformative'  => json_encode([['procedure' => 'Clear Aligners', 'tooth' => 'Full arch', 'visits' => 6, 'cost' => 85000]]),
                'next_visit_date'    => $today->copy()->addDays(3)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[3],
                'doctor_id'          => $dids[2],
                'appt_idx'           => 3,
                'date'               => $today->copy()->subDays(6),
                'status'             => 'completed',
                'chief_complaint'    => 'Broken crown 14',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => 'Fractured crown 14',
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'low',
                'tx_transformative'  => json_encode([['procedure' => 'Zirconia Crown 14', 'tooth' => '14', 'visits' => 2, 'cost' => 18000]]),
                'next_visit_date'    => $today->copy()->addDays(7)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[6],
                'doctor_id'          => $dids[0],
                'appt_idx'           => 4,
                'date'               => $today->copy()->subDays(5),
                'status'             => 'completed',
                'chief_complaint'    => 'Missing tooth 26, implant consultation',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => 'Edentulous 26 — implant candidate',
                'secondary_diagnosis'=> 'Adequate bone height on CBCT',
                'risk_assessment'    => 'low',
                'tx_transformative'  => json_encode([['procedure' => 'Implant 26', 'tooth' => '26', 'visits' => 4, 'cost' => 55000]]),
                'next_visit_date'    => $today->copy()->addDays(5)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[7],
                'doctor_id'          => $dids[0],
                'appt_idx'           => 6,
                'date'               => $today->copy()->subDays(2),
                'status'             => 'completed',
                'chief_complaint'    => 'Teeth whitening / aesthetics',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => 'Extrinsic staining, scaling needed first',
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'low',
                'tx_transformative'  => json_encode([['procedure' => 'Scaling + Whitening', 'tooth' => 'Full mouth', 'visits' => 2, 'cost' => 8500]]),
                'next_visit_date'    => $today->copy()->addDays(10)->format('Y-m-d'),
            ],
            [
                'patient_id'         => $pids[4],
                'doctor_id'          => $dids[1],
                'appt_idx'           => 7,
                'date'               => $today,
                'status'             => 'draft',
                'chief_complaint'    => 'Routine check-up, no pain',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => null,
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'low',
                'tx_transformative'  => null,
                'next_visit_date'    => null,
            ],
            [
                'patient_id'         => $pids[9],
                'doctor_id'          => $dids[0],
                'appt_idx'           => 9,
                'date'               => $today,
                'status'             => 'draft',
                'chief_complaint'    => 'Extraction lower 36, wisdom tooth',
                'visit_type'         => 'routine',
                'primary_diagnosis'  => null,
                'secondary_diagnosis'=> null,
                'risk_assessment'    => 'medium',
                'tx_transformative'  => null,
                'next_visit_date'    => null,
            ],
        ];

        $ids = [];
        foreach ($consultations as $c) {
            $ids[] = DB::table('consultations')->insertGetId([
                'patient_id'          => $c['patient_id'],
                'doctor_id'           => $c['doctor_id'],
                'branch_id'           => $this->branchId,
                'status'              => $c['status'],
                'consultation_date'   => $c['date'],
                'chief_complaint'     => $c['chief_complaint'],
                'visit_type'          => $c['visit_type'],
                'primary_diagnosis'   => $c['primary_diagnosis'],
                'secondary_diagnosis' => $c['secondary_diagnosis'],
                'risk_assessment'     => $c['risk_assessment'],
                'tx_transformative'   => $c['tx_transformative'],
                'next_visit_date'     => $c['next_visit_date'],
                'created_at'          => $c['date'],
                'updated_at'          => now(),
            ]);
        }

        $this->command->info('  🦷 8 consultations created.');
        return $ids;
    }

    // ─────────────────────────────────────────────────────────
    // 5. TREATMENT VISITS
    // ─────────────────────────────────────────────────────────
    private function seedVisits(array $pids, array $dids, array $apptIds, array $conIds): void
    {
        $today = Carbon::today();

        $visits = [
            // Priya — Implant course
            ['patient' => $pids[0], 'doctor' => $dids[0], 'appt' => $apptIds[0],  'consult' => $conIds[0], 'date' => $today->copy()->subDays(10), 'procedure' => 'Implant assessment + CBCT', 'tooth' => '16', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'CBCT done, bone height adequate. Surgery planned.'],
            ['patient' => $pids[0], 'doctor' => $dids[0], 'appt' => $apptIds[11], 'consult' => $conIds[0], 'date' => $today->copy()->addDays(2),  'procedure' => 'Implant surgery 16',       'tooth' => '16', 'status' => 'started',   'visit_no' => 2, 'notes' => 'Surgery scheduled.'],

            // Rahul — RCT 3 sittings
            ['patient' => $pids[1], 'doctor' => $dids[1], 'appt' => $apptIds[1],  'consult' => $conIds[1], 'date' => $today->copy()->subDays(9),  'procedure' => 'RCT sitting 1 — access opening', 'tooth' => '36', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'Access opening done, irrigation, temp restoration.'],
            ['patient' => $pids[1], 'doctor' => $dids[1], 'appt' => $apptIds[5],  'consult' => $conIds[1], 'date' => $today->copy()->subDays(3),   'procedure' => 'RCT sitting 2 — BMP',            'tooth' => '36', 'status' => 'completed', 'visit_no' => 2, 'notes' => 'Biomechanical preparation done. Medicated.'],

            // Anita — Aligner
            ['patient' => $pids[2], 'doctor' => $dids[0], 'appt' => $apptIds[2],  'consult' => $conIds[2], 'date' => $today->copy()->subDays(7),   'procedure' => 'Aligner impressions + photos', 'tooth' => 'Full arch', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'Impressions sent to lab. Patient briefed on aligner process.'],
            ['patient' => $pids[2], 'doctor' => $dids[0], 'appt' => $apptIds[12], 'consult' => $conIds[2], 'date' => $today->copy()->addDays(3),    'procedure' => 'Aligner delivery visit 1',     'tooth' => 'Full arch', 'status' => 'started',   'visit_no' => 2, 'notes' => 'Aligner trays to be delivered.'],

            // Suresh — Crown
            ['patient' => $pids[3], 'doctor' => $dids[2], 'appt' => $apptIds[3],  'consult' => $conIds[3], 'date' => $today->copy()->subDays(6),   'procedure' => 'Crown preparation 14', 'tooth' => '14', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'Tooth prepared, provisional crown placed. Lab impression sent.'],

            // Kavita — Implant
            ['patient' => $pids[6], 'doctor' => $dids[0], 'appt' => $apptIds[4],  'consult' => $conIds[4], 'date' => $today->copy()->subDays(5),   'procedure' => 'Implant evaluation 26',       'tooth' => '26', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'Evaluation complete. CBCT ordered.'],
            ['patient' => $pids[6], 'doctor' => $dids[0], 'appt' => $apptIds[13], 'consult' => $conIds[4], 'date' => $today->copy()->addDays(5),    'procedure' => 'Implant stage 1 surgery 26',  'tooth' => '26', 'status' => 'started',   'visit_no' => 2, 'notes' => 'Surgery scheduled.'],

            // Arun — Whitening
            ['patient' => $pids[7], 'doctor' => $dids[0], 'appt' => $apptIds[6],  'consult' => $conIds[5], 'date' => $today->copy()->subDays(2),   'procedure' => 'Scaling pre-whitening', 'tooth' => 'Full mouth', 'status' => 'completed', 'visit_no' => 1, 'notes' => 'Scaling done. Shade B2. Whitening in next visit.'],

            // Sneha — Scaling
            ['patient' => $pids[8], 'doctor' => $dids[2], 'appt' => $apptIds[8],  'consult' => null,       'date' => $today,                          'procedure' => 'Scaling sitting 1 (today)', 'tooth' => 'Full mouth', 'status' => 'ongoing',   'visit_no' => 1, 'notes' => 'In chair. Upper arch done.'],
        ];

        foreach ($visits as $v) {
            DB::table('treatment_visits')->insert([
                'patient_id'       => $v['patient'],
                'appointment_id'   => $v['appt'],
                'consultation_id'  => $v['consult'],
                'doctor_id'        => $v['doctor'],
                'visit_date'       => $v['date']->format('Y-m-d'),
                'procedure'        => $v['procedure'],
                'tooth_number'     => $v['tooth'],
                'status'           => $v['status'],
                'visit_number'     => $v['visit_no'],
                'clinical_notes'   => $v['notes'],
                'created_by'       => $this->adminId,
                'created_at'       => $v['date'],
                'updated_at'       => now(),
            ]);
        }

        $this->command->info('  🏥 12 treatment visits created.');
    }

    // ─────────────────────────────────────────────────────────
    // 6. TREATMENT PLANS
    // ─────────────────────────────────────────────────────────
    private function seedTreatmentPlans(array $conIds): void
    {
        $plans = [
            // Priya — Implant
            ['consultation_id' => $conIds[0], 'plan_type' => 'best',       'total' => 45000, 'rows' => [['procedure' => 'Implant (Nobel Active) 16', 'tooth' => '16', 'visits' => 3, 'cost' => 45000]]],
            ['consultation_id' => $conIds[0], 'plan_type' => 'acceptable', 'total' => 28000, 'rows' => [['procedure' => 'Bridge 15-16-17', 'tooth' => '15-16-17', 'visits' => 2, 'cost' => 28000]]],

            // Rahul — RCT
            ['consultation_id' => $conIds[1], 'plan_type' => 'best',       'total' => 14500, 'rows' => [['procedure' => 'RCT 36', 'tooth' => '36', 'visits' => 3, 'cost' => 12000], ['procedure' => 'PFM Crown 36', 'tooth' => '36', 'visits' => 1, 'cost' => 2500]]],
            ['consultation_id' => $conIds[1], 'plan_type' => 'acceptable', 'total' => 1500,  'rows' => [['procedure' => 'Extraction 36', 'tooth' => '36', 'visits' => 1, 'cost' => 1500]]],

            // Anita — Aligners
            ['consultation_id' => $conIds[2], 'plan_type' => 'best',       'total' => 85000, 'rows' => [['procedure' => 'Clear Aligners (full arch)', 'tooth' => 'Full arch', 'visits' => 6, 'cost' => 85000]]],
            ['consultation_id' => $conIds[2], 'plan_type' => 'acceptable', 'total' => 45000, 'rows' => [['procedure' => 'Metal Braces', 'tooth' => 'Full arch', 'visits' => 8, 'cost' => 45000]]],

            // Suresh — Crown
            ['consultation_id' => $conIds[3], 'plan_type' => 'best',       'total' => 18000, 'rows' => [['procedure' => 'Zirconia Crown 14', 'tooth' => '14', 'visits' => 2, 'cost' => 18000]]],

            // Kavita — Implant
            ['consultation_id' => $conIds[4], 'plan_type' => 'best',       'total' => 55000, 'rows' => [['procedure' => 'Implant (Straumann) 26', 'tooth' => '26', 'visits' => 4, 'cost' => 55000]]],
        ];

        foreach ($plans as $p) {
            DB::table('treatment_plans')->insert([
                'consultation_id' => $p['consultation_id'],
                'plan_type'       => $p['plan_type'],
                'rows'            => json_encode($p['rows']),
                'total'           => $p['total'],
                'aocp'            => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->command->info('  📋 8 treatment plans created.');
    }

    // ─────────────────────────────────────────────────────────
    // 7. FINANCE INCOME
    // ─────────────────────────────────────────────────────────
    private function seedFinanceIncome(array $pids, array $dids): void
    {
        $today = Carbon::today();

        $entries = [
            ['patient' => $pids[0], 'doctor' => 'Dr. Sumit Firke',  'category' => 'implant',      'mode' => 'upi',          'gross' => 45000, 'outstanding' => 0,     'date' => $today->copy()->subDays(10)],
            ['patient' => $pids[1], 'doctor' => 'Dr. Priya Mehta',  'category' => 'rct',          'mode' => 'cash',         'gross' => 7500,  'outstanding' => 4500,  'date' => $today->copy()->subDays(9)],
            ['patient' => $pids[2], 'doctor' => 'Dr. Sumit Firke',  'category' => 'aligners',     'mode' => 'card',         'gross' => 28000, 'outstanding' => 57000, 'date' => $today->copy()->subDays(7)],
            ['patient' => $pids[3], 'doctor' => 'Dr. Arjun Sharma', 'category' => 'crown',        'mode' => 'bank_transfer','gross' => 18000, 'outstanding' => 0,     'date' => $today->copy()->subDays(6)],
            ['patient' => $pids[4], 'doctor' => 'Dr. Priya Mehta',  'category' => 'consultation', 'mode' => 'upi',          'gross' => 800,   'outstanding' => 0,     'date' => $today->copy()->subDays(4)],
            ['patient' => $pids[5], 'doctor' => null,               'category' => 'membership',   'mode' => 'upi',          'gross' => 12000, 'outstanding' => 0,     'date' => $today->copy()->subDays(3)],
            ['patient' => $pids[6], 'doctor' => 'Dr. Sumit Firke',  'category' => 'implant',      'mode' => 'emi',          'gross' => 15000, 'outstanding' => 40000, 'date' => $today->copy()->subDays(2)],
            ['patient' => $pids[7], 'doctor' => 'Dr. Sumit Firke',  'category' => 'scaling',      'mode' => 'cash',         'gross' => 2500,  'outstanding' => 6000,  'date' => $today->copy()->subDays(2)],
            ['patient' => $pids[8], 'doctor' => 'Dr. Arjun Sharma', 'category' => 'scaling',      'mode' => 'upi',          'gross' => 3000,  'outstanding' => 0,     'date' => $today->copy()->subDay()],
            ['patient' => $pids[9], 'doctor' => 'Dr. Sumit Firke',  'category' => 'consultation', 'mode' => 'cash',         'gross' => 800,   'outstanding' => 0,     'date' => $today],
        ];

        foreach ($entries as $idx => $e) {
            // Master transaction
            $txnId = DB::table('finance_transactions')->insertGetId([
                'clinic_id'    => 1,
                'type'         => 'income',
                'direction'    => 'credit',
                'source_type'  => 'App\\Models\\Finance\\IncomeEntry',
                'amount'       => $e['gross'],
                'gst_amount'   => 0,
                'discount_amount' => 0,
                'net_amount'   => $e['gross'],
                'payment_mode' => $e['mode'],
                'status'       => 'active',
                'patient_id'   => $e['patient'],
                'user_id'      => $this->adminId,
                'transaction_date' => $e['date']->format('Y-m-d'),
                'created_at'   => $e['date'],
                'updated_at'   => now(),
            ]);

            // Income entry
            DB::table('finance_income_entries')->insert([
                'clinic_id'      => 1,
                'transaction_id' => $txnId,
                'source'         => $e['category'] === 'membership' ? 'membership' : 'patient_billing',
                'patient_id'     => $e['patient'],
                'category'       => $e['category'],
                'gross_amount'   => $e['gross'],
                'discount'       => 0,
                'net_amount'     => $e['gross'],
                'outstanding'    => $e['outstanding'],
                'income_date'    => $e['date']->format('Y-m-d'),
                'doctor_name'    => $e['doctor'],
                'status'         => 'active',
                'created_at'     => $e['date'],
                'updated_at'     => now(),
            ]);
        }

        $this->command->info('  💰 10 finance income entries created.');
    }
}
