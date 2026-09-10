<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N-10 — the desk card's three facts, structured.
 *
 * `message` stays exactly as it is: the one-line fallback the bell list, the
 * phone and a push body read. `payload` carries the same facts as fields, so
 * the popup can show reception what to COLLECT, what to DO and when to BOOK
 * as three labelled lines instead of a paragraph nobody finishes reading.
 *
 * Nullable, additive, no backfill: rows written before this simply have no
 * payload and the card falls back to the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
