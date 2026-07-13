<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Vendor Price Lists — every uploaded price list is kept (append-only),
 * not overwritten, so there's a dated history to point back to if a lab
 * disputes an old rate. The most recent row is the "current" price list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_vendor_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_vendor_id')->constrained()->cascadeOnDelete();

            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['lab_vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_vendor_price_lists');
    }
};
