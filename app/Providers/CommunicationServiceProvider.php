<?php

namespace App\Providers;

use App\Models\CommunicationQueue;
use App\Models\FollowUp;
use App\Models\Task;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
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
            $view->with('navBadges', $this->navBadges());
        });
    }

    /**
     * Real sidebar badge counts (2026-07-14 — these were hardcoded to 0, so
     * genuinely overdue work was invisible to staff).
     *
     * Cached for 60s: the sidebar renders on every Communication page, and
     * these are three small aggregate queries, not per-row reads.
     *
     * Fails soft — a badge is decoration; it must never 500 a page.
     */
    private function navBadges(): array
    {
        try {
            return cache()->remember('comm.nav_badges', 60, function () {
                return [
                    // Communication Manager — queue items flagged/past due, still open.
                    'overdue_count' => CommunicationQueue::query()
                        ->whereNotIn('status', ['closed', 'completed'])
                        ->where(function ($q) {
                            $q->where('is_overdue', true)
                              ->orWhere('status', 'overdue')
                              ->orWhere(fn ($w) => $w->whereNotNull('due_at')->where('due_at', '<', now()));
                        })
                        ->count(),

                    // Follow-up Engine — pending follow-ups past their due date.
                    'followup_overdue_count' => FollowUp::overdue()->count(),

                    // Tasks & Assignments — everything not yet done.
                    'pending_tasks_count' => Task::where('status', 'pending')->count(),
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('Communication nav badge counts failed', ['error' => $e->getMessage()]);

            return [
                'overdue_count'          => 0,
                'followup_overdue_count' => 0,
                'pending_tasks_count'    => 0,
            ];
        }
    }
}
