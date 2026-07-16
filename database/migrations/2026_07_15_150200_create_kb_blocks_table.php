<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — Knowledge Bank blocks (frozen §5.1).
 * Reusable content atoms. `body` may contain whitelisted {{tokens}} (no logic)
 * resolved at render, after translation/AI (§6/§7). Media is BY REFERENCE
 * (kb_block_media), never inline. `depth` is reserved — author only `standard`
 * in V1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_topic_id')->constrained('kb_topics')->cascadeOnDelete();
            $table->enum('block_type', [
                'intro', 'animation', 'image', 'video', 'advantage', 'disadvantage',
                'risk', 'contraindication', 'healing_timeline', 'maintenance', 'faq',
                'before_after', 'reference', 'comparison',
            ]);
            $table->string('title')->nullable();
            $table->longText('body')->nullable();   // rich text w/ whitelisted {{tokens}}
            $table->enum('depth', ['simple', 'standard', 'detailed', 'clinical'])
                  ->default('standard');            // reserved — only `standard` authored in V1
            $table->string('locale', 10)->default('en');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('version', 20)->default('1.0.0');
            $table->timestamps();

            $table->index(['kb_topic_id', 'block_type']);
            $table->index(['kb_topic_id', 'locale', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_blocks');
    }
};
