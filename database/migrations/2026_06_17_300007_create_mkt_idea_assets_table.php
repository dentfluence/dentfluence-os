<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference images / videos attached to an idea
        Schema::create('mkt_idea_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idea_id');

            $table->string('file_path');          // storage path
            $table->string('file_name');          // original filename
            $table->string('mime_type', 60)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes

            $table->enum('asset_type', ['image', 'video', 'document', 'other'])
                  ->default('image');

            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Optional: link to DAM asset (no FK — service layer only)
            $table->unsignedBigInteger('dam_asset_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('idea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_idea_assets');
    }
};
