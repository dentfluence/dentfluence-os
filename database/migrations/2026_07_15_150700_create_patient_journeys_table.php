<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case Acceptance Engine — patient journey instance (frozen §5.4).
 * Journey-shaped spine (journey_type/phase) but ONLY case_acceptance is used
 * in V1; no orchestration engine. `token` is the NATIVE public token (the
 * engine mirrors the PublicPresentationController pattern, not the
 * PresentationAccessToken table — see implementation plan Phase 2 §2.2).
 * Edit-after-send SUPERSEDES via `superseded_by`, never mutates (§6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_journeys', function (Blueprint $table) {
            $table->id();
            $table->enum('journey_type', ['case_acceptance'])->default('case_acceptance');
            $table->foreignId('treatment_plan_id')->constrained('treatment_plans')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('relationship_id')->nullable()
                  ->constrained('relationships')->nullOnDelete();
            $table->foreignId('decision_tree_id')->constrained('decision_trees')->restrictOnDelete();

            $table->string('token', 64)->nullable()->unique();   // null until sent
            $table->enum('delivery_mode', ['chairside', 'take_home', 'both'])->default('chairside');
            $table->enum('cost_visibility', ['full', 'starting_from', 'hidden_until_booking'])
                  ->default('full');
            $table->enum('phase', ['education', 'accepted', 'pre_op', 'post_op', 'recall', 'maintenance'])
                  ->default('education');   // only education/accepted used in V1
            $table->enum('status', ['draft', 'sent', 'viewed', 'accepted', 'declined', 'follow_up'])
                  ->default('draft');

            $table->string('pinned_kb_version', 20)->nullable();
            $table->string('pinned_tree_version', 20)->nullable();
            $table->foreignId('superseded_by')->nullable()
                  ->constrained('patient_journeys')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['treatment_plan_id', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_journeys');
    }
};
