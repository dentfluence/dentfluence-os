<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Console\Command;

/**
 * whatsapp:template — send a pre-approved TEMPLATE message through the full
 * pipeline (Phase B item 1.2, Chunk 4). Templates work OUTSIDE the 24-hour
 * window, which is what makes reminders/recalls possible.
 *
 * Dry-run safe (WHATSAPP_DRY_RUN=true → nothing actually sent).
 *
 * Examples:
 *   php artisan whatsapp:template 9876543210 appointment_reminder --patient=7 \
 *       --var=name=Asha --var="date=1 Jul" --var="time=4:00 PM"
 *
 *   php artisan whatsapp:template 9876543210 recall_due --patient=7 \
 *       --var=name=Asha --var="treatment=6-month cleaning"
 */
class WhatsAppTemplateTest extends Command
{
    protected $signature = 'whatsapp:template
                            {phone : Recipient phone (any format)}
                            {template : Template key from config/whatsapp.php}
                            {--patient= : Known patient id (for the consent check)}
                            {--var=* : Variables as name=value (repeatable)}';

    protected $description = 'Send a test WhatsApp TEMPLATE message (dry-run safe)';

    public function handle(OutboundMessageService $outbound): int
    {
        $phone    = $this->argument('phone');
        $template = $this->argument('template');

        // Parse repeatable --var=name=value into an associative array.
        $vars = [];
        foreach ($this->option('var') as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $vars[trim($k)] = $v;
        }

        $opts = [];
        if ($this->option('patient')) {
            $opts['patient_id'] = (int) $this->option('patient');
        }

        $this->info("Sending template '{$template}' to {$phone} …");
        $this->line('  Mode: ' . (config('whatsapp.dry_run') ? 'DRY-RUN (nothing actually sent)' : 'LIVE')
            . ' | enabled=' . (config('whatsapp.enabled') ? 'yes' : 'no'));
        $this->line('  Vars: ' . (empty($vars) ? '(none)' : json_encode($vars)));

        $res = $outbound->sendTemplate($phone, $template, $vars, $opts);

        if (! $res['ok']) {
            $this->warn('  Result: NOT SENT');
            $this->warn('  Reason: ' . ($res['reason'] ?? 'unknown'));
            return self::SUCCESS;
        }

        $msg = $res['message'];
        $this->info("  Result: OK  (message #{$msg->id}, status={$msg->status})");
        $this->line('  Preview: ' . $msg->body);

        return self::SUCCESS;
    }
}
