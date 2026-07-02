<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Lead Source Tracking + PRM Pipeline
 *
 * Adds:
 *   lead_value   — ₹ estimated value of this lead (manual entry by staff)
 *   lead_source  — controlled enum replacing free-text `source` field
 *                  (kept for backward compat; lead_source is the canonical Phase 3 field)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {

            // ₹ value this lead represents (treatment cost estimate)
            $table->decimal('lead_value', 10, 2)->nullable()->after('urgency');
            // e.g. 45000.00 for an implant lead

            // Controlled source channel (Phase 3)
            // Values: google_ads | seo | instagram | facebook | website_form |
            //         whatsapp | phone_call | walk_in | referral | other
            $table->string('lead_source', 50)->nullable()->after('source');
            // Mirrors `source` but uses a controlled enum for analytics
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['lead_value', 'lead_source']);
        });
    }
};
