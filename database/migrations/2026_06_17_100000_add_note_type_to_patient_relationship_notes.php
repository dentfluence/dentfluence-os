<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_relationship_notes', function (Blueprint $table) {
            // Supports: internal, call, whatsapp, email, sms
            $table->string('note_type', 30)->default('internal')->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('patient_relationship_notes', function (Blueprint $table) {
            $table->dropColumn('note_type');
        });
    }
};
