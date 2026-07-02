<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table: password_reset_pins
 * Stores 6-digit PIN for email-based password reset.
 * Each row is deleted after successful reset or expiry (15 min).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_pins', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('pin', 6);                     // 6-digit PIN (hashed)
            $table->string('token')->unique();             // opaque token returned after PIN verify
            $table->boolean('pin_verified')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_pins');
    }
};
