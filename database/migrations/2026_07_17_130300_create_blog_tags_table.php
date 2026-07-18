<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 * Tag taxonomy (m:n with blog_posts via blog_post_tag).
 * `wp_term_id` stores the mapped WordPress tag for publish-time sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->string('name');
            $table->string('slug');

            $table->unsignedBigInteger('wp_term_id')->nullable();

            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tags');
    }
};
