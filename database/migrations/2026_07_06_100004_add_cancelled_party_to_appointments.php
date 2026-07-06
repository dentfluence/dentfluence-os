<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Who initiated the cancellation — kept separate from `cancel_reason`
            // (the free-text note) so it can be reported on / filtered later.
            $table->enum('cancelled_party', ['patient', 'clinic'])
                ->nullable()
                ->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('cancelled_party');
        });
    }
};
