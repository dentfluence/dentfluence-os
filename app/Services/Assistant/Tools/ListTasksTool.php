<?php

namespace App\Services\Assistant\Tools;

use App\Models\Task;
use App\Models\User;

/**
 * ListTasksTool — list tasks, reminders, and callbacks with proper filtering.
 * ----------------------------------------------------------------------------
 * Read-only. Crucially, type="calls" returns ONLY communication tasks
 * (call / whatsapp / follow_up) — so "what calls do I have today" doesn't dump
 * unrelated tasks like maintenance or lab work.
 */
class ListTasksTool implements AssistantTool
{
    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'List tasks, reminders, and callbacks. IMPORTANT: set type="calls" to show ONLY '
             . 'calls/callbacks/WhatsApp follow-ups and exclude other task types (maintenance, lab, admin). '
             . 'Filter by due date with "due". Use for "what calls today", "my tasks", "overdue callbacks".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['all', 'calls'],
                    'description' => 'Use "calls" for only call/WhatsApp/follow-up tasks. "all" for every task type.',
                ],
                'due' => [
                    'type'        => 'string',
                    'enum'        => ['today', 'overdue', 'upcoming', 'all'],
                    'description' => 'Which tasks by due date. Defaults to today.',
                ],
                'mine_only' => [
                    'type'        => 'boolean',
                    'description' => 'True to show only the current user\'s own tasks.',
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
        $type = ($args['type'] ?? 'all') === 'calls' ? 'calls' : 'all';
        $due  = in_array($args['due'] ?? '', ['today', 'overdue', 'upcoming', 'all'], true) ? $args['due'] : 'today';

        $q = Task::query()->where('status', 'pending');

        if (!empty($user->branch_id)) {
            $q->where('branch_id', $user->branch_id);
        }
        if (!empty($args['mine_only'])) {
            $q->where('assigned_to', $user->id);
        }

        // Type filter — "calls" = communication tasks only.
        if ($type === 'calls') {
            $q->whereIn('category', Task::COMM_CATEGORIES);
        }

        // Due filter.
        match ($due) {
            'today'    => $q->whereDate('due_date', today()),
            'overdue'  => $q->whereDate('due_date', '<', today()),
            'upcoming' => $q->whereDate('due_date', '>', today()),
            default    => null, // 'all'
        };

        $tasks = $q->with('patient:id,name')
            ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
            ->orderBy('due_date')
            ->limit(25)->get();

        $noun = $type === 'calls' ? 'call/callback' : 'task';

        if ($tasks->isEmpty()) {
            return [
                'summary' => "Listed {$type} tasks ({$due}) — none",
                'content' => "No {$noun}s found for: {$due}.",
            ];
        }

        $lines = $tasks->map(function (Task $t) {
            $label = $t->categoryLabel();
            $who   = $t->patient ? " — {$t->patient->name}" : '';
            $when  = optional($t->due_date)->format('d M');
            return "- [{$label}] {$t->title}{$who} (due {$when}, {$t->priority})";
        })->implode("\n");

        return [
            'summary' => "Listed {$tasks->count()} {$type} task(s) ({$due})",
            'content' => ucfirst($noun) . "s ({$due}) — {$tasks->count()}:\n{$lines}",
        ];
    }
}
