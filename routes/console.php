<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Recall Engine — Phase 2, Communication OS
|--------------------------------------------------------------------------
| Runs daily at 7:00am. Auto-creates communication_queue items for:
|   - 6-month no-visit patients
|   - Approved treatment plans with no appointment
|   - Post-op follow-up (14 days after surgery)
|   - Lab case received but no appointment booked
|   - 7-day post-treatment follow-up
|   - Birthday / anniversary re-engagement
|
| Manual trigger: php artisan recall:run
| Preview only:   php artisan recall:run --dry-run
*/
Schedule::command('recall:run')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/recall-engine.log'));

/*
|--------------------------------------------------------------------------
| WhatsApp — Appointment Reminders (Phase B 1.2)
|--------------------------------------------------------------------------
| Runs daily at 10:00am. Sends the approved `appointment_reminder` template to
| every patient with a SCHEDULED appointment the next day. DPDP consent-gated +
| idempotent (a dedup key means re-runs never double-send). Dormant unless
| WHATSAPP_ENABLED=true; safe in dry-run.
|
| Manual trigger: php artisan whatsapp:send-reminders
| Preview only:   php artisan whatsapp:send-reminders --dry-run
*/
Schedule::command('whatsapp:send-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/whatsapp-reminders.log'));

/*
|--------------------------------------------------------------------------
| Reviews — Auto-request after completed visits (Phase B 2.4)
|--------------------------------------------------------------------------
| Runs daily at 11:00am. Asks patients whose visit was completed yesterday for
| a review over WhatsApp. Idempotent (one request per appointment), DPDP-gated,
| dormant unless REVIEWS_ENABLED + WHATSAPP_ENABLED.
|
| Manual trigger: php artisan reviews:request
| Preview only:   php artisan reviews:request --dry-run
*/
Schedule::command('reviews:request')
    ->dailyAt('11:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reviews-request.log'));

/*
|--------------------------------------------------------------------------
| Lab Module — Auto-create Tasks for overdue lab cases
|--------------------------------------------------------------------------
| Runs daily at 9:00am. For each overdue lab case (expected date passed)
| with no active task, auto-creates a Task assigned to the clinic manager.
| Also creates doctor-review tasks for trial work sitting 2+ days.
|
| Manual trigger: php artisan lab:create-overdue-tasks
| Branch-scoped:  php artisan lab:create-overdue-tasks --branch=1
*/
Schedule::command('lab:create-overdue-tasks')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/lab-overdue-tasks.log'));

/*
|--------------------------------------------------------------------------
| Phase 5 — Communication OS: Digest Emails + Auto-Escalate
|--------------------------------------------------------------------------
|
| 07:05am  → Morning briefing to every staff member (their queue for today)
|             Runs after recall:run so recall items are already in the queue.
|
| 14:00    → SLA breach alert to clinic manager (2pm)
|
| 18:00    → Evening summary to every staff member (done / open / won)
|
| Every 30 min → Auto-escalate ₹30k+ leads not contacted in 2 hours
|
| Manual triggers:
|   php artisan comm:morning-briefing [--dry-run]
|   php artisan comm:sla-alert [--email=x@y.com]
|   php artisan comm:evening-summary [--dry-run]
|   php artisan comm:auto-escalate [--dry-run]
*/

// 7:05am — morning briefing (5 min after recall so recall items are queued)
Schedule::command('comm:morning-briefing')
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/comm-morning-briefing.log'));

// 2:00pm — SLA breach alert to manager
Schedule::command('comm:sla-alert')
    ->dailyAt('14:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/comm-sla-alert.log'));

// 6:00pm — evening summary to all staff
Schedule::command('comm:evening-summary')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/comm-evening-summary.log'));

// Every 30 minutes — auto-escalate high-value leads not contacted in 2h
Schedule::command('comm:auto-escalate')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/comm-auto-escalate.log'));

/*
|--------------------------------------------------------------------------
| HR — Auto-Absent Marking
|--------------------------------------------------------------------------
| Runs at 10:30am every day (weekdays only).
| Marks any staff with no check-in record as absent.
| Admin can always override manually on the attendance board.
|
| Manual trigger: php artisan hr:mark-absent
| Preview only:   php artisan hr:mark-absent --dry-run
*/
Schedule::command('hr:mark-absent')
    ->weekdays()
    ->dailyAt('10:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/hr-auto-absent.log'));

/*
|--------------------------------------------------------------------------
| Tasks — Shift-Start / Shift-End Reminders
|--------------------------------------------------------------------------
| Runs every 5 minutes. Checks if any staff shift is starting or ending
| within a 5-minute window and fires an in-app notification with their
| pending task count for the day.
|
| Manual trigger: php artisan tasks:shift-reminder
| Preview only:   php artisan tasks:shift-reminder --dry-run
*/
Schedule::command('tasks:shift-reminder')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tasks-shift-reminder.log'));

/*
|--------------------------------------------------------------------------
| Tasks — Periodic In-Shift Reminders (every 2 hours)
|--------------------------------------------------------------------------
| Fires every 2 hours for staff currently within their shift window who
| have >= 1 pending/overdue task. Nudges them to stay on top of their list.
|
| Manual trigger: php artisan tasks:periodic-reminder
| Preview only:   php artisan tasks:periodic-reminder --dry-run
*/
Schedule::command('tasks:periodic-reminder')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tasks-periodic-reminder.log'));

/*
|--------------------------------------------------------------------------
| Route Health Crawler — nightly self-test of every page
|--------------------------------------------------------------------------
| Runs nightly at 2:00am. Logs in as the configured tester account and
| visits every GET page, then writes a colour-coded report to
| storage/app/route-reports/. Catches crashes, blank/truncated pages and
| broken reports automatically — open the latest report each morning.
|
| Only runs when CRAWL_EMAIL and CRAWL_PASSWORD are set in .env, so it
| stays dormant until you opt in. Add to .env:
|     CRAWL_URL=http://dentfluence.test
|     CRAWL_EMAIL=you@example.com
|     CRAWL_PASSWORD=your-password
|
| Manual trigger: php artisan app:crawl-routes --url=... --email=... --password=...
*/
Schedule::command('app:crawl-routes', [
        '--url'      => env('CRAWL_URL', config('app.url')),
        '--email'    => env('CRAWL_EMAIL'),
        '--password' => env('CRAWL_PASSWORD'),
    ])
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/route-crawler.log'))
    ->when(fn () => filled(env('CRAWL_EMAIL')) && filled(env('CRAWL_PASSWORD')));

/*
|--------------------------------------------------------------------------
| Relationship Engine — Appointment Reminder Tasks (Phase 4)
|--------------------------------------------------------------------------
| Runs daily at 8:00am. Finds all appointments scheduled for tomorrow and
| auto-creates a "Reminder call" Task for today so reception knows to call.
| Idempotent — deduplication prevents double-creation on re-runs.
|
| Manual trigger: php artisan relationship:appointment-reminders
| Preview only:   php artisan relationship:appointment-reminders --dry-run
*/
Schedule::command('relationship:appointment-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/appointment-reminders.log'));

/*
|--------------------------------------------------------------------------
| Daily Huddle — morning briefing (Tulip AI module)
|--------------------------------------------------------------------------
| Runs at 8:00am daily and writes the full huddle briefing to a log so the
| team can read the day's plan each morning. Pure data (no AI), so it's fast
| and always accurate.
|
| Manual trigger: php artisan tulip:huddle
| For a date:     php artisan tulip:huddle --date=2026-06-24
*/
Schedule::command('tulip:huddle')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-huddle.log'));

/*
|--------------------------------------------------------------------------
| Practice Protocols — daily task generation
|--------------------------------------------------------------------------
| Runs at 00:10 every day. Turns active practice protocols into real tasks
| for the staff who hold the matching role. Idempotent, so re-runs never
| create duplicates.
|
| Manual trigger: php artisan protocols:generate
| For a date:     php artisan protocols:generate --date=2026-06-27
| Preview only:   php artisan protocols:generate --dry-run
*/
Schedule::command('protocols:generate')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/protocol-generate.log'));

/*
|--------------------------------------------------------------------------
| Relationship Engine — Today's Actions projection rebuild (Phase 1 · WS E)
|--------------------------------------------------------------------------
| Runs every 15 minutes. Rebuilds the `today_actions` projection from the
| TodayActionsEngine so the reception dashboard, the Huddle snapshot, and the
| flag-gated Today's Actions page all read a fresh, pre-computed view instead
| of querying ~12 domains live. Safe + idempotent (the view is replaced each
| run). Reads only flip to the projection when `today.projection` is ON.
|
| Manual trigger: php artisan today:rebuild-projection
| Parity check:   php artisan today:rebuild-projection --check
*/
Schedule::command('today:rebuild-projection')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/today-actions-projection.log'));

/*
|--------------------------------------------------------------------------
| Analytics Engine — snapshot rebuild (Phase 6 · Slice 2)
|--------------------------------------------------------------------------
| Runs every 15 minutes. Rebuilds the `analytics_snapshots` projection from
| AnalyticsController's own (now-public) metric methods — same cadence as the
| Today's Actions projection. Shadow only: the live /relationship/analytics
| dashboard still renders from AnalyticsController's own cached methods;
| nothing reads this projection yet.
|
| Manual trigger: php artisan analytics:rebuild-snapshots
| Parity check:   php artisan analytics:rebuild-snapshots --check
*/
Schedule::command('analytics:rebuild-snapshots')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/analytics-snapshots.log'));
