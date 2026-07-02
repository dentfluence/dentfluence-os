<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Relationship;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * RelationshipListController — PRE (Phase 1 · Workstream D, slice 5).
 *
 * A searchable, filterable, paginated browse over the whole relationship base —
 * the piece the dashboard's 12-row snapshot was missing. Read-only and additive.
 *
 * Query params (all optional, all preserved across paging):
 *   q      — search name / phone / email
 *   status — active | dormant | lost
 *   has    — lead | patient   (only relationships that have one)
 *   sort   — name | score | relationship_since | created_at
 *   dir    — asc | desc
 *
 * Route: GET /relationship/list  [relationship.list]
 */
class RelationshipListController extends Controller
{
    private const SORTS   = ['name', 'score', 'relationship_since', 'created_at'];
    private const STATUSES = ['active', 'dormant', 'lost'];

    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null;
        $has    = in_array($request->query('has'), ['lead', 'patient'], true) ? $request->query('has') : null;
        $sort   = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'created_at';
        $dir    = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = Relationship::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($has === 'lead') {
            $query->whereHas('lead');
        } elseif ($has === 'patient') {
            $query->whereHas('patient');
        }

        $relationships = $query
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('relationship.list.index', [
            'relationships' => $relationships,
            'q'             => $q,
            'status'        => $status,
            'has'           => $has,
            'sort'          => $sort,
            'dir'           => $dir,
            'total'         => Relationship::count(),
        ]);
    }
}
