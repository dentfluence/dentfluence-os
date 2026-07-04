<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ReferralReward;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ReferralRewardController — credits a wallet reward to a patient for a
 * referral they made, once the referred patient has proven to be a real,
 * paying patient (>= 1 paid/partially-paid invoice).
 *
 * Manual, one-click, one-time per referral. Amount and on/off come from
 * PRE Settings (App\Models\AppSetting, group 'referral') — see
 * Relationship\SettingsController@saveReferralConfig.
 *
 * Route: POST /relationship/{id}/referral-reward  [relationship.referral-reward.store]
 */
class ReferralRewardController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function store(Request $request, int $relationshipId): RedirectResponse
    {
        $data = $request->validate([
            'referrer_patient_id' => ['required', 'integer', 'exists:patients,id'],
            'referred_patient_id' => ['required', 'integer', 'exists:patients,id'],
        ]);

        if (AppSetting::get('referral.reward_enabled', '0') !== '1') {
            return back()->with('error', 'Referral rewards are turned off in PRE Settings.');
        }

        $referredPatient = Patient::findOrFail($data['referred_patient_id']);

        if ((int) $referredPatient->referred_patient_id !== (int) $data['referrer_patient_id']) {
            return back()->with('error', 'That patient was not referred by this record.');
        }

        if (ReferralReward::where('referred_patient_id', $referredPatient->id)->exists()) {
            return back()->with('error', 'This referral has already been rewarded.');
        }

        $hasPaidInvoice = Invoice::where('patient_id', $referredPatient->id)
            ->whereIn('status', ['paid', 'partially_paid'])
            ->exists();

        if (! $hasPaidInvoice) {
            return back()->with('error', 'This referral has no paid invoice yet — reward not available.');
        }

        $amount = (float) AppSetting::get('referral.reward_amount', '500');

        DB::transaction(function () use ($amount, $data, $referredPatient, $request) {
            $tx = $this->wallet->credit(
                patientId: (int) $data['referrer_patient_id'],
                amount: $amount,
                creditType: 'permanent',
                notes: 'Referral reward — referred ' . $referredPatient->name . ' (#' . $referredPatient->id . ')',
                createdBy: $request->user()?->id,
                campaignName: 'Referral Reward',
            );

            ReferralReward::create([
                'referrer_patient_id'   => $data['referrer_patient_id'],
                'referred_patient_id'   => $referredPatient->id,
                'amount'                => $amount,
                'wallet_transaction_id' => $tx->id,
                'created_by'            => $request->user()?->id,
            ]);
        });

        return back()->with('success', "Referral reward of ₹{$amount} credited to wallet.");
    }
}
