<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — KB block ↔ media pivot (frozen §5.1).
 * Media referenced by KB blocks, never inlined. `role` distinguishes primary
 * hero media from inline/thumbnail supporting assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_block_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_block_id')->constrained('kb_blocks')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->enum('role', ['primary', 'inline', 'thumbnail'])->default('inline');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kb_block_id', 'media_asset_id', 'role'], 'kb_block_media_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_block_media');
    }
};
