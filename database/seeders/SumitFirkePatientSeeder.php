<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SumitFirkePatientSeeder
 * ─────────────────────────────────────────────────────────────────────────
 * Inserts rich dummy data for patient "Sumit Firke" across:
 *   • patients            — full profile with habits, allergies, conditions
 *   • patient_alerts      — 2 medical alerts
 *   • patient_notes       — 3 notes (general / clinical / billing)
 *   • consultations       — 3 consultation records
 *   • investigations      — linked to consultations
 *   • appointments        — 6 appointments (past + upcoming)
 *   • treatment_visits    — 4 visits (scaling, RCT, implant)
 *   • lab_cases           — 2 lab work orders
 *   • finance_transactions + finance_income_entries — 4 invoices
 *
 * Run:   php artisan db:seed --class=SumitFirkePatientSeeder
 * Reset: add --force to re-run (it checks for existing record first)
 * ─────────────────────────────────────────────────────────────────────────
 */
class SumitFirkePatientSeeder extends Seeder
{
    private int $branchId = 1;
    private int $doctorId = 1; // Dr. Sumit Firke (admin user)

    public function run(): void
    {
        $this->command->info('🦷 Seeding dummy data for patient Sumit Firke…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── 1. Patient ───────────────────────────────────────────────────
        $patientId = $this->seedPatient();

        // ── 2. Alerts ────────────────────────────────────────────────────
        $this->seedAlerts($patientId);

        // ── 3. Notes ─────────────────────────────────────────────────────
        $this->seedNotes($patientId);

        // ── 4. Consultations + Investigations ────────────────────────────
        $consultationIds = $this->seedConsultations($patientId);

        // ── 5. Appointments ──────────────────────────────────────────────
        $appointmentIds = $this->seedAppointments($patientId);

        // ── 6. Treatment Visits ──────────────────────────────────────────
        $this->seedVisits($patientId, $appointmentIds, $consultationIds);

        // ── 7. Lab Cases ─────────────────────────────────────────────────
        $this->seedLabCases($patientId);

        // ── 8. Finance (Invoices / Income) ───────────────────────────────
        $this->seedFinance($patientId);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('✅ Done! Patient Sumit Firke has been loaded with dummy data.');
        $this->command->info("   Patient ID: {$patientId}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. PATIENT PROFILE
    // ─────────────────────────────────────────────────────────────────────
    private function seedPatient(): int
    {
        // Check if already exists so the seeder is safe to re-run
        $existing = DB::table('patients')
            ->where('phone', '9000000001')
            ->first();

        if ($existing) {
            $this->command->warn("  ⚠  Patient already exists (ID: {$existing->id}). Updating profile…");

            DB::table('patients')->where('id', $existing->id)->update([
                'habits'           => json_encode(['tobacco', 'alcohol']),
                'allergies'        => json_encode(['Penicillin', 'Aspirin']),
                'occupation'       => 'Software Engineer',
                'medical_alert'    => 'Penicillin allergy — use Amoxicillin alternatives',
                'recall_status'    => 'active',
                'next_recall_date' => Carbon::now()->addMonths(6)->toDateString(),
                'last_visit_date'  => Carbon::now()->subDays(20)->toDateString(),
                'outstanding_balance' => 5500.00,
                'lifetime_value'   => 32000.00,
                'updated_at'       => now(),
            ]);

            return $existing->id;
        }

        $id = DB::table('patients')->insertGetId([
            // ── Identity ────────────────────────────────────────────────
            'name'            => 'Sumit Firke',
            'first_name'      => 'Sumit',
            'last_name'       => 'Firke',
            'gender'          => 'male',
            'date_of_birth'   => '1990-07-15',
            'phone'           => '9000000001',
            'alternate_phone' => '9000000002',
            'email'           => 'sumit.firke@gmail.com',

            // ── Address ──────────────────────────────────────────────────
            'address'         => 'Flat 4B, Tulip Heights, Baner Road',
            'area'            => 'Baner',
            'city'            => 'Pune',
            'state'           => 'Maharashtra',
            'pincode'         => '411045',

            // ── Clinical ─────────────────────────────────────────────────
            'chief_complaint'       => 'Sensitivity in upper left molar, wants RCT evaluation',
            'medical_alert'         => 'Penicillin allergy — use Amoxicillin alternatives',
            'medical_conditions'    => json_encode(['Mild hypertension (controlled)']),
            'current_medications'   => json_encode(['Amlodipine 5mg (once daily)']),
            'dental_conditions'     => json_encode(['Caries on 26', 'Gum recession lower anteriors']),

            // ── Habits ───────────────────────────────────────────────────
            'habits'          => json_encode(['tobacco', 'alcohol']),
            'allergies'       => json_encode(['Penicillin', 'Aspirin']),
            'occupation'      => 'Software Engineer',
            'family_notes'    => 'Wife Priya also a patient. Son (age 7) due for first visit.',

            // ── Source ───────────────────────────────────────────────────
            'source'          => 'referral',
            'referred_by'     => 'Rahul Mehta',

            // ── Recall / Lifecycle ───────────────────────────────────────
            'recall_status'       => 'active',
            'next_recall_date'    => Carbon::now()->addMonths(6)->toDateString(),
            'last_visit_date'     => Carbon::now()->subDays(20)->toDateString(),

            // ── Financial summary ─────────────────────────────────────────
            'outstanding_balance' => 5500.00,
            'lifetime_value'      => 32000.00,

            // ── Relations ─────────────────────────────────────────────────
            'branch_id'   => $this->branchId,
            'created_by'  => $this->doctorId,
            'created_at'  => Carbon::now()->subMonths(8),
            'updated_at'  => now(),
        ]);

        $this->command->info("  👤  Patient created (ID: {$id})");
        return $id;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. PATIENT ALERTS
    // ─────────────────────────────────────────────────────────────────────
    private function seedAlerts(int $patientId): void
    {
        // Clear old seeded alerts for this patient first
        DB::table('patient_alerts')->where('patient_id', $patientId)->delete();

        $alerts = [
            [
                'alert'    => 'PENICILLIN ALLERGY — Do NOT prescribe Penicillin or Amoxicillin. Use Azithromycin or Clindamycin.',
                'severity' => 'high',
            ],
            [
                'alert'    => 'Patient on Amlodipine for hypertension. Check BP before any surgical procedure. Target < 140/90.',
                'severity' => 'medium',
            ],
        ];

        foreach ($alerts as $alert) {
            DB::table('patient_alerts')->insert([
                'patient_id' => $patientId,
                'alert'      => $alert['alert'],
                'severity'   => $alert['severity'],
                'is_active'  => true,
                'created_by' => $this->doctorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  🚨  2 patient alerts added.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. PATIENT NOTES
    // ─────────────────────────────────────────────────────────────────────
    private function seedNotes(int $patientId): void
    {
        DB::table('patient_notes')->where('patient_id', $patientId)->delete();

        $notes = [
            [
                'note'      => 'Patient is cooperative and punctual. Prefers morning appointments (before 11 AM). WhatsApp preferred for reminders.',
                'note_type' => 'general',
            ],
            [
                'note'      => 'High caries risk — poor interproximal hygiene. Advised electric toothbrush and nightly fluoride rinse. Tobacco use (gutka ~5/day) discussed; patient willing to reduce. Gum recession #31–41 — monitor at every recall.',
                'note_type' => 'clinical',
            ],
            [
                'note'      => 'Implant #36 balance of ₹5,500 pending. Patient requested EMI — offered 3-month no-cost split. Confirm at next visit.',
                'note_type' => 'billing',
            ],
        ];

        foreach ($notes as $n) {
            DB::table('patient_notes')->insert([
                'patient_id' => $patientId,
                'note'       => $n['note'],
                'note_type'  => $n['note_type'],
                'created_by' => $this->doctorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  📝  3 patient notes added.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. CONSULTATIONS + INVESTIGATIONS
    // ─────────────────────────────────────────────────────────────────────
    private function seedConsultations(int $patientId): array
    {
        DB::table('investigations')->whereIn('consultation_id',
            DB::table('consultations')->where('patient_id', $patientId)->pluck('id')
        )->delete();
        DB::table('consultations')->where('patient_id', $patientId)->delete();

        $ids = [];

        // ── Consultation 1 — Initial (8 months ago) ──────────────────────
        $ids[] = $c1 = DB::table('consultations')->insertGetId([
            'patient_id'          => $patientId,
            'doctor_id'           => $this->doctorId,
            'branch_id'           => $this->branchId,
            'status'              => 'completed',
            'consultation_date'   => Carbon::now()->subMonths(8),

            // Chief Complaint
            'chief_complaint'     => 'Severe tooth pain upper left molar',
            'complaint_duration'  => '1 week',
            'severity'            => 'severe',
            'tooth_area'          => '26',
            'location'            => 'Upper left',
            'complaint_notes'     => 'Pain aggravated by cold; spontaneous at night. No previous treatment.',
            'visit_type'          => 'routine',

            // Clinical Data
            'clinical_data'       => json_encode([
                'soft_tissue'           => 'Normal',
                'caries'                => 'Deep caries 26, enamel caries 27',
                'periodontal'           => 'Mild generalised gingivitis',
                'bleeding_on_probing'   => 'Yes — anterior region',
                'plaque_index'          => 'Moderate',
                'occlusion'             => 'Class I',
                'tmj'                   => 'No clicking or pain',
                'existing_condition'    => 'Old composite 15, 25',
                'oral_hygiene'          => 'Fair — advised TBI',
                'notes'                 => 'High caries risk patient. Tobacco use.',
            ]),

            // Diagnosis
            'primary_diagnosis'   => 'Irreversible pulpitis tooth #26',
            'secondary_diagnosis' => 'Chronic generalised gingivitis',
            'risk_assessment'     => 'high',
            'diagnosis_notes'     => 'RCT indicated for #26. Scaling and OHI advised.',

            // Treatment Options
            'tx_emergency'        => json_encode([['tooth' => '26', 'treatment' => 'Pulpotomy / Emergency access']]),
            'tx_transformative'   => json_encode([
                ['tooth' => '26', 'treatment' => 'RCT + PFM Crown', 'fee' => 12000],
                ['tooth' => 'full', 'treatment' => 'Full mouth scaling + polishing', 'fee' => 1500],
            ]),

            // Treatment Plan
            'treatment_plan_best' => json_encode([
                ['item' => 'RCT #26',             'fee' => 8000],
                ['item' => 'PFM Crown #26',        'fee' => 4000],
                ['item' => 'Full mouth scaling',   'fee' => 1500],
            ]),
            'treatment_plan_best_total' => 13500.00,

            // Prescriptions
            'prescriptions'    => json_encode([
                ['drug' => 'Azithromycin 500mg', 'dose' => '1-0-0', 'days' => 5, 'notes' => '(Penicillin allergy)'],
                ['drug' => 'Aceclofenac+Paracetamol', 'dose' => '1-0-1', 'days' => 3, 'notes' => 'After food'],
                ['drug' => 'Clindamycin 300mg', 'dose' => '1-1-1', 'days' => 5, 'notes' => 'If no relief'],
            ]),
            'instructions'     => json_encode([
                'Avoid cold food on left side',
                'Use warm saline rinse 3x daily',
                'No tobacco for 48 hours post-procedure',
            ]),

            // Follow-up
            'next_visit_type'  => 'followup',
            'next_visit_date'  => Carbon::now()->subMonths(7)->toDateString(),
            'recall_interval'  => '6_months',
            'finishing_notes'  => 'Patient counselled on tobacco cessation. Agreed to start RCT next visit.',

            'created_at' => Carbon::now()->subMonths(8),
            'updated_at' => Carbon::now()->subMonths(8),
        ]);

        // Investigations for C1
        DB::table('investigations')->insert([
            ['consultation_id' => $c1, 'type' => 'iopa',       'details' => 'IOPA #26 — deep caries reaching pulp chamber. Widened PDL space visible.', 'created_at' => now(), 'updated_at' => now()],
            ['consultation_id' => $c1, 'type' => 'opg',        'details' => 'OPG full mouth — mild bone loss anteriors, #36 appears periapical radiolucency (old). All other teeth within normal limits.', 'created_at' => now(), 'updated_at' => now()],
            ['consultation_id' => $c1, 'type' => 'blood_test', 'details' => 'Fasting blood sugar 98 mg/dL (normal). BP at chair: 136/88 mmHg — advised monitoring.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Consultation 2 — Implant Planning (4 months ago) ─────────────
        $ids[] = $c2 = DB::table('consultations')->insertGetId([
            'patient_id'          => $patientId,
            'doctor_id'           => $this->doctorId,
            'branch_id'           => $this->branchId,
            'status'              => 'completed',
            'consultation_date'   => Carbon::now()->subMonths(4),

            'chief_complaint'     => 'Missing lower left first molar — wants implant',
            'complaint_duration'  => '2 years',
            'severity'            => 'moderate',
            'tooth_area'          => '36',
            'location'            => 'Lower left',
            'complaint_notes'     => 'Tooth extracted 2 years ago elsewhere. Difficulty chewing on that side. Interested in implant after reading online.',
            'visit_type'          => 'routine',

            'clinical_data'       => json_encode([
                'soft_tissue'        => 'Healed extraction socket',
                'caries'             => 'None active at this visit',
                'periodontal'        => 'Mild recession #31–41, otherwise stable',
                'occlusion'          => 'Class I, over-eruption #16 observed',
                'oral_hygiene'       => 'Improved since last visit — Good',
                'notes'              => 'Bone volume adequate for implant. Sinus safe. Recommend CBCT for implant planning.',
            ]),

            'primary_diagnosis'   => 'Edentulous space #36 — implant candidate',
            'secondary_diagnosis' => 'Super-eruption #16 noted — monitor',
            'risk_assessment'     => 'low',
            'diagnosis_notes'     => 'Good candidate. CBCT ordered. BP stable today 128/82.',

            'tx_transformative'   => json_encode([
                ['tooth' => '36', 'treatment' => 'Implant (Nobel Biocare) + Zirconia Crown', 'fee' => 45000],
            ]),
            'treatment_plan_best'       => json_encode([
                ['item' => 'Implant fixture placement #36', 'fee' => 25000],
                ['item' => 'Healing abutment',              'fee' => 3000],
                ['item' => 'Implant crown (Zirconia)',      'fee' => 17000],
            ]),
            'treatment_plan_best_total' => 45000.00,

            'prescriptions'  => json_encode([]),
            'instructions'   => json_encode(['CBCT scan required before surgery', 'Stop tobacco at least 2 weeks before implant']),

            'next_visit_type' => 'followup',
            'next_visit_date' => Carbon::now()->subMonths(3)->toDateString(),
            'recall_interval' => 'custom',
            'finishing_notes' => 'CBCT ordered. Surgery tentatively planned 3 months out pending scan review.',

            'created_at' => Carbon::now()->subMonths(4),
            'updated_at' => Carbon::now()->subMonths(4),
        ]);

        // Investigations for C2
        DB::table('investigations')->insert([
            ['consultation_id' => $c2, 'type' => 'cbct', 'details' => 'CBCT #36 region — bone height 13mm, width 8mm. Adequate for 4.0×10mm implant. Inferior alveolar nerve safe distance 4mm.', 'created_at' => now(), 'updated_at' => now()],
            ['consultation_id' => $c2, 'type' => 'iopa', 'details' => 'IOPA #36 region — healed socket, crestal bone appears stable.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Consultation 3 — Recall / Review (20 days ago) ───────────────
        $ids[] = $c3 = DB::table('consultations')->insertGetId([
            'patient_id'          => $patientId,
            'doctor_id'           => $this->doctorId,
            'branch_id'           => $this->branchId,
            'status'              => 'completed',
            'consultation_date'   => Carbon::now()->subDays(20),

            'chief_complaint'     => '6-month recall + implant crown review',
            'complaint_duration'  => 'N/A',
            'severity'            => 'mild',
            'tooth_area'          => 'full mouth',
            'visit_type'          => 'followup',

            'clinical_data'       => json_encode([
                'soft_tissue'        => 'Normal, no lesions',
                'caries'             => 'None new',
                'periodontal'        => 'Stable, slight recession #41 unchanged',
                'oral_hygiene'       => 'Good',
                'notes'              => 'Implant #36 fully osseointegrated. Crown fits well, occlusion checked. Patient satisfied.',
            ]),

            'primary_diagnosis'   => 'Dental implant #36 — successful osseointegration',
            'secondary_diagnosis' => 'Recall: no new pathology',
            'risk_assessment'     => 'low',

            'prescriptions' => json_encode([]),
            'instructions'  => json_encode([
                'Use interdental brush around implant daily',
                'Avoid hard foods on implant side',
                'Next recall in 6 months',
            ]),

            'next_visit_type' => 'routine',
            'next_visit_date' => Carbon::now()->addMonths(6)->toDateString(),
            'recall_interval' => '6_months',
            'finishing_notes' => 'Patient happy with implant. Pending payment ₹5,500 discussed.',

            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        $this->command->info('  📋  3 consultations + investigations added.');
        return $ids; // [c1, c2, c3]
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. APPOINTMENTS
    // ─────────────────────────────────────────────────────────────────────
    private function seedAppointments(int $patientId): array
    {
        DB::table('appointments')->where('patient_id', $patientId)->delete();

        $appointments = [
            // Past — done
            [
                'date'      => Carbon::now()->subMonths(8)->toDateString(),
                'time'      => '10:00:00',
                'type'      => 'consultation',
                'status'    => 'done',
                'duration'  => 60,
                'complaint' => 'Initial consultation — tooth pain #26',
                'notes'     => 'Pulpitis diagnosed. RCT planned.',
            ],
            [
                'date'      => Carbon::now()->subMonths(7)->toDateString(),
                'time'      => '09:30:00',
                'type'      => 'treatment',
                'status'    => 'done',
                'duration'  => 90,
                'complaint' => 'RCT #26 — visit 1 (access + biomechanical prep)',
                'notes'     => 'Canal negotiated. K-file #15. NaOCl irrigation. Medicated dressing placed.',
            ],
            [
                'date'      => Carbon::now()->subMonths(6)->toDateString(),
                'time'      => '10:00:00',
                'type'      => 'treatment',
                'status'    => 'done',
                'duration'  => 60,
                'complaint' => 'RCT #26 — visit 2 (obturation) + full scaling',
                'notes'     => 'RCT completed. Scaling done. Post-op instructions given.',
            ],
            [
                'date'      => Carbon::now()->subMonths(4)->toDateString(),
                'time'      => '11:00:00',
                'type'      => 'consultation',
                'status'    => 'done',
                'duration'  => 45,
                'complaint' => 'Implant consultation #36',
                'notes'     => 'CBCT ordered. Implant surgery planned.',
            ],
            [
                'date'      => Carbon::now()->subMonths(3)->toDateString(),
                'time'      => '09:00:00',
                'type'      => 'treatment',
                'status'    => 'done',
                'duration'  => 120,
                'complaint' => 'Implant fixture placement #36',
                'notes'     => 'Nobel Biocare 4.0×10mm placed. Healing abutment seated. Sutures placed. Post-op Rx given.',
            ],
            // Upcoming
            [
                'date'      => Carbon::now()->addDays(10)->toDateString(),
                'time'      => '10:30:00',
                'type'      => 'treatment',
                'status'    => 'scheduled',
                'duration'  => 30,
                'complaint' => 'PFM Crown delivery #26',
                'notes'     => 'Crown from lab expected. Try-in + cementation.',
            ],
        ];

        $ids = [];
        foreach ($appointments as $appt) {
            $ids[] = DB::table('appointments')->insertGetId([
                'patient_id'       => $patientId,
                'doctor_id'        => $this->doctorId,
                'branch_id'        => $this->branchId,
                'appointment_date' => $appt['date'],
                'appointment_time' => $appt['time'],
                'duration_minutes' => $appt['duration'],
                'type'             => $appt['type'],
                'status'           => $appt['status'],
                'chief_complaint'  => $appt['complaint'],
                'notes'            => $appt['notes'],
                'created_by'       => $this->doctorId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        $this->command->info('  📅  6 appointments added (5 past, 1 upcoming).');
        return $ids; // [appt1..appt6]
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. TREATMENT VISITS
    // ─────────────────────────────────────────────────────────────────────
    private function seedVisits(int $patientId, array $apptIds, array $consultIds): void
    {
        DB::table('treatment_visits')->where('patient_id', $patientId)->delete();

        $visits = [
            // Visit 1 — Scaling
            [
                'patient_id'       => $patientId,
                'appointment_id'   => $apptIds[2] ?? null,
                'consultation_id'  => $consultIds[0] ?? null,
                'doctor_id'        => $this->doctorId,
                'visit_date'       => Carbon::now()->subMonths(6)->toDateString(),
                'procedure'        => 'Scaling',
                'tooth_number'     => 'Full Mouth',
                'status'           => 'completed',
                'visit_number'     => 1,
                'treatment_name'   => 'Full Mouth Scaling & Polishing',
                'current_stage'    => 'completed',
                'completed_stages' => json_encode(['ultrasonic_scaling', 'hand_scaling', 'polishing']),
                'scale_quadrants'  => 'UL,UR,LL,LR',
                'scale_method'     => 'Both',
                'clinical_notes'   => 'Moderate calculus removed. Bleeding on probing reduced post-scaling. TBI reinforced.',
                'next_visit_plan'  => 'Review in 6 months. Continue RCT #26.',
                'created_by'       => $this->doctorId,
                'created_at'       => Carbon::now()->subMonths(6),
                'updated_at'       => Carbon::now()->subMonths(6),
            ],
            // Visit 2 — RCT visit 1
            [
                'patient_id'       => $patientId,
                'appointment_id'   => $apptIds[1] ?? null,
                'consultation_id'  => $consultIds[0] ?? null,
                'doctor_id'        => $this->doctorId,
                'visit_date'       => Carbon::now()->subMonths(7)->toDateString(),
                'procedure'        => 'RCT',
                'tooth_number'     => '26',
                'status'           => 'completed',
                'visit_number'     => 1,
                'treatment_name'   => 'Root Canal Treatment #26',
                'current_stage'    => 'obturation',
                'completed_stages' => json_encode(['access_opening', 'biomechanical_prep']),
                'rct_num_canals'   => 3,
                'rct_canal_lengths'=> json_encode([
                    ['canal' => 'MB', 'length' => '21mm'],
                    ['canal' => 'DB', 'length' => '20mm'],
                    ['canal' => 'P',  'length' => '22mm'],
                ]),
                'rct_file_type'    => 'Rotary',
                'rct_irrigant'     => 'NaOCl 3% + EDTA',
                'clinical_notes'   => 'Access opening done. All 3 canals negotiated. Working length established. Biomechanical prep with rotary files. NaOCl + EDTA irrigation. Calcium hydroxide dressing placed.',
                'next_visit_plan'  => 'Return for obturation in 1 week.',
                'created_by'       => $this->doctorId,
                'created_at'       => Carbon::now()->subMonths(7),
                'updated_at'       => Carbon::now()->subMonths(7),
            ],
            // Visit 3 — RCT visit 2 (obturation)
            [
                'patient_id'       => $patientId,
                'appointment_id'   => $apptIds[2] ?? null,
                'consultation_id'  => $consultIds[0] ?? null,
                'doctor_id'        => $this->doctorId,
                'visit_date'       => Carbon::now()->subMonths(6)->toDateString(),
                'procedure'        => 'RCT',
                'tooth_number'     => '26',
                'status'           => 'completed',
                'visit_number'     => 2,
                'treatment_name'   => 'Root Canal Treatment #26',
                'current_stage'    => 'completed',
                'completed_stages' => json_encode(['access_opening', 'biomechanical_prep', 'obturation']),
                'rct_num_canals'         => 3,
                'rct_obturation_method'  => 'Cold Lateral Condensation',
                'rct_irrigant'           => 'NaOCl + EDTA final flush',
                'fill_material'          => 'GIC (base) + Composite (core buildup)',
                'prescription_drugs'     => json_encode([
                    ['drug' => 'Azithromycin 500mg', 'dose' => '1-0-0', 'days' => 5],
                    ['drug' => 'Aceclofenac 100mg',  'dose' => '1-0-1', 'days' => 3, 'notes' => 'After food'],
                ]),
                'clinical_notes'   => 'Obturation completed with gutta-percha. Radiograph confirms adequate fill. Core buildup done with GIC base + composite. Crown prep to follow.',
                'next_visit_plan'  => 'Crown prep and impression next visit.',
                'created_by'       => $this->doctorId,
                'created_at'       => Carbon::now()->subMonths(6),
                'updated_at'       => Carbon::now()->subMonths(6),
            ],
            // Visit 4 — Implant surgery
            [
                'patient_id'       => $patientId,
                'appointment_id'   => $apptIds[4] ?? null,
                'consultation_id'  => $consultIds[1] ?? null,
                'doctor_id'        => $this->doctorId,
                'visit_date'       => Carbon::now()->subMonths(3)->toDateString(),
                'procedure'        => 'Implant',
                'tooth_number'     => '36',
                'status'           => 'completed',
                'visit_number'     => 1,
                'treatment_name'   => 'Dental Implant #36',
                'current_stage'    => 'healing',
                'completed_stages' => json_encode(['planning', 'fixture_placement']),
                'impl_brand'          => 'Nobel Biocare',
                'impl_size'           => '4.0 x 10mm',
                'impl_torque'         => '35 Ncm',
                'impl_graft_used'     => 'Yes',
                'impl_graft_brand'    => 'Cerasorb (synthetic HA)',
                'impl_membrane'       => 'Collagen membrane placed',
                'impl_healing_collar' => '4.5mm healing abutment placed',
                'prescription_drugs'  => json_encode([
                    ['drug' => 'Azithromycin 500mg',   'dose' => '1-0-0', 'days' => 5, 'notes' => '(Allergy-safe)'],
                    ['drug' => 'Metronidazole 400mg',  'dose' => '1-1-1', 'days' => 5],
                    ['drug' => 'Aceclofenac+Para 100mg','dose' => '1-0-1', 'days' => 5, 'notes' => 'After food'],
                    ['drug' => 'Chlorhexidine 0.2% rinse','dose' => 'BD', 'days' => 7, 'notes' => 'Do not swallow'],
                ]),
                'prescription_instructions' => json_encode([
                    'Ice pack 20min on/off for 24 hours',
                    'Soft diet for 2 weeks',
                    'No smoking/tobacco for minimum 4 weeks',
                    'Return in 10 days for suture removal',
                ]),
                'clinical_notes'  => 'Flap raised, osteotomy done, implant placed with 35 Ncm torque (good primary stability). Bone graft placed buccally. Collagen membrane secured. Healing abutment seated. Sutures placed. Patient tolerated well. BP checked post-op: 130/84.',
                'next_visit_plan' => 'Suture removal at 10 days. Crown impression after 3 months osseointegration.',
                'created_by'      => $this->doctorId,
                'created_at'      => Carbon::now()->subMonths(3),
                'updated_at'      => Carbon::now()->subMonths(3),
            ],
        ];

        foreach ($visits as $v) {
            DB::table('treatment_visits')->insert($v);
        }

        $this->command->info('  🦷  4 treatment visits added (Scaling, RCT x2, Implant).');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. LAB CASES
    // ─────────────────────────────────────────────────────────────────────
    private function seedLabCases(int $patientId): void
    {
        DB::table('lab_cases')->where('patient_id', $patientId)->delete();

        $cases = [
            [
                'patient_id'           => $patientId,
                'doctor_id'            => $this->doctorId,
                'work_type'            => 'crown_bridge',
                'work_subtype'         => 'PFM',
                'tooth_number'         => '26',
                'shade'                => 'A2',
                'lab_vendor'           => 'Tulip Dental Lab',
                'lab_cost'             => 1800.00,
                'sent_date'            => Carbon::now()->subDays(7)->toDateString(),
                'expected_return_date' => Carbon::now()->addDays(5)->toDateString(),
                'received_date'        => null,
                'status'               => 'in_progress',
                'instructions'         => 'PFM crown for #26 post-RCT. Shade A2 matched to adjacent teeth. Occlusal clearance 1.5mm confirmed. Margin: shoulder. Please send by '.Carbon::now()->addDays(5)->format('d M Y').'.',
                'notes'                => 'Rush case — patient appointment booked for crown delivery in 10 days.',
                'case_number'          => 'LAB-' . now()->format('Y') . '-0041',
                'created_at'           => Carbon::now()->subDays(7),
                'updated_at'           => Carbon::now()->subDays(7),
            ],
            [
                'patient_id'           => $patientId,
                'doctor_id'            => $this->doctorId,
                'work_type'            => 'implant',
                'work_subtype'         => 'Implant Crown',
                'tooth_number'         => '36',
                'shade'                => 'A3',
                'lab_vendor'           => 'Precision Dental Lab, Pune',
                'lab_cost'             => 5500.00,
                'sent_date'            => Carbon::now()->subDays(30)->toDateString(),
                'expected_return_date' => Carbon::now()->subDays(2)->toDateString(),
                'received_date'        => Carbon::now()->subDays(2)->toDateString(),
                'status'               => 'received',
                'instructions'         => 'Zirconia implant crown on Nobel Biocare platform (4.0mm). Shade A3. Screw-retained preferred. Verify occlusal contacts.',
                'notes'                => 'Crown received and checked. Ready for delivery at next appointment.',
                'case_number'          => 'LAB-' . now()->format('Y') . '-0035',
                'created_at'           => Carbon::now()->subDays(30),
                'updated_at'           => Carbon::now()->subDays(2),
            ],
        ];

        foreach ($cases as $c) {
            DB::table('lab_cases')->insert($c);
        }

        $this->command->info('  🔬  2 lab cases added (PFM Crown in progress, Implant Crown received).');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. FINANCE — INVOICES & INCOME ENTRIES
    // ─────────────────────────────────────────────────────────────────────
    private function seedFinance(int $patientId): void
    {
        // Remove old finance entries for this patient
        DB::table('finance_income_entries')->where('patient_id', $patientId)->delete();
        DB::table('finance_transactions')
            ->where('notes', 'like', '%Sumit Firke%')
            ->delete();

        $invoices = [
            // Invoice 1 — Scaling (paid in full)
            [
                'date'     => Carbon::now()->subMonths(6)->toDateString(),
                'source'   => 'treatment_payment',
                'category' => 'scaling',
                'gross'    => 1500.00,
                'discount' => 0.00,
                'net'      => 1500.00,
                'advance'  => 0.00,
                'outstanding' => 0.00,
                'splits'   => [['mode' => 'upi', 'amount' => 1500]],
                'status'   => 'active',
                'doctor'   => 'Dr. Sumit Firke',
                'notes'    => 'Full mouth scaling & polishing',
            ],
            // Invoice 2 — RCT #26 (partial — deposit)
            [
                'date'     => Carbon::now()->subMonths(7)->toDateString(),
                'source'   => 'treatment_payment',
                'category' => 'rct',
                'gross'    => 8000.00,
                'discount' => 500.00,
                'net'      => 7500.00,
                'advance'  => 5000.00,
                'outstanding' => 2500.00,
                'splits'   => [['mode' => 'cash', 'amount' => 2000], ['mode' => 'upi', 'amount' => 3000]],
                'status'   => 'active',
                'doctor'   => 'Dr. Sumit Firke',
                'notes'    => 'RCT #26 — advance paid. Balance ₹2,500 due on crown delivery.',
            ],
            // Invoice 3 — RCT balance + Crown (paid in full)
            [
                'date'     => Carbon::now()->subMonths(6)->toDateString(),
                'source'   => 'treatment_payment',
                'category' => 'crown',
                'gross'    => 6500.00,
                'discount' => 0.00,
                'net'      => 6500.00,
                'advance'  => 2500.00,
                'outstanding' => 0.00,
                'splits'   => [['mode' => 'upi', 'amount' => 4000]],
                'status'   => 'active',
                'doctor'   => 'Dr. Sumit Firke',
                'notes'    => 'RCT #26 balance (₹2,500) + PFM Crown (₹4,000). Fully paid.',
            ],
            // Invoice 4 — Implant (partial — balance pending)
            [
                'date'     => Carbon::now()->subMonths(3)->toDateString(),
                'source'   => 'treatment_payment',
                'category' => 'implant',
                'gross'    => 45000.00,
                'discount' => 3500.00,
                'net'      => 41500.00,
                'advance'  => 36000.00,
                'outstanding' => 5500.00,
                'splits'   => [
                    ['mode' => 'cash', 'amount' => 10000],
                    ['mode' => 'upi',  'amount' => 20000],
                    ['mode' => 'card', 'amount' => 6000],
                ],
                'status'   => 'active',
                'doctor'   => 'Dr. Sumit Firke',
                'notes'    => 'Implant #36 — Nobel Biocare + Zirconia crown. ₹5,500 balance pending on crown delivery.',
            ],
        ];

        foreach ($invoices as $inv) {
            // Master transaction ledger
            $txId = DB::table('finance_transactions')->insertGetId([
                'clinic_id'        => 1,
                'type'             => 'income',
                'direction'        => 'credit',
                'amount'           => $inv['net'],
                'net_amount'       => $inv['net'],
                'payment_mode'     => $inv['splits'][0]['mode'] ?? 'cash',
                'status'           => 'active',
                'patient_id'       => $patientId,
                'transaction_date' => $inv['date'],
                'notes'            => "Sumit Firke | {$inv['category']} | {$inv['notes']}",
                'created_by'       => $this->doctorId,
                'created_at'       => $inv['date'],
                'updated_at'       => $inv['date'],
            ]);

            // Income entry
            DB::table('finance_income_entries')->insert([
                'clinic_id'        => 1,
                'transaction_id'   => $txId,
                'source'           => $inv['source'],
                'patient_id'       => $patientId,
                'category'         => $inv['category'],
                'gross_amount'     => $inv['gross'],
                'discount'         => $inv['discount'],
                'net_amount'       => $inv['net'],
                'advance_adjusted' => $inv['advance'],
                'outstanding'      => $inv['outstanding'],
                'payment_splits'   => json_encode($inv['splits']),
                'income_date'      => $inv['date'],
                'doctor_name'      => $inv['doctor'],
                'notes'            => $inv['notes'],
                'status'           => $inv['status'],
                'created_by'       => $this->doctorId,
                'created_at'       => $inv['date'],
                'updated_at'       => $inv['date'],
            ]);
        }

        $this->command->info('  💰  4 finance invoices added (total billed ₹57,000 | outstanding ₹5,500).');
    }
}
