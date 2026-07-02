<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * TreatmentPlanDemoSeeder
 * ─────────────────────────────────────────────────────────────────────────
 * Seeds example treatment options against real patients + consultations
 * already in the database. Does NOT require a specific patient.
 *
 * Strategy:
 *   1. Picks the first patient who has at least 2 consultations
 *      → attaches 2 options to consultation 1, 3 options to consultation 2
 *   2. If only 1 consultation exists for that patient
 *      → attaches 3 options to that single consultation
 *   3. If no consultations exist anywhere
 *      → attaches options directly to any existing patient (no consultation link)
 *
 * Run:   php artisan db:seed --class=TreatmentPlanDemoSeeder
 * Safe to re-run: removes previously seeded plans for the chosen patient first.
 * ─────────────────────────────────────────────────────────────────────────
 */
class TreatmentPlanDemoSeeder extends Seeder
{
    private int $doctorId = 1;

    public function run(): void
    {
        $this->command->info('🦷 Seeding demo treatment plans…');

        // ── 1. Pick a patient who has consultations ───────────────────────
        $patientWithConsults = DB::table('consultations')
            ->select('patient_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('patient_id')
            ->orderByDesc('cnt')
            ->first();

        if ($patientWithConsults) {
            $patientId = $patientWithConsults->patient_id;
        } else {
            // Fallback: just use any existing patient
            $anyPatient = DB::table('patients')->orderBy('id')->first();
            if (!$anyPatient) {
                $this->command->error('No patients found in the database. Please create a patient first.');
                return;
            }
            $patientId = $anyPatient->id;
        }

        $patient = DB::table('patients')->find($patientId);
        $this->command->info("  👤  Using patient: {$patient->name} (ID: {$patientId})");

        // ── 2. Load their consultations ───────────────────────────────────
        $consults = DB::table('consultations')
            ->where('patient_id', $patientId)
            ->orderBy('consultation_date')
            ->get();

        $c1Id = $consults->get(0)?->id ?? null;
        $c2Id = $consults->get(1)?->id ?? null;

        // ── 3. Clear existing plans for this patient ──────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $existingIds = DB::table('treatment_plans')
            ->where('patient_id', $patientId)
            ->pluck('id');
        DB::table('treatment_plan_items')->whereIn('treatment_plan_id', $existingIds)->delete();
        DB::table('treatment_plans')->where('patient_id', $patientId)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ═══════════════════════════════════════════════════════════════════
        // GROUP 1 — Pulpitis / Save Tooth scenario
        // Linked to consultation 1 (or no consultation if none exists)
        // ═══════════════════════════════════════════════════════════════════

        // Option 1A: RCT + Zirconia Crown  (ACCEPTED)
        $p1a = $this->insertPlan([
            'patient_id'         => $patientId,
            'consultation_id'    => $c1Id,
            'plan_name'          => 'RCT with Zirconia Crown',
            'display_order'      => 1,
            'status'             => 'completed',
            'accepted_at'        => Carbon::now()->subMonths(2)->toDateTimeString(),
            'estimated_duration' => '3–4 Weeks',
            'visit_count'        => 3,
            'doctor_notes'       => 'Best option to save the natural tooth. Completed successfully.',
            'created_at'         => Carbon::now()->subMonths(3),
        ]);
        $this->insertItems($p1a, [
            ['treatment_name' => 'Root Canal Treatment (Posterior)', 'unit_price' => 8000],
            ['treatment_name' => 'Scaling & Polishing (Full Mouth)', 'unit_price' => 1500],
            ['treatment_name' => 'Zirconia Crown',                   'unit_price' => 8500],
        ]);
        $this->updateTotal($p1a);

        // Option 1B: Extraction + Implant
        $p1b = $this->insertPlan([
            'patient_id'         => $patientId,
            'consultation_id'    => $c1Id,
            'plan_name'          => 'Extraction + Implant',
            'display_order'      => 2,
            'status'             => 'cancelled',
            'accepted_at'        => null,
            'estimated_duration' => '4–6 Months',
            'visit_count'        => 5,
            'doctor_notes'       => 'Alternative if tooth not restorable. Involves extraction and delayed implant.',
            'created_at'         => Carbon::now()->subMonths(3),
        ]);
        $this->insertItems($p1b, [
            ['treatment_name' => 'Extraction',                        'unit_price' => 1500],
            ['treatment_name' => 'Socket Grafting',                   'unit_price' => 5000],
            ['treatment_name' => 'Dental Implant Fixture (4.0×10mm)', 'unit_price' => 25000],
            ['treatment_name' => 'Healing Abutment',                  'unit_price' => 3000],
            ['treatment_name' => 'Zirconia Implant Crown',            'unit_price' => 17000],
        ]);
        $this->updateTotal($p1b);

        $this->command->info("  ✅  Group 1 (Save Tooth) — 2 options added" . ($c1Id ? " (Consultation #{$c1Id})" : " (no consultation)"));

        // ═══════════════════════════════════════════════════════════════════
        // GROUP 2 — Missing Tooth / Replacement scenario
        // Linked to consultation 2 (or same as c1 if only 1 exists)
        // ═══════════════════════════════════════════════════════════════════

        $c2Id = $c2Id ?? $c1Id; // fall back to same consultation if only one

        // Option 2A: Dental Implant  (ACCEPTED)
        $p2a = $this->insertPlan([
            'patient_id'         => $patientId,
            'consultation_id'    => $c2Id,
            'plan_name'          => 'Dental Implant',
            'display_order'      => 1,
            'status'             => 'ongoing',
            'accepted_at'        => Carbon::now()->subMonths(1)->toDateTimeString(),
            'estimated_duration' => '3–4 Months',
            'visit_count'        => 5,
            'doctor_notes'       => 'Patient accepted. Implant placed. Crown delivery pending.',
            'created_at'         => Carbon::now()->subMonths(2),
        ]);
        $this->insertItems($p2a, [
            ['treatment_name' => 'Implant Fixture Placement', 'unit_price' => 25000],
            ['treatment_name' => 'Healing Abutment',          'unit_price' => 3000],
            ['treatment_name' => 'Bone Graft',                'unit_price' => 5000],
            ['treatment_name' => 'Collagen Membrane',         'unit_price' => 3000],
            ['treatment_name' => 'Zirconia Implant Crown',    'unit_price' => 17000],
        ]);
        $this->updateTotal($p2a);

        // Option 2B: Fixed Zirconia Bridge
        $p2b = $this->insertPlan([
            'patient_id'         => $patientId,
            'consultation_id'    => $c2Id,
            'plan_name'          => 'Fixed Zirconia Bridge',
            'display_order'      => 2,
            'status'             => 'pending',
            'accepted_at'        => null,
            'estimated_duration' => '2–3 Weeks',
            'visit_count'        => 3,
            'doctor_notes'       => 'Fixed bridge — requires preparation of adjacent teeth as abutments.',
            'created_at'         => Carbon::now()->subMonths(2),
        ]);
        $this->insertItems($p2b, [
            ['treatment_name' => 'Tooth Preparation (Abutment ×2)',  'unit_price' => 8000],
            ['treatment_name' => '3-Unit Zirconia Bridge',           'unit_price' => 28000],
        ]);
        $this->updateTotal($p2b);

        // Option 2C: Removable Partial Denture
        $p2c = $this->insertPlan([
            'patient_id'         => $patientId,
            'consultation_id'    => $c2Id,
            'plan_name'          => 'Removable Partial Denture',
            'display_order'      => 3,
            'status'             => 'pending',
            'accepted_at'        => null,
            'estimated_duration' => '1–2 Weeks',
            'visit_count'        => 2,
            'doctor_notes'       => 'Most economical option. Removable — less preferred for a single posterior tooth.',
            'created_at'         => Carbon::now()->subMonths(2),
        ]);
        $this->insertItems($p2c, [
            ['treatment_name' => 'Impressions (Primary + Secondary)', 'unit_price' => 1500],
            ['treatment_name' => 'Acrylic RPD Fabrication',           'unit_price' => 7000],
            ['treatment_name' => 'Delivery & Adjustments',            'unit_price' => 500],
        ]);
        $this->updateTotal($p2c);

        $this->command->info("  ✅  Group 2 (Missing Tooth) — 3 options added" . ($c2Id ? " (Consultation #{$c2Id})" : " (no consultation)"));

        $this->command->newLine();
        $this->command->info('✅ Done! Open the Treatment Plans tab for patient: ' . $patient->name);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertPlan(array $data): int
    {
        return DB::table('treatment_plans')->insertGetId(array_merge([
            'plan_uuid'        => (string) Str::uuid(),
            'plan_type'        => 'best',
            'total'            => 0,
            'overall_disc_pct' => 0,
            'created_by'       => $this->doctorId,
            'updated_at'       => now(),
        ], $data));
    }

    private function insertItems(int $planId, array $items): void
    {
        foreach ($items as $idx => $item) {
            $price = (float) $item['unit_price'];
            DB::table('treatment_plan_items')->insert([
                'treatment_plan_id' => $planId,
                'treatment_name'    => $item['treatment_name'],
                'unit_price'        => $price,
                'units'             => 1,
                'disc_pct'          => 0,
                'disc_amount'       => 0,
                'net_amount'        => $price,
                'gst_pct'           => 0,
                'gst_amount'        => 0,
                'total'             => $price,
                'option_rank'       => 'best',
                'status'            => 'pending',
                'sort_order'        => $idx,
                'aocp_applied'      => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    private function updateTotal(int $planId): void
    {
        $total = DB::table('treatment_plan_items')
            ->where('treatment_plan_id', $planId)
            ->sum('total');

        DB::table('treatment_plans')
            ->where('id', $planId)
            ->update(['total' => $total, 'updated_at' => now()]);
    }
}
