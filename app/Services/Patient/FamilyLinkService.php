<?php

namespace App\Services\Patient;

use App\Models\Patient;
use App\Models\PatientLink;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\PatientService;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FamilyLinkService — the single canonical writer/reader of the interpersonal
 * family graph (`patient_links`). Patients Phase 3, Slice 2.
 *
 * Storage convention (single-row, directional):
 *   row (patient_id = A, linked_patient_id = B, relationship_type = T, is_guardian = g)
 *   means "B is the T of A", and g means "B is the guardian of A".
 *
 * `relationship_type` is biological/social ONLY (see RELATIONSHIP_TYPES).
 * Guardianship is the separate `is_guardian` capacity flag; "ward" is never
 * stored — it is the derived inverse of a guardian link. The correct label from
 * either patient's perspective is DERIVED via the inverse map, never stored twice.
 *
 * This service does NOT do household de-duplication (that is a PRE/presentation
 * concern owned by the consumer). New guardians are minted only through
 * PatientService::register() so the Single Patient Mint Point invariant holds.
 */
class FamilyLinkService
{
    /** Canonical biological/social vocabulary. Guardianship is NOT here (see is_guardian). */
    public const RELATIONSHIP_TYPES = ['mother', 'father', 'spouse', 'child', 'sibling', 'other'];

    /**
     * Inverse of a stored type, from the counterpart's perspective.
     * 'parent' is an intermediate label refined to mother/father by gender.
     */
    private const INVERSE = [
        'mother'  => 'child',
        'father'  => 'child',
        'child'   => 'parent',
        'spouse'  => 'spouse',
        'sibling' => 'sibling',
        'other'   => 'other',
    ];

    public function __construct(
        private readonly PatientService $patients,
        private readonly ActivityEngine $activity,
    ) {
    }

    // ── Reads ────────────────────────────────────────────────────────────────

    /**
     * The pure relationship graph for a patient: every link in both directions,
     * resolved to the counterpart, the stored type, the correct label from THIS
     * patient's side, and the guardian direction. No household de-duplication.
     *
     * @return Collection<int,array{link_id:int,counterpart:Patient,relationship_type:string,label:string,is_guardian:bool,is_ward:bool,added_by:?int}>
     */
    public function linksFor(Patient $patient): Collection
    {
        $forward = PatientLink::where('patient_id', $patient->id)->get();      // (patient, B): B is T of patient
        $reverse = PatientLink::where('linked_patient_id', $patient->id)->get(); // (A, patient): patient is T of A

        $counterpartIds = $forward->pluck('linked_patient_id')
            ->merge($reverse->pluck('patient_id'))
            ->unique()
            ->all();

        $counterparts = Patient::withoutGlobalScope(BranchScope::class)
            ->whereIn('id', $counterpartIds)
            ->get()
            ->keyBy('id');

        $items = collect();

        foreach ($forward as $link) {
            $other = $counterparts->get($link->linked_patient_id);
            if (! $other) {
                continue;
            }
            $items->push([
                'link_id'           => $link->id,
                'counterpart'       => $other,
                'relationship_type' => $link->relationship_type,
                'label'             => $this->refineLabel($link->relationship_type, $other),
                'is_guardian'       => (bool) $link->is_guardian,   // counterpart is THIS patient's guardian
                'is_ward'           => false,
                'added_by'          => $link->added_by,
            ]);
        }

        foreach ($reverse as $link) {
            $other = $counterparts->get($link->patient_id);
            if (! $other) {
                continue;
            }
            $base = self::INVERSE[$link->relationship_type] ?? 'other';
            $items->push([
                'link_id'           => $link->id,
                'counterpart'       => $other,
                'relationship_type' => $link->relationship_type,
                'label'             => $this->refineLabel($base, $other),
                'is_guardian'       => false,
                'is_ward'           => (bool) $link->is_guardian,   // counterpart is THIS patient's ward
                'added_by'          => $link->added_by,
            ]);
        }

        return $items->values();
    }

    /** Patients who are the guardian OF the given patient. */
    public function guardiansFor(Patient $patient): Collection
    {
        $ids = PatientLink::where('patient_id', $patient->id)
            ->where('is_guardian', true)
            ->pluck('linked_patient_id');

        return $this->fetchPatients($ids);
    }

    /** Patients the given patient is the guardian OF (their wards). */
    public function wardsFor(Patient $patient): Collection
    {
        $ids = PatientLink::where('linked_patient_id', $patient->id)
            ->where('is_guardian', true)
            ->pluck('patient_id');

        return $this->fetchPatients($ids);
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    /**
     * Link two patients: "$relative is the $type of $patient". If a link already
     * exists in EITHER direction it is updated (never duplicated). Set
     * $opts['as_guardian'] = true to mark $relative as $patient's guardian.
     *
     * @param  array{as_guardian?:bool,notes?:?string}  $opts
     * @throws \InvalidArgumentException on self-link or invalid type
     */
    public function addLink(Patient $patient, Patient $relative, string $type, array $opts, User $actor): PatientLink
    {
        if ($patient->id === $relative->id) {
            throw new \InvalidArgumentException('Cannot link a patient to themselves.');
        }
        $this->assertValidType($type);

        $asGuardian = (bool) ($opts['as_guardian'] ?? false);
        $notes      = $opts['notes'] ?? null;

        $link = $this->writeLink($patient, $relative, $type, $asGuardian, $notes, $actor);

        $this->activity->log(
            $patient,
            'family.link_added',
            $actor,
            ['linked_patient_id' => $relative->id, 'relationship_type' => $type, 'is_guardian' => $asGuardian],
            $patient->relationship_id,
            "Linked {$relative->name} as {$this->refineLabel($type, $relative)}.",
        );

        return $link;
    }

    /**
     * Remove the link between two patients (either direction). Returns true if a
     * link was removed.
     */
    public function removeLink(Patient $patient, Patient $relative, User $actor): bool
    {
        $link = $this->existingLink($patient, $relative);
        if (! $link) {
            return false;
        }

        $link->delete(); // Auditable records the delete
        $this->activity->log(
            $patient,
            'family.link_removed',
            $actor,
            ['linked_patient_id' => $relative->id],
            $patient->relationship_id,
            "Removed family link with {$relative->name}.",
        );

        return true;
    }

    /**
     * Attach a guardian to a (typically minor) patient. $guardian may be an
     * existing Patient or an input array for a new person — new guardians are
     * minted through PatientService::register() so the mint invariant holds. The
     * whole operation is atomic: a partial failure never leaves an orphan patient.
     *
     * @param  Patient|array<string,mixed>  $guardian
     * @param  array{relationship_type?:string,notes?:?string}  $opts
     * @throws \InvalidArgumentException if the guardian would be a minor
     */
    public function attachGuardian(Patient $minor, Patient|array $guardian, array $opts, User $actor): PatientLink
    {
        $type = $opts['relationship_type'] ?? 'other';
        $this->assertValidType($type);
        $notes = $opts['notes'] ?? null;

        return DB::transaction(function () use ($minor, $guardian, $type, $notes, $actor) {
            $guardianPatient = $guardian instanceof Patient
                ? $guardian
                : $this->patients->register($guardian, $actor);

            if ($guardianPatient->id === $minor->id) {
                throw new \InvalidArgumentException('A patient cannot be their own guardian.');
            }
            if ($guardianPatient->isMinor()) {
                throw new \InvalidArgumentException('A guardian cannot be a minor.');
            }

            // "guardian is the guardian of minor" → row (patient_id = minor, linked = guardian, is_guardian = true).
            $link = $this->writeLink($minor, $guardianPatient, $type, true, $notes, $actor);

            $this->activity->log(
                $minor,
                'guardian.assigned',
                $actor,
                ['guardian_patient_id' => $guardianPatient->id, 'relationship_type' => $type],
                $minor->relationship_id,
                "Assigned {$guardianPatient->name} as guardian.",
            );

            return $link;
        });
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Create or update the single row for the {$patient, $relative} pair so it
     * records "$relative is $type of $patient" (+ guardian direction). Handles a
     * pre-existing row in either orientation without violating the ordered-pair
     * unique index.
     */
    private function writeLink(Patient $patient, Patient $relative, string $type, bool $asGuardian, ?string $notes, User $actor): PatientLink
    {
        $existing = $this->existingLink($patient, $relative);

        if (! $existing) {
            return PatientLink::create([
                'patient_id'        => $patient->id,
                'linked_patient_id' => $relative->id,
                'relationship_type' => $type,
                'is_guardian'       => $asGuardian,
                'notes'             => $notes,
                'added_by'          => $actor->id,
            ]);
        }

        // Existing row already in this orientation (patient_id = $patient): update in place.
        if ((int) $existing->patient_id === $patient->id) {
            $existing->relationship_type = $type;
            $existing->is_guardian       = $existing->is_guardian || $asGuardian;
            if ($notes !== null) {
                $existing->notes = $notes;
            }
            $existing->save();

            return $existing;
        }

        // Existing row is reversed (patient_id = $relative), meaning "$patient is X of $relative".
        if ($asGuardian) {
            // Guardian direction ($relative guardian of $patient) can't be expressed on the
            // reversed row — re-orient it to (patient_id = $patient, linked = $relative).
            $existing->patient_id        = $patient->id;
            $existing->linked_patient_id = $relative->id;
            $existing->relationship_type = $type;
            $existing->is_guardian       = true;
        } else {
            // Keep orientation; store the inverse (describing $patient, the linked
            // side of the reversed row) so the pair's meaning is preserved.
            $existing->relationship_type = $this->storedInverse($type, $patient);
        }
        if ($notes !== null) {
            $existing->notes = $notes;
        }
        $existing->save();

        return $existing;
    }

    /**
     * The stored (vocabulary-valid) type for the inverse of $type, describing
     * $describes. Only 'parent' needs refining (to mother/father by gender);
     * every other inverse is already a storable type.
     */
    private function storedInverse(string $type, Patient $describes): string
    {
        $inverse = self::INVERSE[$type] ?? 'other';

        if ($inverse === 'parent') {
            return $describes->gender === 'male' ? 'father'
                : ($describes->gender === 'female' ? 'mother' : 'other');
        }

        return $inverse;
    }

    /** The single link between two patients, in either direction (or null). */
    private function existingLink(Patient $a, Patient $b): ?PatientLink
    {
        return PatientLink::where(function ($q) use ($a, $b) {
            $q->where(function ($w) use ($a, $b) {
                $w->where('patient_id', $a->id)->where('linked_patient_id', $b->id);
            })->orWhere(function ($w) use ($a, $b) {
                $w->where('patient_id', $b->id)->where('linked_patient_id', $a->id);
            });
        })->first();
    }

    /** Refine a base label to a gender-specific term where one exists. */
    private function refineLabel(string $base, Patient $person): string
    {
        $g = $person->gender;

        return match ($base) {
            'child'   => $g === 'male' ? 'son'     : ($g === 'female' ? 'daughter' : 'child'),
            'parent'  => $g === 'male' ? 'father'  : ($g === 'female' ? 'mother'   : 'parent'),
            'spouse'  => $g === 'male' ? 'husband' : ($g === 'female' ? 'wife'     : 'spouse'),
            'sibling' => $g === 'male' ? 'brother' : ($g === 'female' ? 'sister'   : 'sibling'),
            default   => $base, // mother, father, other — already specific
        };
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, self::RELATIONSHIP_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid relationship_type '{$type}'. Allowed: " . implode(', ', self::RELATIONSHIP_TYPES) . '.'
            );
        }
    }

    /** Load patients by id, ignoring the branch scope (family crosses branches). */
    private function fetchPatients(Collection|iterable $ids): Collection
    {
        $ids = collect($ids)->unique()->all();
        if (empty($ids)) {
            return collect();
        }

        return Patient::withoutGlobalScope(BranchScope::class)->whereIn('id', $ids)->get();
    }
}
