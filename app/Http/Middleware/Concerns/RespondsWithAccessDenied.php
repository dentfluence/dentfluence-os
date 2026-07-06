<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared "access denied" response for permission-gate middleware.
 *
 * Previously these middleware called abort(403, $message), which swaps
 * the whole page for Laravel's bare error page — jarring, unbranded,
 * and requires a back-button to recover. Instead we bounce the user
 * back to wherever they came from (or the dashboard, if there's no
 * previous page) and flash the reason. The layout renders that flash
 * as a blocking popup instead of navigating anywhere.
 *
 * See: resources/views/partials/access-denied-modal.blade.php
 */
trait RespondsWithAccessDenied
{
    protected function denyAccess(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->back(fallback: route('dashboard'))
            ->with('access_denied', $message);
    }
}
