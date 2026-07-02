<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * retention_policies (DPDP item 5.4 — data retention)
 * ---------------------------------------------------
 * Defines how long each kind of data is kept. The dry-run report uses these to
 * show what WOULD be purged. Nothing is deleted automatically — the action is
 * recorded here for when you decide to enable purging.
 *
 * Additive — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('data_type');                 // audit_logs|data_requests|breaches|consent_logs|inactive_patients
            $table->text('description')->nullable();
            $table->unsignedInteger('retain_days');      // keep records younger than this
            $table->string('action')->default('report'); // report (dry-run) | anonymise | delete
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
