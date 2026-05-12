<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('patients', function (Blueprint $table) {

    $table->id();

    $table->string('patient_uid')->nullable();

    $table->string('first_name');
    $table->string('last_name')->nullable();

    $table->string('phone')->unique();
    $table->string('email')->nullable();

    $table->date('date_of_birth')->nullable();
    $table->integer('age')->nullable();
    $table->string('gender')->nullable();

    $table->text('address')->nullable();

    $table->string('city')->nullable();
    $table->string('state')->nullable();

    $table->string('pincode')->nullable();

    $table->string('occupation')->nullable();

    $table->string('blood_group')->nullable();

    $table->text('medical_history')->nullable();
    $table->text('allergies')->nullable();

    $table->string('reference_source')->nullable();

    $table->boolean('is_active')->default(true);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
