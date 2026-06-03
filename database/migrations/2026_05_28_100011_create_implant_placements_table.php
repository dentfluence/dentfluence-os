<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Implant Placements — traceability table
 * Links an implant component to a patient treatment visit.
 * Staff can upload the implant label / QR code photo at time of placement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implant_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('treatment_visit_id')->nullable()->constrained('treatment_visits')->nullOnDelete();
            $table->foreignId('implant_catalog_id')->nullable()->constrained('implant_catalog')->nullOnDelete();
            $table->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();

            // Traceability fields
            $table->string('lot_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('tooth_position', 30)->nullable();     // FDI notation e.g. 16, 26, 46
            $table->date('surgery_date')->nullable();

            // Free-text for when catalog item isn't known
            $table->string('implant_brand_freetext', 150)->nullable();
            $table->string('implant_code_freetext', 150)->nullable();

            // Label / QR code photo uploaded by staff
            $table->string('label_photo_path', 500)->nullable();

            // Status of the placement
            $table->string('status', 40)->default('placed'); // placed|osseointegrating|loaded|failed|explanted

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('surgery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implant_placements');
    }
};
