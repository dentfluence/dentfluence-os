<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_media', function (Blueprint $table) {
            // visit_date — used by CmsSearchService and ClinicalMedia model
            if (!Schema::hasColumn('clinical_media', 'visit_date')) {
                $table->date('visit_date')->nullable()->after('media_date');
            }

            // disk — used by model for Storage::disk($this->disk)
            if (!Schema::hasColumn('clinical_media', 'disk')) {
                $table->string('disk')->default('public')->after('watermarked_path');
            }

            // searchable_tags — model fillable uses this name (DB has 'tags')
            // Add as separate json column rather than rename, to keep backward compat
            if (!Schema::hasColumn('clinical_media', 'searchable_tags')) {
                $table->json('searchable_tags')->nullable()->after('tags');
            }

            // upload_date — model fillable uses this name
            if (!Schema::hasColumn('clinical_media', 'upload_date')) {
                $table->date('upload_date')->nullable()->after('visit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinical_media', function (Blueprint $table) {
            $cols = ['visit_date', 'disk', 'searchable_tags', 'upload_date'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('clinical_media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
