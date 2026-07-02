<?php

namespace App\Services\Assistant\Tools;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * CreateTaskTool — create a task / reminder / callback for the staff member.
 * ----------------------------------------------------------------------------
 * Low-risk WRITE (category 'write'), so it runs immediately without a confirm
 * card. Writes into the existing `tasks` table exactly like the app does.
 */
class CreateTaskTool implements AssistantTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Create a task, reminder, or callback for the current staff member. '
             . 'Use for "remind me to…", "add a task to…", "schedule a call to…". '
             . 'Can link to a patient and set a due date and priority.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title'       => ['type' => 'string', 'description' => 'Short task title, e.g. "Call about crown fitting".'],
                'description' => ['type' => 'string', 'description' => 'Optional longer detail / notes for the task.'],
                'due_date' => ['type' => 'string', 'description' => "Due date: 'today', 'tomorrow', or YYYY-MM-DD. Defaults to today."],
                'patient'  => ['type' => 'string', 'description' => 'Optional patient name, phone, or ID to link the task to.'],
                'priority' => ['type' => 'string', 'enum' => ['urgent', 'high', 'medium', 'low'], 'description' => 'Defaults to medium.'],
                'category' => ['type' => 'string', 'enum' => ['call', 'whatsapp', 'follow_up', 'clinical', 'lab', 'admin', 'other'], 'description' => 'Defaults to follow_up.'],
            ],
            'required' => ['title'],
        ];
    }

    public function category(): string
    {
        return 'write'; // low-risk → auto-executes (no confirm card)
    }

    public function run(array $args, User $user): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            return ['summary' => 'Create task — missing title', 'content' => 'I need a short title for the task.'];
        }

        $due      = $this->parseDue($args['due_date'] ?? null);
        $priority = in_array($args['priority'] ?? '', ['urgent', 'high', 'medium', 'low'], true) ? $args['priority'] : 'medium';
        $category = array_key_exists($args['category'] ?? '', Task::CATEGORIES) ? $args['category'] : 'follow_up';

        // Optional patient link.
        $patient = null;
        if (!empty($args['patient'])) {
            $patient = $this->resolvePatient((string) $args['patient']);
        }

        $task = Task::create([
            'title'       => $title,
            'description' => $args['description'] ?? null,
            'assigned_to' => $user->id,
            'created_by'  => $user->id,
            'branch_id'   => $user->branch_id ?? null,
            'patient_id'  => $patient?->id,
            'due_date'    => $due->toDateString(),
            'priority'    => $priority,
            'category'    => $category,
            'status'      => 'pending',
        ]);

        $for  = $patient ? " for {$patient->name}" : '';
        $when = $due->isToday() ? 'today' : ($due->isTomorrow() ? 'tomorrow' : $due->format('d M Y'));

        return [
            'summary' => "Created task \"{$title}\"{$for} due {$when}",
            'content' => "Done — created a {$category} task \"{$title}\"{$for}, due {$when} (priority: {$priority}).",
            'target'  => $task,
        ];
    }

    /** Parse a flexible due-date string into a Carbon date. */
    protected function parseDue(?string $s): Carbon
    {
        $s = strtolower(trim((string) $s));
        if ($s === '' || $s === 'today')  return today();
        if ($s === 'tomorrow')            return today()->addDay();

        try {
            return Carbon::parse($s);
        } catch (\Throwable $e) {
            return today();
        }
    }
}
