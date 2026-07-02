<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    /**
     * Register Marketing module services.
     */
    public function register(): void
    {
        // Nothing to bind into the container yet.
        // Future: register marketing-specific services (AI clients, queue handlers, etc.)
    }

    /**
     * Bootstrap Marketing module services.
     */
    public function boot(): void
    {
        // Register the 'marketing' view namespace.
        // Blade templates can be referenced as: view('marketing::overview.index')
        $this->loadViewsFrom(resource_path('views/marketing'), 'marketing');
    }
}
