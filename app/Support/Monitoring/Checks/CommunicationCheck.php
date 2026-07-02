<?php

namespace App\Support\Monitoring\Checks;

use App\Support\Monitoring\HealthCheck;

/**
 * Reports whether outbound communication is configured (presence-only).
 *
 * Phase 0 only checks that the config seams exist — it does NOT call any
 * external provider (that belongs to the Integration Engine, Phase 7).
 * Missing config is 'warn', not 'fail', since a clinic may not use a channel.
 */
class CommunicationCheck implements HealthCheck
{
    public function key(): string
    {
        return 'communication';
    }

    public function run(): array
    {
        $whatsapp = (bool) config('whatsapp.phone_number_id') || (bool) config('whatsapp.token');
        $mailer   = (bool) config('mail.default');

        $meta = [
            'whatsapp_configured' => $whatsapp,
            'mail_configured'     => $mailer,
        ];

        // Presence-only: if nothing is configured, warn; otherwise ok.
        $status = ($whatsapp || $mailer) ? 'ok' : 'warn';

        return ['status' => $status, 'meta' => $meta];
    }
}
