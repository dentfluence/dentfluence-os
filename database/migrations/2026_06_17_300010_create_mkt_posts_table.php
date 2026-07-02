<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');

            // Optional campaign link
            $table->unsignedBigInteger('campaign_id')->nullable();

            // Master content (platform-agnostic)
            $table->string('title')->nullable();             // internal label
            $table->text('caption');                         // master caption text
            $table->enum('content_type', ['reel', 'post', 'carousel', 'story', 'blog', 'offer'])
                  ->default('post');

            $table->json('platforms')->nullable();           // platforms to publish on
            $table->json('hashtags')->nullable();            // ["#smile","#dental"]

            // CTA
            $table->string('cta_type', 50)->nullable();
            // book_appointment|learn_more|call_now|custom
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url')->nullable();

            // AI quality score (0-100)
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->json('ai_score_notes')->nullable();      // checklist of score reasons

            // Workflow status
            $table->enum('status', [
                'draft',
                'pending',       // waiting for approval
                'approved',
                'scheduled',
                'published',
                'failed',
            ])->default('draft');

            $table->text('rejection_reason')->nullable();    // if pending → rejected

            // Assignee (team member responsible for creating)
            $table->unsignedBigInteger('assignee_id')->nullable();

            // Optional festival link
            $table->unsignedBigInteger('festival_date_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['clinic_id', 'status']);
            $table->index('campaign_id');
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_posts');
    }
};
