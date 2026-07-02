<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_media', function (Blueprint $table) {
            // Patient consent for marketing use
            $table->enum('consent_status', ['not_given', 'given', 'pending'])
                  ->default('pending')
                  ->after('is_marketing')
                  ->index();

            // What kind of photo this is (for marketing context)
            $table->enum('photo_type', [
                'before', 'after', 'before_after',
                'procedure', 'team', 'clinic', 'testimonial',
            ])->nullable()->after('consent_status')->index();

            // Treatment category tag (drives the marketing library filters)
            $table->enum('tag_treatment_type', [
                'implant', 'aligner', 'whitening', 'rct', 'crown',
                'smile_makeover', 'braces', 'extraction', 'veneer', 'other',
            ])->nullable()->after('photo_type')->index();

            // Replaces the raw is_marketing bool with a proper approval workflow
            $table->enum('marketing_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('tag_treatment_type')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('cms_media', function (Blueprint $table) {
            $table->dropColumn([
                'consent_status',
                'photo_type',
                'tag_treatment_type',
                'marketing_status',
            ]);
        });
    }
};
