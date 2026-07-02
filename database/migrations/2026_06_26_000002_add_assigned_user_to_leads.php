<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRM — Phase 2a (auto-assign)
 * ----------------------------------------------------------------------------
 * Links a lead to a REAL staff user (users.id) so auto-assign, team-performance
 * reports (Phase 5) and notifications can target an actual account.
 *
 * Additive + nullable: the existing `assigned_to` (staff name string) stays and
 * is still set alongside this, so every current view keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to_id')->nullable()->after('assigned_to');
            $table->index('assigned_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_id']);
            $table->dropColumn('assigned_to_id');
        });
    }
};
