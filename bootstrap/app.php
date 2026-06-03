<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/cms.php'));
            Route::middleware('web')->group(base_path('routes/communication.php')); // communication-s7-s8.php removed (duplicate)
            Route::middleware('web')->group(base_path('routes/content-management.php'));
            Route::middleware('web')->group(base_path('routes/followup.php'));
            Route::middleware('web')->group(base_path('routes/prm.php'));
            Route::middleware('web')->group(base_path('routes/tags-routes.php'));
            Route::middleware('web')->group(base_path('routes/timeline.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'communication.access' => \App\Http\Middleware\CommunicationModuleAccess::class,
            'module'               => \App\Http\Middleware\CheckModulePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
