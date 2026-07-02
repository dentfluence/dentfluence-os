<?php
// =============================================================================
// Phase 1 — Lab Master: Multiple Contacts per Lab Vendor
//
// A lab may have a primary contact, a billing contact, a technical rep, etc.
// Storing them in a separate table keeps lab_vendors clean and supports
// per-contact email / WhatsApp dispatches from the Lab module.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_vendor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_vendor_id')
                  ->constrained('lab_vendors')
                  ->cascadeOnDelete();

            $table->string('name');                         // contact person name
            $table->string('role')->nullable();             // e.g. "Billing", "Technical", "Primary"
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);  // one primary contact per lab
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['lab_vendor_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_vendor_contacts');
    }
};
