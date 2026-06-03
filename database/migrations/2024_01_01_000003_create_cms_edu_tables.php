<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_edu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();          // SVG path string or icon name
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_edu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('cms_edu_categories')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('media_type', 30);              // photo|xray|video|pdf|scan|animation
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); // for videos
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('tags')->nullable();

            // Counters (denormalised for speed)
            $table->unsignedSmallInteger('photo_count')->default(0);
            $table->unsignedSmallInteger('xray_count')->default(0);
            $table->unsignedSmallInteger('video_count')->default(0);

            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_edu_items');
        Schema::dropIfExists('cms_edu_categories');
    }
};
