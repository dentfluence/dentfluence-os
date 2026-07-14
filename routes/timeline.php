<?php

use App\Http\Controllers\Communication\TimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Communication Timeline — UNREGISTERED 2026-07-14 (production hardening)
|--------------------------------------------------------------------------
| TimelineController still renders hardcoded SAMPLE patients ("Riya Sharma",
| "Amit Kulkarni" …) — see getDummyPatients()/getDummyTimeline(). A paying
| clinic opening this page would see fabricated people and phone numbers.
|
| The nav entries were removed (config/communication.php + the communication
| sidebar) and the controller aborts 404 as a backstop. The routes themselves
| are now unregistered too, so the page doesn't linger as a permanent "broken"
| entry in `php artisan app:crawl-routes` — a report with a known-red line in it
| is a report people stop reading.
|
| TO RESTORE (once the controller is wired to live data — the union query is
| sketched in the TODO inside TimelineController):
|   1. uncomment the routes below
|   2. remove guardNotWired() from TimelineController
|   3. re-add the nav item in config/communication.php + communication-sidebar
|--------------------------------------------------------------------------
*/

// Route::middleware(['auth'])->prefix('communication')->name('communication.')->group(function () {
//
//     // Communication Timeline — patient/lead list
//     Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');
//
//     // Per-person communication timeline
//     Route::get('/timeline/{personId}', [TimelineController::class, 'show'])->name('timeline.show');
// });
