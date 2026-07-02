<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7F — Documentation Protocol Steps Table
 *
 * Each step defines one required file within a protocol.
 * e.g. Root Canal Protocol → Step 1: "Pre-op IOPA" (file_type=xray, stage=before, required=true)
 *
 * Run AFTER documentation_protocols (300002) and BEFORE FK addendum (300004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_protocol_steps', function (Blueprint $table) {

            $table->id();

            // Parent protocol
            $table->foreignId('protocol_id')
                  ->constrained('documentation_protocols')
                  ->cascadeOnDelete();

            // Step label shown to the user, e.g. "Pre-op X-ray", "Working Length X-ray"
            $table->string('name');

            // Optional guidance text shown in upload modal
            $table->text('description')->nullable();

            // Expected file type for this step — matches clinical_files.file_type enum
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

            // Expected capture stage — matches clinical_files.stage enum
            $table->enum('stage', [
                'general',
                'before',
                'during',
                'after',
                'followup',
            ])->default('general');

            // If true, the Documents tab shows a warning when this step has no file
            $table->boolean('is_required')->default(false);

            // Display order within the protocol
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['protocol_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_protocol_steps');
    }
};
