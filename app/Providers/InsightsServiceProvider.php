<?php

namespace App\Providers;

use App\Contracts\Insights\AppointmentReadContract;
use App\Contracts\Insights\BillingReadContract;
use App\Contracts\Insights\CommunicationReadContract;
use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Relationship\ActivityRecorded;
use App\Listeners\Insights\RecalculateInsightSignalsListener;
use App\Services\Insights\Reads\EloquentAppointmentReadContract;
use App\Services\Insights\Reads\EloquentBillingReadContract;
use App\Services\Insights\Reads\EloquentCommunicationReadContract;
use Illuminate\Support\ServiceProvider;

/**
 * InsightsServiceProvider — Phase 6 · Slice 1 (Insights Engine) + Slice 4
 * (read-contracts).
 *
 * Subscribes the Insights Engine's incremental recompute listener to the
 * domain event bus, and binds the read-contract interfaces the signal
 * calculators depend on to their Eloquent implementations. Additive and
 * self-contained: removing this provider from bootstrap/providers.php fully
 * disables the listener (the `insights.signals` flag already gates its
 * behaviour too) — the contract bindings would need re-adding elsewhere if
 * this provider were ever removed, since the calculators depend on the
 * interfaces, not concrete classes.
 *
 * Nothing else in the app is touched by adding this provider.
 */
class InsightsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AppointmentReadContract::class, EloquentAppointmentReadContract::class);
        $this->app->bind(CommunicationReadContract::class, EloquentCommunicationReadContract::class);
        $this->app->bind(BillingReadContract::class, EloquentBillingReadContract::class);
    }

    public function boot(): void
    {
        $this->app->make(DomainEventBus::class)->subscribe(
            ActivityRecorded::class,
            RecalculateInsightSignalsListener::class,
        );
    }
}
