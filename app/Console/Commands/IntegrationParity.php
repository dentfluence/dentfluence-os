<?php

namespace App\Console\Commands;

use App\Models\IntegrationShadowLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * integration:parity — Phase 7 (cutover decision tooling).
 *
 * Read-only report over `integration_shadow_log`, the table IntegrationEngine
 * writes to every time a provider touchpoint (legacy or connector path) is
 * shadow-compared. This command does NOT re-run anything and does NOT flip
 * any flag — it just summarises what's accumulated so Sumit can decide
 * whether `integration.<provider>` is safe to flip on.
 *
 * Usage:
 *   php artisan integration:parity            — whatsapp (default)
 *   php artisan integration:parity whatsapp
 *   php artisan integration:parity google
 *   php artisan integration:parity meta
 *   php artisan integration:parity website
 */
class IntegrationParity extends Command
{
    /** What actually has to happen for each provider to produce a row — shown when the table is empty. */
    private const TRIGGER_HINTS = [
        'whatsapp' => 'Send a WhatsApp message (OutboundMessageService::sendText()/sendTemplate()).',
        'google'   => 'Connect/reconnect a Google Business Profile or Google Analytics integration, or let its health-check ping run (Marketing → Integrations), or publish a scheduled post with a Google Business Profile variant.',
        'meta'     => 'Connect/reconnect Instagram or Facebook (Marketing → Integrations), publish a scheduled post with an Instagram/Facebook variant, or receive a Meta Lead Ads webhook.',
        'website'  => 'Publish a scheduled post with a WordPress variant.',
    ];

    protected $signature   = 'integration:parity {provider=whatsapp : Which provider to report on}';
    protected $description = 'Report agreement/divergence stats from the Phase 7 Integration Engine shadow-run (read-only, no flags flipped)';

    public function handle(): int
    {
        $provider = $this->argument('provider');

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>🔍 Integration Engine Parity</> — ' . now()->format('D d M Y, H:i'));
        $this->line("  Provider: <fg=yellow>{$provider}</>");
        $this->newLine();

        $rows = IntegrationShadowLog::where('provider', $provider)->get();

        if ($rows->isEmpty()) {
            $this->warn("  No shadow-run data yet for [{$provider}].");
            $hint = self::TRIGGER_HINTS[$provider] ?? "No trigger is documented for '{$provider}' yet — it may not be wrapped in this phase (see docs/phase-7/README.md).";
            $this->line("  To get evidence: {$hint}");
            $this->line('  Rows accumulate whether integration.' . $provider . ' is on or off.');
            $this->newLine();
            $this->line('  Nothing to decide yet — there is no evidence either way.');
            $this->newLine();
            return self::SUCCESS;
        }

        $total  = $rows->count();
        $agreed = $rows->where('agreed', true)->count();
        $rated  = $rows->whereNotNull('agreed')->count();
        $agreeRate = $rated > 0 ? round(($agreed / $rated) * 100, 1) : null;

        $byAction = $rows->groupBy('action')->map->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Shadow-run rows',   $total],
                ['  via legacy (flag off)',  $byAction->get('legacy', 0)],
                ['  via connector (flag on)', $byAction->get('cutover', 0)],
                ['Agreed',            $agreed . ' / ' . $rated],
                ['Agreement rate',    $agreeRate !== null ? $agreeRate . '%' : 'n/a'],
            ]
        );

        $divergent = $rows->where('agreed', false);

        if ($divergent->isNotEmpty()) {
            $this->newLine();
            $this->warn("  ⚠ {$divergent->count()} divergent row(s) — most recent 20:");
            $this->table(
                ['ID', 'Method', 'Action', 'Notes'],
                $divergent->sortByDesc('id')->take(20)->map(fn ($r) => [
                    $r->id,
                    $r->method,
                    $r->action,
                    $r->notes ? Str::limit($r->notes, 60) : '',
                ])->values()->all()
            );
        }

        $this->newLine();
        $this->line('  This report does not flip any flag. Note: agreement is a real');
        $this->line('  byte-for-byte payload match only while sends are dry_run/disabled');
        $this->line('  (the local/default state). Once real sends go live, "agreed" just');
        $this->line('  confirms the connector reported success — see IntegrationEngine::log().');
        $this->line("  The cutover decision (flip integration.{$provider}) is Sumit's call.");
        $this->newLine();

        return self::SUCCESS;
    }
}
