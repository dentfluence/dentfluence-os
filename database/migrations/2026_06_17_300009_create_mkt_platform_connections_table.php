<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_platform_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->enum('platform', [
                'instagram',
                'facebook',
                'google_business',
                'whatsapp',
                'wordpress',
                'google_analytics',
            ]);

            // Tokens stored encrypted via Laravel encrypt()/decrypt()
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            // Token metadata
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();            // comma-separated granted scopes

            // Platform-specific IDs/metadata
            $table->string('external_account_id')->nullable(); // IG user_id, FB page_id, etc.
            $table->string('external_account_name')->nullable(); // display name
            $table->string('external_account_avatar')->nullable(); // URL

            // Additional platform-specific config (JSON)
            $table->json('meta')->nullable();

            // Connection state
            $table->enum('status', ['connected', 'expired', 'error', 'disconnected'])
                  ->default('connected');
            $table->text('error_message')->nullable();

            // Last health check
            $table->timestamp('last_checked_at')->nullable();

            $table->unsignedBigInteger('connected_by')->nullable(); // user who connected
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'platform']);
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_platform_connections');
    }
};
