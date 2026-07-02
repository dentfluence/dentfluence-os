<?php
// =============================================================================
// Repeat-work tracking on treatment visit items
// When the same treatment is recorded again on the same tooth for the same
// patient, staff flag it as repeat work and give a reason. This lets us report
// on how often work has to be redone.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            // Is this a repeat of work already done on the same tooth?
            $table->boolean('is_repeat')->default(false)->after('billing_status');

            // Why is it being redone (required when is_repeat = true)
            $table->string('repeat_reason', 300)->nullable()->after('is_repeat');

            // The original visit item this repeats (best-effort link for reporting).
            // Plain nullable column + index — no hard FK so deleting old items is safe.
            $table->unsignedBigInteger('repeat_of_visit_item_id')->nullable()->after('repeat_reason');

            $table->index(['is_repeat']);
            $table->index(['repeat_of_visit_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            $table->dropIndex(['is_repeat']);
            $table->dropIndex(['repeat_of_visit_item_id']);
            $table->dropColumn(['is_repeat', 'repeat_reason', 'repeat_of_visit_item_id']);
        });
    }
};
