<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            $table->string('name');
            $table->text('description')->nullable();

            // Status
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])
                  ->default('draft');

            // Platforms targeted (JSON array)
            $table->json('channels')->nullable();
            // e.g. ["instagram","facebook","google_business"]

            // Dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Budget
            $table->decimal('budget_total', 10, 2)->default(0);
            $table->decimal('budget_utilized', 10, 2)->default(0);

            // Visual
            $table->string('campaign_color', 7)->default('#6366f1');
            // hex used for calendar color coding

            $table->string('cover_image')->nullable();

            // Ownership
            $table->unsignedBigInteger('owner_id')->nullable(); // FK to users
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'status']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_campaigns');
    }
};
