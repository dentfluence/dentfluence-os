<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();

            // Source references — links to existing tables, no FK constraints
            // so CMS never breaks if consultation/visit is deleted
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('consultation_id')->nullable()->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('doctor_id')->nullable()->index();

            // Clinical metadata
            $table->string('treatment_name')->nullable()->index();
            $table->string('tooth_no')->nullable()->index();
            $table->enum('treatment_stage', [
                'before_treatment',
                'during_treatment',
                'after_treatment',
                'follow_up',
            ])->nullable()->index();

            // Media info
            $table->enum('media_type', [
                'photo', 'xray', 'opg', 'cbct', 'scan', 'video', 'pdf', 'other'
            ])->default('photo');
            $table->string('original_filename')->nullable();
            $table->string('original_path')->nullable();
            $table->string('watermarked_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('mime_type')->nullable();

            // Searchable tags (JSON array)
            $table->json('searchable_tags')->nullable();

            // Status flags
            $table->enum('treatment_status', ['ongoing', 'completed', 'paused'])->default('ongoing');
            $table->boolean('is_marketing')->default(false)->index();
            $table->boolean('watermark_applied')->default(false);

            // Dates
            $table->date('treatment_start_date')->nullable();
            $table->date('treatment_end_date')->nullable();
            $table->timestamp('upload_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};
