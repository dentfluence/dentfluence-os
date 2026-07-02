<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materials attached to a practice protocol — the "how to do it" layer.
     *
     * A protocol can carry several materials:
     *   - sop_steps : an ordered checklist stored as a JSON array of strings
     *   - file      : an uploaded document (path on disk)
     *   - link      : an external URL
     *
     * Mirrors the JSON-step shape already used by `treatment_sops.doctor_steps`.
     */
    public function up(): void
    {
        Schema::create('practice_protocol_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('practice_protocol_id')
                  ->constrained('practice_protocols')
                  ->cascadeOnDelete();

            $table->enum('type', ['sop_steps', 'file', 'link']);

            $table->string('title');

            // type = sop_steps → ["Step 1...", "Step 2..."]
            $table->json('body')->nullable();

            // type = file → stored upload path
            $table->string('file_path')->nullable();

            // type = link → external URL
            $table->string('url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_protocol_materials');
    }
};
