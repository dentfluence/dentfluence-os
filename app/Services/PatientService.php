<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * PatientService
 * --------------
 * The single "brain" for the Patients module. ALL patient reads and writes go
 * through here so the web Blade pages and the /api/v1/patients endpoints behave
 * identically — same search, same filters, same create/update rules.
 *
 * Controllers stay thin: they validate input and shape the response. The actual
 * query building and persistence lives in this one place.
 *
 * Input keys are accepted in BOTH the web form's names and cleaner API names,
 * so the same methods serve both clients:
 *   phone  | mobile
 *   date_of_birth | dob
 *   chief_complaint | notes
 *   search | q
 */
class PatientService
{
    // ──────────────────────────────────────────────────────────────────────
    //  READ — list / search
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build a branch-scoped, filtered, sorted query for the patient list.
     * Returns an un-executed Builder so the caller can ->paginate() (web) or
     * hand it to the API pagination helper (mobile).
     *
     * Recognised filter keys (all optional):
     *   q / search, gender, area, age_min, age_max, membership,
     *   follow_up, source, birthday_month, family, sort
     */
    public function filteredQuery(int $branchId, array $filters = []): Builder
    {
        $query = Patient::where('branch_id', $branchId);

        // ── Search (web sends ?q=, API may send ?search=) ──
        $term = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        if ($term !== '') {
            $query->where(fn ($qb) => $qb
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('patient_id', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        }

        // ── Simple equality filters ──
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['area'])) {
            $query->where('area', $filters['area']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['follow_up'])) {
            $query->where('follow_up_status', $filters['follow_up']);
        }
        if (! empty($filters['birthday_month'])) {
            $query->whereMonth('date_of_birth', $filters['birthday_month']);
        }

        // ── Age range (covers real DOB and unknown-DOB patients) ──
        if (! empty($filters['age_min'])) {
            $min = $filters['age_min'];
            $query->where(fn ($q) => $q
                ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?', [$min])
                ->orWhere(fn ($q2) => $q2->where('dob_unknown', true)->where('age_years', '>=', $min)));
        }
        if (! empty($filters['age_max'])) {
            $max = $filters['age_max'];
            $query->where(fn ($q) => $q
                ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?', [$max])
                ->orWhere(fn ($q2) => $q2->where('dob_unknown', true)->where('age_years', '<=', $max)));
        }

        // ── Membership status ──
        if (! empty($filters['membership'])) {
            $this->applyMembershipFilter($query, $filters['membership']);
        }

        // ── Family links ──
        if (! empty($filters['family'])) {
            if ($filters['family'] === 'has_family') {
                $query->where(fn ($q) => $q
                    ->whereHas('linkedPatients')
                    ->orWhereHas('linkedByPatients'));
            } elseif ($filters['family'] === 'no_family') {
                $query->whereDoesntHave('linkedPatients')
                      ->whereDoesntHave('linkedByPatients');
            }
        }

        $this->applySort($query, (string) ($filters['sort'] ?? 'newest'));

        return $query;
    }

    /** Membership filter broken out so the main method stays readable. */
    protected function applyMembershipFilter(Builder $query, string $membership): void
    {
        if ($membership === 'active') {
            $query->where('membership_status', 'active')
                  ->where(fn ($q) => $q
                      ->whereNull('membership_expires_at')
                      ->orWhereDate('membership_expires_at', '>=', now()));
        } elseif ($membership === 'expired') {
            $query->where(fn ($q) => $q
                ->where('membership_status', 'expired')
                ->orWhere(fn ($q2) => $q2
                    ->where('membership_status', 'active')
                    ->whereDate('membership_expires_at', '<', now())));
        } elseif ($membership === 'not_enrolled') {
            $query->where('membership_status', 'not_enrolled');
        }
    }

    /** Whitelisted sort options (same set the web list offers). */
    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name'            => $query->orderBy('name', 'asc'),
            'name_desc'       => $query->orderBy('name', 'desc'),
            'patient_id'      => $query->orderBy('patient_id', 'asc'),
            'patient_id_desc' => $query->orderBy('patient_id', 'desc'),
            'last_visit'      => $query->orderBy('last_visit_date', 'desc')->orderBy('created_at', 'desc'),
            'last_visit_asc'  => $query->orderBy('last_visit_date', 'asc')->orderBy('created_at', 'asc'),
            'oldest'          => $query->orderBy('created_at', 'asc'),
            default           => $query->orderBy('created_at', 'desc'), // newest first
        };
    }

    /** Distinct, non-empty areas in this branch — used to build filter dropdowns. */
    public function distinctAreas(int $branchId)
    {
        return Patient::where('branch_id', $branchId)
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');
    }

    /**
     * Lightweight type-ahead search. Returns up to $limit slim Patient rows
     * (id, patient_id, name, phone). Callers shape these for their own UI.
     */
    public function suggest(string $term, int $branchId, int $limit = 10)
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Patient::where('branch_id', $branchId)
            ->where(fn ($qb) => $qb
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('patient_id', 'like', "%{$term}%"))
            ->select('id', 'patient_id', 'name', 'phone')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  WRITE — create / update / lifecycle
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a full patient record from form/API input.
     * Handles display-name assembly, DOB-unknown logic, and tag syncing.
     */
    public function createFromInput(array $in, User $actor): Patient
    {
        $displayName = $in['name'] ?? $this->buildName($in);

        $dobUnknown = (bool) ($in['dob_unknown'] ?? false);
        $dob        = $in['date_of_birth'] ?? $in['dob'] ?? null;

        $patient = Patient::create([
            // Identity
            'title'       => $in['title'] ?? null,
            'first_name'  => $in['first_name'] ?? null,
            'middle_name' => $in['middle_name'] ?? null,
            'last_name'   => $in['last_name'] ?? null,
            'name'        => $displayName,
            'gender'      => $in['gender'] ?? null,
            'date_of_birth' => $dobUnknown ? null : $dob,
            'dob_unknown' => $dobUnknown,
            'age_years'   => $in['age_years'] ?? null,
            // Contact
            'phone'           => $in['phone'] ?? $in['mobile'] ?? null,
            'alternate_phone' => $in['alternate_phone'] ?? null,
            'email'           => $in['email'] ?? null,
            'emergency_contact_name'         => $in['emergency_contact_name'] ?? null,
            'emergency_contact_relationship' => $in['emergency_contact_relationship'] ?? null,
            'emergency_contact_number'       => $in['emergency_contact_number'] ?? null,
            // Address
            'address' => $in['address'] ?? null,
            'area'    => $in['area'] ?? null,
            'city'    => $in['city'] ?? null,
            'pincode' => $in['pincode'] ?? null,
            // Clinical
            'medical_conditions'  => $in['medical_conditions'] ?? [],
            'current_medications' => $in['current_medications'] ?? null,
            'dental_conditions'   => $in['dental_conditions'] ?? [],
            // Habits
            'habits'          => $in['habits'] ?? [],
            'habit_frequency' => $in['habit_frequency'] ?? [],
            // Source
            'source'               => $in['source'] ?? null,
            'source_referral_name' => $in['source_referral_name'] ?? null,
            'source_camp_name'     => $in['source_camp_name'] ?? null,
            'source_campaign'      => $in['source_campaign'] ?? null,
            // Structured referral
            'referral_type'       => $in['referral_type'] ?? null,
            'referred_patient_id' => $in['referred_patient_id'] ?? null,
            'referrer_name'       => $in['referrer_name'] ?? null,
            'referrer_mobile'     => $in['referrer_mobile'] ?? null,
            'referrer_type'       => $in['referrer_type'] ?? null,
            'referrer_notes'      => $in['referrer_notes'] ?? null,
            // Misc
            'chief_complaint' => $in['chief_complaint'] ?? $in['notes'] ?? null,
            // Ownership
            'branch_id'  => $actor->branch_id,
            'created_by' => $actor->id,
        ]);

        $this->syncTags($patient, $in['tags'] ?? [], $actor);

        // Phase 1 (Workstream A) — link to Master Relationship. NO-OP unless the
        // 'identity.link_patient' feature flag is on; never breaks creation.
        app(\App\Services\Relationship\PatientRelationshipLinker::class)->link($patient);

        return $patient->fresh(['tags']);
    }

    /**
     * Existing patients in this branch that share a phone number.
     *
     * Families legitimately share one mobile, so this is NOT a hard block —
     * callers use it to warn ("possible duplicate — is this a returning
     * patient?") and let the user decide. Previously only quickCreate() looked
     * for duplicates, so the main registration form silently created a second
     * record for every returning patient, splitting their history and billing.
     *
     * @return \Illuminate\Support\Collection<int, Patient>
     */
    public function findDuplicatesByPhone(?string $phone, int $branchId)
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return collect();
        }

        return Patient::where('branch_id', $branchId)
            ->where('phone', $phone)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'phone', 'date_of_birth']);
    }

    /**
     * Minimal "quick add" (e.g. from the appointment modal): name + phone only.
     * Returns ['duplicate' => Patient] if the phone already exists in this
     * branch, otherwise ['patient' => Patient].
     */
    public function quickCreate(array $in, User $actor): array
    {
        $phone = trim((string) ($in['phone'] ?? ''));

        $existing = $this->findDuplicatesByPhone($phone, (int) $actor->branch_id)->first();

        if ($existing) {
            return ['duplicate' => $existing];
        }

        $patient = Patient::create([
            'first_name' => $in['first_name'] ?? null,
            'last_name'  => $in['last_name'] ?? null,
            'name'       => trim(($in['first_name'] ?? '') . ' ' . ($in['last_name'] ?? '')),
            'phone'      => $phone,
            'branch_id'  => $actor->branch_id,
            'created_by' => $actor->id,
        ]);

        // Phase 1 (Workstream A) — link to Master Relationship. NO-OP unless the
        // 'identity.link_patient' feature flag is on; never breaks creation.
        app(\App\Services\Relationship\PatientRelationshipLinker::class)->link($patient);

        return ['patient' => $patient];
    }

    /**
     * Update an existing patient from form/API input. Only keys actually
     * provided (non-null) are written, so partial updates are safe.
     */
    public function updateFromInput(Patient $patient, array $in): Patient
    {
        // Rebuild the display name only when name parts were supplied.
        if (! empty($in['first_name']) || ! empty($in['last_name'])) {
            $displayName = $this->buildName([
                'title'       => $in['title'] ?? $patient->title,
                'first_name'  => $in['first_name'] ?? $patient->first_name,
                'middle_name' => $in['middle_name'] ?? $patient->middle_name,
                'last_name'   => $in['last_name'] ?? $patient->last_name,
            ]);
        } else {
            $displayName = $in['name'] ?? $patient->name;
        }

        $dobUnknown = array_key_exists('dob_unknown', $in) ? (bool) $in['dob_unknown'] : null;
        $dob        = $in['date_of_birth'] ?? $in['dob'] ?? null;

        // Structured referral fields are only meaningful when Source = Referral.
        // Normalize explicitly (rather than relying on the blanket array_filter
        // below) so both setting AND clearing a referral persist reliably —
        // an empty string must become null, not survive as '' or be silently
        // dropped, and referred_patient_id must be a clean int for the FK.
        $referralType = array_key_exists('referral_type', $in) && $in['referral_type'] !== ''
            ? $in['referral_type']
            : null;
        $referredPatientId = array_key_exists('referred_patient_id', $in) && $in['referred_patient_id'] !== ''
            ? (int) $in['referred_patient_id']
            : null;
        $referrerType = array_key_exists('referrer_type', $in) && $in['referrer_type'] !== ''
            ? $in['referrer_type']
            : null;

        $fields = array_filter([
            'title'       => $in['title'] ?? null,
            'first_name'  => $in['first_name'] ?? null,
            'middle_name' => $in['middle_name'] ?? null,
            'last_name'   => $in['last_name'] ?? null,
            'name'        => $displayName,
            'phone'           => $in['phone'] ?? $in['mobile'] ?? null,
            'alternate_phone' => $in['alternate_phone'] ?? null,
            'email'           => $in['email'] ?? null,
            'date_of_birth'   => ($in['dob_unknown'] ?? false) ? null : $dob,
            'dob_unknown'     => $dobUnknown,
            'age_years'       => $in['age_years'] ?? null,
            'gender'          => $in['gender'] ?? null,
            'occupation'      => $in['occupation'] ?? null,
            'address'         => $in['address'] ?? null,
            'area'            => $in['area'] ?? null,
            'city'            => $in['city'] ?? null,
            'state'           => $in['state'] ?? null,
            'pincode'         => $in['pincode'] ?? null,
            'emergency_contact_name'         => $in['emergency_contact_name'] ?? null,
            'emergency_contact_relationship' => $in['emergency_contact_relationship'] ?? null,
            'emergency_contact_number'       => $in['emergency_contact_number'] ?? null,
            'medical_alert'       => $in['medical_alert'] ?? null,
            'medical_conditions'  => $in['medical_conditions'] ?? null,
            'current_medications' => $in['current_medications'] ?? null,
            'dental_conditions'   => $in['dental_conditions'] ?? null,
            'habits'              => $in['habits'] ?? null,
            'habit_frequency'     => $in['habit_frequency'] ?? null,
            'allergies'           => $in['allergies'] ?? null,
            'family_notes'        => $in['family_notes'] ?? null,
            'source'              => $in['source'] ?? null,
            'referred_by'         => $in['referred_by'] ?? null,
            'source_referral_name' => $in['source_referral_name'] ?? null,
            'source_camp_name'     => $in['source_camp_name'] ?? null,
            'source_campaign'      => $in['source_campaign'] ?? null,
            'referrer_name'       => $in['referrer_name'] ?? null,
            'referrer_mobile'     => $in['referrer_mobile'] ?? null,
            'referrer_notes'      => $in['referrer_notes'] ?? null,
            'membership_status'    => $in['membership_status'] ?? null,
            'membership_expires_at' => $in['membership_expires_at'] ?? null,
            'follow_up_status'     => $in['follow_up_status'] ?? null,
            'follow_up_date'       => $in['follow_up_date'] ?? null,
        ], fn ($v) => $v !== null);

        // referral_type / referred_patient_id / referrer_type are handled
        // separately (not inside the array_filter above) because they can
        // legitimately need to be set back to NULL — e.g. switching the
        // "Existing Patient" / "Others" toggle, or clearing a referral
        // entirely. array_filter(fn($v) => $v !== null) would silently drop
        // a null here and never persist the clear. Only touch them at all
        // when the referral panel was actually part of this submission.
        if (array_key_exists('referral_type', $in) || array_key_exists('referred_patient_id', $in) || array_key_exists('referrer_type', $in)) {
            $fields['referral_type']       = $referralType;
            $fields['referred_patient_id'] = $referredPatientId;
            $fields['referrer_type']       = $referrerType;
        }

        $patient->update($fields);

        return $patient->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Lifecycle — deactivate / reactivate / soft delete
    // ──────────────────────────────────────────────────────────────────────

    public function deactivate(Patient $patient, string $reason, int $actorId): Patient
    {
        $patient->update([
            'is_active'           => false,
            'deactivation_reason' => $reason,
            'deactivated_by'      => $actorId,
        ]);

        return $patient;
    }

    public function reactivate(Patient $patient): Patient
    {
        $patient->update([
            'is_active'           => true,
            'deactivation_reason' => null,
            'deactivated_by'      => null,
        ]);

        return $patient;
    }

    /** Soft delete (keeps the record) with an audit-friendly reason. */
    public function softDelete(Patient $patient, string $reason): void
    {
        $patient->deleted_reason = $reason;
        $patient->save();
        $patient->delete();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────

    /** Join title + first + middle + last into a single display name. */
    protected function buildName(array $in): string
    {
        return implode(' ', array_filter([
            $in['title'] ?? null,
            $in['first_name'] ?? null,
            $in['middle_name'] ?? null,
            $in['last_name'] ?? null,
        ]));
    }

    /**
     * Resolve incoming tag *names* to tag IDs (creating tags on the fly) and
     * sync them onto the patient, stamping who added each one.
     */
    protected function syncTags(Patient $patient, $tagNames, User $actor): void
    {
        if (empty($tagNames)) {
            return;
        }

        $branchId = $actor->branch_id;

        $tagIds = collect($tagNames)
            ->filter()
            ->map(function ($name) use ($branchId) {
                $tag = Tag::forBranch($branchId)->where('name', $name)->first();
                $tag ??= Tag::create(['name' => $name, 'branch_id' => $branchId]);

                return $tag->id;
            })
            ->all();

        $patient->tags()->sync(array_fill_keys($tagIds, ['added_by' => $actor->id]));
    }
}
