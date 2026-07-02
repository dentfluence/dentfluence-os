<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_ideas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            // Optional link to a campaign (can be standalone idea)
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            // Content type suggestion
            $table->enum('content_type', ['reel', 'post', 'carousel', 'story', 'blog', 'offer', 'general'])
                  ->default('post');

            // Platforms this idea is intended for
            $table->json('platforms')->nullable(); // ["instagram","facebook"]

            // Tags (simple JSON array — not linked to mkt_asset_tags)
            $table->json('tags')->nullable(); // ["implants","whitening","festival"]

            // AI-generated or user-created
            $table->boolean('is_ai_generated')->default(false);

            // Status in the idea bank
            $table->enum('status', ['idea', 'in_progress', 'converted', 'archived'])
                  ->default('idea');

            // If converted: track what it became
            $table->string('converted_to')->nullable(); // 'post' or 'campaign'
            $table->unsignedBigInteger('converted_id')->nullable();

            // Reference image/cover (stored path)
            $table->string('cover_image')->nullable();

            // Key points (JSON list for display)
            $table->json('key_points')->nullable();

            // Internal notes
            $table->text('notes')->nullable();

            // Festival link (optional)
            $table->unsignedBigInteger('festival_date_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable(); // creator user
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'status']);
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_ideas');
    }
};
