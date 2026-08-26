<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-role DATA scope — Slice 3 of the doctor-visibility change (2026-08-26).
 *
 * `can_view` answers "may this role open the module at all". It cannot answer
 * "and how much of the data does it see", which is the question a multi-doctor
 * clinic actually asks: an associate dentist may legitimately hold
 * appointments.view while being shown only his own chair.
 *
 * Values (null = fall back to the clinic-wide default, so nothing changes on
 * deploy day and no backfill is required):
 *   all         - the whole branch.
 *   own_default - the module OPENS on the user's own records; a toggle back to
 *                 everything is offered. A view default, not a boundary.
 *   own_only    - hard boundary, enforced server-side, no toggle.
 *
 * Deliberately generic rather than `appointment_scope`: the same question is
 * coming for Consultations, Treatment Plans and Reports, and one column per
 * module-row answers it for all of them without another migration.
 *
 * Additive and reversible. No existing row changes meaning: every row is
 * created with data_scope = null, which resolves exactly as it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_module_permissions', function (Blueprint $table) {
            $table->string('data_scope', 20)->nullable()->after('can_settings');
        });
    }

    public function down(): void
    {
        Schema::table('role_module_permissions', function (Blueprint $table) {
            $table->dropColumn('data_scope');
        });
    }
};
