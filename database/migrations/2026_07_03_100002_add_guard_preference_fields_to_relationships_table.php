<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Communication Guard: Preference + Do-Not-Contact
 *
 * Adds the two fields the Guard's "Preference" factor and "channel
 * eligibility" check need, neither of which existed anywhere in the schema
 * before this (confirmed by search — no preferred_channel / do_not_contact /
 * opt_out field existed on any table).
 *
 *   - preferred_channel: informational only. The Guard logs it on every
 *     decision (for the Decision Log / explainability) but never blocks a
 *     send just because it went out on a different channel — that's a
 *     product/UX rule this migration deliberately does NOT invent.
 *   - do_not_contact: a hard, unambiguous stop. If true, the Guard blocks
 *     all channels (this one's the exception — "do not contact me" has only
 *     one reasonable interpretation).
 *
 * Both nullable/defaulted so every existing relationship is unaffected.
 * Gated behind guard.full_8factor (declared, default off) — adding the
 * columns here does not change any live behaviour by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->string('preferred_channel', 20)->nullable()->after('status');
            $table->boolean('do_not_contact')->default(false)->after('preferred_channel');
        });
    }

    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->dropColumn(['preferred_channel', 'do_not_contact']);
        });
    }
};
