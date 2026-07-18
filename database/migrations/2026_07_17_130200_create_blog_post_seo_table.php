<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 * 1:1 SEO workspace per blog post. Clinic scope is inherited via the parent
 * blog_posts row. Scores are nullable until the Wave 2 heuristics fill them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_seo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');

            $table->string('focus_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();

            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            // OG image from the Marketing DAM (mkt_assets) — no FK, mkt_* style
            $table->unsignedBigInteger('og_image_asset_id')->nullable();

            $table->unsignedTinyInteger('seo_score')->nullable();          // 0-100
            $table->unsignedTinyInteger('readability_score')->nullable();  // 0-100

            $table->boolean('noindex')->default(false);

            $table->timestamps();

            $table->unique('blog_post_id'); // enforce the 1:1
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_seo');
    }
};
