<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v2 — Attachments
 *
 * STL files, intraoral photos, shade photos, x-rays, prescriptions, PDFs.
 * Files stored on the 'public' disk under lab-cases/{case_id}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_case_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_case_id')->constrained()->cascadeOnDelete();

            $table->string('file_path');                 // storage path
            $table->string('original_name');             // name as uploaded
            $table->string('category')->default('other'); // stl | intraoral_photo | shade_photo | xray | prescription | pdf | other
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            // Audit
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();                       // soft delete only — audit trail
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_case_attachments');
    }
};
