<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiPagination
 * -------------
 * One reusable way to return long lists to the mobile app. Supports:
 *   ?page=   ?limit=   ?search=   ?sort=   ?dir=
 * and returns the standard envelope plus a "meta" block describing the pages.
 * Keeps mobile payloads small (default 20 rows per page, hard max 100).
 *
 * Used from any API controller (it lives on the base ApiController).
 */
trait ApiPagination
{
    /**
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $searchable  Columns the ?search= term may match against.
     * @param  array  $sortable    Columns the client is allowed to ?sort= by.
     */
    protected function paginated($query, Request $request, array $searchable = [], array $sortable = []): JsonResponse
    {
        // --- Search across the allowed columns ---
        $search = trim((string) $request->query('search', ''));
        if ($search !== '' && $searchable) {
            $query->where(function ($q) use ($searchable, $search) {
                foreach ($searchable as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        // --- Sorting (only columns we explicitly permit) ---
        $sort = $request->query('sort');
        $dir  = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        }

        // --- Paginate (clamp the limit so no client can request everything) ---
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        $page = $query->paginate($limit)->appends($request->query());

        return $this->success($page->items(), '', 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }
}
