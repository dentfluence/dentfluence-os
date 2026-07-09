<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

// Huddle Repositories
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Repositories\HuddleCardRepository;
use App\Modules\Huddle\Repositories\HuddleTaskRepository;
use App\Modules\Huddle\Repositories\HuddleCommentRepository;

// Huddle Services
use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Modules\Huddle\Services\RoleBasedHuddleService;

// Huddle Transformers
use App\Modules\Huddle\Transformers\AppointmentToCardTransformer;
use App\Modules\Huddle\Transformers\TaskToCardTransformer;

// Phase 4 — B2B Observer
use App\Models\LabCase;
use App\Observers\LabCaseObserver;

// Backend Orchestration — Activity observers (docs/backend-orchestration-plan.md)
use App\Models\Consultation;
use App\Observers\ConsultationActivityObserver;

// Finance — mirror staff into finance_vendors for the Expense form
use App\Models\User;
use App\Observers\UserVendorSyncObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Huddle Module bindings (Session 2)
        $this->app->bind(HuddleBoardRepository::class);
        $this->app->bind(HuddleCardRepository::class);
        $this->app->bind(HuddleAggregationService::class);
        $this->app->bind(AppointmentToCardTransformer::class);
        $this->app->bind(TaskToCardTransformer::class);

        // Huddle Module bindings (Session 3)
        $this->app->singleton(HuddleTaskRepository::class);
        $this->app->singleton(HuddleCommentRepository::class);
        $this->app->singleton(RoleBasedHuddleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phase 4: LabCase observer — auto-sync comm status with lab case status
        LabCase::observe(LabCaseObserver::class);

        // Backend Orchestration: Consultation -> Activity/Timeline (additive,
        // no rule currently matches 'consultation.completed', feeds Insights only)
        Consultation::observe(ConsultationActivityObserver::class);

        // Finance: keep every staff member mirrored into finance_vendors
        // (vendor_type = 'staff') so they appear in the Expense form's
        // Vendor dropdown for petty cash / reimbursements.
        User::observe(UserVendorSyncObserver::class);

        // Security (Phase A): force every generated URL to https when configured
        // (on by default in production). Keeps links/redirects/assets on HTTPS.
        if (config('security.force_https')) {
            URL::forceScheme('https');
        }

        // Security (Phase A): one place that defines what a strong password is.
        // Any validator using `Password::defaults()` gets these rules. We only
        // add the HaveIBeenPwned "uncompromised" check in production so local /
        // offline dev isn't blocked by the external API call.
        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers();
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
