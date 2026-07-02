<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            // Who receives this notification
            $table->unsignedBigInteger('user_id')->nullable()->index(); // null = broadcast to all
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Type for icon/colour selection: appointment, lab, inventory, payment, system
            $table->string('type', 40)->default('system');

            // Display
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('action_url', 500)->nullable(); // click-through link
            $table->string('action_label', 80)->nullable(); // e.g. "View Lab Case"

            // State
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps(); // created_at = when notification was fired
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
