<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured SOP per treatment.
     * One active SOP per treatment at a time; versioning tracks history.
     */
    public function up(): void
    {
        Schema::create('treatment_sops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();

            // Version & lifecycle
            $table->unsignedSmallInteger('version')->default(1);
            $table->enum('status', ['draft', 'active', 'under_review', 'archived'])->default('draft');

            // ── Structured SOP fields ──────────────────────────────────────────
            // Each field is a JSON array of step strings for ordered checklists,
            // or plain text for instructions.

            $table->json('doctor_steps')->nullable();        // ["Step 1...", "Step 2..."]
            $table->json('assistant_steps')->nullable();     // Chairside / assistant prep checklist
            $table->text('pre_instructions')->nullable();    // Patient pre-care (shown before visit)
            $table->text('post_instructions')->nullable();   // Patient post-care (shown after visit)
            $table->text('clinical_notes')->nullable();      // Internal clinical notes / tips for doctors
            $table->text('consent_notes')->nullable();       // What to explain to patient before consent

            // ── Review tracking ───────────────────────────────────────────────
            $table->date('last_reviewed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_sops');
    }
};
