<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Treatments ──────────────────────────────────────────────────────
        if (!Schema::hasTable('treatments'))
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('default_price', 10, 2)->default(0);
            $table->integer('duration_mins')->default(30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Complaints ───────────────────────────────────────────────────────
        if (!Schema::hasTable('complaints'))
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Diagnosis Masters (renamed to avoid conflict with consultation diagnoses table) ──
        if (!Schema::hasTable('diagnosis_masters'))
        Schema::create('diagnosis_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icd_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Investigation Masters (renamed to avoid conflict with consultation investigations table) ──
        if (!Schema::hasTable('investigation_masters'))
        Schema::create('investigation_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Medicines ────────────────────────────────────────────────────────
        if (!Schema::hasTable('medicines'))
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('type')->nullable();
            $table->string('default_dosage')->nullable();
            $table->string('default_frequency')->nullable();
            $table->string('default_duration')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Medical Conditions ────────────────────────────────────────────────
        if (!Schema::hasTable('medical_conditions'))
        Schema::create('medical_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Dental Conditions ─────────────────────────────────────────────────
        if (!Schema::hasTable('dental_conditions'))
        Schema::create('dental_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Patient Sources ───────────────────────────────────────────────────
        if (!Schema::hasTable('patient_sources'))
        Schema::create('patient_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Message Templates ─────────────────────────────────────────────────
        if (!Schema::hasTable('message_templates'))
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('channel', ['whatsapp', 'sms', 'email'])->default('whatsapp');
            $table->enum('type', ['appointment_reminder', 'followup', 'recall', 'birthday', 'custom'])->default('custom');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('patient_sources');
        Schema::dropIfExists('dental_conditions');
        Schema::dropIfExists('medical_conditions');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('investigation_masters');
        Schema::dropIfExists('diagnosis_masters');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('treatments');
    }
};
