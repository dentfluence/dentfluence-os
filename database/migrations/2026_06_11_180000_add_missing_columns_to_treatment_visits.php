<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add columns that the TreatmentVisit model expects but were never migrated:
     *   - notes            (general visit notes; original migration used 'clinical_notes')
     *   - chief_complaint
     *   - next_visit_date
     *   - next_visit_type
     *
     * Also fixes the 'status' enum to match the values the controller actually uses:
     *   scheduled | in_chair | completed | cancelled | no_show
     */
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_visits', 'notes')) {
                $table->text('notes')->nullable()->after('tooth_number');
            }

            if (! Schema::hasColumn('treatment_visits', 'chief_complaint')) {
                $table->string('chief_complaint', 500)->nullable()->after('notes');
            }

            if (! Schema::hasColumn('treatment_visits', 'next_visit_date')) {
                $table->date('next_visit_date')->nullable()->after('chief_complaint');
            }

            if (! Schema::hasColumn('treatment_visits', 'next_visit_type')) {
                $table->string('next_visit_type', 100)->nullable()->after('next_visit_date');
            }
        });

        // Fix the status enum — original had: started, ongoing, completed, abandoned
        // Controller expects:              scheduled, in_chair, completed, cancelled, no_show
        // We keep ALL values so existing rows don't break, and add new ones.
        DB::statement("
            ALTER TABLE treatment_visits
            MODIFY COLUMN status ENUM(
                'scheduled','in_chair','completed','cancelled','no_show',
                'started','ongoing','abandoned'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('treatment_visits', 'notes')           ? 'notes'           : null,
                Schema::hasColumn('treatment_visits', 'chief_complaint') ? 'chief_complaint' : null,
                Schema::hasColumn('treatment_visits', 'next_visit_date') ? 'next_visit_date' : null,
                Schema::hasColumn('treatment_visits', 'next_visit_type') ? 'next_visit_type' : null,
            ]));
        });

        // Revert enum to original values
        DB::statement("
            ALTER TABLE treatment_visits
            MODIFY COLUMN status ENUM('started','ongoing','completed','abandoned')
            NOT NULL DEFAULT 'started'
        ");
    }
};
