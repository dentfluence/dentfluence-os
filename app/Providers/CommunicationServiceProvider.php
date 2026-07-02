<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;

class CommunicationServiceProvider extends ServiceProvider
{
    /**
     * Register Communication OS services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            config_path('communication.php'), 'communication'
        );
    }

    /**
     * Bootstrap Communication OS services.
     */
    public function boot(): void
    {
        // Share navigation config with all communication views
        View::composer('layouts.communication', function ($view) {
            $view->with('commNavItems', config('communication.navigation'));
        });

        View::composer('layouts.partials.communication-sidebar', function ($view) {
            $view->with('commNavItems', config('communication.navigation'));
            // Placeholder badge counts — replaced with real queries in Session 11
            $view->with('navBadges', [
                'overdue_count'          => 0,
                'followup_overdue_count' => 0,
                'pending_tasks_count'    => 0,
            ]);
        });
    }
}
