<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM Phase 1 · Wave 1 — Per-branch settings.
 *
 * The existing app_settings table is global key-value and stays exactly as-is.
 * ABDM needs PER-FACILITY config (each branch has different HFR/HIP setup), so we
 * add a parallel branch-scoped store with the same group/key/value shape.
 * Setting groups: abdm | fhir | consent | data_exchange | security |
 * api_endpoints | audit | sync | feature_flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('group')->default('general');
            $table->string('key');
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_settings');
    }
};
