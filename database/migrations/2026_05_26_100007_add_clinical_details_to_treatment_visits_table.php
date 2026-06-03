<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration was a duplicate of 100006 and has been made a no-op.
     * All clinical detail columns are already added by 100006_create_treatment_visits_table.
     */
    public function up(): void
    {
        // No-op — columns were already added in migration 100006
    }

    public function down(): void
    {
        // No-op
    }
};
