<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice Notes — Phase 1 (foundation)
 * ----------------------------------------------------------------------------
 * One row = one voice recording made during a visit, plus everything the local
 * AI pipeline produces from it (transcript + structured clinical notes).
 *
 * It's POLYMORPHIC: a voice note can attach to a Consultation, a TreatmentVisit,
 * or a Patient (general note) via the noteable_id / noteable_type pair. This is
 * the same pattern the app already uses for audit logs, so we build it once and
 * reuse it everywhere.
 *
 * Audio is patient PHI, so it is stored on the PRIVATE 'local' disk — never the
 * public disk. Only logged-in staff can stream it back through a controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_notes', function (Blueprint $table) {
            $table->id();

            // ── What this note is attached to (polymorphic) ──────────────────
            // nullableMorphs so a recording can exist briefly before it's linked.
            $table->nullableMorphs('noteable'); // noteable_id + noteable_type

            // Always know the patient, even when noteable is a consultation/visit.
            // Makes "all voice notes for this patient" a simple, fast query.
            $table->foreignId('patient_id')
                  ->nullable()
                  ->constrained('patients')
                  ->nullOnDelete();

            // ── Audio file (stored on the private 'local' disk) ──────────────
            $table->string('disk')->default('local');
            $table->string('audio_path')->nullable();     // relative path on the disk
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();       // e.g. audio/webm, audio/mpeg
            $table->unsignedBigInteger('file_size')->nullable();   // bytes
            $table->unsignedInteger('duration_seconds')->nullable();

            // ── AI pipeline output ───────────────────────────────────────────
            $table->string('language', 16)->default('en')->nullable();
            $table->longText('transcript')->nullable();    // raw text from Whisper
            $table->json('structured_notes')->nullable();  // GPT/Llama-extracted fields
            $table->string('transcribe_model')->nullable();// e.g. faster-whisper:small
            $table->string('analyze_model')->nullable();   // e.g. llama3.1:8b

            // ── Pipeline state machine ───────────────────────────────────────
            // uploaded → transcribing → transcribed → analyzing → ready → saved
            // (any step can land on 'failed')
            $table->string('status')->default('uploaded');
            $table->text('error_message')->nullable();

            // True once the reviewed notes have been committed into the parent
            // record (consultation fields, visit notes, etc.).
            $table->boolean('saved_to_record')->default(false);

            // ── Audit ────────────────────────────────────────────────────────
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Helpful indexes for listing/filtering
            $table->index('status');
            $table->index('saved_to_record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_notes');
    }
};
