<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use Illuminate\Console\Command;

/**
 * PushSweep — N-5 safety net (2026-09-09).
 *
 * The dispatcher queues a SendPushNotification per popup row. If the queue
 * worker was down when that happened, the job is simply gone — the row would
 * sit with push=true and push_sent_at=null forever, and nobody would know.
 *
 * This picks up any such row between 2 minutes and 2 hours old and queues it
 * again. The floor keeps it from racing the original job; the ceiling stops
 * it resurrecting yesterday's alerts after a long outage — those are stale,
 * and the bell already carries them.
 *
 *   php artisan push:sweep
 */
class PushSweep extends Command
{
    protected $signature = 'push:sweep';
    protected $description = 'Re-queue popup pushes the queue worker missed (N-5 safety net)';

    public function handle(): int
    {
        $rows = AppNotification::query()
            ->where('push', true)
            ->whereNull('push_sent_at')
            ->whereNull('acknowledged_at')
            ->whereBetween('created_at', [now()->subHours(2), now()->subMinutes(2)])
            ->limit(200)
            ->get(['id']);

        foreach ($rows as $row) {
            SendPushNotification::dispatch($row->id);
        }

        $this->info("push:sweep re-queued {$rows->count()} push(es).");

        return self::SUCCESS;
    }
}
