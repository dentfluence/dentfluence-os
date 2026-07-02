<?php
// =============================================================================
// Phase 1 — Lab Master: Service Catalog per Lab Vendor
//
// Each lab vendor can offer different prosthetic services at agreed prices.
// These become "financial defaults" — pre-filled rates when creating lab cases
// for this vendor. Also used in Phase 2 Lab Billing reconciliation.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_vendor_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_vendor_id')
                  ->constrained('lab_vendors')
                  ->cascadeOnDelete();

            $table->string('service_name');                             // e.g. "PFM Crown", "Zirconia Bridge"
            $table->string('category')->nullable();                     // e.g. "Crown & Bridge", "Implant"
            $table->decimal('default_rate', 10, 2)->default(0);        // agreed price per unit (₹)
            $table->string('unit')->default('per unit');                // "per unit", "per tooth", etc.
            $table->unsignedSmallInteger('turnaround_days')->nullable(); // service-specific TAT override
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['lab_vendor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_vendor_services');
    }
};
