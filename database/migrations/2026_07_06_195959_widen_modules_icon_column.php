<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * modules.icon was varchar(255) — too short for some of the longer SVG icon
 * paths being synced in from the sidebar (the Settings gear icon alone is
 * ~900 characters). Widen it to TEXT so the icon-sync migration doesn't
 * fail partway through (2026-07-06 — `php artisan migrate` errored with
 * "Data too long for column 'icon'" trying to set the Settings icon,
 * after several earlier rows in the same migration had already committed).
 *
 * Must run before 2026_07_06_200003_sync_module_icons_names_with_sidebar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->text('icon')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('icon')->nullable()->change();
        });
    }
};
