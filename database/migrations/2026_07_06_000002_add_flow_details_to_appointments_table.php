<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Today's Patient Flow popup (Huddle board) — front-desk prep fields
     * captured per scheduled appointment: how much to collect, an essential
     * item/task for the visit, and who's assisting chairside.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'amount_to_collect')) {
                $table->decimal('amount_to_collect', 10, 2)->nullable()->after('staff_instruction');
            }
            if (! Schema::hasColumn('appointments', 'prep_item')) {
                $table->string('prep_item', 255)->nullable()->after('amount_to_collect');
            }
            if (! Schema::hasColumn('appointments', 'chairside_assistant_id')) {
                $table->foreignId('chairside_assistant_id')
                      ->nullable()
                      ->after('prep_item')
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'chairside_assistant_id')) {
                $table->dropForeign(['chairside_assistant_id']);
                $table->dropColumn('chairside_assistant_id');
            }
            if (Schema::hasColumn('appointments', 'prep_item')) {
                $table->dropColumn('prep_item');
            }
            if (Schema::hasColumn('appointments', 'amount_to_collect')) {
                $table->dropColumn('amount_to_collect');
            }
        });
    }
};
