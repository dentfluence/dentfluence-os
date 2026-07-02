<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore cms_media table.
 *
 * The drop migration (2026_06_14_600002) ran before the Phase 8 data migration
 * (phase8:merge-cms-media) was executed. This migration recreates the full
 * table so existing code keeps working until Phase 8 is ready.
 *
 * Run AFTER this is confirmed working:
 *   1. php artisan phase8:merge-cms-media
 *   2. Verify row counts in clinical_files match
 *   3. Roll this migration back (or re-run the drop)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_media')) {
            return; // Already exists — nothing to do
        }

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

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};
