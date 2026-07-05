<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',   // Dentfluence API (/api/v1/...) — added for mobile/Tulip
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/cms.php'));           // has own auth inside
            Route::middleware('web')->group(base_path('routes/clinical-library.php')); // has own auth inside
            Route::middleware('web')->group(base_path('routes/communication.php')); // has own auth + communication.access inside
            // followup.php removed — routes already defined in communication.php
            // prm.php removed — Phase 8 PRM Retirement (Slice 5). PRE (routes/relationship.php)
            // now owns every lead-pipeline write. Archived at under_review/phase8_prm_retirement/.
            Route::middleware('web')->group(base_path('routes/tags-routes.php'));   // has own auth inside
            Route::middleware('web')->group(base_path('routes/timeline.php'));      // has own auth inside
            Route::middleware('web')->group(base_path('routes/reviews.php'));        // PUBLIC patient rating pages (Phase B 2.4)
            Route::middleware('web')->group(base_path('routes/relationship.php')); // Relationship Engine (Phase 2: Today's Actions)
            // prescriptions.php — removed from here; already require'd inside web.php's auth group (line ~395)
            // Loading it here caused duplicate route registration with named routes overwritten without auth
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (Caddy/nginx) in front of the app so Laravel
        // reads X-Forwarded-Proto and knows requests are HTTPS. Without this,
        // generated URLs/redirects would be http:// and secure cookies misbehave.
        // Safe to trust all proxies here: the app is only reachable through our
        // own proxy on the private network, never exposed directly. (Deploy)
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'communication.access' => \App\Http\Middleware\CommunicationModuleAccess::class,
            'module'               => \App\Http\Middleware\CheckModulePermission::class,
            'marketing.active'     => \App\Http\Middleware\EnsureMarketingActive::class,
            'api.role'             => \App\Http\Middleware\EnsureApiRole::class,   // API role gate (mobile/Tulip)
        ]);

        // Add standard security headers to every API response.
        $middleware->appendToGroup('api', \App\Http\Middleware\SecureApiHeaders::class);

        // Enforce a hard cap on web session age (Phase A).
        $middleware->appendToGroup('web', \App\Http\Middleware\AbsoluteSessionTimeout::class);

        // Defence-in-depth security headers on every web response (Phase A).
        $middleware->appendToGroup('web', \App\Http\Middleware\SecureWebHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep API errors in the standard envelope: { success, message, errors }.
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors'  => [],
                ], 401);
            }
        });

        // ── Never leak Laravel/routing internals to staff or the mobile app ──
        // Before this, a bad/renamed route (or a client hitting a stale
        // deployment) surfaced Laravel's raw exception message, e.g.
        // "The route api/v1/relationships/pipelines/leads could not be
        // found." straight to reception staff / the Flutter app. Every api/*
        // request now gets the same clean envelope; the real exception is
        // still logged for developers (PRE mobile rollout, 2026-07-05).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                \Illuminate\Support\Facades\Log::warning('API route not found', [
                    'uri'    => $request->path(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to load data. Please try again.',
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The record you requested could not be found.',
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                    'uri'       => $request->path(),
                    'method'    => $request->method(),
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong on our end. Please try again.',
                    'errors'  => [],
                ], 500);
            }
        });
    })->create();
