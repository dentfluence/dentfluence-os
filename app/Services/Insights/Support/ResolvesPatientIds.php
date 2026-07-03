<?php

namespace App\Services\Insights\Support;

use App\Models\Relationship;
use Illuminate\Support\Facades\DB;

/**
 * Shared helper for every Insights signal calculator — resolves the patient
 * record(s) linked to a Master Relationship. A relationship can have several
 * linked patients (households sharing one phone — see Relationship::patients()),
 * so signals aggregate across ALL of them, not just the first.
 *
 * Deliberately tiny and dependency-free: each calculator owns exactly the
 * tables its signal needs, this only resolves the join key.
 */
trait ResolvesPatientIds
{
    /**
     * @return array<int,int> patient IDs linked to this relationship (may be empty).
     */
    protected function resolvePatientIds(Relationship $relationship): array
    {
        $ids = DB::table('patients')
            ->where('relationship_id', $relationship->id)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
