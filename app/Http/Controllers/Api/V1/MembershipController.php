<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Patient;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Finance\MembershipBenefitLog;
use App\Services\MembershipBenefitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MembershipController (API v1)
 * ------------------------------
 * AOCP membership — full parity with the web flow. Enrollment runs the SAME
 * MembershipBenefitService::enrollWithFinance() used (logically) by the web
 * BillingController, so the invoice / payment / receipt / final-bill / finance
 * records are identical. Branch-scoped via the patient.
 */
class MembershipController extends ApiController
{
    private const PAYMENT_MODES = ['cash', 'upi', 'card', 'debit_card', 'netbanking', 'bank_transfer'];

    /** Active membership plan master (for the enroll form). */
    public function plans(Request $request): JsonResponse
    {
        $rows = FinanceMembershipPlan::active()
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => $this->planPayload($p));

        return $this->success([
            'plans'         => $rows,
            'payment_modes' => self::PAYMENT_MODES,
        ], '');
    }

    /** A patient's memberships (current + history) with benefits. */
    public function index(Request $request, $patient): JsonResponse
    {
        $pt = $this->findPatient($request, $patient);
        if ($pt instanceof JsonResponse) return $pt;

        // Keep statuses honest on read (mirrors the web's on-read expiry).
        FinancePatientMembership::expireStale();

        $rows = FinancePatientMembership::with('plan')
            ->where('patient_id', $pt->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($m) => $this->membershipPayload($m));

        $active = $rows->firstWhere('is_active', true);

        return $this->success([
            'active'      => $active,
            'memberships' => $rows->values(),
        ], '');
    }

    /** Benefits the patient has availed through membership. */
    public function benefitLogs(Request $request, $patient): JsonResponse
    {
        $pt = $this->findPatient($request, $patient);
        if ($pt instanceof JsonResponse) return $pt;

        $rows = MembershipBenefitLog::forPatient($pt->id)
            ->latest('availed_at')
            ->limit(100)
            ->get()
            ->map(fn ($l) => [
                'id'          => $l->id,
                'type'        => $l->benefit_type,
                'type_label'  => $l->benefit_type_label,
                'label'       => $l->benefit_label,
                'amount_saved' => (float) $l->amount_saved,
                'invoice_id'  => $l->invoice_id,
                'availed_at'  => $l->availed_at?->format('d M Y'),
            ]);

        return $this->success($rows, '');
    }

    /**
     * Active memberships in the branch — candidates a new add-on can attach to
     * (the "family head" picker). Mirrors the web members list.
     */
    public function activeMembers(Request $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;

        $rows = FinancePatientMembership::with(['plan', 'patient:id,branch_id,name,patient_id'])
            ->active()
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($m) => [
                'id'           => $m->id,
                'patient_name' => $m->patient?->name,
                'patient_code' => $m->patient?->patient_id,
                'plan'         => $m->plan?->plan_name,
                'family_name'  => $m->family_name,
                'end_date'     => $m->end_date?->format('d M Y'),
            ]);

        return $this->success($rows, '');
    }

    /** Enroll the patient into a plan (full finance chain, like web). */
    public function enroll(Request $request, $patient): JsonResponse
    {
        $pt = $this->findPatient($request, $patient);
        if ($pt instanceof JsonResponse) return $pt;

        $data = $request->validate([
            'plan_id'                   => 'required|exists:finance_membership_plans,id',
            'amount_paid'               => 'required|numeric|min:0',
            'payment_mode'              => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'family_head_membership_id' => 'nullable|exists:finance_patient_memberships,id',
            'family_name'               => 'nullable|string|max:100',
            'start_date'                => 'nullable|date|before_or_equal:today',
            // Default is now to invoice the fee as outstanding, not auto-collect it.
            // Staff must explicitly confirm payment was taken at enrollment.
            'collect_now'               => 'nullable|boolean',
        ]);

        $familyHeadId = $data['family_head_membership_id'] ?? null;
        $familyName   = trim($data['family_name'] ?? '') ?: null;
        $memberType   = $familyHeadId ? 'addon' : 'individual';

        // Same guards as the web controller for add-on enrollments.
        if ($memberType === 'addon') {
            $head = FinancePatientMembership::find($familyHeadId);
            if (! $head || ! $head->isActive()) {
                return $this->error('The selected member does not have an active membership.', [], 422);
            }
            $plan = FinanceMembershipPlan::find($data['plan_id']);
            if ($plan && $plan->isAddonModel()) {
                $currentAddons = $head->familyMembers()->where('status', 'active')->count();
                if ($currentAddons >= ($plan->max_family_members ?? 4)) {
                    return $this->error('This family has reached the maximum allowed add-on members.', [], 422);
                }
            }
            if (! $familyName) {
                $familyName = $head->family_name;
            }
        }

        $collectNow = (bool) ($data['collect_now'] ?? false);

        $result = MembershipBenefitService::enrollWithFinance(
            $pt->id,
            (int) $data['plan_id'],
            (float) $data['amount_paid'],
            $data['payment_mode'],
            $request->user()->id,
            $memberType,
            $familyHeadId ? (int) $familyHeadId : null,
            $familyName,
            $data['start_date'] ?? null,
            $collectNow
        );

        $message = $collectNow
            ? 'Membership enrolled. Receipt generated.'
            : 'Membership enrolled. Fee added to outstanding dues.';

        return $this->success([
            'membership' => $this->membershipPayload($result['membership']),
            'invoice_id' => $result['invoice']->id,
        ], $message, 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findPatient(Request $request, $id)
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($id)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }
        return $pt;
    }

    private function planPayload(FinanceMembershipPlan $p): array
    {
        return [
            'id'                  => $p->id,
            'plan_name'           => $p->plan_name,
            'description'         => $p->description,
            'price'               => (float) $p->price,
            'duration'            => $p->duration,
            'duration_label'      => $p->duration_label,
            'discount_percentage' => (float) $p->discount_percentage,
            'benefits'            => $p->getBenefitList(),
            'benefit_summary'     => $p->benefit_summary,
            'is_family'           => $p->isFamilyPlan(),
            'family_option'       => $p->family_option,
            'family_option_label' => $p->family_option_label,
            'addon_price'         => $p->addon_price !== null ? (float) $p->addon_price : null,
            'max_family_members'  => $p->max_family_members,
        ];
    }

    private function membershipPayload(FinancePatientMembership $m): array
    {
        return [
            'id'                  => $m->id,
            'plan_id'             => $m->plan_id,
            'plan'                => $m->plan?->plan_name,
            'status'              => $m->status,
            'is_active'           => $m->isActive(),
            'start_date'          => $m->start_date?->format('d M Y'),
            'end_date'            => $m->end_date?->format('d M Y'),
            'days_remaining'      => $m->days_remaining,
            'amount_paid'         => (float) $m->amount_paid,
            'member_type'         => $m->member_type,
            'family_name'         => $m->family_display_name,
            'family_member_count' => $m->active_family_member_count,
            'duration_label'      => $m->plan?->duration_label,
            'benefit_summary'     => $m->plan?->benefit_summary,
            'benefits'            => $m->plan?->getBenefitList(),
        ];
    }
}
