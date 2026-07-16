<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — Knowledge Bank topics (frozen §5.1).
 * Global, versioned education (Dentfluence IP). NEVER carries prices,
 * discounts, brands, clinic_id, or patient data (enforced at the model layer
 * via GuardsKnowledgeBankPurity).
 *
 * `content_uuid` + `version` are carried now so a future central content-sync
 * API (deferred §13) drops in without a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_topics', function (Blueprint $table) {
            $table->id();
            $table->uuid('content_uuid')->unique();
            $table->string('slug')->unique();
            $table->enum('type', ['condition', 'procedure', 'material', 'addon']);
            $table->string('title');
            $table->string('version', 20)->default('1.0.0');   // semver
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_topics');
    }
};
