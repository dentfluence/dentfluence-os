<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * data_requests (DPDP item 5.2 — patient rights)
 * ----------------------------------------------
 * Every request a patient makes to exercise a DPDP right:
 *   access | correction | erasure | grievance | nominee
 *
 * Each one gets a reference, a due-date (SLA), an owner, and a resolution,
 * so the clinic can prove it handled the request within the legal timeline.
 *
 * Additive — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();              // e.g. DR-2026-0001
            $table->foreignId('patient_id')->index();
            $table->foreignId('branch_id')->nullable()->index();
            $table->string('type');                             // access|correction|erasure|grievance|nominee
            $table->string('status')->default('pending');       // pending|in_progress|completed|rejected
            $table->text('details')->nullable();                // what the patient is asking for
            $table->string('requested_via')->default('web');    // web|portal|email|phone|paper
            $table->string('requester_name')->nullable();       // if raised by someone other than the patient
            $table->timestamp('requested_at');
            $table->timestamp('due_at')->nullable();            // SLA deadline
            $table->foreignId('assigned_to')->nullable()->index();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('payload')->nullable();                // type-specific extras (nominee details, etc.)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
