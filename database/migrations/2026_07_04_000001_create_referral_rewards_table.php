<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per referral that has actually been rewarded. Kept as its own
 * audit table (rather than columns on `patients`) so a reward is always
 * traceable to the wallet transaction that paid it, and so this can grow
 * into a standalone Referral Program module later without another
 * migration on the patients table.
 *
 * A referral can only be rewarded once — enforced by the unique index on
 * referred_patient_id, not just application logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_patient_id'); // who receives the reward
            $table->unsignedBigInteger('referred_patient_id'); // the patient they referred
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('referred_patient_id');

            $table->foreign('referrer_patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('referred_patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('wallet_transaction_id')->references('id')->on('wallet_transactions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
