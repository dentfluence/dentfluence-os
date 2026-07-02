<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Huddle\HuddleService;

/**
 * DailyHuddleTool — lets Tulip generate the morning huddle on request in chat,
 * then answer follow-ups about it. Read-only. Scoped to the staff member's branch.
 */
class DailyHuddleTool implements AssistantTool
{
    public function name(): string
    {
        return 'daily_huddle';
    }

    public function description(): string
    {
        return 'Generate the daily huddle / morning briefing for the clinic: today\'s schedule, '
             . 'patient safety alerts, money to collect, treatment opportunities, yesterday\'s flow & '
             . 'collections, no-shows, pending tasks/callbacks, lab cases due, low stock, and new patients. '
             . 'Use for "run the huddle", "morning briefing", or "what does my day look like".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type'        => 'string',
                    'description' => 'Optional date (YYYY-MM-DD). Omit for today.',
                ],
            ],
            'required' => [],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $branch = $user->branch_id ?? null;
        $text   = app(HuddleService::class)->render($branch, $args['date'] ?? null);

        return [
            'summary' => 'Generated daily huddle briefing',
            // Hint to the model: present this faithfully, don't drop sections.
            'content' => "Here is the full daily huddle briefing. Present it to the user clearly, "
                       . "keeping all sections and their items:\n\n" . $text,
        ];
    }
}
