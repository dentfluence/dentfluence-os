<?php

namespace App\Console\Commands;

use App\Services\Automation\RecallShadowRunner;
use App\Services\Automation\ReminderAutomationRunner;
use App\Services\Communication\FollowUpRulesService;
use App\Services\Relationship\FollowUpRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * automation:parity — Phase 2, Slice 3.
 *
 * Runs the shadow dual-run and prints a per-trigger diff of the LEGACY vs the
 * AUTOMATION decision. Read-only: writes only to automation_shadow_log, never to
 * communication_queue. Use this to confirm zero divergence BEFORE flipping the
 * automation.engine flag for a trigger (Slice 4).
 *
 * Usage:
 *   php artisan automation:parity            — recall (no_visit_6months)
 *   php artisan automation:parity recall     — same, explicit
 */
class AutomationParity extends Command
{
    protected $signature   = 'automation:parity {surface=recall : Which surface to parity-check (recall)}';
    protected $description = 'Shadow dual-run: compare legacy vs Automation Engine decisions (no writes to comm queue)';

    public function handle(RecallShadowRunner $runner): int
    {
        $surface = $this->argument('surface');

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>🔍 Automation Parity</> — ' . now()->format('D d M Y, H:i'));
        $this->newLine();

        if (! in_array($surface, ['recall', 'reminders', 'rules'], true)) {
            $this->error("  Unknown surface [{$surface}]. Supported: recall, reminders, rules.");
            return self::INVALID;
        }

        if ($surface === 'reminders') {
            return $this->reminders();
        }

        if ($surface === 'rules') {
            return $this->rules();
        }

        $runId = (string) Str::uuid();
        $this->line("  Run ID: <fg=gray>{$runId}</>");
        $this->line('  Trigger: <fg=yellow>no_visit_6months</> (shadow only — no comm items created)');
        $this->newLine();

        $summary = $runner->run($runId);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Candidates evaluated', $summary['candidates']],
                ['Legacy would queue',   $summary['legacy_queue']],
                ['Automation would queue', $summary['automation_queue']],
                ['Divergences', $summary['divergences'] > 0
                    ? "<fg=red>{$summary['divergences']}</>"
                    : '<fg=green>0</>'],
            ]
        );

        if ($summary['divergences'] > 0) {
            $this->newLine();
            $this->warn('  ⚠  Divergent patient IDs (review before cutover):');
            $this->line('     ' . implode(', ', $summary['divergent_patient_ids']));
            $this->newLine();
            $this->line('  <fg=red>Not safe to cut over yet — reconcile the differences above.</>');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Zero divergence — Automation reproduces legacy for this trigger.</>');
        $this->line('  Safe-to-cutover gate (Slice 4) is green for no_visit_6months.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Reminders surface — read-only preview of how many appointment-reminder
     * tasks the Automation path WOULD create for tomorrow. There is no legacy
     * comparison here: the legacy AppointmentReminderEngine is broken on the
     * current schema (created_by NOT NULL), which is exactly what the cutover
     * fixes. Writes nothing.
     */
    private function reminders(): int
    {
        $this->line('  Surface: <fg=yellow>appointment reminders</> (tomorrow — shadow only, no tasks created)');
        $this->newLine();

        $would = app(ReminderAutomationRunner::class)->previewCount();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Reminder tasks Automation would create', $would],
                ['Legacy path', '<fg=red>broken (created_by NOT NULL) — fixed by cutover</>'],
            ]
        );

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Preview complete.</> Flip automation.engine on to let Automation own reminders.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Rules surface — proves the Rules-Engine-owned FollowUpRuleEngine reproduces
     * the legacy FollowUpRulesService for EVERY configured trigger. Read-only:
     * resolve() computes definitions, it does not write. Zero divergence is the
     * gate for flipping rules.single_engine on.
     */
    private function rules(): int
    {
        $this->line('  Surface: <fg=yellow>follow-up rules</> (legacy FollowUpRulesService vs ported FollowUpRuleEngine)');
        $this->newLine();

        $legacy = new FollowUpRulesService();
        $ported = app(FollowUpRuleEngine::class);

        // Fixed base date so both sides compute identical due dates.
        $ctx      = ['base_date' => '2026-07-06', 'patient_id' => 1, 'lead_id' => null, 'assigned_to' => null];
        $checked  = 0;
        $diverged = 0;
        $diffs    = [];

        foreach (config('followup_rules', []) as $triggerType => $section) {
            foreach ($section as $value => $inner) {
                // treatment_status_changed nests one more level: [treatment][status]
                $subValues = ($triggerType === 'treatment_status_changed') ? array_keys($inner) : [''];

                foreach ($subValues as $sub) {
                    $a = $legacy->resolve($triggerType, (string) $value, (string) $sub, $ctx);
                    $b = $ported->resolve($triggerType, (string) $value, (string) $sub, $ctx);
                    $checked++;

                    if (json_encode($a) !== json_encode($b)) {
                        $diverged++;
                        $diffs[] = "{$triggerType}/{$value}" . ($sub !== '' ? "/{$sub}" : '');
                    }
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Trigger combinations checked', $checked],
                ['Divergences', $diverged > 0 ? "<fg=red>{$diverged}</>" : '<fg=green>0</>'],
            ]
        );

        if ($diverged > 0) {
            $this->newLine();
            $this->warn('  ⚠  Divergent triggers: ' . implode(', ', $diffs));
            $this->line('  <fg=red>Not safe to retire the legacy service yet.</>');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Zero divergence — the ported engine matches legacy for every trigger.</>');
        $this->line('  Safe to flip rules.single_engine on.');
        $this->newLine();

        return self::SUCCESS;
    }
}
