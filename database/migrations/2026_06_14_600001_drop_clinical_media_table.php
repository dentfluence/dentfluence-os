<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8E — Drop clinical_media
 *
 * Safe to run only AFTER phase8:migrate-clinical-media has completed and
 * row counts have been verified. All data is now in clinical_files.
 *
 * Reversible: down() recreates the full table structure (original columns
 * + columns added by 2026_05_28_160000_add_missing_columns_to_clinical_media_table).
 * Data will NOT be restored on rollback — this is a structural rollback only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clinical_media');
    }

    public function down(): void
    {
        // Recreate the full clinical_media schema (original + ALTER additions).
        // NOTE: This restores structure only — data is not restored on rollback.
        Schema::create('clinical_media', function (Blueprint $table) {
            $table->id();

            // ── Foreign keys ───────────────────────────────────────────────────
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->nullOnDelete();

            // ── Clinical context ───────────────────────────────────────────────
            $table->string('treatment_name')->nullable();
            $table->string('tooth_no', 50)->nullable();
            $table->string('treatment_stage')->nullable();
            $table->string('media_type')->default('photo');
            $table->string('category')->nullable();

            // ── File paths ─────────────────────────────────────────────────────
            $table->string('original_path');
            $table->string('watermarked_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // ── Metadata ───────────────────────────────────────────────────────
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->date('media_date')->nullable();
            $table->boolean('is_generic')->default(false);
            $table->boolean('watermark_applied')->default(false);
            $table->boolean('is_active')->default(true);

            // ── Columns added by 2026_05_28_160000 ALTER migration ─────────────
            $table->date('visit_date')->nullable();
            $table->string('disk')->default('public');
            $table->json('searchable_tags')->nullable();
            $table->date('upload_date')->nullable();

            // ── Timestamps ─────────────────────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────────────────
            $table->index(['patient_id', 'treatment_name']);
            $table->index(['patient_id', 'tooth_no']);
            $table->index(['treatment_stage', 'media_type']);
            $table->index(['is_generic', 'category']);
        });
    }
};
