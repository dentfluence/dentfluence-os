<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — decision tree templates (frozen §5.3).
 * Reusable templates OWNED BY DENTFLUENCE. Clinics never fork the global tree;
 * doctors curate instances (journey_curations). `entry_condition` maps to a
 * charted condition / kb_topic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_trees', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('entry_condition')->nullable();   // e.g. charted "missing_tooth"
            $table->string('version', 20)->default('1.0.0');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['entry_condition', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_trees');
    }
};
