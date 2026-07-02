<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            // Vendor's own invoice/bill number — allows multiple invoices per PO (partial deliveries)
            $table->string('vendor_invoice_no', 80)->nullable()->after('source_id');
            // Link directly to the GRN that generated this expense
            $table->string('grn_number', 40)->nullable()->after('vendor_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropColumn(['vendor_invoice_no', 'grn_number']);
        });
    }
};
