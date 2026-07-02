<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Link to a Purchase Order (nullable — most tasks are not PO-related)
            $table->unsignedBigInteger('po_id')->nullable()->after('patient_id');
            // Human-readable context note for vendor tasks (e.g. "PO# INV-001 — MediSupply Co.")
            $table->string('vendor_note')->nullable()->after('po_id');

            $table->foreign('po_id')
                  ->references('id')
                  ->on('purchase_orders')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['po_id']);
            $table->dropColumn(['po_id', 'vendor_note']);
        });
    }
};
