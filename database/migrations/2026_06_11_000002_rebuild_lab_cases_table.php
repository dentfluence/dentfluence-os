<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Module v2 — Rebuild lab_cases
 *
 * ⚠️ DESTRUCTIVE: drops the old lab_cases table and recreates it
 * with the full enterprise schema (confirmed: old data is disposable).
 *
 * Statuses:  draft → sent → in_progress → ready → received → delivered → closed
 * Priority:  routine | urgent | express
 * "Overdue" is COMPUTED (expected_return_date passed & not yet received),
 * never stored — keeps a single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('lab_cases');

        Schema::create('lab_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();          // LAB-2026-0001 (auto)
            $table->unsignedBigInteger('branch_id')->default(1)->index();

            // Who
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lab_vendor_id')->nullable()->constrained('lab_vendors')->nullOnDelete();
            $table->string('technician_name')->nullable();

            // What
            $table->string('work_category')->index();         // Crown & Bridge, Implant Prosthesis, ...
            $table->string('work_subtype')->nullable();
            $table->string('priority')->default('routine')->index(); // routine | urgent | express

            // Workflow
            $table->string('status')->default('draft')->index();

            // Dates (each status step gets its own date for turnaround analytics)
            $table->date('sent_date')->nullable()->index();
            $table->date('expected_return_date')->nullable()->index();
            $table->date('received_date')->nullable();
            $table->date('delivered_date')->nullable();

            // Money
            $table->decimal('lab_cost', 10, 2)->nullable();
            $table->string('payment_status')->default('pending'); // pending | paid | monthly_account

            // Expense integration — set once an expense is created; prevents duplicates
            $table->foreignId('expense_id')->nullable()
                  ->constrained('finance_expenses')->nullOnDelete();

            // Remake tracking (feeds remake-rate analytics)
            $table->boolean('is_remake')->default(false);
            $table->foreignId('remake_of_id')->nullable()
                  ->constrained('lab_cases')->nullOnDelete();

            // Text
            $table->text('instructions')->nullable();         // visible to the lab
            $table->text('internal_notes')->nullable();       // clinic-only, never printed/shared

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();                            // archive instead of delete

            // Composite indexes for the dashboard's hottest queries
            $table->index(['status', 'expected_return_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_cases');
    }
};
