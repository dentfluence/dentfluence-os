<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient Merge — Phase 1, Slice 1 (schema foundation).
 *
 * Adds archive/redirect fields to `patients` (merged_into_id / merged_at /
 * merged_by) and a `patient_merges` audit + reversibility record. The
 * patient-level merge ORCHESTRATES the existing Relationship\MergeService for
 * the relationship_id cascade, so patient_merges links to relationship_merges.
 *
 * Additive only. No data is moved by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'merged_into_id')) {
                $table->unsignedBigInteger('merged_into_id')->nullable()->after('deactivated_by')
                    ->comment('If set, this record was merged into patient #id and is archived; requests redirect to the master.');
                $table->timestamp('merged_at')->nullable()->after('merged_into_id');
                $table->unsignedBigInteger('merged_by')->nullable()->after('merged_at');
                $table->index('merged_into_id');
            }
        });

        if (! Schema::hasTable('patient_merges')) {
            Schema::create('patient_merges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('surviving_patient_id');
                $table->unsignedBigInteger('merged_patient_id');
                $table->unsignedBigInteger('relationship_merge_id')->nullable()
                    ->comment('FK to relationship_merges — the delegated relationship-cascade half.');
                $table->string('reason', 500)->nullable();
                $table->json('field_choices')->nullable()->comment('Winning value per conflicting demographic field.');
                $table->json('reassignments')->nullable()->comment('{ table: [row ids moved] } for patient_id children.');
                $table->json('wallet_transfer')->nullable()->comment('Wallet sum/transfer detail.');
                $table->string('retired_patient_id', 30)->nullable()->comment('Loser human patient_id, retained as an alias.');
                $table->json('snapshot')->nullable()->comment('Merged (loser) patient attributes at merge time.');
                $table->unsignedBigInteger('merged_by')->nullable();
                $table->timestamp('undone_at')->nullable();
                $table->timestamps();

                $table->index('surviving_patient_id');
                $table->index('merged_patient_id');
                $table->index('relationship_merge_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_merges');

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'merged_into_id')) {
                $table->dropIndex(['merged_into_id']);
                $table->dropColumn(['merged_into_id', 'merged_at', 'merged_by']);
            }
        });
    }
};
