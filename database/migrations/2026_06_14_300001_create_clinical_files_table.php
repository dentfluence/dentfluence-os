<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7A — Clinical Files Table
 *
 * Single unified table that will eventually replace:
 *   - clinical_media
 *   - cms_media
 *   - patient_documents
 *
 * Old tables are NOT dropped here. They stay until Phase 8 (data migration).
 *
 * NOTE: protocol_step_id is an unsignedBigInteger here (no FK constraint).
 * The FK is added in migration 300004 after documentation_protocol_steps exists.
 *
 * Run ORDER: 300002 → 300003 → 300001 → 300004
 * (Protocols tables must exist before the FK addendum runs)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop if partially created by a previously failed migration run
        Schema::dropIfExists('clinical_files');

        Schema::create('clinical_files', function (Blueprint $table) {

            $table->id();

            // ── Core Anchors ────────────────────────────────────────────────────

            // Patient is always required — every file must be patient-scoped
            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // Visit scope (optional)
            $table->foreignId('visit_id')
                  ->nullable()
                  ->constrained('treatment_visits')
                  ->nullOnDelete();

            // Treatment plan item scope (optional — most granular level)
            $table->foreignId('treatment_plan_item_id')
                  ->nullable()
                  ->constrained('treatment_plan_items')
                  ->nullOnDelete();

            // ── Clinical Context ────────────────────────────────────────────────

            // Auto-filled from visit.treatment_name when visit_id is set
            $table->string('procedure')->nullable();

            // Single tooth or comma-separated (e.g. "16", "16,17")
            $table->string('tooth_number')->nullable();

            // Stage in the treatment workflow
            $table->enum('stage', [
                'general',
                'before',
                'during',
                'after',
                'followup',
            ])->default('general');

            // ── File Classification ─────────────────────────────────────────────

            $table->enum('file_type', [
                'photo',
                'video',
                'xray',
                'opg',
                'cbct',
                'stl',
                'intraoral_scan',
                'pdf',
                'consent',
                'estimate',
                'invoice',
                'lab_slip',
                'other',
            ])->default('other');

            // Human-readable label (optional — auto-derived from file_type if null)
            $table->string('title')->nullable();

            // Clinical notes about this file
            $table->text('notes')->nullable();

            // ── Storage ─────────────────────────────────────────────────────────

            // Laravel filesystem disk name (local, s3, azure)
            $table->string('disk')->default('public');

            // ALWAYS relative to disk root — NEVER an absolute Windows path
            $table->string('path');

            // Watermarked copy path (original is NEVER modified)
            $table->string('watermarked_path')->nullable();

            // ── File Metadata ───────────────────────────────────────────────────

            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // bytes

            // When the file was clinically captured (may differ from created_at)
            $table->dateTime('captured_at')->nullable();

            // User who performed the upload
            $table->foreignId('uploaded_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // ── Source Tracing ──────────────────────────────────────────────────

            // Links this file back to the entity that triggered its creation
            // e.g. source_type='prescription', source_id=42
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Documentation protocol step that required this file.
            // No FK constraint here — added in migration 300004 after the
            // documentation_protocol_steps table is created.
            $table->unsignedBigInteger('protocol_step_id')->nullable();

            // ── Sync Status ─────────────────────────────────────────────────────

            $table->enum('sync_status', [
                'local_only',
                'sync_pending',
                'synced',
                'cloud_only',
            ])->default('local_only');

            // ── Content Eligibility Flags ───────────────────────────────────────
            // These determine which Content Manager tab views show this file.
            // NOT separate storage — filters over this same table.

            $table->boolean('is_marketing_eligible')->default(false);
            $table->boolean('is_education_eligible')->default(false);
            $table->boolean('is_teaching_eligible')->default(false);
            $table->boolean('is_research_eligible')->default(false);
            $table->boolean('is_case_library_eligible')->default(false);

            // ── Consent & Approval ──────────────────────────────────────────────

            $table->enum('consent_status', [
                'not_given',
                'pending',
                'given',
            ])->default('not_given');

            // Only relevant when is_marketing_eligible = true
            $table->enum('marketing_status', [
                'pending',
                'approved',
                'rejected',
            ])->nullable();

            // ── Optional Metadata ───────────────────────────────────────────────

            // 1–5 star rating for quality (used in Content Manager sorting)
            $table->tinyInteger('content_rating')->nullable()->unsigned();

            // Flexible tagging (JSON array of strings)
            $table->json('tags')->nullable();

            // ── Timestamps & Soft Delete ────────────────────────────────────────

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ─────────────────────────────────────────────────────────

            $table->index('patient_id');
            $table->index('visit_id');
            $table->index(['patient_id', 'file_type']);
            $table->index('is_case_library_eligible');
            // Explicit short name — MySQL enforces a 64-char identifier limit
            $table->index(['is_marketing_eligible', 'consent_status', 'marketing_status'], 'cf_marketing_consent_idx');
            $table->index('protocol_step_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_files');
    }
};
