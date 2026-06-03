<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable Assets — individual physical asset tracking.
     *
     * Unlike consumables (tracked at item level), each reusable instrument
     * (drill, torque wrench, surgical kit) gets a unique row here with its
     * own lifecycle: usage count, sterilization history, maintenance schedule.
     *
     * Examples:
     *   Implant Drill #001 — 23/150 uses, sterilized 23 times, available
     *   Torque Wrench #002 — under maintenance
     *   Ratchet #003 — sterilization pending
     */
    public function up(): void
    {
        Schema::create('reusable_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 50)->unique();   // e.g. DRILL-001
            $table->string('serial_number', 80)->nullable();

            /* ── Tracking type ── */
            $table->enum('tracking_type', [
                'usage_based',
                'sterilization_based',
                'time_based',
            ])->default('usage_based');

            /* ── Usage lifecycle ── */
            $table->unsignedSmallInteger('max_usage_count')->nullable();
            $table->unsignedSmallInteger('current_usage_count')->default(0);
            $table->unsignedSmallInteger('retirement_threshold')->nullable(); // warn at this count

            /* ── Sterilization ── */
            $table->boolean('sterilization_required')->default(true);
            $table->timestamp('last_sterilized_at')->nullable();
            $table->unsignedSmallInteger('sterilization_count')->default(0);

            /* ── Maintenance ── */
            $table->unsignedSmallInteger('maintenance_interval')->nullable(); // every N uses
            $table->timestamp('last_maintained_at')->nullable();
            $table->timestamp('next_maintenance_due')->nullable();

            /* ── Status ── */
            $table->enum('status', [
                'available',
                'in_use',
                'sterilization_pending',
                'under_maintenance',
                'retired',
            ])->default('available');

            /* ── Meta ── */
            $table->date('purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reusable_assets');
    }
};
