<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add digital_email to lab_vendors.
 *
 * Separate from the general email — used for sending digital work
 * (STL files, scan orders, CBCT exports, DSD files) to the lab.
 * Many labs have a dedicated inbox for digital case submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_vendors', function (Blueprint $table) {
            $table->string('digital_email')->nullable()->after('email')
                  ->comment('Email for digital file submission (STL, scan orders, DSD)');
        });
    }

    public function down(): void
    {
        Schema::table('lab_vendors', function (Blueprint $table) {
            $table->dropColumn('digital_email');
        });
    }
};
