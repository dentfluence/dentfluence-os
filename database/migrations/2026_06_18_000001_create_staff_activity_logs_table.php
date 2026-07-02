<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_activity_logs', function (Blueprint $table) {
            $table->id();

            // Who was affected
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Who performed the action
            $table->foreignId('performed_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // What happened: 'activated' | 'deactivated' | 'role_changed' | 'profile_updated'
            $table->string('action', 40);

            // Optional context: old value → new value
            $table->string('old_value', 100)->nullable();
            $table->string('new_value', 100)->nullable();

            // Extra notes (e.g. fields changed)
            $table->string('note', 255)->nullable();

            // Network info
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_logs');
    }
};
