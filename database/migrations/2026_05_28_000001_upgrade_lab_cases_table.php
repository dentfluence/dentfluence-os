<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session 7 — Lab Module
 * Upgrades the lab_cases table with all needed columns.
 * Uses hasColumn() guards so it's safe to run even if some columns
 * already exist from a previous manual or partial migration.
 */
return new class extends Migration
{
    /** All columns we want — in order */
    private array $columns = [
        'patient_id', 'doctor_id', 'work_type', 'work_subtype',
        'tooth_number', 'shade', 'lab_vendor', 'lab_cost',
        'sent_date', 'expected_return_date', 'received_date',
        'status', 'instructions', 'notes',
    ];

    public function up(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            $has = fn(string $col) => Schema::hasColumn('lab_cases', $col);

            if (!$has('patient_id')) {
                $table->foreignId('patient_id')
                      ->constrained()->cascadeOnDelete()
                      ->after('id');
            }

            if (!$has('doctor_id')) {
                $table->foreignId('doctor_id')
                      ->nullable()->constrained('users')->nullOnDelete()
                      ->after('patient_id');
            }

            if (!$has('work_type')) {
                $table->string('work_type')->after('doctor_id');
            }

            if (!$has('work_subtype')) {
                $table->string('work_subtype')->nullable()->after('work_type');
            }

            if (!$has('tooth_number')) {
                $table->string('tooth_number')->nullable()->after('work_subtype');
            }

            if (!$has('shade')) {
                $table->string('shade')->nullable()->after('tooth_number');
            }

            if (!$has('lab_vendor')) {
                $table->string('lab_vendor')->nullable()->after('shade');
            }

            if (!$has('lab_cost')) {
                $table->decimal('lab_cost', 10, 2)->nullable()->after('lab_vendor');
            }

            if (!$has('sent_date')) {
                $table->date('sent_date')->after('lab_cost');
            }

            if (!$has('expected_return_date')) {
                $table->date('expected_return_date')->nullable()->after('sent_date');
            }

            if (!$has('received_date')) {
                $table->date('received_date')->nullable()->after('expected_return_date');
            }

            if (!$has('status')) {
                $table->string('status')->default('sent')->after('received_date');
            }

            if (!$has('instructions')) {
                $table->text('instructions')->nullable()->after('status');
            }

            if (!$has('notes')) {
                $table->text('notes')->nullable()->after('instructions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_cases', function (Blueprint $table) {
            // Drop foreign keys first (ignore if they don't exist)
            try { $table->dropForeign(['patient_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['doctor_id']); }  catch (\Throwable $e) {}

            $existing = array_filter(
                $this->columns,
                fn($col) => Schema::hasColumn('lab_cases', $col)
            );

            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
