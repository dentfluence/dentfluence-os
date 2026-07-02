<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communication Queue table
 * Phase 1.4 — replaces dummy-queue.json stub
 *
 * Stores manually logged and system-generated communication items:
 * calls, WhatsApp messages, walk-ins, inquiries, follow-ups, recalls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_queue', function (Blueprint $table) {
            $table->id();

            // Who contacted the clinic
            $table->string('person_name');
            $table->string('phone');
            $table->string('whatsapp_number')->nullable();

            // How / what / who
            $table->string('source')->default('call');        // call, whatsapp, instagram, walkin, google, sms, facebook, other
            $table->string('type')->default('inquiry');       // callback, follow_up, inquiry, recall
            $table->string('classification')->default('new_patient'); // new_patient, existing, ongoing_case, doctor, vendor, lab, spam, other_important, other

            // State
            $table->string('status')->default('pending');     // pending, in_progress, completed
            $table->boolean('is_overdue')->default(false);
            $table->string('overdue_since')->nullable();      // human-readable e.g. "2 hours", "1 day"
            $table->string('priority')->default('medium');    // high, medium, low

            // Content
            $table->text('note')->nullable();
            $table->json('tags')->nullable();                 // ["New Patient", "Orthodontics"]

            // Assignment & scheduling
            $table->string('assigned_to')->nullable();        // staff name
            $table->string('assigned_avatar')->nullable();    // first letter avatar
            $table->timestamp('due_at')->nullable();

            // Link to patient if known
            $table->unsignedBigInteger('patient_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_queue');
    }
};
