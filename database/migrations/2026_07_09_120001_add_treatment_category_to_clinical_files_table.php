<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinical Library Slice 2 — structured treatment category.
 *
 * `procedure` stays free text (kept for search + display). This adds a
 * fixed-vocabulary companion column so files can be reliably filtered by
 * treatment type years later, regardless of how `procedure` was typed.
 * Vocabulary lives on ClinicalFile::TREATMENT_CATEGORIES.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->string('treatment_category', 30)
                ->nullable()
                ->after('procedure')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_files', function (Blueprint $table) {
            $table->dropColumn('treatment_category');
        });
    }
};
