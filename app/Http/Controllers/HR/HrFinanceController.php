<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrBonus;
use App\Models\HrIncentiveRule;
use App\Models\HrSalaryComponent;
use App\Models\HrStaffAdvance;
use App\Models\HrEntryExitLog;
use App\Models\User;
use Illuminate\Http\Request;

class HrFinanceController extends Controller
{
    /* ══════════════════════════════════════════
       SALARY COMPONENTS
    ══════════════════════════════════════════ */

    public function saveSalary(Request $request, User $user)
    {
        $request->validate([
            'basic_salary'      => 'required|numeric|min:0',
            'hra'               => 'nullable|numeric|min:0',
            'conveyance'        => 'nullable|numeric|min:0',
            'medical'           => 'nullable|numeric|min:0',
            'special'           => 'nullable|numeric|min:0',
            'pf_applicable'     => 'nullable|boolean',
            'esi_applicable'    => 'nullable|boolean',
            'professional_tax'  => 'nullable|numeric|min:0',
            'ot_multiplier'     => 'nullable|numeric|min:1|max:3',
        ]);

        $user->hrSalary()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'basic_salary'     => $request->basic_salary,
                'hra'              => $request->hra ?? 0,
                'conveyance'       => $request->conveyance ?? 0,
                'medical'          => $request->medical ?? 0,
                'special'          => $request->special ?? 0,
                'pf_applicable'    => $request->boolean('pf_applicable'),
                'esi_applicable'   => $request->boolean('esi_applicable'),
                'professional_tax' => $request->professional_tax ?? 200,
                'ot_multiplier'    => $request->ot_multiplier ?? 1.5,
            ]
        );

        return back()->with('success', 'Salary structure saved.')->withFragment('finance');
    }

    /* ══════════════════════════════════════════
       INCENTIVE RULE
    ══════════════════════════════════════════ */

    public function saveIncentive(Request $request, User $user)
    {
        $request->validate([
            'compensation_type'   => 'required|in:fixed,fixed_revenue,pure_revenue,per_patient,fixed_bonus',
            'revenue_target'      => 'nullable|numeric|min:0',
            'incentive_rate'      => 'nullable|numeric|min:0|max:100',
            'per_patient_rate'    => 'nullable|numeric|min:0',
            'minimum_guarantee'   => 'nullable|numeric|min:0',
            'target_appointments' => 'nullable|integer|min:0',
            'bonus_amount'        => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string|max:500',
        ]);

        $user->hrIncentiveRule()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only([
                'compensation_type', 'revenue_target', 'incentive_rate',
                'per_patient_rate', 'minimum_guarantee', 'target_appointments',
                'bonus_amount', 'notes',
            ])
        );

        return back()->with('success', 'Incentive rule saved.')->withFragment('finance');
    }

    /* ══════════════════════════════════════════
       ADVANCES
    ══════════════════════════════════════════ */

    public function storeAdvance(Request $request, User $user)
    {
        $request->validate([
            'reason'         => 'nullable|string|max:255',
            'principal'      => 'required|numeric|min:100',
            'given_date'     => 'required|date',
            'with_interest'  => 'nullable|boolean',
            'interest_rate'  => 'nullable|numeric|min:0|max:100',
            'tenure_months'  => 'required|integer|min:1|max:60',
            'notes'          => 'nullable|string|max:500',
        ]);

        $withInterest = $request->boolean('with_interest');
        $calc = HrStaffAdvance::calculateEmi(
            (float) $request->principal,
            (int)   $request->tenure_months,
            $withInterest ? (float) $request->interest_rate : 0
        );

        HrStaffAdvance::create([
            'user_id'        => $user->id,
            'reason'         => $request->reason,
            'principal'      => $request->principal,
            'given_date'     => $request->given_date,
            'with_interest'  => $withInterest,
            'interest_rate'  => $withInterest ? $request->interest_rate : 0,
            'tenure_months'  => $request->tenure_months,
            'emi_amount'     => $calc['emi'],
            'total_payable'  => $calc['total'],
            'amount_paid'    => 0,
            'status'         => 'active',
            'notes'          => $request->notes,
            'created_by'     => auth()->id(),
        ]);

        return back()->with('success', "Advance of ₹" . number_format($request->principal) . " recorded. EMI: ₹" . number_format($calc['emi']))->withFragment('finance');
    }

    public function closeAdvance(Request $request, User $user, HrStaffAdvance $advance)
    {
        abort_if($advance->user_id !== $user->id, 403);
        $advance->update(['status' => $request->input('action', 'closed')]);
        return back()->with('success', 'Advance marked as ' . $request->input('action', 'closed') . '.')->withFragment('finance');
    }

    /* ══════════════════════════════════════════
       BONUSES
    ══════════════════════════════════════════ */

    public function storeBonus(Request $request, User $user)
    {
        $request->validate([
            'bonus_name' => 'required|string|max:255',
            'bonus_type' => 'required|in:festival,performance,annual,joining,retention,other',
            'amount'     => 'required|numeric|min:1',
            'bonus_date' => 'required|date',
            'month_year' => 'nullable|string|max:7',
            'notes'      => 'nullable|string|max:500',
        ]);

        HrBonus::create([
            'user_id'    => $user->id,
            'bonus_name' => $request->bonus_name,
            'bonus_type' => $request->bonus_type,
            'amount'     => $request->amount,
            'bonus_date' => $request->bonus_date,
            'month_year' => $request->month_year,
            'notes'      => $request->notes,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', "Bonus \"{$request->bonus_name}\" of ₹" . number_format($request->amount) . " saved.")->withFragment('finance');
    }

    public function destroyBonus(User $user, HrBonus $bonus)
    {
        abort_if($bonus->user_id !== $user->id, 403);
        $bonus->delete();
        return back()->with('success', 'Bonus entry deleted.')->withFragment('finance');
    }

    /* ══════════════════════════════════════════
       QR ENTRY / EXIT SCAN
    ══════════════════════════════════════════ */

    /** Mobile-friendly scan page (no auth required — uses QR token) */
    public function scanPage()
    {
        $staffList = User::with('hrProfile')
            ->where('is_active', true)
            ->orderByRaw("FIELD(role, 'doctor') DESC")
            ->orderBy('name')
            ->get();

        return view('hr.scan', compact('staffList'));
    }

    /** Handle QR scan or manual tap — logs entry/exit */
    public function logScan(Request $request)
    {
        $request->validate([
            'qr_token' => 'required|string',
            'type'     => 'required|in:entry,exit',
        ]);

        $profile = \App\Models\HrStaffProfile::where('qr_token', $request->qr_token)->firstOrFail();
        $user    = $profile->user;

        // Prevent duplicate entry/exit within 5 minutes
        $recent = HrEntryExitLog::where('user_id', $user->id)
            ->where('type', $request->type)
            ->where('logged_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recent) {
            return response()->json(['ok' => false, 'message' => 'Already logged recently. Wait 5 minutes.'], 422);
        }

        HrEntryExitLog::create([
            'user_id'    => $user->id,
            'type'       => $request->type,
            'logged_at'  => now(),
            'method'     => 'qr_scan',
            'ip_address' => $request->ip(),
        ]);

        // Also mark attendance as present for today if entry
        if ($request->type === 'entry') {
            $user->hrAttendance()->updateOrCreate(
                ['date' => today()],
                ['status' => 'present', 'check_in' => now()->format('H:i')]
            );
        } elseif ($request->type === 'exit') {
            $att = $user->hrAttendance()->where('date', today())->first();
            if ($att) $att->update(['check_out' => now()->format('H:i')]);
        }

        return response()->json([
            'ok'      => true,
            'name'    => $user->name,
            'type'    => $request->type,
            'time'    => now()->format('h:i A'),
            'message' => ucfirst($request->type) . ' logged for ' . $user->name,
        ]);
    }
}
