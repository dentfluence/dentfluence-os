<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->nullOnDelete(); // visits table if exists

            // Treatment context
            $table->string('treatment_name')->nullable();
            $table->string('tooth_no', 50)->nullable();         // e.g. "11, 46"
            $table->string('treatment_stage')->nullable();      // before, during, after, followup
            $table->string('media_type')->default('photo');     // photo, xray, opg, cbct, scan, video, pdf
            $table->string('category')->nullable();             // for generic: implantology, endodontics…

            // Paths
            $table->string('original_path');
            $table->string('watermarked_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes

            // Metadata
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();                   // searchable tag array
            $table->date('media_date')->nullable();
            $table->boolean('is_generic')->default(false);      // true = generic educational content
            $table->boolean('watermark_applied')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for search performance
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
