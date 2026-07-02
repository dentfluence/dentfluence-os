<?php
// =============================================================================
// Billing Tables — Phase 2.1
// Creates: invoices, invoice_items, invoice_payments
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Invoices ─────────────────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();   // e.g. INV-2026-00001
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Totals
            $table->decimal('subtotal', 12, 2)->default(0);       // sum of items before disc/gst
            $table->decimal('discount_pct', 5, 2)->default(0);    // overall invoice-level discount %
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0); // subtotal - discount
            $table->decimal('gst_amount', 12, 2)->default(0);     // total GST
            $table->decimal('total_amount', 12, 2)->default(0);   // final payable
            $table->decimal('paid_amount', 12, 2)->default(0);    // sum of payments
            $table->decimal('balance_due', 12, 2)->default(0);    // total - paid

            // Status
            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'cancelled', 'refunded'])
                  ->default('draft');

            // Optional links
            $table->unsignedBigInteger('treatment_plan_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index('invoice_date');
        });

        // ── Invoice Items ─────────────────────────────────────────────────────
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description', 200);          // treatment / product name
            $table->string('tooth_number', 20)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedSmallInteger('qty')->default(1);
            $table->decimal('disc_pct', 5, 2)->default(0);
            $table->decimal('disc_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);  // (unit_price * qty) - disc
            $table->decimal('gst_pct', 5, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);       // net + gst

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id');
        });

        // ── Invoice Payments ──────────────────────────────────────────────────
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('payment_mode', ['cash', 'card', 'upi', 'cheque', 'netbanking', 'emi', 'other'])
                  ->default('cash');
            $table->date('payment_date');
            $table->string('reference_no', 100)->nullable();  // UPI txn ID / cheque no
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['invoice_id']);
            $table->index(['patient_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
