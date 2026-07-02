<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand category enum to include all new task types
        DB::statement("
            ALTER TABLE tasks
            MODIFY COLUMN category ENUM(
                'clinical','admin','lab','follow_up',
                'call','whatsapp',
                'maintenance',
                'other'
            ) NOT NULL DEFAULT 'admin'
        ");

        Schema::table('tasks', function (Blueprint $table) {

            // ── Recurring / AMC fields ────────────────────────────────────────
            $table->boolean('is_recurring')->default(false)->after('category');

            // How often to repeat: e.g. 3 months, 1 week, 90 days
            $table->unsignedTinyInteger('recurrence_interval')->nullable()->after('is_recurring');
            $table->enum('recurrence_unit', ['days','weeks','months','years'])
                  ->nullable()->after('recurrence_interval');

            // ── Maintenance sub-type ─────────────────────────────────────────
            $table->enum('maintenance_type', [
                'ac_service',
                'pest_control',
                'deep_cleaning',
                'autoclave',
                'dental_chair',
                'xray_machine',
                'water_purifier',
                'fire_safety',
                'generator',
                'other',
            ])->nullable()->after('recurrence_unit');

            // ── Recurring chain tracking ─────────────────────────────────────
            // Points to the root task of this recurring chain
            $table->foreignId('parent_task_id')
                  ->nullable()
                  ->constrained('tasks')
                  ->nullOnDelete()
                  ->after('maintenance_type');

            // The next task's due date (computed on markDone, stored for quick display)
            $table->date('next_due_date')->nullable()->after('parent_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropColumn([
                'is_recurring',
                'recurrence_interval',
                'recurrence_unit',
                'maintenance_type',
                'parent_task_id',
                'next_due_date',
            ]);
        });

        // Revert category enum
        DB::statement("
            ALTER TABLE tasks
            MODIFY COLUMN category ENUM(
                'clinical','admin','lab','follow_up','other'
            ) NOT NULL DEFAULT 'admin'
        ");
    }
};
