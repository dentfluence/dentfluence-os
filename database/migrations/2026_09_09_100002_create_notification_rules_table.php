<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N-1 — who receives which event, and how loudly.
 *
 * One row per (event, role). `role` is a Role slug ('front_desk', 'admin'…)
 * or the literal 'owner' — the person the record belongs to (the case's
 * doctor, the task's assignee), resolved per event by the caller.
 *
 * level:  off | bell | popup
 * push:   also send an FCM push to that role's phones (N-5)
 *
 * branch_id NULL = clinic-wide default. A branch row, when present, wins
 * over the NULL row for that branch — so a second branch can quieten a
 * popup without touching the first. Not used in V1.1 (single branch); the
 * column exists so the matrix never needs a migration to grow.
 *
 * NotificationCatalog carries the shipped defaults; NotificationRuleSeeder
 * copies them here. If a row is missing the dispatcher falls back to the
 * catalogue, so an unseeded install still notifies sensibly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80);
            $table->string('role', 40);
            $table->string('level', 10)->default('bell');
            $table->boolean('push')->default(false);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['event_key', 'role', 'branch_id']);
            $table->index('event_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
