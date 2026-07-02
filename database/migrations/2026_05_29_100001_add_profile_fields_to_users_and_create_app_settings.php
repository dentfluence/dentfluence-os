<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add extra profile fields to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('designation')->nullable()->after('phone'); // e.g. "Senior Dentist"
            $table->string('avatar')->nullable()->after('designation'); // path to uploaded photo
        });

        // Generic key-value settings store for clinic profile, notifications, billing
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general'); // clinic | notifications | billing
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'designation', 'avatar']);
        });
        Schema::dropIfExists('app_settings');
    }
};
