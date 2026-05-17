<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('huddle_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');

            // Settings are scoped per branch + role (null role = applies to all roles)
            $table->enum('role', ['admin', 'doctor', 'front_desk', 'assistant'])->nullable();

            // Key-value settings store
            $table->string('key');
            $table->json('value'); // always JSON so booleans, arrays, strings all fit

            // Human-readable label for the settings UI
            $table->string('label')->nullable();
            $table->string('description')->nullable();

            $table->timestamps();

            // One setting per branch + role + key
            $table->unique(['branch_id', 'role', 'key'], 'huddle_settings_unique');

            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_settings');
    }
};
