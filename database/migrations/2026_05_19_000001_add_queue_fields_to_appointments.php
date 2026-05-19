<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_walkin')->default(false)->after('status');
            $table->timestamp('checked_in_at')->nullable()->after('is_walkin');
            $table->timestamp('in_chair_at')->nullable()->after('checked_in_at');
            $table->timestamp('completed_at')->nullable()->after('in_chair_at');
            $table->unsignedSmallInteger('queue_position')->nullable()->after('completed_at');
            $table->unsignedSmallInteger('estimated_wait_minutes')->nullable()->after('queue_position');
            $table->string('chair_number', 10)->nullable()->after('estimated_wait_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'is_walkin',
                'checked_in_at',
                'in_chair_at',
                'completed_at',
                'queue_position',
                'estimated_wait_minutes',
                'chair_number',
            ]);
        });
    }
};