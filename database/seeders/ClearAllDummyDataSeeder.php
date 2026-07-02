<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ClearAllDummyDataSeeder
 * ─────────────────────────────────────────────────────────────────────────
 * Wipes ALL transactional / demo data from every module.
 *
 * KEEPS (master data, config, system):
 *   users, roles, modules, role_module_permissions
 *   branches, app_settings, tags, patient_sources
 *   finance_settings, finance_expense_categories, finance_bank_accounts
 *   finance_membership_plans, emi_providers, emi_schemes
 *   treatment_types, treatment_categories, treatments
 *   dental_conditions, diagnosis_masters, investigation_masters
 *   rx_* tables (all drug master data)
 *   inventory_categories, inventory_sub_types, inventory_locations
 *   inventory_settings, implant_catalog
 *   message_templates, reusable_assets, huddle_settings
 *   education_categories, cms_edu_categories, cms_tags
 *   product_dealers, coupon_codes (definitions only)
 *
 * CLEARS (all transactional / demo rows):
 *   patients and everything patient-linked
 *   appointments, consultations, clinical data
 *   prescriptions, billing, finance, inventory, lab, leads, comms
 *
 * Run: php artisan db:seed --class=ClearAllDummyDataSeeder
 * ─────────────────────────────────────────────────────────────────────────
 */
class ClearAllDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🗑  ClearAllDummyDataSeeder — wiping all transactional/demo data…');
        $this->command->info('   Master data, config and system tables will be untouched.');
        $this->command->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ══════════════════════════════════════════════════════════════════
        // FINANCE
        // ══════════════════════════════════════════════════════════════════
        $this->clear('finance_vouchers');
        $this->clear('finance_vendor_payments');
        $this->clear('finance_transactions');
        $this->clear('finance_income_entries');
        $this->clear('finance_expenses');
        $this->clear('finance_payroll');
        $this->clear('finance_cashbook');
        $this->clear('finance_bank_transactions');
        $this->clear('finance_gst_records');
        $this->clear('finance_staff_advances');
        $this->clear('finance_vendors');
        $this->clear('finance_audit_log');
        $this->clear('finance_patient_memberships');
        // KEEP: finance_settings, finance_expense_categories,
        //       finance_bank_accounts, finance_membership_plans

        // ══════════════════════════════════════════════════════════════════
        // BILLING / INVOICES
        // ══════════════════════════════════════════════════════════════════
        $this->clear('billing_audit_logs');
        $this->clear('billing_prompts');
        $this->clear('emi_schedules');          // actual payment schedules, not the config
        $this->clear('invoice_items');
        $this->clear('invoice_payments');
        $this->clear('invoices');
        $this->clear('receipts');
        $this->clear('final_bills');
        // KEEP: emi_providers, emi_schemes (config)

        // ══════════════════════════════════════════════════════════════════
        // PATIENTS & CLINICAL
        // ══════════════════════════════════════════════════════════════════
        $this->clear('prescription_audit_logs');
        $this->clear('prescription_overrides');
        $this->clear('prescription_items');
        $this->clear('prescriptions');
        $this->clear('clinical_findings');
        $this->clear('clinical_media');
        $this->clear('consultation_coha_reports');
        $this->clear('consultation_photographs');
        $this->clear('consultation_scans');
        $this->clear('consultation_specialty_modules');
        $this->clear('diagnoses');
        $this->clear('investigations');
        $this->clear('medicines');
        $this->clear('medical_conditions');
        $this->clear('complaints');
        $this->clear('treatment_visit_items');
        $this->clear('treatment_visits');
        $this->clear('treatment_plan_items');
        $this->clear('treatment_plans');
        $this->clear('treatment_opportunities');
        $this->clear('treatment_media');
        $this->clear('consultations');
        $this->clear('appointments');
        $this->clear('follow_up_notes');
        $this->clear('follow_ups');
        $this->clear('escalations');
        $this->clear('tasks');
        $this->clear('patient_alerts');
        $this->clear('patient_notes');
        $this->clear('patient_documents');
        $this->clear('patient_links');
        $this->clear('patient_relationship_notes');
        $this->clear('patient_tag');
        $this->clear('patient_communications');
        $this->clear('patients');
        // KEEP: patient_sources (master), dental_conditions, diagnosis_masters
        //       investigation_masters, treatment_types, treatment_categories
        //       treatments, treatment_knowledge, treatment_rules, treatment_sops

        // ══════════════════════════════════════════════════════════════════
        // LAB
        // ══════════════════════════════════════════════════════════════════
        $this->clear('lab_reconciliation_events');
        $this->clear('lab_reconciliation_items');
        $this->clear('lab_monthly_reconciliations');
        $this->clear('lab_case_attachments');
        $this->clear('lab_case_events');
        $this->clear('lab_case_items');
        $this->clear('lab_cases');
        $this->clear('lab_vendor_contacts');
        $this->clear('lab_vendor_services');
        $this->clear('lab_vendors');

        // ══════════════════════════════════════════════════════════════════
        // INVENTORY / PROCUREMENT
        // ══════════════════════════════════════════════════════════════════
        $this->clear('stock_movements');
        $this->clear('grn_items');
        $this->clear('goods_receipt_notes');
        $this->clear('vendor_invoice_items');
        $this->clear('vendor_invoices');
        $this->clear('purchase_order_items');
        $this->clear('purchase_orders');
        $this->clear('inventory_stocks');
        $this->clear('inventory_variants');
        $this->clear('inventory_items');
        $this->clear('inventory_vendors');
        $this->clear('implant_placements');
        // KEEP: inventory_categories, inventory_sub_types, inventory_locations
        //       inventory_settings, implant_catalog, product_dealers

        // ══════════════════════════════════════════════════════════════════
        // LEADS & COMMUNICATIONS
        // ══════════════════════════════════════════════════════════════════
        $this->clear('lead_activities');
        $this->clear('leads');
        $this->clear('communication_queue');
        // KEEP: message_templates

        // ══════════════════════════════════════════════════════════════════
        // WALLET & COUPONS
        // ══════════════════════════════════════════════════════════════════
        $this->clear('wallet_transactions');
        $this->clear('wallets');
        $this->clear('coupon_usage');
        $this->clear('wallet_campaigns');
        // KEEP: coupon_codes (the definitions, not usage logs)

        // ══════════════════════════════════════════════════════════════════
        // HUDDLE
        // ══════════════════════════════════════════════════════════════════
        $this->clear('huddle_task_logs');
        $this->clear('huddle_comments');
        $this->clear('huddle_notes');
        $this->clear('huddle_cards');
        $this->clear('huddle_boards');
        // KEEP: huddle_settings

        // ══════════════════════════════════════════════════════════════════
        // CMS / EDUCATION (demo content only — keep categories)
        // ══════════════════════════════════════════════════════════════════
        $this->clear('education_media');
        $this->clear('education_treatments');
        $this->clear('cms_treatment_cases');
        $this->clear('cms_media');
        $this->clear('cms_edu_items');
        // KEEP: education_categories, cms_edu_categories, cms_tags

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->newLine();
        $this->command->info('✅  Done! All dummy/demo data cleared.');
        $this->command->info('   Master data, config, and system tables are untouched.');
        $this->command->newLine();
        $this->command->warn('   NEXT STEPS:');
        $this->command->warn('   • Your admin user account is intact — log in normally.');
        $this->command->warn('   • Finance bank accounts, expense categories, membership plans preserved.');
        $this->command->warn('   • All rx/drug master data, treatment types, and inventory categories preserved.');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function clear(string $table): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            $this->command->warn("  ⚠  {$table} — table not found, skipped.");
            return;
        }

        $count = DB::table($table)->count();

        if ($count === 0) {
            $this->command->line("  –  {$table} — already empty.");
            return;
        }

        DB::table($table)->delete();
        $this->command->info("  ✅  {$table} — {$count} rows removed.");
    }
}
