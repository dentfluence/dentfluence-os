<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — B2B Communication Module
 *
 * Extends communication_queue to support non-patient contacts:
 *   contact_type  — who this comm is with (patient|lab|vendor|consultant)
 *   contact_id    — FK to the relevant entity (patient_id, lab_vendor_id, finance_vendor_id, etc.)
 *   b2b_subtype   — the specific purpose within B2B (lab_case_status|vendor_followup|referral_note|maintenance)
 *   lab_case_id   — direct FK to lab_cases when this comm tracks a specific lab case
 *
 * patient_id remains for patient comms; contact_type=patient means use patient_id.
 * For B2B, contact_id holds the entity PK; contact_type tells which table to look in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {

            // ── Contact polymorphism ─────────────────────────────────────
            $table->string('contact_type')->default('patient')->after('patient_id');
            // patient | lab | vendor | consultant

            $table->unsignedBigInteger('contact_id')->nullable()->after('contact_type');
            // FK value; which table to join depends on contact_type

            // ── B2B subtype ──────────────────────────────────────────────
            $table->string('b2b_subtype')->nullable()->after('contact_id');
            // lab_case_status | vendor_followup | vendor_order | consultant_referral
            // consultant_feedback | maintenance | service | other

            // ── Direct lab case link ─────────────────────────────────────
            $table->unsignedBigInteger('lab_case_id')->nullable()->after('b2b_subtype');
            // When set, comm auto-closes when the linked lab_case reaches received/delivered/closed

            // Index for fast B2B queries
            $table->index(['contact_type', 'contact_id'], 'idx_comm_contact');
            $table->index('lab_case_id', 'idx_comm_lab_case');
        });
    }

    public function down(): void
    {
        Schema::table('communication_queue', function (Blueprint $table) {
            $table->dropIndex('idx_comm_contact');
            $table->dropIndex('idx_comm_lab_case');
            $table->dropColumn(['contact_type', 'contact_id', 'b2b_subtype', 'lab_case_id']);
        });
    }
};
