<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Referral source type: 'existing_patient' | 'other' | null (no referral)
            if (!Schema::hasColumn('patients', 'referral_type')) {
                $table->string('referral_type', 30)->nullable()->after('source');
            }

            // If referred by an existing patient — FK to patients.id
            if (!Schema::hasColumn('patients', 'referred_patient_id')) {
                $table->unsignedBigInteger('referred_patient_id')->nullable()->after('referral_type');
                $table->foreign('referred_patient_id')
                      ->references('id')->on('patients')
                      ->nullOnDelete();
            }

            // If referred by someone outside the patient list
            if (!Schema::hasColumn('patients', 'referrer_name')) {
                $table->string('referrer_name', 150)->nullable()->after('referred_patient_id');
            }
            if (!Schema::hasColumn('patients', 'referrer_mobile')) {
                $table->string('referrer_mobile', 20)->nullable()->after('referrer_name');
            }
            if (!Schema::hasColumn('patients', 'referrer_type')) {
                // Doctor / Friend / Family / Staff / Corporate / Other
                $table->string('referrer_type', 50)->nullable()->after('referrer_mobile');
            }
            if (!Schema::hasColumn('patients', 'referrer_notes')) {
                $table->text('referrer_notes')->nullable()->after('referrer_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['referred_patient_id']);
            $table->dropColumn([
                'referral_type',
                'referred_patient_id',
                'referrer_name',
                'referrer_mobile',
                'referrer_type',
                'referrer_notes',
            ]);
        });
    }
};
