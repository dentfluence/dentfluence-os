<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds clinical workflow fields to treatment_plans:
     *  - plan_uuid      : stable public identifier (safe to expose in URLs/print)
     *  - display_order  : ordering within a consultation (Option 1, Option 2 …)
     *  - accepted_at    : when patient accepted this option (null = not yet accepted)
     *
     * NOTE: disc_pct / gst_pct / units / option_rank on items are retained in the
     * database — Billing will read them. They are simply hidden from the clinical UI.
     */
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->uuid('plan_uuid')->nullable()->after('id');
            $table->unsignedTinyInteger('display_order')->default(0)->after('plan_name');
            $table->timestamp('accepted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_uuid', 'display_order', 'accepted_at']);
        });
    }
};
