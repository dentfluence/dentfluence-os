<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * data_breaches (DPDP item 5.3 — breach notification)
 * ---------------------------------------------------
 * A register of personal-data breaches. DPDP requires breaches to be reported
 * to the Data Protection Board and affected patients notified, so we track
 * those two milestones (reported_to_board_at, patients_notified_at) and a
 * simple status lifecycle.
 *
 * Additive — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();              // BR-2026-0001
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');      // low|medium|high|critical
            $table->text('nature')->nullable();                 // what kind of data / how it happened
            $table->string('affected_scope')->nullable();       // e.g. "approx 120 patient records"
            $table->unsignedInteger('affected_count')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('discovered_at');
            $table->string('status')->default('open');          // open|contained|reported|closed
            $table->timestamp('reported_to_board_at')->nullable();
            $table->string('board_reference')->nullable();
            $table->timestamp('patients_notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
