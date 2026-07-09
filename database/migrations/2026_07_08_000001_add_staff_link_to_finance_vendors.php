<?php
// =============================================================================
// Finance Vendors — Staff link
//
// Lets a staff member (User) be paid out of the generic Expense form (petty
// cash, reimbursements, ad-hoc payouts) without inventing a parallel payee
// concept. Each active staff member gets a mirrored finance_vendors row
// (vendor_type = 'staff', user_id = the staff's user id), kept in sync by
// UserVendorSyncObserver. Recurring monthly salary should still go through
// Finance > Payroll (finance_payroll table) — that keeps payroll reporting
// accurate and this is only for one-off payouts.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_vendors', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('clinic_id')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Set when this vendor row mirrors a staff member (vendor_type=staff)');
        });

        // MySQL requires re-declaring the full enum list when modifying.
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
            'staff',
            'other'
        ) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        Schema::table('finance_vendors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

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
};
