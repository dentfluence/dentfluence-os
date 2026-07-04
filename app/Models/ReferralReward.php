<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReferralReward — audit record for a wallet credit given to a patient for
 * referring another patient who has gone on to pay for at least one visit.
 *
 * One row per rewarded referral (unique on referred_patient_id — see
 * migration). Created by ReferralRewardController alongside the
 * WalletTransaction it pays out through.
 */
class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_patient_id', 'referred_patient_id', 'amount',
        'wallet_transaction_id', 'created_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function referrerPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referrer_patient_id');
    }

    public function referredPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referred_patient_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
