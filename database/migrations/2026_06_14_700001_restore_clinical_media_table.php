<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore clinical_media table.
 *
 * The drop migration (2026_06_14_600001) ran before the Phase 8 data migration
 * (phase8:migrate-clinical-media) was executed. This migration recreates the
 * full table so existing code keeps working until Phase 8 is ready.
 *
 * Run AFTER this is confirmed working:
 *   1. php artisan phase8:migrate-clinical-media
 *   2. Verify row counts match
 *   3. Roll this migration back (or re-run the drop)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_media')) {
            return; // Already exists — nothing to do
        }

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

            // ── Columns added by 2026_05_28 ALTER migration ────────────────────
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

    public function down(): void
    {
        Schema::dropIfExists('clinical_media');
    }
};
