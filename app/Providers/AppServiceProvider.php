<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        //
    }
}