<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Folder tree for marketing library (self-referencing)
        // Must be created BEFORE mkt_assets (assets reference folders)
        Schema::create('mkt_asset_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->string('name');
            $table->text('description')->nullable();

            // Self-referencing parent (null = root level)
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('color', 7)->default('#6366f1'); // hex for UI
            $table->string('icon', 50)->default('folder');   // heroicon name

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_asset_folders');
    }
};
