<?php
// ─── Migration 1: create_tags_table ───────────────────────────────────────
// File: database/migrations/2025_01_01_000010_create_tags_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. "High Value"
            $table->string('slug')->unique();              // e.g. "high-value"
            $table->string('color', 7)->default('#6a0f70'); // hex color for icon/text
            $table->string('bg_color', 7)->default('#f5f3ff'); // hex bg color
            $table->string('group')->default('General');   // e.g. "Financial", "Behavior"
            $table->text('description')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->boolean('is_system')->default(false);  // system tags can't be deleted
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('patient_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_tag');
        Schema::dropIfExists('tags');
    }
};
