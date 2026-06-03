<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op — all these columns already exist in the base patients table
        // (gender, email, address, city, state, pincode, chief_complaint,
        //  medical_alert, source, referred_by are in create_patients_table).
        // The 'dob' alias is stored as 'date_of_birth' in the base table.
    }

    public function down(): void
    {
        // No-op
    }
};