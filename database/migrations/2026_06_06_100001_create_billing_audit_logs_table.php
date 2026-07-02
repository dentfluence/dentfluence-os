<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_audit_logs', function (Blueprint $table) {
            $table->id();

            // What happened
            $table->string('action');           // delete | edit | cancel | restore

            // Which record was acted on (polymorphic)
            $table->string('auditable_type');   // App\Models\Invoice, Receipt, etc.
            $table->unsignedBigInteger('auditable_id');
            $table->index(['auditable_type', 'auditable_id']);

            // Human-readable context
            $table->text('reason');             // mandatory reason entered by user
            $table->string('display_ref')->nullable(); // e.g. INV-2026-00006 (for deleted records)

            // Who did it (password was verified at controller level)
            $table->unsignedBigInteger('performed_by');
            $table->foreign('performed_by')->references('id')->on('users');

            // Snapshot of the record BEFORE the action (JSON)
            $table->json('snapshot')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audit_logs');
    }
};
