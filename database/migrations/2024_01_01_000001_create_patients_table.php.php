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

            // Personal Info
            $table->string('name');
            $table->string('phone', 20);
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('email')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();

            // Clinical
            $table->text('chief_complaint')->nullable();
            $table->text('medical_alert')->nullable();

            // Source / Referral
            $table->string('source', 100)->nullable(); // e.g. walk-in, google, referral
            $table->string('referred_by')->nullable();

            // Relations
            $table->unsignedBigInteger('branch_id')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();

            // Timestamps + Soft Delete
            $table->softDeletes();
            $table->timestamps();

            // Foreign Keys
            
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
