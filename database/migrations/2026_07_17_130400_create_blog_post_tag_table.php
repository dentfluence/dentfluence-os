<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 * Pivot: blog_posts ⇄ blog_tags. Clinic scope inherited via parents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('blog_tag_id');

            $table->timestamps();

            $table->unique(['blog_post_id', 'blog_tag_id']);
            $table->index('blog_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
    }
};
