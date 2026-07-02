<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8E — Drop patient_documents
 *
 * Safe to run only AFTER phase8:migrate-patient-documents has completed and
 * row counts have been verified. All data is now in clinical_files.
 *
 * Reversible: down() recreates the full table structure from
 * 2026_06_04_000001_create_patient_documents_table.
 * Data will NOT be restored on rollback — this is a structural rollback only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('patient_documents');
    }

    public function down(): void
    {
        // Recreate the patient_documents schema.
        // NOTE: This restores structure only — data is not restored on rollback.
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('Other');
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
