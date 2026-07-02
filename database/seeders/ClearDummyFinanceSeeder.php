<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ClearDummyFinanceSeeder
 * ─────────────────────────────────────────────────────────────────────────
 * Wipes ALL dummy/demo finance & vendor data so you can start fresh.
 * KEEPS:
 *   • All patient records
 *   • Finance entries for patient ID 3448 (Sumit Firke — real data)
 *   • finance_settings  (your clinic config)
 *   • finance_expense_categories  (system categories — needed by the UI)
 *
 * REMOVES:
 *   • finance_transactions       (all except Sumit Firke's)
 *   • finance_income_entries     (all except Sumit Firke's)
 *   • finance_expenses           (all dummy)
 *   • finance_vendor_payments    (all dummy)
 *   • finance_vendors            (all dummy)
 *   • finance_payroll            (all dummy)
 *   • finance_cashbook           (all dummy)
 *   • finance_bank_transactions  (all dummy)
 *   • finance_gst_records        (all dummy)
 *   • finance_staff_advances     (all dummy)
 *   • lab_cases                  (all except Sumit Firke's)
 *
 * Run: php artisan db:seed --class=ClearDummyFinanceSeeder
 * ─────────────────────────────────────────────────────────────────────────
 */
class ClearDummyFinanceSeeder extends Seeder
{
    // Keep Sumit Firke's real data (patient ID inserted by SumitFirkePatientSeeder)
    private int $keepPatientId = 3448;

    public function run(): void
    {
        $this->command->info('🗑  Clearing dummy finance & vendor data…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── Finance Transactions (keep Sumit Firke's) ────────────────────
        $kept = DB::table('finance_transactions')
            ->where('patient_id', $this->keepPatientId)
            ->pluck('id');

        DB::table('finance_transactions')
            ->whereNotIn('id', $kept)
            ->delete();
        $this->command->info('  ✅  finance_transactions cleared (kept ' . count($kept) . ' for Sumit Firke).');

        // ── Finance Income Entries (keep Sumit Firke's) ───────────────────
        $keptIncome = DB::table('finance_income_entries')
            ->where('patient_id', $this->keepPatientId)
            ->pluck('id');

        DB::table('finance_income_entries')
            ->whereNotIn('id', $keptIncome)
            ->delete();
        $this->command->info('  ✅  finance_income_entries cleared (kept ' . count($keptIncome) . ' for Sumit Firke).');

        // ── Finance Expenses ──────────────────────────────────────────────
        $this->clearTable('finance_expenses');

        // ── Vendor Payments ───────────────────────────────────────────────
        $this->clearTable('finance_vendor_payments');

        // ── Vendors ───────────────────────────────────────────────────────
        $this->clearTable('finance_vendors');

        // ── Payroll ───────────────────────────────────────────────────────
        $this->clearTable('finance_payroll');

        // ── Cashbook ──────────────────────────────────────────────────────
        $this->clearTable('finance_cashbook');

        // ── Bank Transactions ─────────────────────────────────────────────
        if ($this->tableExists('finance_bank_transactions')) {
            $this->clearTable('finance_bank_transactions');
        }

        // ── GST Records ───────────────────────────────────────────────────
        $this->clearTable('finance_gst_records');

        // ── Staff Advances ────────────────────────────────────────────────
        $this->clearTable('finance_staff_advances');

        // ── Lab Cases (keep Sumit Firke's) ────────────────────────────────
        $keptLab = DB::table('lab_cases')
            ->where('patient_id', $this->keepPatientId)
            ->pluck('id');

        DB::table('lab_cases')
            ->whereNotIn('id', $keptLab)
            ->delete();
        $this->command->info('  ✅  lab_cases cleared (kept ' . count($keptLab) . ' for Sumit Firke).');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('');
        $this->command->info('✅  Done! Finance module is clean and ready for real data.');
        $this->command->info('   Patients, settings, and expense categories are untouched.');
    }

    private function clearTable(string $table): void
    {
        if (!$this->tableExists($table)) {
            $this->command->warn("  ⚠  Table {$table} not found — skipped.");
            return;
        }

        $count = DB::table($table)->count();
        DB::table($table)->delete();
        $this->command->info("  ✅  {$table} cleared ({$count} rows removed).");
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
