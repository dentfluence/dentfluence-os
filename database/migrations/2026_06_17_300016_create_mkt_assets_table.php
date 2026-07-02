<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            // Optional folder (null = root / uncategorized)
            $table->unsignedBigInteger('folder_id')->nullable();

            // Optional campaign link
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->string('name');                          // display name
            $table->string('file_path');                     // storage path
            $table->string('file_name');                     // original filename
            $table->string('mime_type', 60)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes

            $table->enum('asset_type', ['image', 'video', 'document', 'template', 'other'])
                  ->default('image');

            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();

            // Dimensions (for images/videos)
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable(); // video

            // Optional: link to DAM asset (no FK — service layer only)
            $table->unsignedBigInteger('dam_asset_id')->nullable();

            $table->boolean('is_favorite')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'folder_id']);
            $table->index(['clinic_id', 'asset_type']);
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_assets');
    }
};
