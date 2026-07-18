<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 *
 * The dedicated blog entity. Content is a canonical block-JSON document in
 * `body_json` (source of truth, see App\Services\Blog\BlogBlockSchema);
 * `body_html` is a generated cache produced by BlogBlockRenderer at
 * save/publish time — never edited directly.
 *
 * No hard foreign keys, matching the mkt_* migration style (service-layer
 * integrity only): featured_asset_id → mkt_assets.id (standard bigint id PK),
 * category_id → blog_categories.id, author_id/created_by/updated_by → users.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();

            // Canonical block-JSON content + generated HTML cache
            $table->json('body_json')->nullable();
            $table->longText('body_html')->nullable();

            // Featured image from the Marketing DAM (mkt_assets)
            $table->unsignedBigInteger('featured_asset_id')->nullable();

            $table->unsignedBigInteger('category_id')->nullable();

            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])
                  ->default('draft');

            // Displayed author (a user) — nullable so imports/AI drafts work
            $table->unsignedBigInteger('author_id')->nullable();

            $table->unsignedInteger('reading_time')->nullable(); // minutes

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
            $table->index('clinic_id');
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'scheduled_at']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
