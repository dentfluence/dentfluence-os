<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marketing-scoped event log (overview feed, audit trail)
        Schema::create('mkt_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            // Actor
            $table->unsignedBigInteger('user_id')->nullable(); // null = system action

            // Event type
            $table->string('event', 100);
            // e.g. post_published, campaign_created, idea_converted, platform_connected

            // Polymorphic subject (what the event is about)
            $table->string('subject_type')->nullable();   // App\Models\Marketing\Campaign
            $table->unsignedBigInteger('subject_id')->nullable();

            // Human-readable description
            $table->string('description');

            // Extra context (JSON)
            $table->json('properties')->nullable();

            $table->timestamp('occurred_at')->useCurrent();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_activity_log');
    }
};
