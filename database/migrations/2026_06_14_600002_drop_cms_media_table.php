<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8E — Drop cms_media
 *
 * Safe to run only AFTER phase8:merge-cms-media has completed and
 * row counts have been verified. All data is now in clinical_files.
 *
 * Reversible: down() recreates the full table structure (original columns from
 * 2025_01_01_000001_create_cms_media_table + columns added by
 * 2026_06_03_000001_add_tagging_consent_marketing_to_cms_media).
 * Data will NOT be restored on rollback — this is a structural rollback only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cms_media');
    }

    public function down(): void
    {
        // Recreate the full cms_media schema (original + ALTER additions).
        // NOTE: This restores structure only — data is not restored on rollback.
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();

            // ── Source references (no FK constraints — intentional) ─────────────
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('consultation_id')->nullable()->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('doctor_id')->nullable()->index();

            // ── Clinical metadata ──────────────────────────────────────────────
            $table->string('treatment_name')->nullable()->index();
            $table->string('tooth_no')->nullable()->index();
            $table->enum('treatment_stage', [
                'before_treatment',
                'during_treatment',
                'after_treatment',
                'follow_up',
            ])->nullable()->index();

            // ── Media info ─────────────────────────────────────────────────────
            $table->enum('media_type', [
                'photo', 'xray', 'opg', 'cbct', 'scan', 'video', 'pdf', 'other',
            ])->default('photo');
            $table->string('original_filename')->nullable();
            $table->string('original_path')->nullable();
            $table->string('watermarked_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('searchable_tags')->nullable();

            // ── Status flags ───────────────────────────────────────────────────
            $table->enum('treatment_status', ['ongoing', 'completed', 'paused'])->default('ongoing');
            $table->boolean('is_marketing')->default(false)->index();
            $table->boolean('watermark_applied')->default(false);

            // ── Dates ──────────────────────────────────────────────────────────
            $table->date('treatment_start_date')->nullable();
            $table->date('treatment_end_date')->nullable();
            $table->timestamp('upload_date')->nullable();

            // ── Columns added by 2026_06_03 ALTER migration ────────────────────
            $table->enum('consent_status', ['not_given', 'given', 'pending'])
                  ->default('pending')->index();
            $table->enum('photo_type', [
                'before', 'after', 'before_after',
                'procedure', 'team', 'clinic', 'testimonial',
            ])->nullable()->index();
            $table->enum('tag_treatment_type', [
                'implant', 'aligner', 'whitening', 'rct', 'crown',
                'smile_makeover', 'braces', 'extraction', 'veneer', 'other',
            ])->nullable()->index();
            $table->enum('marketing_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')->index();

            // ── Timestamps ─────────────────────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
