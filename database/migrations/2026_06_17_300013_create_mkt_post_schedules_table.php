<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_post_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');

            // Optional: specific variant to schedule (null = all variants)
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->timestamp('scheduled_at');               // when to publish

            $table->enum('status', ['pending', 'processing', 'done', 'failed'])
                  ->default('pending');

            // Laravel queue job ID — used to cancel before firing
            $table->string('job_id')->nullable();

            // Result details
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index(['status', 'scheduled_at']); // used by queue worker
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_post_schedules');
    }
};
