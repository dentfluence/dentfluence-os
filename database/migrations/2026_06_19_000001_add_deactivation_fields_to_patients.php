<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('recall_status');
            $table->text('deactivation_reason')->nullable()->after('is_active');
            $table->text('deleted_reason')->nullable()->after('deactivation_reason');
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete()->after('deleted_reason');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deactivation_reason', 'deleted_reason', 'deactivated_by']);
        });
    }
};
