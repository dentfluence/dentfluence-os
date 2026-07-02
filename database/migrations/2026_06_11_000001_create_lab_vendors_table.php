<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v2 — Lab Vendors
 *
 * Dedicated table for dental laboratories the clinic works with.
 * Optionally linked to finance_vendors so lab spend flows into
 * the Finance module without duplicating master data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->default(1)->index();

            // Link to Finance vendor master (optional, set when expense integration is used)
            $table->foreignId('finance_vendor_id')->nullable()
                  ->constrained('finance_vendors')->nullOnDelete();

            // Identity & contact
            $table->string('name');                          // e.g. "Smile Dental Lab"
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();   // used by "WhatsApp Lab Details" action
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Operational defaults
            $table->json('specialties')->nullable();         // ["Crown & Bridge", "Implant Prosthesis", ...]
            $table->unsignedSmallInteger('default_turnaround_days')->default(7);
            $table->string('payment_terms')->default('per_case'); // per_case | monthly_account

            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();                           // never hard-delete vendors
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_vendors');
    }
};
