<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — Media Library (frozen §5.2).
 * Additive. Scoped assets: global stock = Dentfluence, clinic captures = PHI
 * (consent-gated). V1 keeps handling dumb: one row = one file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['global', 'clinic']);
            $table->enum('media_type', ['image', 'video', 'svg', 'lottie', 'model_3d']);
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->string('locale', 10)->nullable();
            // Future resolution ladder (deferred §13) — one file points at its parent.
            $table->foreignId('variant_of')->nullable()
                  ->constrained('media_assets')->nullOnDelete();
            // Required for clinic-scope PHI captures (DPDP consent reference).
            $table->string('consent_ref')->nullable();
            $table->foreignId('uploaded_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
