<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Practice Protocols — catalog of STANDARD recurring duties keyed to a role.
     *
     * This is the "template" layer. It does NOT touch the existing `tasks` table.
     * Phase 2 will generate real tasks from active protocols. Until then this table
     * is purely a catalog and nothing runs automatically.
     *
     * Distinct from clinical `documentation_protocols` / `treatment_sops`.
     */
    public function up(): void
    {
        Schema::create('practice_protocols', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->text('description')->nullable();

            // WHO performs it — drives task assignment in Phase 2.
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();

            // null = applies to all branches.
            $table->foreignId('branch_id')
                  ->nullable()
                  ->constrained('branches')
                  ->nullOnDelete();

            // Grouping label (mirrors how the tasks board already categorises work).
            $table->enum('category', [
                'clinical', 'admin', 'lab', 'decon',
                'reception', 'maintenance', 'other',
            ])->default('admin');

            // How often this protocol becomes a task.
            $table->enum('frequency', ['once', 'daily', 'weekly', 'monthly'])
                  ->default('daily');

            // Used only when frequency = weekly (0 = Sunday … 6 = Saturday).
            $table->unsignedTinyInteger('weekday')->nullable();

            // Used only when frequency = monthly (1–28, kept safe for short months).
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // When the generated task is due each occurrence (e.g. 08:30).
            $table->time('default_due_time')->nullable();

            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])
                  ->default('medium');

            // If true, the generated task cannot be completed without proof.
            $table->boolean('requires_evidence')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Helps the Phase 2 generator query "active protocols due today".
            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_protocols');
    }
};
