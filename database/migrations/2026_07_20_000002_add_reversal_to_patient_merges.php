<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient Merge — un-merge readiness (slice 5).
 *
 * Stores everything a FUTURE un-merge would need but a blind reverse can't infer:
 * the master's pre-merge attributes (overwritten by reconciliation), the
 * memberships that were expired, and the pivot rows removed on collision.
 * The un-merge feature itself is deferred per the frozen design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_merges', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_merges', 'reversal')) {
                $table->json('reversal')->nullable()->after('snapshot')
                    ->comment('Master pre-merge state + expired memberships + removed pivot rows, for a future un-merge.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_merges', function (Blueprint $table) {
            if (Schema::hasColumn('patient_merges', 'reversal')) {
                $table->dropColumn('reversal');
            }
        });
    }
};
