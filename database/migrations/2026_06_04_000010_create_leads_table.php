<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Core contact info
            $table->string('name');
            $table->string('phone', 20);
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();

            // Pipeline
            $table->string('stage')->default('new_lead'); // new_lead|contacted|appointment|consultation|plan_given|converted|lost
            $table->string('source')->nullable();         // Call Manager|WhatsApp|Instagram|etc.
            $table->string('urgency', 10)->default('low'); // low|medium|high

            // Treatment interest
            $table->string('treatment')->nullable();
            $table->string('secondary_treatment')->nullable();

            // Assignment & follow-up
            $table->string('assigned_to')->nullable();
            $table->date('followup_date')->nullable();
            $table->string('followup_time', 20)->nullable();
            $table->string('preferred_contact', 20)->default('call'); // call|whatsapp|email

            // Notes & tags
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Personal details (optional)
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->string('location')->nullable();
            $table->string('language', 50)->nullable();
            $table->string('referred_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
