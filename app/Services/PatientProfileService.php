<?php

namespace App\Services;

use App\Models\BillingPrompt;
use App\Models\ClinicalFile;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Finance\MembershipBenefitLog;
use App\Models\Invoice;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Patient;
use App\Models\PatientRelationshipNote;
use App\Models\Task;
use App\Models\TreatmentOpportunity;
use App\Models\Wallet;
use App\Models\Prescription\Prescription;
use App\Services\MembershipBenefitService;
use Illuminate\Support\Facades\Auth;

class PatientProfileService
{
    /**
     * Load everything the Patient Profile screen needs in one place.
     */
    public function loadProfile(Patient $patient): array
    {
        $patient->load([
            'appointments.treatment',
            'appointments.treatmentCategory',
            'relationshipNotes.author',
            'opportunities.author',
            'alerts',
            'treatmentVisits.doctor',
            'treatmentVisits.visitItems',
            'treatmentVisits.implantPlacement',
            'treatmentPlans.items',
            'treatmentPlans.creator',
            'consultations',
        ]);

        // Recall task — pending follow_up task for this patient (for the recall card)
        $recallTask = Task::where('patient_id', $patient->id)
            ->where('category', 'follow_up')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('title', 'like', '%recall%')
                  ->orWhere('title', 'like', '%6-month%')
                  ->orWhere('title', 'like', '%6 month%');
            })
            ->orderBy('due_date')
            ->first();

        // Phase 7E — Clinical Files for the Documents tab
        // Eager-loaded separately (not via $patient->load()) so we can order
        // by captured_at and include visit + uploader without conflicting with
        // the existing 'documents' eager load above.
        $clinicalFiles = ClinicalFile::with(['visit.doctor', 'uploadedBy'])
            ->forPatient($patient->id)
            ->latest('captured_at')
            ->get();

        // Billing data
        $billingPrompts = BillingPrompt::with(['invoice'])
            ->forPatient($patient->id)
            ->orderByRaw("FIELD(status, 'pending', 'invoiced', 'dismissed')")
            ->latest()
            ->get();

        $invoices = Invoice::with(['items', 'payments', 'receipts', 'finalBill'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('invoice_date')
            ->get();

        $wallet = Wallet::forPatient($patient->id);

        // Prescriptions (latest 20, include soft-deleted cancelled ones)
        $prescriptions = Prescription::forPatient($patient->id)
            ->with(['prescribedBy', 'items'])
            ->withTrashed()
            ->latest()
            ->limit(20)
            ->get();

        // Membership data
        $activeMembership  = MembershipBenefitService::getActive($patient->id);
        $membershipPlans   = FinanceMembershipPlan::active()->orderBy('price')->get();

        // Full membership history (all enrollments, newest first)
        $membershipHistory = FinancePatientMembership::with('plan')
            ->where('patient_id', $patient->id)
            ->orderByDesc('start_date')
            ->get();

        // Benefits availed log (latest 50) — table may not exist yet if migration pending
        try {
            $benefitLogs = MembershipBenefitLog::with(['invoice', 'membership.plan'])
                ->where('patient_id', $patient->id)
                ->orderByDesc('availed_at')
                ->limit(50)
                ->get();
        } catch (\Exception $e) {
            $benefitLogs = collect();
        }

        // Existing AOCP members — for the family "link to member" dropdown.
        // No "head" concept anymore: list ALL active members regardless of
        // their plan, price, or member_type. A new add-on can be linked to any
        // of them. We just exclude the current patient.
        $activeFamilyHeads = FinancePatientMembership::active()
            ->where('patient_id', '!=', $patient->id)
            ->with('patient', 'plan')
            ->orderBy('id', 'desc')
            ->get();

        // Implant catalog — for the stock-linked component picker on the
        // Treatment Visit "Implant" sub-form (fixture + accessory components).
        $implantCatalog = ImplantCatalog::active()
            ->with('inventoryItem.stocks')
            ->orderBy('component_type')
            ->orderBy('brand')
            ->get();

        return [
            'patient'           => $patient,
            'recentVisits'      => $patient->appointments->take(10),
            'relationshipNotes' => $patient->relationshipNotes,
            'opportunities'     => $patient->opportunities,
            'doctors'           => \App\Models\User::where('role', 'doctor')->orderBy('name')->get(),
            'treatmentVisits'   => $patient->treatmentVisits,
            // Phase 2 refinement (consent per item) — one extra query for the
            // whole set of consent-flagged treatment IDs, not per-treatment,
            // so the hot patient-profile path stays a single lookup.
            'treatments'        => (function () {
                $consentIds = \App\Models\TreatmentRule::where('rule_type', 'consent_required')
                    ->where('is_active', true)
                    ->pluck('treatment_id')
                    ->flip();

                return \App\Models\Treatment::select('id', 'name', 'default_price')
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->get()
                    ->each(fn ($t) => $t->consent_required = $consentIds->has($t->id));
            })(),
            'consultations'     => $patient->consultations,
            // Prescriptions
            'prescriptions'     => $prescriptions,
            // Billing
            'billingPrompts'    => $billingPrompts,
            'invoices'          => $invoices,
            'wallet'            => $wallet,
            // Membership
            'activeMembership'  => $activeMembership,
            'membershipPlans'   => $membershipPlans,
            'activeFamilyHeads' => $activeFamilyHeads,
            'membershipHistory' => $membershipHistory,
            'benefitLogs'       => $benefitLogs,
            // Phase 7E — Clinical Library
            'clinicalFiles'     => $clinicalFiles,
            // Recall / Follow-up
            'recallTask'        => $recallTask,
        ];
    }

    /**
     * Add a relationship note.
     */
    public function addRelationshipNote(Patient $patient, array $data): PatientRelationshipNote
    {
        return $patient->relationshipNotes()->create([
            'note'       => $data['note'],
            'note_type'  => $data['type'] ?? 'internal',
            'tags'       => $data['tags'] ?? [],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Add / update a treatment opportunity.
     */
    public function saveOpportunity(Patient $patient, array $data, ?int $id = null): TreatmentOpportunity
    {
        $payload = [
            'type'            => $data['type'],
            'label'           => $data['label'] ?? null,
            'status'          => $data['status'] ?? 'prospect',
            'priority'        => $data['priority'] ?? 'medium',
            'follow_up_date'  => $data['follow_up_date'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'created_by'      => Auth::id(),
            // Phase 4 — stamp the patient's Relationship link so this opportunity shows
            // up correctly on the PRE Opportunities board (it groups by relationship,
            // not patient). Previously omitted here — the only other creation path
            // (Communication\OpportunityController::store) already set this, so
            // opportunities added from the patient profile page were silently
            // rendering as "Unlinked opportunity" even when the patient WAS linked.
            'relationship_id' => $patient->relationship_id,
        ];

        if ($id) {
            $opp = TreatmentOpportunity::findOrFail($id);
            $opp->update($payload);
            return $opp;
        }

        return $patient->opportunities()->create($payload);
    }
}
