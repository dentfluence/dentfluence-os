<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * MembershipController
 *
 * Admin CRUD for AOCP Membership tiers.
 * Lives under Finance Settings → Membership tab.
 * Only admins/finance roles should access these routes.
 */
class MembershipController extends Controller
{
    // -------------------------------------------------------------------------
    // Index — list all tiers
    // -------------------------------------------------------------------------

    public function index()
    {
        $plans = FinanceMembershipPlan::orderBy('price')->get();

        // --- Dashboard stats ---
        $totalPlans    = FinanceMembershipPlan::count();
        $activePlans   = FinanceMembershipPlan::where('is_active', true)->count();
        $inactivePlans = FinanceMembershipPlan::where('is_active', false)->count();

        // Total patients with an active membership (status=active AND end_date >= today)
        $totalActiveMembers = FinancePatientMembership::active()->count();

        // Revenue for current Indian financial year (April 1 → March 31)
        $fyYear  = now()->month >= 4 ? now()->year : now()->year - 1;
        $fyStart = Carbon::create($fyYear, 4, 1)->startOfDay();
        $fyEnd   = Carbon::create($fyYear + 1, 3, 31)->endOfDay();
        $membershipRevenue = FinancePatientMembership::whereBetween('created_at', [$fyStart, $fyEnd])
                                ->sum('amount_paid');

        // Active memberships expiring in the current calendar month
        $expiringThisMonth = FinancePatientMembership::where('status', 'active')
                                ->whereMonth('end_date', now()->month)
                                ->whereYear('end_date', now()->year)
                                ->count();

        return view('finance.membership.index', compact(
            'plans',
            'totalPlans', 'activePlans', 'inactivePlans',
            'totalActiveMembers', 'membershipRevenue', 'expiringThisMonth'
        ));
    }

    // -------------------------------------------------------------------------
    // Create / Store
    // -------------------------------------------------------------------------

    public function create()
    {
        return view('finance.membership.form', ['plan' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['benefits'] = $this->buildBenefitsArray($request);

        FinanceMembershipPlan::create($data);

        return redirect()->route('finance.membership.index')
                         ->with('success', 'Membership tier created.');
    }

    // -------------------------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------------------------

    public function edit(FinanceMembershipPlan $membership)
    {
        return view('finance.membership.form', ['plan' => $membership]);
    }

    public function update(Request $request, FinanceMembershipPlan $membership)
    {
        $data = $this->validated($request);
        $data['benefits'] = $this->buildBenefitsArray($request);

        $membership->update($data);

        return redirect()->route('finance.membership.index')
                         ->with('success', 'Membership tier updated.');
    }

    // -------------------------------------------------------------------------
    // Toggle active status (AJAX)
    // -------------------------------------------------------------------------

    public function toggle(FinanceMembershipPlan $membership)
    {
        $membership->update(['is_active' => !$membership->is_active]);

        return response()->json([
            'is_active' => $membership->is_active,
            'label'     => $membership->is_active ? 'Active' : 'Inactive',
        ]);
    }

    // -------------------------------------------------------------------------
    // Active Members list (AJAX JSON for slide panel)
    // -------------------------------------------------------------------------

    public function members()
    {
        $members = FinancePatientMembership::active()
            ->with(['patient', 'plan', 'familyHead.patient'])
            ->get()
            ->map(function ($m) {
                $p = $m->patient;

                // Determine the display label for the family column
                // Head: shows "Firke Family (Head)" | Addon: "Firke Family" | Individual: null
                $familyLabel = null;
                if ($m->member_type === 'head' && $m->family_name) {
                    $familyLabel = $m->family_name . ' (Head)';
                } elseif ($m->member_type === 'addon') {
                    $familyLabel = $m->family_name
                        ?? $m->familyHead?->family_name
                        ?? ($m->familyHead?->patient?->name ? $m->familyHead->patient->name . ' Family' : null);
                }

                return [
                    'enrollment_id'            => $m->id,
                    'patient_id'               => $p?->id,
                    'name'                     => $p?->name ?? '—',
                    'gender'                   => $p?->gender ?? '',
                    'age'                      => $p?->numeric_age ?? null,
                    'age_label'                => $p?->age_string ?? '—',
                    'last_visit'               => $p?->last_visit_date?->format('Y-m-d'),
                    'last_visit_fmt'           => $p?->last_visit_date?->format('d M Y') ?? 'Never',
                    'plan_name'                => $m->plan?->plan_name ?? '—',
                    'amount_paid'              => (float) $m->amount_paid,
                    'start_date'               => $m->start_date?->format('Y-m-d'),
                    'start_date_fmt'           => $m->start_date?->format('d M Y') ?? '—',
                    'end_date'                 => $m->end_date?->format('Y-m-d'),
                    'end_date_fmt'             => $m->end_date?->format('d M Y') ?? '—',
                    // Family fields
                    'member_type'              => $m->member_type,           // individual / head / addon
                    'family_name'              => $familyLabel,              // display label for member list
                    'family_head_membership_id'=> $m->family_head_membership_id,
                ];
            });

        return response()->json($members);
    }

    // -------------------------------------------------------------------------
    // Delete a patient's enrollment (no refund)
    // -------------------------------------------------------------------------

    public function destroyEnrollment(FinancePatientMembership $enrollment)
    {
        $enrollment->update(['status' => 'cancelled']);

        // Reset the denormalised columns on the patient row so the
        // patient list badge reflects the cancellation immediately.
        $enrollment->patient?->update([
            'membership_status'     => 'not_enrolled',
            'membership_expires_at' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Membership cancelled.']);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function destroy(FinanceMembershipPlan $membership)
    {
        // Don't delete if patients are enrolled
        if ($membership->patientMemberships()->where('status', 'active')->exists()) {
            return back()->with('error', 'Cannot delete — patients are enrolled in this tier.');
        }

        $membership->delete();

        return redirect()->route('finance.membership.index')
                         ->with('success', 'Membership tier deleted.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'plan_name'           => 'required|string|max:100',
            'description'         => 'nullable|string|max:500',
            'price'               => 'required|numeric|min:0',
            'duration'            => 'required|in:monthly,quarterly,half_yearly,yearly',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active'           => 'nullable|boolean',
            // Family options
            'family_option'       => 'nullable|in:none,addon,bundle',
            'addon_price'         => 'nullable|numeric|min:0',
            'max_family_members'  => 'nullable|integer|min:1|max:20',
        ]);

        // Default family_option to 'none' if not submitted
        $data['family_option'] = $data['family_option'] ?? 'none';

        // Clear addon_price when not in addon mode
        if ($data['family_option'] !== 'addon') {
            $data['addon_price'] = null;
        }

        // Clear max_family_members when no family option.
        // The DB column is NOT nullable (defaults to 4), so we remove the key
        // entirely instead of setting null — letting the column default apply.
        if ($data['family_option'] === 'none') {
            unset($data['max_family_members']);
        }

        return $data;
    }

    private function buildBenefitsArray(Request $request): array
    {
        // Free treatments — comma-separated string → array
        $freeTreatmentsRaw = $request->input('free_treatments_text', '');
        $freeTreatments = array_filter(
            array_map('trim', explode(',', $freeTreatmentsRaw))
        );

        return [
            'free_consultation' => (bool) $request->input('free_consultation', false),
            'free_xray'         => (bool) $request->input('free_xray', false),
            'free_scaling'      => (bool) $request->input('free_scaling', false),
            'discount_percent'  => (float) $request->input('benefit_discount_percent', 0),
            'free_treatments'   => array_values($freeTreatments),
            'notes'             => $request->input('benefit_notes', ''),
        ];
    }
}
