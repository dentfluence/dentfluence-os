<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // Channel type: call, whatsapp, email, sms
            $table->enum('type', ['call', 'whatsapp', 'email', 'sms'])->default('call');

            // Direction from clinic perspective
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing');

            // Auto = triggered by schedule/system; manual = added by staff
            $table->boolean('is_auto')->default(false);

            // Status of the communication
            $table->enum('status', ['scheduled', 'sent', 'received', 'failed', 'cancelled'])->default('sent');

            // Content
            $table->string('subject')->nullable();   // used for email subject
            $table->text('message')->nullable();     // body / notes / transcript summary

            // Timing
            $table->timestamp('scheduled_at')->nullable(); // for future scheduled comms
            $table->timestamp('sent_at')->nullable();      // when actually sent/received

            // Who logged / sent it
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name')->nullable();  // denormalised display name

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_communications');
    }
};
