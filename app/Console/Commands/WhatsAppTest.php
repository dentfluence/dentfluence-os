<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Console\Command;

/**
 * whatsapp:test — send one WhatsApp message through the full pipeline so you can
 * verify Chunk 2 end-to-end without any UI (Phase B item 1.2).
 *
 * It runs the real flow: thread resolution → DPDP consent gate → record message
 * → provider send. With WHATSAPP_DRY_RUN=true (the default) NOTHING leaves your
 * machine — the payload is logged and the message is stored with status
 * "dry_run", so it's completely safe to run.
 *
 * Examples:
 *   php artisan whatsapp:test 9876543210 --message="Hi from Dentfluence"
 *   php artisan whatsapp:test 9876543210 --patient=1 --message="Your appointment is confirmed"
 *   php artisan whatsapp:test 9876543210 --category=marketing --message="Festive offer"
 */
class WhatsAppTest extends Command
{
    protected $signature = 'whatsapp:test
                            {phone : Recipient phone (any format — it gets normalized)}
                            {--message= : The text to send}
                            {--patient= : Link/force a known patient id (for the consent check)}
                            {--category=service : service or marketing}';

    protected $description = 'Send a test WhatsApp message through the consent gate + provider (dry-run safe)';

    public function handle(OutboundMessageService $outbound): int
    {
        $phone    = $this->argument('phone');
        $message  = $this->option('message') ?: 'Test message from Dentfluence.';
        $category = $this->option('category');

        $opts = ['category' => $category];
        if ($this->option('patient')) {
            $opts['patient_id'] = (int) $this->option('patient');
        }

        $this->info("Sending ({$category}) to {$phone} …");
        $this->line('  Mode: ' . (config('whatsapp.dry_run') ? 'DRY-RUN (nothing actually sent)' : 'LIVE')
            . ' | enabled=' . (config('whatsapp.enabled') ? 'yes' : 'no'));

        $res = $outbound->sendText($phone, $message, $opts);

        $thread = $res['thread'];
        $this->line("  Thread #{$thread->id}  contact={$thread->contact_phone}"
            . ($thread->patient_id ? "  patient_id={$thread->patient_id}" : '  (no patient linked)'));

        if (! $res['ok']) {
            // Either blocked by consent, or the provider failed.
            $this->warn('  Result: NOT SENT');
            $this->warn('  Reason: ' . ($res['reason'] ?? 'unknown'));
            // A blocked-by-consent attempt is a successful test of the gate, so exit 0.
            return self::SUCCESS;
        }

        $msg = $res['message'];
        $this->info("  Result: OK  (message #{$msg->id}, status={$msg->status})");
        $this->line('  Check storage/logs/laravel.log for the dry-run payload, and the wa_messages table for the stored row.');

        return self::SUCCESS;
    }
}
