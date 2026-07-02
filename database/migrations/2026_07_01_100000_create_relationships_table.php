<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Relationship Engine Foundation
 * Create the master `relationships` table.
 *
 * One record per person, forever. Neither Lead nor Patient — the entity
 * that contains both journeys across the entire lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();

            // Core identity fields — matched against leads + patients on link
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();

            // Source channel of first contact (mirrors Lead::LEAD_SOURCES keys)
            $table->enum('source', [
                'google_ads',
                'seo',
                'instagram',
                'facebook',
                'website_form',
                'whatsapp',
                'phone_call',
                'walk_in',
                'referral',
                'other',
            ])->nullable();

            // Lifecycle status
            $table->enum('status', ['active', 'dormant', 'lost'])->default('active')->index();

            // Relationship health score — recalculated by RelationshipScoreEngine (Phase 6)
            $table->unsignedInteger('score')->default(0);

            // When did this relationship start? (date of first enquiry or first visit)
            $table->date('relationship_since')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
