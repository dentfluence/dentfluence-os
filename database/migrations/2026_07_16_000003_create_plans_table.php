<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // e.g. os-core-solo
            $table->string('name');                    // e.g. OS Core — Solo
            $table->enum('kind', ['os_core', 'module', 'bundle', 'suite', 'growth']);
            $table->unsignedInteger('monthly_price');  // INR, whole rupees
            $table->unsignedInteger('annual_price');   // INR, whole rupees
            $table->string('description')->nullable();
            $table->json('unlocks')->nullable();       // store/module codes this plan opens; ["*"] = pro pass (all stores)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
