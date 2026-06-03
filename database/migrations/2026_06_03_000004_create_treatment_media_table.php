<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media / documents attached to a treatment.
     * Covers images, videos (URL or local), PDFs, consent templates,
     * instruction sheets.
     */
    public function up(): void
    {
        Schema::create('treatment_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();

            $table->enum('media_type', [
                'image',             // Photo / illustration of procedure
                'video',             // Procedure / explainer video
                'pdf',               // Generic PDF document
                'consent_template',  // Consent form template
                'pre_care_sheet',    // Printable pre-care instructions
                'post_care_sheet',   // Printable post-care instructions
                'protocol_doc',      // Clinical protocol document
            ]);

            $table->string('label');                     // Display name
            $table->string('file_path')->nullable();     // Local storage path
            $table->string('external_url')->nullable();  // YouTube / Vimeo / external link
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_media');
    }
};
