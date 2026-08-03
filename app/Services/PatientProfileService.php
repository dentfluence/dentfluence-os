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
use App\Services\Patient\FamilyLinkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Patient Profile read model (Patients Module Phase 4, Slice 1).
 *
 * The profile screen is split into:
 *   coreProfile()  — everything the eager page needs (header, Profile tab,
 *                    Quick Pay modal, Alpine root state). Hot path — keep lean.
 *   tabData()      — per-tab data for the lazy fragments served by
 *                    PatientController@tab. Loaded only when a tab is opened.
 *   familyPanel()  — Phase 3 Family/Guardian view-model (moved here from the
 *                    controller so web + API share one derivation).
 *
 * loadProfile() remains as the backward-compatible full composition.
 */
class PatientProfileService
{
    /** Tabs served as lazy fragments. Order mirrors the tab nav. */
    public const LAZY_TABS = [
        'consultation', 'treatment-plan', 'visits', 'lab', 'prescriptions',
        'billing', 'wallet', 'membership', 'documents', 'notes',
    ];

    /**
     * Data for the eager page: sticky header (financial stat cards), Profile
     * tab (details, rapport notes, opportunities, visit log), Quick Pay modal.
     */
    public function coreProfile(Patient $patient): array
    {
        $patient->load([
            'relationshipNotes.author',
            'opportunities.author',
            // Slice 2: the Journey Timeline replaced the old Visit Log card,
            // so consultations/treatmentVisits are no longer eager-loaded here —
            // the timeline endpoint and the lazy tabs load their own data.
        ]);

        // Header stat cards + Quick Pay modal both read the invoice collection
        // (totals are accessor-driven, so items/payments must be loaded).
        $invoices = Invoice::with(['items', 'payments'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('invoice_date')
            ->get();

        return [
            'patient'           => $patient,
            'relationshipNotes' => $patient->relationshipNotes,
            'opportunities'     => $patient->opportunities,
            'invoices'          => $invoices,
            'wallet'            => Wallet::forPatient($patient->id),
            'activeMembership'  => MembershipBenefitService::getActive($patient->id),
        ];
    }

    /**
     * Family & Contacts view-model (Phase 3) — previously assembled inline in
     * PatientController@show; owned by the service since Phase 4.
     */
    public function familyPanel(Patient $patient, $activeMembership = null): array
    {
        $family = app(FamilyLinkService::class);

        return [
            'familyLinks'          => $family->linksFor($patient),
            'familyGuardians'      => $family->guardiansFor($patient),
            'isMinor'              => $patient->isMinor(),
            'householdCount'       => $patient->relationship_id
                ? Patient::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->where('relationship_id', $patient->relationship_id)
                    ->where('id', '!=', $patient->id)
                    ->count()
                : 0,
            'membershipFamilyName' => $activeMembership
                ? ($activeMembership->family_name ?: optional($activeMembership->familyHead)->family_name)
                : null,
            'canEditFamily'        => (bool) optional(Auth::user())->canAccess('patients', 'edit'),
        ];
    }

    /**
     * Data for one lazy tab fragment. Every branch returns ['patient' => …]
     * plus exactly the variables that tab's blade chain consumes.
     */
    public function tabData(Patient $patient, string $tab): array
    {
        $base = ['patient' => $patient];

        switch ($tab) {
            case 'consultation':
                $patient->load(['consultations', 'treatmentVisits.doctor', 'treatmentVisits.visitItems']);

                return $base + [
                    'consultations'   => $patient->consultations,
                    'treatmentVisits' => $patient->treatmentVisits,
                    'prescriptions'   => $this->prescriptions($patient),
                    'recallTask'      => $this->recallTask($patient),
                ];

            case 'treatment-plan':
                $patient->load(['treatmentPlans.items', 'treatmentPlans.creator', 'consultations']);

                return $base + [
                    'consultations' => $patient->consultations,
                    'treatments'    => $this->treatmentsWithConsentFlag(),
                ];

            case 'visits':
                $patient->load([
                    'appointments.treatment',
                    'appointments.treatmentCategory',
                    'treatmentVisits.doctor',
                    'treatmentVisits.visitItems',
                    'treatmentVisits.implantPlacement',
                    'treatmentPlans.items',
                ]);

                return $base + [
                    'treatmentVisits' => $patient->treatmentVisits,
                    'doctors'         => \App\Models\User::where('role', 'doctor')->orderBy('name')->get(),
                    'treatments'      => $this->treatmentsWithConsentFlag(),
                    'implantCatalog'  => ImplantCatalog::active()
                        ->with('inventoryItem.stocks')
                        ->orderBy('component_type')
                        ->orderBy('brand')
                        ->get(),
                    'prescriptions'   => $this->prescriptions($patient),
                ];

            case 'lab':
                // Lab tab computes its own case/doctor/vendor lists inline
                // (verbatim-moved @php block in patients/tabs/lab.blade.php).
                return $base;

            case 'prescriptions':
                return $base + ['prescriptions' => $this->prescriptions($patient)];

            case 'billing':
                return $base + [
                    'invoices'         => Invoice::with(['items', 'payments', 'receipts', 'finalBill'])
                        ->where('patient_id', $patient->id)
                        ->orderByDesc('invoice_date')
                        ->get(),
                    'billingPrompts'   => BillingPrompt::with(['invoice'])
                        ->forPatient($patient->id)
                        ->orderByRaw("FIELD(status, 'pending', 'invoiced', 'dismissed')")
                        ->latest()
                        ->get(),
                    'wallet'           => Wallet::forPatient($patient->id),
                    'activeMembership' => MembershipBenefitService::getActive($patient->id),
                ];

            case 'wallet':
                return $base + ['wallet' => Wallet::forPatient($patient->id)];

            case 'membership':
                return $base + [
                    'activeMembership'  => MembershipBenefitService::getActive($patient->id),
                    'membershipPlans'   => FinanceMembershipPlan::active()->orderBy('price')->get(),
                    'membershipHistory' => FinancePatientMembership::with('plan')
                        ->where('patient_id', $patient->id)
                        ->orderByDesc('start_date')
                        ->get(),
                    'benefitLogs'       => $this->benefitLogs($patient),
                    'activeFamilyHeads' => FinancePatientMembership::active()
                        ->where('patient_id', '!=', $patient->id)
                        ->with('patient', 'plan')
                        ->orderBy('id', 'desc')
                        ->get(),
                ];

            case 'documents':
                $patient->load(['treatmentVisits.doctor', 'treatmentVisits.visitItems']);

                return $base + [
                    'treatmentVisits' => $patient->treatmentVisits,
                    'clinicalFiles'   => ClinicalFile::with(['visit.doctor', 'uploadedBy'])
                        ->forPatient($patient->id)
                        ->latest('captured_at')
                        ->get(),
                ];

            case 'notes':
                // Notes & Logs renders from the Alpine root state (relationship
                // notes + opportunities) that coreProfile() already seeded.
                return $base;
        }

        throw new \InvalidArgumentException("Unknown patient profile tab [{$tab}].");
    }

    /**
     * Backward-compatible full composition (pre-Phase 4 behaviour): the core
     * page data plus every tab's data in one array. Prefer coreProfile() +
     * tabData() — this exists for callers that still need everything at once.
     */
    public function loadProfile(Patient $patient): array
    {
        $data = $this->coreProfile($patient);

        foreach (self::LAZY_TABS as $tab) {
            $data += $this->tabData($patient, $tab);
        }

        return $data;
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
            // not patient).
            'relationship_id' => $patient->relationship_id,
        ];

        if ($id) {
            $opp = TreatmentOpportunity::findOrFail($id);
            $opp->update($payload);
            return $opp;
        }

        return $patient->opportunities()->create($payload);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /** Latest 20 prescriptions incl. soft-deleted cancelled ones (unchanged rule). */
    private function prescriptions(Patient $patient)
    {
        return Prescription::forPatient($patient->id)
            ->with(['prescribedBy', 'items'])
            ->withTrashed()
            ->latest()
            ->limit(20)
            ->get();
    }

    /** Pending recall follow-up task for the recall card (unchanged rule). */
    private function recallTask(Patient $patient): ?Task
    {
        return Task::where('patient_id', $patient->id)
            ->where('category', 'follow_up')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('title', 'like', '%recall%')
                  ->orWhere('title', 'like', '%6-month%')
                  ->orWhere('title', 'like', '%6 month%');
            })
            ->orderBy('due_date')
            ->first();
    }

    /** Benefit logs (latest 50) — table may not exist yet if migration pending. */
    private function benefitLogs(Patient $patient)
    {
        // Narrowed guard (Phase 2, PM-013): only the documented "migration not
        // run yet" case is swallowed. Any other failure (bad query, DB outage,
        // etc.) now surfaces normally instead of silently rendering "no data".
        if (! Schema::hasTable('membership_benefit_logs')) {
            return collect();
        }

        return MembershipBenefitLog::with(['invoice', 'membership.plan'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('availed_at')
            ->limit(50)
            ->get();
    }

    /**
     * Active treatments with a consent_required flag resolved in ONE extra
     * query for the whole set (Phase 2 refinement, unchanged).
     */
    private function treatmentsWithConsentFlag()
    {
        $consentIds = \App\Models\TreatmentRule::where('rule_type', 'consent_required')
            ->where('is_active', true)
            ->pluck('treatment_id')
            ->flip();

        return \App\Models\Treatment::select('id', 'name', 'default_price', 'treatment_category_id')
            ->with('category:id,name,color')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->each(fn ($t) => $t->consent_required = $consentIds->has($t->id));
    }
}
