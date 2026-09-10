<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\Notifications\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendPushNotification — N-5 (2026-09-09).
 *
 * One queued job per popup-level notification row. Queued so a slow or
 * unreachable FCM never delays the consultation save that produced it —
 * QUEUE_CONNECTION=database, so this needs the queue worker (it runs as its
 * own Docker service in production).
 *
 * Takes an ID, not the model: by the time the worker picks it up the row may
 * have been acknowledged, and re-reading gets the truth. Two tries only —
 * a push is a moment, and a notification that arrives ten minutes late is
 * worse than one that never arrives, because the bell already has it.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $notificationId) {}

    public function handle(FcmSender $sender): void
    {
        $notification = AppNotification::find($this->notificationId);
        if (! $notification) {
            return;
        }

        // Answered at the desk before the worker got here — the phone buzzing
        // now would send someone to a job already done.
        if ($notification->acknowledged_at) {
            $notification->forceFill(['push_sent_at' => now()])->saveQuietly();

            return;
        }

        $sender->send($notification);
    }
}
