<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {

            // ── Treatment course tracking ──────────────────────────────────
            $table->string('treatment_name')->nullable()->after('procedure');   // RCT, Implant, etc.
            $table->string('current_stage')->nullable()->after('treatment_name'); // stage label
            $table->json('completed_stages')->nullable()->after('current_stage'); // array of completed stage keys
            // status already exists: started → ongoing → completed

            // ── RCT fields ────────────────────────────────────────────────
            $table->unsignedTinyInteger('rct_num_canals')->nullable();
            $table->json('rct_canal_lengths')->nullable();    // [{canal:"MB", length:"21mm"}, ...]
            $table->string('rct_file_type')->nullable();      // K-file, Rotary, Reciprocating
            $table->string('rct_irrigant')->nullable();       // NaOCl, EDTA, CHX
            $table->string('rct_obturation_method')->nullable(); // Cold lateral, Warm vertical

            // ── Implant fields ────────────────────────────────────────────
            $table->string('impl_brand')->nullable();
            $table->string('impl_size')->nullable();          // e.g. 4.0x10mm
            $table->string('impl_torque')->nullable();
            $table->string('impl_graft_used')->nullable();
            $table->string('impl_graft_brand')->nullable();
            $table->string('impl_membrane')->nullable();
            $table->string('impl_healing_collar')->nullable();

            // ── Filling fields ────────────────────────────────────────────
            $table->string('fill_material')->nullable();      // GIC, Composite, Amalgam
            $table->string('fill_shade')->nullable();

            // ── Scaling fields ────────────────────────────────────────────
            $table->string('scale_quadrants')->nullable();    // comma separated: UL,UR,LL,LR
            $table->string('scale_method')->nullable();       // Ultrasonic, Hand, Both

            // ── Extraction fields ─────────────────────────────────────────
            $table->string('ext_type')->nullable();           // Simple, Surgical
            $table->string('ext_socket')->nullable();         // Intact, Bone graft placed, etc.
            $table->boolean('ext_suture')->default(false);

            // ── Crown prep fields ─────────────────────────────────────────
            $table->string('crown_type')->nullable();         // PFM, Zirconia, Metal, Emax, PFZ
            $table->string('crown_shade')->nullable();
            $table->boolean('crown_impression')->default(false);
            $table->string('crown_temp_placed')->nullable();

            // ── Prescription (JSON — same structure as consultation Rx) ───
            $table->json('prescription_drugs')->nullable();
            $table->json('prescription_instructions')->nullable();
            $table->text('prescription_custom_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_visits', function (Blueprint $table) {
            $table->dropColumn([
                'treatment_name',
                'current_stage',
                'completed_stages',
                'rct_num_canals',
                'rct_canal_lengths',
                'rct_file_type',
                'rct_irrigant',
                'rct_obturation_method',
                'impl_brand',
                'impl_size',
                'impl_torque',
                'impl_graft_used',
                'impl_graft_brand',
                'impl_membrane',
                'impl_healing_collar',
                'fill_material',
                'fill_shade',
                'scale_quadrants',
                'scale_method',
                'ext_type',
                'ext_socket',
                'ext_suture',
                'crown_type',
                'crown_shade',
                'crown_impression',
                'crown_temp_placed',
                'prescription_drugs',
                'prescription_instructions',
                'prescription_custom_notes',
            ]);
        });
    }
};
