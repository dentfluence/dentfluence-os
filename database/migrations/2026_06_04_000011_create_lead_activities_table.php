<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);        // call|whatsapp|note|followup|email|stage_change
            $table->string('label');           // "Call Done", "WhatsApp Sent", etc.
            $table->string('outcome')->nullable(); // "Interested", "Not Reachable", etc.
            $table->text('note')->nullable();
            $table->date('activity_date')->nullable();
            $table->string('activity_time', 20)->nullable();
            $table->string('by')->nullable();  // staff name who logged it

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
