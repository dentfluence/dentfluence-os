<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * consent_logs
 * ------------
 * The TAMPER-EVIDENT history of every consent event (DPDP item 5.6).
 *
 * This table is APPEND-ONLY: rows are only ever inserted, never updated or
 * deleted (the ConsentLog model blocks edits/deletes in code). Each row also
 * stores a `hash` = sha256(prev_hash + this row's content). Because every row
 * is chained to the one before it, if anyone tampers with an old row the chain
 * breaks and we can prove it — that is what "tamper-evident" means.
 *
 * We snapshot the purpose key + version + full context so the record still
 * makes sense even if a purpose is later renamed or retired.
 *
 * Additive table — safe to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index();
            $table->foreignId('consent_purpose_id')->nullable()->index();
            $table->string('purpose_key')->nullable();      // snapshot of the slug at event time
            $table->string('event');                        // granted | withdrawn | renewed | expired | updated
            $table->unsignedInteger('purpose_version')->nullable();
            $table->string('capture_method')->nullable();   // web | portal | paper | mobile | import
            $table->foreignId('captured_by')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('snapshot')->nullable();           // full context at the moment of the event
            $table->string('prev_hash', 64)->nullable();    // hash of the previous log row (chain link)
            $table->string('hash', 64);                     // sha256 fingerprint of THIS row
            $table->timestamp('created_at')->nullable();    // append-only: created_at only, no updated_at

            $table->index(['patient_id', 'consent_purpose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
