<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N-1 — Notification system (2026-09-09).
 *
 * app_notifications was a flat inbox: one row per user (or a broadcast), a
 * title, a link. It stays that — but the dispatcher now needs to know:
 *
 *   priority        popup | bell. Popup = a human must act in the next few
 *                   minutes while the patient is still at the desk. Everything
 *                   else is bell. Only NotificationDispatcher sets this.
 *   event_key       which catalogue event produced the row ('consultation.saved').
 *   target_role     the role the rule resolved to ('front_desk'), or 'owner'.
 *   branch_id       the branch the event happened in.
 *   source_type/id  the record it points at (morph-shaped, no relation needed).
 *   group_key       event_key + source — one popup shown at the desk is ONE
 *                   thing whoever answers it; acknowledging it clears the whole
 *                   group, not just the clicker's row.
 *   dedupe_key      group_key + user — unique, so re-firing the same event for
 *                   the same record (an Invoice recalculated twice, a visit
 *                   re-saved) can never produce a second row.
 *   acknowledged_*  "Done" on a popup. is_read stays the bell's own state.
 *   push            the rule asked for a phone push (popup-level only).
 *   push_sent_at    set by the FCM sender (N-5) so a retry never double-pushes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->string('priority', 10)->default('bell')->after('type')->index();
            $table->string('event_key', 80)->nullable()->after('priority')->index();
            $table->string('target_role', 40)->nullable()->after('event_key');
            $table->unsignedBigInteger('branch_id')->nullable()->after('target_role')->index();

            $table->string('source_type', 120)->nullable()->after('branch_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);

            $table->string('group_key', 200)->nullable()->after('source_id')->index();
            $table->string('dedupe_key', 240)->nullable()->after('group_key')->unique();

            $table->timestamp('acknowledged_at')->nullable()->after('read_at');
            $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
            $table->boolean('push')->default(false)->after('acknowledged_by');
            $table->timestamp('push_sent_at')->nullable()->after('push');
            $table->index(['push', 'push_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['push', 'push_sent_at']);
            $table->dropColumn([
                'priority', 'event_key', 'target_role', 'branch_id',
                'source_type', 'source_id', 'group_key', 'dedupe_key',
                'acknowledged_at', 'acknowledged_by', 'push', 'push_sent_at',
            ]);
        });
    }
};
