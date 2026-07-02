<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add provider EMI receipt tracking columns.
     *
     * invoice_payments.provider_paid_at — set when clinic marks the provider as having paid
     * receipts.receipt_type             — 'patient_upfront' | 'provider_settlement' | null (regular)
     */
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            // NOTE: no ->after('clinic_net_amount') — that column is added by a
            // later migration, so positioning here breaks a fresh migrate.
            $table->timestamp('provider_paid_at')->nullable();
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->string('receipt_type', 30)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('provider_paid_at');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('receipt_type');
        });
    }
};
