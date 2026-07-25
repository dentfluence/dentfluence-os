<?php

namespace App\Services\Smoke\Journeys;

use App\Models\Patient;
use App\Models\PatientLink;
use App\Models\User;
use App\Services\Patient\FamilyLinkService;
use App\Services\Patient\PatientJourneyService;
use App\Services\PatientService;
use App\Services\PatientProfileService;
use App\Services\Smoke\HttpProbe;
use App\Services\Smoke\SmokeRun;
use Illuminate\Support\Facades\Artisan;

/**
 * Patients smoke journey (frozen module V1.0).
 *
 * TEST INFRASTRUCTURE ONLY — every write goes through the frozen canonical
 * services (PatientService::register / updateFromInput, FamilyLinkService,
 * PatientJourneyService). No business rule is re-implemented here; the suite
 * only asserts outcomes the production code produced.
 */
class PatientsSmokeJourney
{
    private const J = 'Patients';

    public function __construct(
        private readonly PatientService $patients,
        private readonly FamilyLinkService $family,
        private readonly PatientJourneyService $journey,
        private readonly HttpProbe $probe,
    ) {
    }

    /** Returns the smoke adult patient so the Appointments journey can reuse it. */
    public function run(SmokeRun $run, User $actor): ?Patient
    {
        $m = $run->marker();

        // ── 1–2. Create adult through the canonical path; exactly one row ────
        $adult = $this->patients->register([
            'first_name'    => $m,
            'last_name'     => 'Adult',
            'gender'        => 'male',
            'date_of_birth' => now()->subYears(34)->toDateString(),
            'phone'         => $run->phone(1),
        ], $actor);
        $run->track($adult, "patient #{$adult->id} ({$m} Adult)");

        $run->check(self::J, 'Adult registered via PatientService::register()', $adult->exists, SmokeRun::CRITICAL);
        $run->check(self::J, 'TDC / patient number auto-assigned', ! empty($adult->patient_id), SmokeRun::CRITICAL);
        $run->check(
            self::J,
            'Branch + creator stamped from actor',
            (int) $adult->branch_id === (int) $actor->branch_id && (int) $adult->created_by === (int) $actor->id,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Exactly one adult row written (no duplicate registration)',
            Patient::where('name', 'like', "{$m} Adult%")->count() === 1,
            SmokeRun::CRITICAL
        );

        // ── 3. Search by name and by mobile (canonical read paths) ───────────
        $run->check(
            self::J,
            'Name search (suggest) finds the patient',
            $this->patients->suggest($m, (int) $actor->branch_id)->pluck('id')->contains($adult->id)
        );
        $run->check(
            self::J,
            'Mobile lookup (findDuplicatesByPhone) finds the patient',
            $this->patients->findDuplicatesByPhone($run->phone(1), (int) $actor->branch_id)
                ->pluck('id')->contains($adult->id)
        );

        // ── 4–6. Profile renders + every lazy tab endpoint responds ──────────
        $profile = $this->probe->get(route('patients.show', $adult));
        $run->check(self::J, 'Patient profile page renders (HTTP 200)', $profile['ok'], SmokeRun::TECHNICAL, $profile['error']);
        $run->check(self::J, 'Profile shows the patient name', str_contains($profile['body'], "{$m} Adult"));
        $run->check(self::J, 'Profile shows the Journey Timeline card', str_contains($profile['body'], 'Journey Timeline'));

        $brokenTabs = [];
        foreach (PatientProfileService::LAZY_TABS as $tab) {
            $r = $this->probe->get(route('patients.tab', [$adult, $tab]));
            if (! $r['ok']) {
                $brokenTabs[] = "{$tab} ({$r['error']})";
            }
        }
        $run->check(
            self::J,
            'All ' . count(PatientProfileService::LAZY_TABS) . ' lazy profile tabs respond',
            $brokenTabs === [],
            SmokeRun::WORKFLOW,
            $brokenTabs === [] ? null : 'broken: ' . implode(', ', $brokenTabs)
        );

        // ── 7. Harmless demographic update persists ──────────────────────────
        $this->patients->updateFromInput($adult, ['occupation' => "{$m} occupation", 'city' => 'SmokeCity']);
        $freshAdult = Patient::find($adult->id);
        $run->check(
            self::J,
            'Demographic update persisted to the database',
            $freshAdult?->occupation === "{$m} occupation" && $freshAdult?->city === 'SmokeCity',
            SmokeRun::CRITICAL
        );

        // ── 8–9. Second family member + reciprocal relationship ──────────────
        $spouse = $this->patients->register([
            'first_name'    => $m,
            'last_name'     => 'Spouse',
            'gender'        => 'female',
            'date_of_birth' => now()->subYears(32)->toDateString(),
            'phone'         => $run->phone(2),
        ], $actor);
        $run->track($spouse, "patient #{$spouse->id} ({$m} Spouse)");

        $this->family->addLink($adult, $spouse, 'spouse', [], $actor);

        $run->check(
            self::J,
            'Family link visible from the adult side',
            $this->family->linksFor($adult)->pluck('counterpart.id')->contains($spouse->id),
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Reciprocal link visible from the spouse side',
            $this->family->linksFor($spouse)->pluck('counterpart.id')->contains($adult->id),
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Exactly one patient_links row for the pair (no duplicate link)',
            $this->pairLinkCount($adult, $spouse) === 1,
            SmokeRun::CRITICAL
        );

        // ── 10. Change the relationship and verify ───────────────────────────
        $this->family->updateLink($adult, $spouse, 'sibling', [], $actor);
        $changed = $this->family->linksFor($adult)->first(fn ($l) => $l['counterpart']->id === $spouse->id);
        $run->check(
            self::J,
            'Relationship change persisted (spouse → sibling)',
            ($changed['relationship_type'] ?? null) === 'sibling'
        );

        // ── 11. Remove the link; verify both directions ──────────────────────
        $this->family->removeLink($adult, $spouse, $actor);
        $run->check(
            self::J,
            'Link removal cleared BOTH directions (no orphan row)',
            $this->family->linksFor($adult)->isEmpty()
                && $this->family->linksFor($spouse)->isEmpty()
                && $this->pairLinkCount($adult, $spouse) === 0,
            SmokeRun::CRITICAL
        );

        // ── 12–13. Minor + guardian-required state ───────────────────────────
        $minor = $this->patients->register([
            'first_name'    => $m,
            'last_name'     => 'Minor',
            'gender'        => 'female',
            'date_of_birth' => now()->subYears(10)->toDateString(),
            'phone'         => $run->phone(3),
        ], $actor);
        $run->track($minor, "patient #{$minor->id} ({$m} Minor)");

        $run->check(self::J, 'Minor is detected as a minor (isMinor)', $minor->isMinor(), SmokeRun::CRITICAL);
        $run->check(
            self::J,
            'Minor starts with no guardian (guardian-required state)',
            $this->family->guardiansFor($minor)->isEmpty()
        );
        $run->check(
            self::J,
            'Consent flow flags the guardian-less minor',
            $this->probe->sees(route('consent.patient', $minor), 'No guardian is linked to this minor.')
        );

        // ── 14–15. Attach the adult as guardian; verify the graph ────────────
        $this->family->attachGuardian($minor, $adult, ['relationship_type' => 'father'], $actor);

        $run->check(
            self::J,
            'Guardian attached: guardiansFor(minor) contains the adult',
            $this->family->guardiansFor($minor)->pluck('id')->contains($adult->id),
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Guardian graph reciprocal: wardsFor(adult) contains the minor',
            $this->family->wardsFor($adult)->pluck('id')->contains($minor->id),
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Exactly one guardian link row (no duplicate guardian record)',
            PatientLink::where('patient_id', $minor->id)
                ->where('linked_patient_id', $adult->id)
                ->where('is_guardian', true)->count() === 1,
            SmokeRun::CRITICAL
        );

        // ── 16. Guardian appears in the minor consent flow ───────────────────
        $run->check(
            self::J,
            'Consent flow shows the consenting guardian',
            $this->probe->sees(route('consent.patient', $minor), 'Consenting guardian:')
        );

        // ── 17. Guardian demotion, then full removal, both directions ────────
        $this->family->updateLink($minor, $adult, 'father', ['as_guardian' => false], $actor);
        $run->check(
            self::J,
            'Guardian demotion clears guardianship (link kept)',
            $this->family->guardiansFor($minor)->isEmpty() && $this->pairLinkCount($minor, $adult) === 1,
            SmokeRun::CRITICAL
        );

        $this->family->removeLink($minor, $adult, $actor);
        $run->check(
            self::J,
            'Guardian link removal cleared both directions',
            $this->family->linksFor($minor)->isEmpty()
                && $this->family->wardsFor($adult)->isEmpty(),
            SmokeRun::CRITICAL
        );

        // ── 18. Journey Timeline read model still serves events ──────────────
        $timeline = $this->journey->for($adult, $actor);
        $run->check(
            self::J,
            'Journey Timeline read model returns events for the patient',
            ($timeline['events'] ?? collect())->isNotEmpty(),
            SmokeRun::WORKFLOW,
            'family link activity should appear on the adult timeline'
        );

        // ── Integrity: counts, invariant, cross-patient bleed ────────────────
        $run->check(
            self::J,
            'Exactly 3 smoke patients exist for this run (no unexpected duplicates)',
            Patient::where('name', 'like', "{$m}%")->count() === 3,
            SmokeRun::CRITICAL
        );
        $run->check(
            self::J,
            'Patient registration invariant intact (patients:invariant-check)',
            Artisan::call('patients:invariant-check') === 0,
            SmokeRun::CRITICAL,
            'a business path creates Patients outside PatientService::register()'
        );
        $run->check(
            self::J,
            'No cross-patient data bleed (spouse retains no links or wards)',
            $this->family->linksFor($spouse)->isEmpty()
                && $this->family->guardiansFor($spouse)->isEmpty()
                && $this->family->wardsFor($spouse)->isEmpty(),
            SmokeRun::CRITICAL
        );

        // Commit-mode cleanup: activity rows written for these patients.
        $ids = [$adult->id, $spouse->id, $minor->id];
        $run->onCleanup(function () use ($ids) {
            \App\Models\Activity::where('subject_type', Patient::class)
                ->whereIn('subject_id', $ids)->delete();
        });

        return $adult;
    }

    /** Rows linking the two patients, in either orientation. */
    private function pairLinkCount(Patient $a, Patient $b): int
    {
        return PatientLink::where(function ($q) use ($a, $b) {
            $q->where(function ($w) use ($a, $b) {
                $w->where('patient_id', $a->id)->where('linked_patient_id', $b->id);
            })->orWhere(function ($w) use ($a, $b) {
                $w->where('patient_id', $b->id)->where('linked_patient_id', $a->id);
            });
        })->count();
    }
}
