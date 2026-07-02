<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * huddle_settings
     *
     * Key-value settings store scoped to branch + optional role.
     * A null role means the setting applies to all roles in that branch.
     *
     * Example keys:
     *   carry_forward_enabled        → true/false
     *   proof_required_for           → ["sterilization"]
     *   overdue_threshold_minutes    → 30
     *   column_visibility            → {"lab": false, "marketing": true}
     *   escalation_after_minutes     → 60
     *   notification_channels        → ["email", "whatsapp"]
     */
    public function up(): void
    {
        Schema::create('huddle_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');

            // Null role = branch-wide default; set role to override per-role
            $table->string('role', 50)->nullable();

            // Setting identifier (snake_case)
            $table->string('key', 100);

            // JSON value — cast to array in model, supports scalars + arrays
            $table->json('value')->nullable();

            // Human-readable label for the settings UI
            $table->string('label', 255)->nullable();

            // Tooltip/description shown in settings panel
            $table->text('description')->nullable();

            $table->timestamps();

            // ── Foreign keys ──────────────────────────────────────────────────
            // branch_id references branches table if it exists,
            // otherwise just an integer scope matching users.branch_id
            // Commented out — add if you have a branches table:
            // $table->foreign('branch_id')->references('id')->on('branches');

            // ── Unique constraint ─────────────────────────────────────────────
            // One value per branch + role + key combination
            $table->unique(
                ['branch_id', 'role', 'key'],
                'huddle_settings_branch_role_key_unique'
            );

            // ── Indexes ───────────────────────────────────────────────────────
            // HuddleSetting::getValue() — most common lookup
            $table->index(['branch_id', 'key'], 'huddle_settings_branch_key_idx');

            // Role-scoped settings lookup
            $table->index(['branch_id', 'role'], 'huddle_settings_branch_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_settings');
    }
};
