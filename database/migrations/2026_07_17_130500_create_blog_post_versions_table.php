<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 * Immutable history rows for autosave / manual save / publish snapshots.
 * `snapshot` holds the full body_json + seo + meta at that moment, so a
 * restore never has to reassemble state from other tables. Append-only:
 * created_at only, no updated_at (BlogPostVersion sets UPDATED_AT = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');

            $table->json('snapshot');

            // User who produced the snapshot (null for system/autosave jobs)
            $table->unsignedBigInteger('editor_id')->nullable();

            // 'autosave' | 'manual' | 'publish' — string, not enum, so later
            // slices can add labels without a migration
            $table->string('label', 30)->default('autosave');

            $table->timestamp('created_at')->nullable();

            $table->index(['blog_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_versions');
    }
};
