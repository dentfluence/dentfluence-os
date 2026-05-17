<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_scans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            $table->date('scan_date')->nullable();
            $table->string('path')->comment('Storage path relative to disk root');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_id');
            $table->index('scan_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_scans');
    }
};
