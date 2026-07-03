<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 · Slice 3 — Search Engine index projection.
 *
 * A denormalised, disposable copy of the searchable fields already used by
 * ProfileController::search() (name/phone/email/score) — one row per
 * Master Relationship. Purely additive; the live search route is untouched
 * and keeps querying `relationships` directly until a future cutover behind
 * the (already declared) `search.index` flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('relationship_id')->unique();

            // Denormalised searchable fields — mirrors relationships.* exactly
            // so the index needs no joins at query time.
            $table->string('name', 191)->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email', 191)->nullable();
            $table->integer('score')->default(0);
            $table->string('status', 20)->nullable();
            $table->string('source', 100)->nullable();

            // Denormalised display fields (avoid a join back to patients at read time).
            $table->string('patient_name', 191)->nullable();
            $table->string('link', 255)->nullable();

            $table->timestamp('computed_at')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->index(['name']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index');
    }
};
