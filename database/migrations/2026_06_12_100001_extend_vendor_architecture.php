<?php
// =============================================================================
// Phase 1 — Vendor Architecture
//
// 1. Add finance_vendor_id to inventory_vendors  → enables auto-sync to Finance
// 2. Expand finance_vendors.vendor_type enum     → covers all Finance-only expense
//    categories: rent, electricity, water, internet, salary, lawyer, amc,
//    office_supplies, miscellaneous
//    (existing: lab, implant_company, dental_supplier, marketing_agency,
//     software_vendor, consultant, ca, utility_provider, equipment_supplier, other)
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. inventory_vendors: add finance_vendor_id for sync link ──────────
        Schema::table('inventory_vendors', function (Blueprint $table) {
            $table->foreignId('finance_vendor_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('finance_vendors')
                  ->nullOnDelete()
                  ->comment('Auto-sync: when set, this inventory vendor is mirrored in Finance');
        });

        // ── 2. finance_vendors: expand vendor_type to include Finance-only types ─
        // MySQL requires re-declaring the full enum list when modifying
        DB::statement("ALTER TABLE finance_vendors MODIFY COLUMN vendor_type ENUM(
            'lab',
            'implant_company',
            'dental_supplier',
            'marketing_agency',
            'software_vendor',
            'consultant',
            'ca',
            'utility_provider',
            'equipment_supplier',
            'rent',
            'electricity',
            'water',
            'internet',
            'salary',
            'lawyer',
            'amc',
            'office_supplies',
            'miscellaneous',
            'other'
        ) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        Schema::table('inventory_vendors', function (Blueprint $table) {
            $table->dropForeign(['finance_vendor_id']);
            $table->dropColumn('finance_vendor_id');
        });

        // Restore original enum
        DB::statement("ALTER TABLE finance_vendors MODIFY COLUMN vendor_type ENUM(
            'lab',
            'implant_company',
            'dental_supplier',
            'marketing_agency',
            'software_vendor',
            'consultant',
            'ca',
            'utility_provider',
            'equipment_supplier',
            'other'
        ) NOT NULL DEFAULT 'other'");
    }
};
