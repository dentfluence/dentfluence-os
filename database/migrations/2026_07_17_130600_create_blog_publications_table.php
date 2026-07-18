<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog Marketing Hub — Wave 1 Slice 1 (additive only).
 *
 * Per-website publish ledger. One row per (post × publish target), written by
 * every publish/update/delete through the WebsitePublishAdapter layer (Slice 6),
 * so the UI can always show honest status and offer retry — the antidote to
 * the social pipeline's silent fake "published".
 *
 * platform_connection_id → mkt_platform_connections.id (standard bigint id PK,
 * no hard FK per mkt_* style). Null for the 'standalone' target (no live site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_publications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');

            $table->enum('target_type', ['dentfluence_static', 'wordpress', 'standalone']);

            $table->unsignedBigInteger('platform_connection_id')->nullable();

            // Remote identifiers (e.g. WordPress post ID + public URL)
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();

            $table->enum('status', ['pending', 'publishing', 'published', 'failed', 'deleted'])
                  ->default('pending');

            $table->timestamp('last_synced_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('retry_count')->default(0);

            $table->timestamps();

            $table->index(['blog_post_id', 'target_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_publications');
    }
};
