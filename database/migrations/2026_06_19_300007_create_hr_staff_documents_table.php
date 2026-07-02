<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50); // contract, id_proof, certificate, bank, other
            $table->string('label', 255);         // e.g. "Aadhar Card", "Employment Contract"
            $table->string('file_path', 500);
            $table->string('file_name', 255);     // original filename shown to user
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('mime_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_documents');
    }
};
