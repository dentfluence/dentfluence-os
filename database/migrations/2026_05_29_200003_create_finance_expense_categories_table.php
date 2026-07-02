<?php
// =============================================================================
// Expense Categories — Customizable by clinic admin.
// =============================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable(); // for subcategories
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_system')->default(false); // system cats can't be deleted
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['clinic_id', 'parent_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('finance_expense_categories'); }
};
