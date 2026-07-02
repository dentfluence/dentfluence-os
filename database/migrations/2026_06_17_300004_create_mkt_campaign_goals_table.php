<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_campaign_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');

            // Goal type
            $table->enum('goal_type', ['leads', 'appointments', 'treatments', 'revenue', 'posts', 'custom'])
                  ->default('leads');
            $table->string('custom_label')->nullable(); // used when goal_type = custom

            // Targets and actuals
            $table->decimal('target_value', 12, 2)->default(0);
            $table->decimal('actual_value', 12, 2)->default(0);

            // Optional unit label for display e.g. "patients", "INR", "posts"
            $table->string('unit', 30)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_campaign_goals');
    }
};
