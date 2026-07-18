<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 * Taxonomy table for the dedicated Blog module. Categories are created
 * before blog_posts because posts carry a nullable category_id.
 * `wp_term_id` stores the mapped WordPress term for publish-time sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->string('name');
            $table->string('slug');

            // WordPress category term id once synced (no FK — external system)
            $table->unsignedBigInteger('wp_term_id')->nullable();

            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
