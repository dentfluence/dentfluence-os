<?php
// =============================================================================
// Phase 2 — Lab Monthly Reconciliation
//
// Workflow:
//   Lab Cases → Monthly Bill → Invoice Matching → Conflict Detection
//   → Finance Expense → Voucher → Payment
//
// lab_monthly_reconciliations  — one reconciliation per vendor per billing period
// lab_reconciliation_items     — each lab case included in the reconciliation
// lab_reconciliation_events    — append-only audit trail for every status change
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop partial tables from any failed previous run (safe for local dev re-runs)
        Schema::dropIfExists('lab_reconciliation_events');
        Schema::dropIfExists('lab_reconciliation_items');
        Schema::dropIfExists('lab_monthly_reconciliations');

        /* ── Monthly Reconciliation Header ── */
        Schema::create('lab_monthly_reconciliations', function (Blueprint $table) {
            $table->id();

            // Auto-generated: REC-2026-0001
            $table->string('reconciliation_ref', 30)->unique();

            $table->foreignId('lab_vendor_id')
                  ->constrained('lab_vendors')
                  ->cascadeOnDelete();

            // Finance vendor link (for AP entry — mirrors Phase 1 pattern)
            $table->foreignId('finance_vendor_id')
                  ->nullable()
                  ->constrained('finance_vendors')
                  ->nullOnDelete();

            // Billing period
            $table->unsignedSmallInteger('billing_month');  // 1–12
            $table->unsignedSmallInteger('billing_year');

            // Amounts
            $table->decimal('our_total', 12, 2)->default(0);      // sum of lab_cost in our records
            $table->decimal('vendor_total', 12, 2)->default(0);   // amount on vendor's bill
            $table->decimal('difference', 12, 2)->default(0);     // vendor_total - our_total
            $table->decimal('agreed_amount', 12, 2)->default(0);  // final settled amount

            // Vendor bill details (entered when creating reconciliation)
            $table->string('vendor_bill_number')->nullable();
            $table->date('vendor_bill_date')->nullable();

            // Workflow status
            // draft           = being built, cases being added
            // pending_review  = submitted for review, conflicts flagged
            // approved        = amounts matched / agreed; creates Finance expense
            // paid            = Finance expense marked paid
            // disputed        = placed on hold for dispute resolution
            $table->string('status', 30)->default('draft')->index();

            // Remarks / notes
            $table->text('notes')->nullable();
            $table->text('dispute_reason')->nullable();

            // Finance sync — AP entry created on approval
            $table->foreignId('finance_expense_id')
                  ->nullable()
                  ->constrained('finance_expenses')
                  ->nullOnDelete();

            // Voucher link (created when paid)
            $table->foreignId('voucher_id')
                  ->nullable()
                  ->constrained('finance_vouchers')
                  ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['lab_vendor_id', 'billing_year', 'billing_month'], 'lmr_vendor_period_idx');
            // Note: status index already created inline via ->index() on the column definition above
        });

        /* ── Reconciliation Line Items (one per lab case) ── */
        Schema::create('lab_reconciliation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reconciliation_id')
                  ->constrained('lab_monthly_reconciliations')
                  ->cascadeOnDelete();

            $table->foreignId('lab_case_id')
                  ->constrained('lab_cases')
                  ->cascadeOnDelete();

            // Amounts at time of reconciliation (snapshot for audit)
            $table->decimal('our_amount', 10, 2)->default(0);      // lab_cost in our record
            $table->decimal('vendor_amount', 10, 2)->default(0);   // what vendor billed for this case
            $table->decimal('difference', 10, 2)->default(0);      // vendor_amount - our_amount

            // Per-case conflict resolution
            // matched   = amounts agree
            // conflict  = amounts differ
            // disputed  = flagged for follow-up
            // accepted  = accepted despite difference
            $table->string('match_status', 20)->default('matched')->index();
            $table->text('remarks')->nullable();

            // Whether this item was auto-selected or manually added
            $table->boolean('auto_selected')->default(true);

            $table->timestamps();

            $table->unique(['reconciliation_id', 'lab_case_id']); // no duplicates per reconciliation
            $table->index(['lab_case_id']);
        });

        /* ── Reconciliation Audit Events (append-only) ── */
        Schema::create('lab_reconciliation_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reconciliation_id')
                  ->constrained('lab_monthly_reconciliations')
                  ->cascadeOnDelete();

            $table->string('event_type', 50);    // created|submitted|approved|paid|disputed|note_added
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — append-only audit log
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_reconciliation_events');
        Schema::dropIfExists('lab_reconciliation_items');
        Schema::dropIfExists('lab_monthly_reconciliations');
    }
};
