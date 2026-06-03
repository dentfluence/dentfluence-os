<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            // Treatment code (e.g. "RCT-01", "SC-02")
            $table->string('code', 30)->nullable()->after('name');

            // Calendar / UI colour (hex)
            $table->string('color', 7)->default('#6a0f70')->after('code');

            // Pricing
            $table->decimal('min_price', 10, 2)->nullable()->after('default_price');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            $table->decimal('gst_pct', 5, 2)->default(0.00)->after('max_price');

            // Display order within category
            $table->unsignedInteger('sort_order')->default(0)->after('gst_pct');

            // Soft deletes so historical plan items still resolve
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn(['code', 'color', 'min_price', 'max_price', 'gst_pct', 'sort_order', 'deleted_at']);
        });
    }
};
