<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per platform per post (adapted version)
        Schema::create('mkt_post_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');

            $table->enum('platform', [
                'instagram',
                'facebook',
                'google_business',
                'whatsapp',
                'wordpress',
            ]);

            // Platform-adapted caption (may differ from master)
            $table->text('caption')->nullable();

            // Platform-specific fields as JSON:
            // Instagram: alt_text, location_tag
            // Blog: title, slug, meta_title, meta_description, excerpt
            // Google Business: offer_type, offer_start, offer_end
            // WhatsApp: template_name, template_params
            $table->json('platform_specific_meta')->nullable();

            // Status of this variant
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])
                  ->default('draft');

            // Platform's own post ID after publishing
            $table->string('external_id')->nullable();

            // Publishing result details
            $table->string('external_url')->nullable();      // live post URL
            $table->text('publish_error')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'platform']);
            $table->index('post_id');
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_post_variants');
    }
};
