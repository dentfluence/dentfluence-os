<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reviews — reputation / review-request loop (Phase B item 2.4).
 * ----------------------------------------------------------------------------
 * One row per review request. After a visit we send the patient a WhatsApp asking
 * for feedback with a unique link (the `token`). They tap it, rate 1–5, and (if
 * happy) are routed to your public Google review page; unhappy feedback is kept
 * private/internal so it can be addressed instead of posted publicly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();

            // Unguessable token used in the public link /r/{token}.
            $table->string('token', 64)->unique();

            $table->string('channel', 20)->default('whatsapp');

            // requested | rated | expired
            $table->string('status', 20)->default('requested');

            $table->unsignedTinyInteger('rating')->nullable();   // 1–5
            $table->text('comment')->nullable();
            $table->boolean('routed_to_google')->default(false);  // happy patient sent to Google?

            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
