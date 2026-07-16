<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — KB topic graph (frozen §5.1, decision #9).
 * Typed topic relations for FUTURE AI retrieval/recommendation — NOT
 * navigation and NOT the assembly path (assembly walks the decision tree).
 * Table added now, populated lazily as content is authored; no traversal
 * engine in V1 (deferred §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_topic_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_topic_id')->constrained('kb_topics')->cascadeOnDelete();
            $table->foreignId('to_topic_id')->constrained('kb_topics')->cascadeOnDelete();
            $table->enum('relation_type', ['related', 'prerequisite', 'followup']);
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['from_topic_id', 'to_topic_id', 'relation_type'], 'kb_topic_relations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_topic_relations');
    }
};
