<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescription Templates — clinic-level presets (RCT, Extraction, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rx_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // RCT Pain, Extraction, Implant Surgery
            $table->string('category')->nullable(); // dental, surgical, periodontal
            $table->text('description')->nullable();
            $table->text('instructions')->nullable(); // general instructions for this template
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rx_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('rx_templates')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('rx_drugs')->cascadeOnDelete();
            $table->string('strength')->nullable();
            $table->integer('morning')->default(0);
            $table->integer('afternoon')->default(0);
            $table->integer('night')->default(0);
            $table->boolean('is_sos')->default(false);
            $table->integer('duration')->nullable();
            $table->enum('duration_unit', ['days', 'weeks', 'months'])->default('days');
            $table->foreignId('food_instruction_id')->nullable()->constrained('rx_food_instructions')->nullOnDelete();
            $table->string('route')->nullable();
            $table->text('instructions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rx_template_items');
        Schema::dropIfExists('rx_templates');
    }
};
