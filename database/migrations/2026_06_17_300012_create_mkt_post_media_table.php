<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Images/videos attached to a post
        Schema::create('mkt_post_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');

            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 60)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes

            $table->enum('media_type', ['image', 'video', 'document'])->default('image');

            // Alt text for accessibility / Instagram alt
            $table->string('alt_text')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Optional: link to DAM asset (no FK — service layer only)
            $table->unsignedBigInteger('dam_asset_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_post_media');
    }
};
