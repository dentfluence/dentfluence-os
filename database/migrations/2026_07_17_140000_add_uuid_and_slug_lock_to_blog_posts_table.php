<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Blog Marketing Hub — Wave 1 Slice 2 (ADDITIVE ONLY).
 *
 * Adds the canonical permanent identifier + slug-lock bookkeeping to
 * blog_posts. Nothing here alters or drops an existing column.
 *
 *  - `uuid`              : permanent, immutable, canonical reference used by
 *                          ALL routes/URLs/edit links and future cross-module
 *                          links (PRE, analytics, comments). The bigint `id`
 *                          stays the primary key and every same-module child
 *                          FK (seo/versions/tags/publications) keeps using it
 *                          for join efficiency — those FKs are immutable and
 *                          slug-independent, so they satisfy the "never key on
 *                          the slug" intent without paying a uuid-join cost.
 *  - `slug_locked`       : authoritative flag flipped true at first publish.
 *                          Once true, the normal update path ignores slug
 *                          changes (a URL is a promise once it is live).
 *  - `first_published_at`: timestamp of the very first publish, for auditing
 *                          and as a defensive secondary signal for the lock.
 *
 * `uuid` is added nullable + unique so the backfill can run safely; the model
 * generates it on create (boot hook) and treats it as immutable thereafter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            // Nullable at the DB layer so the backfill below can populate it;
            // the model guarantees a value on every create from here on.
            $table->char('uuid', 36)->nullable()->unique()->after('id');

            $table->boolean('slug_locked')->default(false)->after('slug');

            $table->timestamp('first_published_at')->nullable()->after('published_at');
        });

        // Backfill any pre-existing rows (there are none in practice, but this
        // is safe and idempotent). Chunked to avoid loading a large table.
        DB::table('blog_posts')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('blog_posts')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // Backfill slug_locked for any already-published rows so an existing
        // live post can never have its URL silently changed after this deploy.
        DB::table('blog_posts')
            ->where('status', 'published')
            ->orWhereNotNull('published_at')
            ->update(['slug_locked' => true]);
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'slug_locked', 'first_published_at']);
        });
    }
};
