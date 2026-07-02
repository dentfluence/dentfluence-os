<?php
// =============================================================================
// Phase 2 — Lab Billing fields on lab_cases
//
// Adds:
//   estimated_cost   — quoted/estimated before work begins
//   billing_status   — unbilled | in_reconciliation | billed | paid
//
// lab_cost (Phase 1) = final actual charge from lab (Final Lab Charge).
// payment_status (Phase 1) = pending | paid | monthly_account (case-level).
// billing_status (Phase 2) = tracks reconciliation lifecycle.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            // Estimated cost quoted before case is sent
            $table->decimal('estimated_cost', 10, 2)
                  ->nullable()
                  ->after('lab_cost');

            // Billing lifecycle for reconciliation workflow
            // unbilled        = no reconciliation started yet
            // in_reconciliation = included in a monthly reconciliation
            // billed          = reconciliation approved → Finance expense created
            // paid            = Finance expense marked paid
            $table->string('billing_status', 30)
                  ->default('unbilled')
                  ->after('estimated_cost')
                  ->index();

            // Link to the monthly reconciliation this case belongs to (nullable)
            $table->foreignId('reconciliation_id')
                  ->nullable()
                  ->after('billing_status')
                  ->constrained('lab_monthly_reconciliations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $table->dropForeign(['reconciliation_id']);
            $table->dropIndex(['billing_status']);
            $table->dropColumn(['estimated_cost', 'billing_status', 'reconciliation_id']);
        });
    }
};
