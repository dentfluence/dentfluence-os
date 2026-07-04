<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for the reusable/single-use override decision.
     *
     * `is_reusable` and `inventory_behavior` already exist on inventory_items and
     * default to the manufacturer's IFU (usually single-use). When a clinic
     * deliberately overrides a component to "reusable" (off-label sterilize +
     * reuse — e.g. cover screws, healing abutments, copings, scan bodies), we
     * need to know who made that call and when, without building a separate
     * audit-flag column or event table. These two nullable fields are stamped
     * by the controller whenever is_reusable actually changes value.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('usage_mode_changed_by')
                  ->nullable()
                  ->after('sterilization_required')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('usage_mode_changed_at')
                  ->nullable()
                  ->after('usage_mode_changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usage_mode_changed_by');
            $table->dropColumn('usage_mode_changed_at');
        });
    }
};
