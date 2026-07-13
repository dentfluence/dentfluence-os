<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Vendors — richer payment terms + single source of truth for capabilities.
 *
 * credit_days: used when payment_terms = 'monthly_account' (e.g. "Net 30").
 * specialties: dropped — a vendor's capabilities are now whatever's in its
 * LabVendorService catalog (category + rate + turnaround per service),
 * not a separate unpriced checklist that could drift out of sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_vendors', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_days')->nullable()->after('payment_terms');
            $table->dropColumn('specialties');
        });
    }

    public function down(): void
    {
        Schema::table('lab_vendors', function (Blueprint $table) {
            $table->dropColumn('credit_days');
            $table->json('specialties')->nullable()->after('address');
        });
    }
};
