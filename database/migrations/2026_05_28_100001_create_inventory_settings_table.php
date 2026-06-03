<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Inventory Settings — key/value store for global thresholds and preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('label', 120);
            $table->string('type', 20)->default('text'); // text | number | boolean | select
            $table->string('group', 60)->default('general');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Seed defaults
        $now = now();
        DB::table('inventory_settings')->insert([
            [
                'key'         => 'expiry_alert_days',
                'value'       => '30',
                'label'       => 'Expiry Alert Days',
                'type'        => 'number',
                'group'       => 'alerts',
                'description' => 'Show expiry warning when stock expires within this many days.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'critical_expiry_days',
                'value'       => '7',
                'label'       => 'Critical Expiry Days',
                'type'        => 'number',
                'group'       => 'alerts',
                'description' => 'Highlight in red when stock expires within this many days.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'low_stock_notifications',
                'value'       => '1',
                'label'       => 'Low Stock Notifications',
                'type'        => 'boolean',
                'group'       => 'alerts',
                'description' => 'Show a badge on the inventory nav when any item falls below minimum quantity.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'default_gst_rate',
                'value'       => '12',
                'label'       => 'Default GST Rate (%)',
                'type'        => 'number',
                'group'       => 'purchasing',
                'description' => 'Pre-filled GST% on new purchase order lines.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'auto_generate_item_code',
                'value'       => '1',
                'label'       => 'Auto-generate Item Code',
                'type'        => 'boolean',
                'group'       => 'catalogue',
                'description' => 'Automatically generate a sequential ITEM-XXXX code when adding new items.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'stock_out_requires_location',
                'value'       => '1',
                'label'       => 'Stock Out Requires Location',
                'type'        => 'boolean',
                'group'       => 'movements',
                'description' => 'Force staff to select a source location when recording stock out.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_settings');
    }
};
