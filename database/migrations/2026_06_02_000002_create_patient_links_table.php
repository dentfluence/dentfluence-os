<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table for linking family members / related patients.
 * Relationship is bidirectional: if A links to B, B links to A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('linked_patient_id');
            $table->string('relationship', 50)->nullable()
                ->comment('e.g. Husband, Wife, Son, Daughter, Parent');
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'linked_patient_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('linked_patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_links');
    }
};
