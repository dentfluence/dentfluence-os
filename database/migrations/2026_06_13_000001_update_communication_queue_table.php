<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * PRM Update — Communication Queue Schema Upgrade
 *
 * Changes:
 *  - Adds channel, comm_type, purpose, direction, next_action, move_to
 *  - Adds follow_up_date, follow_up_time
 *  - Adds created_by, last_modified_by (audit)
 *  - Maps old status values: in_progress → waiting_for_patient, completed → closed
 *  - Copies source → channel, classification → comm_type for existing rows
 *  - Drops old columns: source, type, classification
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {

            // ── New core columns ────────────────────────────────────────
            // channel: how they contacted (call|whatsapp|walk_in|referral|instagram|facebook|website|email|other)
            $table->string('channel')->default('call')->after('whatsapp_number');

            // comm_type: who they are (new_lead|existing_patient|ongoing_treatment|vendor|lab|doctor|staff|other|spam)
            $table->string('comm_type')->default('new_lead')->after('channel');

            // purpose: what they want (appointment|treatment_inquiry|price_inquiry|emergency|recall|complaint|payment|general_query|other)
            $table->string('purpose')->nullable()->after('comm_type');

            // direction: incoming or outgoing
            $table->string('direction')->default('incoming')->after('purpose');

            // next_action: what needs to happen next
            $table->string('next_action')->nullable()->after('direction');

            // move_to: routing destination after creation
            $table->string('move_to')->nullable()->after('next_action');

            // ── Follow-up scheduling ────────────────────────────────────
            $table->date('follow_up_date')->nullable()->after('due_at');
            $table->string('follow_up_time', 10)->nullable()->after('follow_up_date');

            // ── Audit ───────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable()->after('patient_id');
            $table->unsignedBigInteger('last_modified_by')->nullable()->after('created_by');
        });

        // ── Migrate existing data ──────────────────────────────────────

        // Map old status values to new ones
        DB::statement("UPDATE communication_queue SET status = 'waiting_for_patient' WHERE status = 'in_progress'");
        DB::statement("UPDATE communication_queue SET status = 'closed' WHERE status = 'completed'");

        // Copy source → channel, classification → comm_type for existing rows
        DB::statement("UPDATE communication_queue SET channel = source WHERE source IS NOT NULL AND source != ''");
        DB::statement("UPDATE communication_queue SET comm_type = classification WHERE classification IS NOT NULL AND classification != ''");

        // ── Drop old replaced columns ──────────────────────────────────
        Schema::table('communication_queue', function (Blueprint $table) {
            $table->dropColumn(['source', 'type', 'classification']);
        });
    }

    public function down(): void
    {
        // Restore dropped columns
        Schema::table('communication_queue', function (Blueprint $table) {
            $table->string('source')->default('call')->nullable();
            $table->string('type')->default('inquiry')->nullable();
            $table->string('classification')->default('new_patient')->nullable();
        });

        // Restore status values
        DB::statement("UPDATE communication_queue SET status = 'in_progress' WHERE status = 'waiting_for_patient'");
        DB::statement("UPDATE communication_queue SET status = 'completed' WHERE status = 'closed'");

        // Drop new columns
        Schema::table('communication_queue', function (Blueprint $table) {
            $table->dropColumn([
                'channel', 'comm_type', 'purpose', 'direction',
                'next_action', 'move_to',
                'follow_up_date', 'follow_up_time',
                'created_by', 'last_modified_by',
            ]);
        });
    }
};
