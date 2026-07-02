<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RemoveDummyDataSeeder
 * ─────────────────────────────────────────────────────────────────────────
 * Removes ALL seeded demo/dummy data.
 * PRESERVES: real patients you added manually + all master/config data.
 *
 * Dummy patients identified by phone (from MasterDemoSeeder + SumitFirkePatientSeeder):
 *   9876543210, 9123456789, 8765432109, 7654321098, 6543210987,
 *   9988776655, 9871234560, 9765432108, 8877665544, 9654321087, 9000000001
 *
 * Run: php artisan db:seed --class=RemoveDummyDataSeeder
 */
class RemoveDummyDataSeeder extends Seeder
{
    /** Known dummy patient phone numbers from all seeders */
    private array $dummyPhones = [
        '9876543210', // Priya Sharma       (MasterDemoSeeder)
        '9123456789', // Rahul Mehta        (MasterDemoSeeder)
        '8765432109', // Anita Desai        (MasterDemoSeeder)
        '7654321098', // Suresh Kumar       (MasterDemoSeeder)
        '6543210987', // Meera Patel        (MasterDemoSeeder)
        '9988776655', // Vijay Singh        (MasterDemoSeeder)
        '9871234560', // Kavita Rao         (MasterDemoSeeder)
        '9765432108', // Arun Joshi         (MasterDemoSeeder)
        '8877665544', // Sneha Kulkarni     (MasterDemoSeeder)
        '9654321087', // Deepak Nair        (MasterDemoSeeder)
        '9000000001', // Sumit Firke        (SumitFirkePatientSeeder)
    ];

    public function run(): void
    {
        $this->command->info('🗑  RemoveDummyDataSeeder starting…');
        $this->command->info('   Your real patients will be preserved.');
        $this->command->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── 1. Find dummy patient IDs ──────────────────────────────────
        $dummyIds = DB::table('patients')
            ->whereIn('phone', $this->dummyPhones)
            ->pluck('id')
            ->toArray();

        if (empty($dummyIds)) {
            $this->command->warn('   No dummy patients found — already clean or never seeded.');
        } else {
            $this->command->info("   Found " . count($dummyIds) . " dummy patients to remove.");
            $this->removeDummyPatientData($dummyIds);
        }

        // ── 2. Clear all non-patient dummy/transactional data ──────────
        $this->clearOtherDummyData();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── 3. Report real patients preserved ─────────────────────────
        $realCount = DB::table('patients')->count();
        $this->command->newLine();
        $this->command->info("✅  Done! {$realCount} real patient(s) preserved.");
        $this->command->info('   All dummy data removed. Master data untouched.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete everything linked to dummy patient IDs
    // ──────────────────────────────────────────────────────────────────
    private function removeDummyPatientData(array $ids): void
    {
        $tables = [
            // prescriptions
            'prescription_audit_logs' => 'prescription_id',
            'prescription_overrides'  => 'prescription_id',
            'prescription_items'      => 'prescription_id',
            'prescriptions'           => 'patient_id',

            // clinical
            'clinical_findings'       => 'consultation_id',
            'clinical_media'          => 'consultation_id',
            'consultation_coha_reports'       => 'consultation_id',
            'consultation_photographs'        => 'consultation_id',
            'consultation_scans'              => 'consultation_id',
            'consultation_specialty_modules'  => 'consultation_id',
            'diagnoses'               => 'consultation_id',
            'investigations'          => 'consultation_id',
            'medicines'               => 'consultation_id',
            'medical_conditions'      => 'patient_id',
            'complaints'              => 'consultation_id',

            // treatment
            'treatment_visit_items'   => 'visit_id',
            'treatment_visits'        => 'patient_id',
            'treatment_plan_items'    => 'plan_id',
            'treatment_plans'         => 'patient_id',
            'treatment_opportunities' => 'patient_id',
            'treatment_media'         => 'patient_id',
            'consultations'           => 'patient_id',
            'appointments'            => 'patient_id',

            // patient profile
            'follow_up_notes'         => 'patient_id',
            'follow_ups'              => 'patient_id',
            'escalations'             => 'patient_id',
            'tasks'                   => 'patient_id',
            'patient_alerts'          => 'patient_id',
            'patient_notes'           => 'patient_id',
            'patient_documents'       => 'patient_id',
            'patient_links'           => 'patient_id',
            'patient_relationship_notes' => 'patient_id',
            'patient_communications'  => 'patient_id',
            'patient_tag'             => 'patient_id',

            // finance / billing
            'finance_patient_memberships' => 'patient_id',
            'invoice_items'           => 'invoice_id',
            'invoice_payments'        => 'invoice_id',
            'invoices'                => 'patient_id',
            'receipts'                => 'patient_id',
            'final_bills'             => 'patient_id',
            'emi_schedules'           => 'patient_id',
            'finance_income_entries'  => 'patient_id',
            'finance_transactions'    => 'patient_id',
            'wallets'                 => 'patient_id',
            'wallet_transactions'     => 'wallet_id',

            // lab
            'lab_case_attachments'    => 'lab_case_id',
            'lab_case_events'         => 'lab_case_id',
            'lab_case_items'          => 'lab_case_id',
            'lab_cases'               => 'patient_id',

            // comm queue linked to patient
            'communication_queue'     => 'patient_id',
        ];

        $schema = DB::getSchemaBuilder();

        foreach ($tables as $table => $fk) {
            if (!$schema->hasTable($table)) continue;

            // For tables that reference a parent table (consultations, invoices etc.)
            // we need to resolve the FK properly
            $deleteIds = $ids;

            // Special: consultation-linked tables need consultation IDs
            $consultLinked = [
                'clinical_findings','clinical_media','consultation_coha_reports',
                'consultation_photographs','consultation_scans',
                'consultation_specialty_modules','diagnoses','investigations',
                'medicines','complaints',
            ];
            if (in_array($table, $consultLinked)) {
                $deleteIds = DB::table('consultations')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: treatment_visit_items needs visit IDs
            if ($table === 'treatment_visit_items') {
                $deleteIds = DB::table('treatment_visits')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: treatment_plan_items needs plan IDs
            if ($table === 'treatment_plan_items') {
                $deleteIds = DB::table('treatment_plans')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: prescription sub-tables need prescription IDs
            if (in_array($table, ['prescription_audit_logs','prescription_overrides','prescription_items'])) {
                $deleteIds = DB::table('prescriptions')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: invoice sub-tables need invoice IDs
            if (in_array($table, ['invoice_items','invoice_payments'])) {
                $deleteIds = DB::table('invoices')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: wallet_transactions needs wallet IDs
            if ($table === 'wallet_transactions') {
                $deleteIds = DB::table('wallets')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            // Special: lab sub-tables need lab_case IDs
            if (in_array($table, ['lab_case_attachments','lab_case_events','lab_case_items'])) {
                $deleteIds = DB::table('lab_cases')
                    ->whereIn('patient_id', $ids)
                    ->pluck('id')
                    ->toArray();
                if (empty($deleteIds)) continue;
            }

            $count = DB::table($table)->whereIn($fk, $deleteIds)->count();
            if ($count > 0) {
                DB::table($table)->whereIn($fk, $deleteIds)->delete();
                $this->command->info("  ✅  {$table} — {$count} rows removed.");
            }
        }

        // Finally delete the dummy patients themselves
        $count = DB::table('patients')->whereIn('id', $ids)->count();
        DB::table('patients')->whereIn('id', $ids)->delete();
        $this->command->info("  ✅  patients — {$count} dummy rows removed.");

        // Dummy doctor users (Dr. Priya Mehta, Dr. Arjun Sharma — not real users)
        $dummyDoctorEmails = ['priya@tulipdental.in', 'arjun@tulipdental.in'];
        $doctorCount = DB::table('users')->whereIn('email', $dummyDoctorEmails)->count();
        if ($doctorCount > 0) {
            DB::table('users')->whereIn('email', $dummyDoctorEmails)->delete();
            $this->command->info("  ✅  users — {$doctorCount} dummy doctor accounts removed.");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Clear non-patient dummy transactional data (all rows — no real data here)
    // ──────────────────────────────────────────────────────────────────
    private function clearOtherDummyData(): void
    {
        $tables = [
            // Leads & comms (all dummy from LeadSeeder / CommunicationQueueSeeder)
            'lead_activities',
            'leads',
            'comm_activity_logs',

            // Inventory demo stock (InventoryDemoSeeder)
            'stock_movements',
            'grn_items',
            'goods_receipt_notes',
            'vendor_invoice_items',
            'vendor_invoices',
            'purchase_order_items',
            'purchase_orders',
            'inventory_stocks',
            'inventory_variants',
            'inventory_items',
            'inventory_vendors',
            'implant_placements',

            // Huddle boards (HuddleSeeder)
            'huddle_task_logs',
            'huddle_comments',
            'huddle_notes',
            'huddle_cards',
            'huddle_boards',

            // Finance misc
            'finance_vouchers',
            'finance_vendor_payments',
            'finance_expenses',
            'finance_payroll',
            'finance_cashbook',
            'finance_bank_transactions',
            'finance_gst_records',
            'finance_staff_advances',
            'finance_vendors',
            'finance_audit_log',
            'billing_audit_logs',
            'billing_prompts',
            'coupon_usage',
            'wallet_campaigns',

            // Lab vendors (demo data)
            'lab_reconciliation_events',
            'lab_reconciliation_items',
            'lab_monthly_reconciliations',
            'lab_vendor_contacts',
            'lab_vendor_services',
            'lab_vendors',
        ];

        $schema = DB::getSchemaBuilder();
        foreach ($tables as $table) {
            if (!$schema->hasTable($table)) continue;
            $count = DB::table($table)->count();
            if ($count > 0) {
                DB::table($table)->delete();
                $this->command->info("  ✅  {$table} — {$count} rows removed.");
            } else {
                $this->command->line("  –  {$table} — already empty.");
            }
        }

        // communication_queue rows NOT linked to any patient (orphaned dummy rows)
        if ($schema->hasTable('communication_queue')) {
            $orphaned = DB::table('communication_queue')
                ->whereNull('patient_id')
                ->orWhereNotIn('patient_id', DB::table('patients')->pluck('id'))
                ->count();
            if ($orphaned > 0) {
                DB::table('communication_queue')
                    ->whereNull('patient_id')
                    ->orWhereNotIn('patient_id', DB::table('patients')->pluck('id'))
                    ->delete();
                $this->command->info("  ✅  communication_queue — {$orphaned} orphaned rows removed.");
            }
        }
    }
}
