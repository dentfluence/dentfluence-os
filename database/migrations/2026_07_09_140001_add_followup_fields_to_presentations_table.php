<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->text('follow_up_notes')->nullable()->after('doctor_message');
            $table->timestamp('declined_at')->nullable()->after('view_count');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['follow_up_notes', 'declined_at']);
        });
    }
};
