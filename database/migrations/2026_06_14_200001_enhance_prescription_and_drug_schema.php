<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part A — Prescription & Drug Master Schema Enhancement
 *
 * Changes:
 *  1. prescriptions       — add `source`, expand `status` enum, add `email_sent_at` + `email_sent_count`
 *  2. rx_drugs            — add `dispensing_type`, `unit_label`, `pack_size`, `adult_dose`,
 *                           `pediatric_dose`, `allergy_tags`, `interaction_tags`
 *  3. prescription_items  — add `dispensing_type` + `unit_label` (snapshot at time of Rx)
 *  4. prescription_audit_logs — expand `action` enum with `downloaded` + `email_sent`
 */
return new class extends Migration
{
    // ── UP ──────────────────────────────────────────────────────────────────
    public function up(): void
    {
        // ── 1. prescriptions ─────────────────────────────────────────────────

        Schema::table('prescriptions', function (Blueprint $table) {
            // Source: where was this prescription generated from?
            // consultation | visit | emergency_consultation | review_visit | post_operative_visit
            $table->enum('source', [
                'consultation',
                'visit',
                'emergency_consultation',
                'review_visit',
                'post_operative_visit',
            ])->default('consultation')->after('consultation_id');

            // Email tracking (whatsapp_sent_at already exists)
            $table->timestamp('email_sent_at')->nullable()->after('whatsapp_sent_at');
            $table->integer('email_sent_count')->default(0)->after('print_count');
        });

        // Expand status enum — MySQL requires ALTER TABLE with full new definition.
        // Old: draft | finalized | cancelled
        // New: draft | issued | printed | whatsapp_sent | email_sent | revised | cancelled
        DB::statement("ALTER TABLE prescriptions MODIFY COLUMN status ENUM(
            'draft',
            'issued',
            'printed',
            'whatsapp_sent',
            'email_sent',
            'revised',
            'cancelled'
        ) NOT NULL DEFAULT 'draft'");

        // ── 2. rx_drugs ───────────────────────────────────────────────────────

        Schema::table('rx_drugs', function (Blueprint $table) {
            // Dispensing logic determines how quantity is calculated on the Rx
            // unit   → Tablet / Capsule  → qty = frequency × duration
            // pack   → Gel / Mouthwash / Toothpaste / Spray → qty = 1 (editable)
            // manual → Injection / LA Cartridge / Dressing Kit → qty entered manually
            // volume → Syrup / Suspension → volume-based
            $table->enum('dispensing_type', ['unit', 'pack', 'manual', 'volume'])
                  ->default('unit')
                  ->after('dosage_form');

            // Human-readable unit shown on Rx (Tablet, Capsule, Tube, Bottle, ml, Cartridge…)
            $table->string('unit_label')->nullable()->after('dispensing_type');

            // For pack-type drugs: default pack size shown on Rx (e.g. "10 g Tube", "100 ml Bottle")
            $table->string('pack_size')->nullable()->after('unit_label');

            // Separate adult / pediatric reference doses (default_dose field still exists for generic default)
            $table->string('adult_dose')->nullable()->after('default_dose');
            $table->string('pediatric_dose')->nullable()->after('adult_dose');

            // JSON tag arrays for safety matching
            // allergy_tags    → e.g. ["penicillin", "amoxicillin"] — matched against patient allergy records
            // interaction_tags → e.g. ["nsaid", "anticoagulant"] — matched against co-prescribed drugs
            $table->json('allergy_tags')->nullable()->after('drug_interactions_note');
            $table->json('interaction_tags')->nullable()->after('allergy_tags');
        });

        // ── 3. prescription_items ─────────────────────────────────────────────
        // Snapshot the dispensing type at the time of prescribing so quantity
        // calc remains correct even if the drug master is updated later.

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->enum('dispensing_type', ['unit', 'pack', 'manual', 'volume'])
                  ->default('unit')
                  ->after('dosage_form');

            $table->string('unit_label')->nullable()->after('dispensing_type');
        });

        // ── 4. prescription_audit_logs — expand action enum ───────────────────
        // Old: created | edited | finalized | printed | whatsapp_sent | repeated | cancelled | override
        // New: + downloaded | email_sent
        DB::statement("ALTER TABLE prescription_audit_logs MODIFY COLUMN action ENUM(
            'created',
            'edited',
            'finalized',
            'printed',
            'downloaded',
            'whatsapp_sent',
            'email_sent',
            'repeated',
            'cancelled',
            'override'
        ) NOT NULL");
    }

    // ── DOWN ─────────────────────────────────────────────────────────────────
    public function down(): void
    {
        // Revert audit log action enum
        DB::statement("ALTER TABLE prescription_audit_logs MODIFY COLUMN action ENUM(
            'created','edited','finalized','printed','whatsapp_sent','repeated','cancelled','override'
        ) NOT NULL");

        // Revert prescription_items
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn(['dispensing_type', 'unit_label']);
        });

        // Revert rx_drugs
        Schema::table('rx_drugs', function (Blueprint $table) {
            $table->dropColumn([
                'dispensing_type', 'unit_label', 'pack_size',
                'adult_dose', 'pediatric_dose',
                'allergy_tags', 'interaction_tags',
            ]);
        });

        // Revert status enum on prescriptions
        DB::statement("ALTER TABLE prescriptions MODIFY COLUMN status ENUM(
            'draft','finalized','cancelled'
        ) NOT NULL DEFAULT 'draft'");

        // Revert prescriptions columns
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['source', 'email_sent_at', 'email_sent_count']);
        });
    }
};
