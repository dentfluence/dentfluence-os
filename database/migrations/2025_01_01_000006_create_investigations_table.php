<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_id')
                  ->constrained('consultations')
                  ->cascadeOnDelete();

            // Kept as string (not enum) — list grows over time without migrations:
            // opg | iopa | cbct | blood_test | saliva_test | biopsy | other
            $table->string('type', 80)->comment('opg | iopa | cbct | blood_test | saliva_test | biopsy | other');

            $table->text('details')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigations');
    }
};
