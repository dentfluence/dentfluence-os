<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches table — multi-branch support foundation.
     * Must run before appointments, huddle_boards, and any table with branch_id FK.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique()->nullable();   // e.g. MAIN, BR-2
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert the default single-branch clinic so branch_id = 1 always resolves
        DB::table('branches')->insert([
            'id'         => 1,
            'name'       => 'Main Clinic',
            'code'       => 'MAIN',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
