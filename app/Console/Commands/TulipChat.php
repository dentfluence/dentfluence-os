<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\OllamaClient;
use Illuminate\Console\Command;

/**
 * tulip:chat — terminal test harness for the assistant engine (A2).
 * ----------------------------------------------------------------------------
 * Lets you talk to Tulip from the command line before any UI exists.
 *
 *   php artisan tulip:chat "what's on the schedule today?"
 *   php artisan tulip:chat "find patient sharma" --user=1
 *
 * Pass no message to enter an interactive loop (type 'exit' to quit).
 */
class TulipChat extends Command
{
    protected $signature = 'tulip:chat {message? : Your message to the assistant}
                                       {--user= : User ID to run as (defaults to first user)}
                                       {--new : Force a brand new conversation}';

    protected $description = 'Chat with the Tulip AI assistant from the terminal (engine test).';

    public function handle(AssistantService $assistant, OllamaClient $ollama): int
    {
        // 0. Is Ollama up?
        if (!$ollama->isUp()) {
            $this->error('Ollama is not reachable at ' . config('assistant.ollama_url'));
            $this->line('Start Ollama, then check: ollama --version');
            return self::FAILURE;
        }

        $installed = $ollama->models();
        $model     = config('assistant.model');
        if (!empty($installed) && !in_array($model, $installed, true)) {
            $this->warn("Configured model '{$model}' isn't pulled yet. Installed: " . implode(', ', $installed));
            $this->line("Run: ollama pull {$model}");
        }

        // 1. Pick the staff member to run as.
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::query()->orderBy('id')->first();

        if (!$user) {
            $this->error('No user found to run as. Create a user first, or pass --user=ID.');
            return self::FAILURE;
        }

        $this->info("Running as: {$user->name} (#{$user->id})  •  model: {$model}");

        // 2. Conversation (reuse latest unless --new).
        $conversation = (!$this->option('new'))
            ? AiConversation::where('user_id', $user->id)->latest('id')->first()
            : null;
        $conversation ??= $assistant->startConversation($user);

        $this->line("Conversation #{$conversation->id}");
        $this->newLine();

        // 3. One-shot or interactive.
        $message = $this->argument('message');

        if ($message) {
            $this->respond($assistant, $conversation, $user, $message);
            return self::SUCCESS;
        }

        $this->comment("Interactive mode — type 'exit' to quit.");
        while (true) {
            $input = $this->ask('You');
            if (in_array(strtolower(trim((string) $input)), ['exit', 'quit', 'q'], true)) {
                break;
            }
            if (trim((string) $input) === '') {
                continue;
            }
            $this->respond($assistant, $conversation, $user, $input);
        }

        return self::SUCCESS;
    }

    protected function respond(AssistantService $assistant, AiConversation $conversation, User $user, string $text): void
    {
        $start = microtime(true);

        try {
            $reply = $assistant->ask($conversation, $text, $user);
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $secs = round(microtime(true) - $start, 1);

        $this->newLine();
        $this->line('<fg=cyan>' . config('assistant.name') . ":</> " . $reply->content);
        $this->newLine();

        // Show any tools that ran this turn (for debugging).
        $tools = $conversation->actionLogs()->latest('id')->limit(5)->get()
            ->where('created_at', '>=', now()->subSeconds((int) ceil($secs) + 2));
        if ($tools->isNotEmpty()) {
            $this->comment('Tools used: ' . $tools->pluck('tool_name')->implode(', '));
        }
        $this->comment("({$secs}s)");
    }
}
