<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one genuinely new piece of infrastructure Smart Treatment
     * Presentation needed: a secure, expiring, revocable link a patient can
     * open without logging in. No Laravel signed-route mechanism existed
     * anywhere in the codebase, and signed routes can't be individually
     * revoked without a DB record anyway — so this is a plain random token
     * looked up server-side, which also lets us track views per-link.
     */
    public function up(): void
    {
        Schema::create('presentation_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable(); // null = never expires (dentist toggled off)
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_access_tokens');
    }
};
