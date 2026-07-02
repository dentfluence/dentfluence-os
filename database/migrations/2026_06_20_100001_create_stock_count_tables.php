<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Count Tables
 * ─────────────────────────────────────────────────────────────────
 * stock_count_sessions  — one record per 15-day count cycle
 * stock_count_lines     — one record per item counted in that session
 *
 * Workflow:
 *   Staff starts session → enters physical counts → submits
 *   System creates stock_adjustment movements for any variance
 *   Admin gets notified of low/critical items
 * ─────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Count Sessions ───────────────────────────────────
        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->id();

            $table->string('session_no', 30)->unique(); // e.g. SCS-2026-001
            $table->date('count_date');                 // date staff performed the count
            $table->date('next_count_due')->nullable(); // auto-set to +15 days on complete

            $table->enum('status', ['draft', 'in_progress', 'completed'])
                  ->default('draft');

            $table->unsignedInteger('items_counted')->default(0);
            $table->unsignedInteger('items_adjusted')->default(0); // items with variance != 0
            $table->unsignedInteger('low_stock_count')->default(0);
            $table->unsignedInteger('critical_stock_count')->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('started_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('completed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->index('status');
            $table->index('count_date');
        });

        // ── 2. Count Lines ──────────────────────────────────────
        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                  ->constrained('stock_count_sessions')
                  ->cascadeOnDelete();

            $table->foreignId('inventory_item_id')
                  ->constrained('inventory_items')
                  ->cascadeOnDelete();

            $table->string('category_name', 100)->nullable(); // snapshot at count time
            $table->string('product_name', 255);              // snapshot — item name may change

            $table->decimal('system_qty', 10, 2)->default(0);   // qty from DB before count
            $table->decimal('physical_qty', 10, 2)->nullable();  // what staff actually counted
            $table->decimal('variance', 10, 2)->nullable();      // physical - system (can be negative)

            // Stock status AFTER applying physical count
            $table->enum('stock_status', ['healthy', 'low', 'critical', 'out'])
                  ->nullable();

            $table->string('consumption_unit', 40)->nullable(); // e.g. piece, box
            $table->decimal('minimum_qty', 10, 2)->default(0);  // snapshot of threshold
            $table->decimal('reorder_level', 10, 2)->default(0);

            $table->text('notes')->nullable(); // staff notes per item

            // Was a stock_adjustment movement created for this line?
            $table->foreignId('stock_movement_id')
                  ->nullable()
                  ->constrained('stock_movements')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('session_id');
            $table->index('stock_status');
            $table->unique(['session_id', 'inventory_item_id']); // one line per item per session
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_count_sessions');
    }
};
