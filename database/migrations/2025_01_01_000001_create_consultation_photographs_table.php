<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_photographs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            $table->string('slot')->comment(
                'extraoral_front | extraoral_side | intraoral_front | ' .
                'intraoral_upper | intraoral_lower | intraoral_right | intraoral_left'
            );

            $table->string('path')->comment('Storage path relative to disk root');
            $table->string('original_name');
            $table->string('mime_type', 100);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_id');
            $table->index('slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_photographs');
    }
};
