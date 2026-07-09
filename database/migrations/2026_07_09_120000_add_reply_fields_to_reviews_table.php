<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds clinic-reply fields to the existing `reviews` table (Communication
 * module). Built for the Marketing Reviews screen's "Reply" action —
 * internal note only for now (docs/marketing-module-reengineering-plan.md,
 * V2). Purely additive/nullable; does not touch any existing column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('clinic_reply')->nullable()->after('comment');
            $table->timestamp('replied_at')->nullable()->after('responded_at');
            $table->unsignedBigInteger('replied_by_id')->nullable()->after('replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['clinic_reply', 'replied_at', 'replied_by_id']);
        });
    }
};
