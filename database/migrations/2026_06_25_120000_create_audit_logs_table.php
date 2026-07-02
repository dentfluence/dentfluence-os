<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log table.
 * Records every Create / Update / Delete on auditable models:
 * who did it, what changed (before/after), and from which device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();   // who did it (null = system)
            $table->string('action');                            // created | updated | deleted
            $table->string('auditable_type');                    // the model class
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('module')->nullable();                // e.g. "patients", "users"
            $table->json('old_values')->nullable();              // values before the change
            $table->json('new_values')->nullable();              // values after the change
            $table->string('device_type')->nullable();           // web | android | ios | api
            $table->string('ip_address', 45)->nullable();        // future use
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
