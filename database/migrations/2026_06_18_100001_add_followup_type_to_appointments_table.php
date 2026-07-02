<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't allow ALTER COLUMN on ENUM directly via Blueprint,
        // so we use a raw statement to add 'follow-up' to the allowed values.
        DB::statement("ALTER TABLE appointments MODIFY COLUMN `type` ENUM('consultation','treatment','follow-up') NOT NULL DEFAULT 'consultation'");
    }

    public function down(): void
    {
        // Revert: rows with 'follow-up' will become the default 'consultation'
        DB::statement("UPDATE appointments SET `type` = 'consultation' WHERE `type` = 'follow-up'");
        DB::statement("ALTER TABLE appointments MODIFY COLUMN `type` ENUM('consultation','treatment') NOT NULL DEFAULT 'consultation'");
    }
};
