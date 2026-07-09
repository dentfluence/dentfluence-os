<?php

namespace App\Providers;

use App\Contracts\Marketing\Providers\AppointmentProvider;
use App\Contracts\Marketing\Providers\MediaProvider;
use App\Contracts\Marketing\Providers\PatientProvider;
use App\Contracts\Marketing\Providers\ReviewProvider;
use App\Contracts\Marketing\Providers\RevenueProvider;
use App\Contracts\Marketing\Providers\TreatmentProvider;
use App\Services\Marketing\Providers\Integrated\IntegratedMediaProvider;
use App\Services\Marketing\Providers\Integrated\IntegratedRevenueProvider;
use App\Services\Marketing\Providers\Integrated\IntegratedReviewProvider;
use App\Services\Marketing\Providers\Standalone\StandaloneAppointmentProvider;
use App\Services\Marketing\Providers\Standalone\StandaloneMediaProvider;
use App\Services\Marketing\Providers\Standalone\StandalonePatientProvider;
use App\Services\Marketing\Providers\Standalone\StandaloneReviewProvider;
use App\Services\Marketing\Providers\Standalone\StandaloneRevenueProvider;
use App\Services\Marketing\Providers\Standalone\StandaloneTreatmentProvider;
use App\Support\Features\Feature;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    /**
     * Register Marketing module services.
     *
     * V3 (docs/marketing-module-reengineering-plan.md §8-9): binds each
     * provider interface to its Standalone or Integrated implementation,
     * decided per-clinic by the 'marketing.integrated_providers' flag. Each
     * binding is a closure, so the Feature check runs at resolution time
     * (during request handling, once auth/clinic context exists) — not at
     * boot time, when it wouldn't be safe to evaluate yet.
     *
     * Revenue, Review, and (V4) Media have real Integrated implementations so
     * far. The remaining two (Patient, Treatment) bind Standalone in both
     * modes until their Integrated versions are built; flipping the flag for
     * those today is a no-op, not a crash.
     */
    public function register(): void
    {
        $this->app->bind(RevenueProvider::class, fn ($app) => Feature::enabled('marketing.integrated_providers')
            ? $app->make(IntegratedRevenueProvider::class)
            : $app->make(StandaloneRevenueProvider::class));

        $this->app->bind(ReviewProvider::class, fn ($app) => Feature::enabled('marketing.integrated_providers')
            ? $app->make(IntegratedReviewProvider::class)
            : $app->make(StandaloneReviewProvider::class));

        $this->app->bind(MediaProvider::class, fn ($app) => Feature::enabled('marketing.integrated_providers')
            ? $app->make(IntegratedMediaProvider::class)
            : $app->make(StandaloneMediaProvider::class));

        // Not yet built — bind Standalone regardless of the flag until their
        // Integrated counterparts exist.
        $this->app->bind(PatientProvider::class, StandalonePatientProvider::class);
        $this->app->bind(TreatmentProvider::class, StandaloneTreatmentProvider::class);
        $this->app->bind(AppointmentProvider::class, StandaloneAppointmentProvider::class);
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
