<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: asset <-> tag
        Schema::create('mkt_asset_tag_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->unique(['asset_id', 'tag_id']);
            $table->index('asset_id');
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_asset_tag_map');
    }
};
