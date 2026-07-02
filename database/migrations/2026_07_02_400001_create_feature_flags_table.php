<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 — Feature-flag overrides.
 *
 * Additive, non-destructive. Stores OPTIONAL per-clinic / global overrides.
 * When no row exists for a flag, resolution falls back to config/features.php.
 * Empty table === every flag at its config default (legacy behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_flags')) {
            return; // idempotent guard
        }

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();

            // The flag key, e.g. 'guard.fail_closed'. Must exist in config/features.php.
            $table->string('key')->index();

            // NULL = global override (applies to all clinics). Otherwise scoped to a branch/clinic.
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // The override value.
            $table->boolean('enabled')->default(false);

            // Optional human note explaining who/why flipped it.
            $table->string('note')->nullable();

            $table->timestamps();

            // One override row per (key, scope).
            $table->unique(['key', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
