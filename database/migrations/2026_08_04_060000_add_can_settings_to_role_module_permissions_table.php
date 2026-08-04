<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings Architecture v2 — Phase 1: permission infrastructure.
 *
 * Adds a fourth, distinct permission tier: can_settings.
 * Today "view" and "edit" are the only tiers, which means a role that can
 * edit a module's operational data (e.g. see and record billing) also
 * implicitly gets no say over that module's *configuration* (tax rate,
 * invoice numbering) unless the front-end route happens to check something
 * else. The frozen Settings Architecture v2 spec requires a distinct
 * `<module>.settings` permission so a receptionist can hold `billing.edit`
 * (record payments) without `billing.settings` (change the tax rate).
 *
 * Non-negotiable: existing permissions continue to work until migrated.
 * Backfill rule: can_settings starts equal to can_edit for every existing
 * row, so nobody's access changes on deploy day. The Clinic Owner can
 * deliberately narrow it afterward via Roles & Permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_module_permissions', function (Blueprint $table) {
            $table->boolean('can_settings')->default(false)->after('can_edit');
        });

        // Backfill: preserve current effective access exactly.
        DB::table('role_module_permissions')->update([
            'can_settings' => DB::raw('can_edit'),
        ]);
    }

    public function down(): void
    {
        Schema::table('role_module_permissions', function (Blueprint $table) {
            $table->dropColumn('can_settings');
        });
    }
};
