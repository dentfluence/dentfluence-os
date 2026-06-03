<?php
// =============================================================================
// Finance Audit Log — Immutable trail of every change. No hard deletes.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->string('model_type');               // e.g. App\Models\Finance\Expense
            $table->unsignedBigInteger('model_id');
            $table->enum('action', ['created','updated','cancelled','voided','approved','rejected']);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('performed_at');

            $table->index(['model_type', 'model_id']);
            $table->index(['clinic_id', 'performed_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_audit_log'); }
};
