<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared, clinic-editable option lists for the Today's Actions call
 * workflow. Two use cases share one table (see `option_type`):
 *
 *  - 'call_outcome'    — the "Call outcome" dropdown in the Action Board
 *                        drawer, one set per action category (falls back to
 *                        config('relationship_rules.response_options') if a
 *                        category has zero active rows — see
 *                        TodayController::index()).
 *  - 'dismiss_reason'  — the required-reason list shown when a row is
 *                        Dismissed instead of logged (see
 *                        today_action_dismissals migration). Not category-
 *                        specific — `action_category` is null for these rows.
 *
 * See docs/feature-specs/feature-spec-custom-call-outcomes.md and
 * docs/feature-specs/feature-spec-action-board-dismiss.md for the full spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_option_lists', function (Blueprint $table) {
            $table->id();
            $table->string('option_type', 20);       // 'call_outcome' | 'dismiss_reason'
            $table->string('action_category', 60)->nullable(); // e.g. 'recall_calls', 'default'; null for dismiss_reason rows
            $table->string('key', 60);                // stable value stored on the logged Activity/dismissal, e.g. 'connected_booked'
            $table->string('label', 150);             // shown in the dropdown
            $table->boolean('closes_task')->default(true);   // false = row stays open after this outcome is logged
            $table->boolean('requires_notes')->default(false); // force a note before allowing submit
            $table->string('next_action_key', 60)->nullable(); // optional override; falls back to config('relationship_rules.next_actions')
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['option_type', 'action_category', 'key'], 'action_option_lists_unique_entry');
            $table->index(['option_type', 'action_category', 'is_active'], 'action_option_lists_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_option_lists');
    }
};
