<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N-1 (schema) / N-5 (writer) — one row per phone that can receive a push.
 *
 * The token is FCM's registration token; it is unique across the table
 * because a phone that logs out and in as another user must move to that
 * user, not be duplicated. `last_seen_at` is refreshed on every register
 * call so stale tokens can be swept without guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 20)->default('android');
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'invalidated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
