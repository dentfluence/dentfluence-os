<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-1 — give the treatment VISIT the identity it was dropping.
 *
 * treatment_plan_items has treatment_id. invoice_items has treatment_id.
 * treatment_visit_items — the fact sitting between the promise and the bill —
 * carried only a name string, so the chain master → plan → visit → invoice
 * broke in the middle. That single gap is the root cause of the report bucket
 * with no treatment against it and the billed money with no doctor against it.
 *
 * Purely additive: two nullable columns, no backfill, no data touched. NULL
 * stays a legitimate, permanent value — a procedure the catalogue does not
 * carry can still be recorded, and the name column remains the doctor's label.
 *
 * treatment_option_id is added in the same migration rather than a second one
 * later: the column costs nothing empty, and the variant picker that fills it
 * is the next row. Its foreign key is added only if treatment_options exists,
 * because a migration must not assume a table it did not create.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_visit_items', 'treatment_id')) {
                $table->foreignId('treatment_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('treatments')
                    ->nullOnDelete();
            }
        });

        Schema::table('treatment_visit_items', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_visit_items', 'treatment_option_id')) {
                return;
            }

            if (Schema::hasTable('treatment_options')) {
                $table->foreignId('treatment_option_id')
                    ->nullable()
                    ->after('treatment_id')
                    ->constrained('treatment_options')
                    ->nullOnDelete();
            } else {
                // No table to point at yet. The column still goes in so the
                // model and the variant picker have somewhere to write; the
                // constraint follows when treatment_options is created.
                $table->unsignedBigInteger('treatment_option_id')
                    ->nullable()
                    ->after('treatment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visit_items', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_visit_items', 'treatment_option_id')) {
                if (Schema::hasTable('treatment_options')) {
                    $table->dropForeign(['treatment_option_id']);
                }
                $table->dropColumn('treatment_option_id');
            }

            if (Schema::hasColumn('treatment_visit_items', 'treatment_id')) {
                $table->dropForeign(['treatment_id']);
                $table->dropColumn('treatment_id');
            }
        });
    }
};
