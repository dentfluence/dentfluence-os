<?php

namespace App\Services\Assistant;

use App\Models\AiActionLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Assistant\Tools\AssistantTool;
use App\Services\Assistant\Tools\ConfirmableTool;
use Illuminate\Database\Eloquent\Model;

/**
 * AssistantService — the brain orchestrator for "Tulip".
 * ----------------------------------------------------------------------------
 * Responsibilities:
 *   1. Hold a conversation (persist every turn as AiMessage).
 *   2. Build the prompt: system persona + runtime context + rolling history.
 *   3. Call Ollama, and run any tools the model asks for (the agent loop).
 *   4. Log every tool run to AiActionLog (audit trail).
 *
 * In A2 only READ tools are registered, so the loop is fully safe. Write/confirm
 * handling slots into runTool() in Phase D without changing this flow.
 */
class AssistantService
{
    /** Safety cap on tool-call round-trips per user message. */
    protected int $maxSteps = 5;

    /**
     * Set when ask() defers a clinical/financial action awaiting confirmation.
     * The controller reads this to render a confirm card.
     */
    public ?AiActionLog $pendingAction = null;

    public function __construct(
        protected OllamaClient $ollama,
        protected ToolRegistry $tools,
    ) {}

    /** Start a fresh conversation for a staff member, optionally about a record. */
    public function startConversation(User $user, ?Model $context = null): AiConversation
    {
        return AiConversation::create([
            'user_id'      => $user->id,
            'branch_id'    => $user->branch_id ?? null,
            'context_id'   => $context?->getKey(),
            'context_type' => $context ? $context->getMorphClass() : null,
            'model'        => config('assistant.model'),
            'status'       => 'active',
        ]);
    }

    /**
     * Send one user message and get the assistant's reply (persisted).
     * Returns the final assistant AiMessage.
     */
    public function ask(AiConversation $conversation, string $userText, User $user, ?string $contextNote = null): AiMessage
    {
        // 1. Persist the user's turn.
        $conversation->messages()->create([
            'user_id' => $user->id,
            'role'    => AiMessage::ROLE_USER,
            'content' => $userText,
        ]);
        $conversation->titleFrom($userText);

        // ── Deterministic shortcut: the daily huddle is a fixed report, so we
        //    run it directly instead of relying on the small model to tool-call
        //    it (which it does unreliably). Fast and always correct.
        if ($this->isHuddleRequest($userText)) {
            return $this->directHuddle($conversation, $user);
        }

        $model = $conversation->model ?: config('assistant.model');

        // 2. Build the payload: system + rolling history.
        $systemContent = $this->systemPrompt($user, $conversation);
        if ($contextNote) {
            $systemContent .= "\nThe user is currently on this screen: {$contextNote}";
        }

        $payload = array_merge(
            [['role' => 'system', 'content' => $systemContent]],
            $this->history($conversation),
        );

        // 3. Agent loop: call model, run tools, repeat until a plain answer.
        for ($step = 0; $step < $this->maxSteps; $step++) {
            $reply = $this->ollama->chat($payload, $this->tools->definitions(), $model);

            $toolCalls = $reply['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                // Final answer — persist and return.
                $assistantMsg = $conversation->messages()->create([
                    'role'    => AiMessage::ROLE_ASSISTANT,
                    'content' => $reply['content'] ?? '',
                    'model'   => $model,
                ]);
                $conversation->forceFill(['model' => $model, 'last_message_at' => now()])->save();
                return $assistantMsg;
            }

            // ── Confirm gate: if the model wants a clinical/financial action,
            //    DON'T run it — defer into a confirm card the user must approve.
            foreach ($toolCalls as $call) {
                [$name, $args] = $this->parseCall($call);
                $tool = $this->tools->get($name);
                if ($tool && $this->needsConfirmation($tool)) {
                    return $this->deferAction($conversation, $user, $tool, $args, $model);
                }
            }

            // The model wants tools. Record its request turn...
            $conversation->messages()->create([
                'role'       => AiMessage::ROLE_ASSISTANT,
                'content'    => $reply['content'] ?? '',
                'tool_calls' => $toolCalls,
                'model'      => $model,
            ]);
            $payload[] = [
                'role'       => 'assistant',
                'content'    => $reply['content'] ?? '',
                'tool_calls' => $toolCalls,
            ];

            // ...then run each tool and feed results back.
            foreach ($toolCalls as $call) {
                [$name, $args] = $this->parseCall($call);
                $result = $this->runTool($name, $args, $user, $conversation);

                $conversation->messages()->create([
                    'role'        => AiMessage::ROLE_TOOL,
                    'tool_name'   => $name,
                    'content'     => $result['content'],
                    'tool_result' => $result,
                ]);
                $payload[] = [
                    'role'    => 'tool',
                    'name'    => $name,
                    'content' => $result['content'],
                ];
            }
        }

        // Safety net: loop exhausted without a plain answer.
        $fallback = $conversation->messages()->create([
            'role'    => AiMessage::ROLE_ASSISTANT,
            'content' => "I gathered the information but couldn't finish composing a reply. Please try rephrasing.",
            'model'   => $model,
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        return $fallback;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /** Execute a single tool by name, with audit logging. */
    protected function runTool(string $name, array $args, User $user, AiConversation $conversation): array
    {
        $tool = $this->tools->get($name);

        if (!$tool) {
            return ['summary' => "Unknown tool: {$name}", 'content' => "Tool '{$name}' is not available."];
        }

        $log = AiActionLog::create([
            'user_id'            => $user->id,
            'ai_conversation_id' => $conversation->id,
            'tool_name'          => $name,
            'category'           => $tool->category(),
            'payload'            => $args,
            'result'             => AiActionLog::RESULT_SUCCESS,
        ]);

        try {
            $result = $tool->run($args, $user);

            $log->update([
                'summary'     => $result['summary'] ?? null,
                'target_id'   => isset($result['target']) ? $result['target']?->getKey() : null,
                'target_type' => isset($result['target']) ? $result['target']?->getMorphClass() : null,
                'result'      => AiActionLog::RESULT_SUCCESS,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $log->update([
                'result' => AiActionLog::RESULT_FAILED,
                'error'  => $e->getMessage(),
            ]);
            return [
                'summary' => "Tool {$name} failed",
                'content' => "The {$name} tool hit an error: " . $e->getMessage(),
            ];
        }
    }

    /** Pull tool name + arguments out of an Ollama tool_call (handles both shapes). */
    protected function parseCall(array $call): array
    {
        $fn   = $call['function'] ?? $call;
        $name = $fn['name'] ?? '';
        $args = $fn['arguments'] ?? [];

        if (is_string($args)) {
            $args = json_decode($args, true) ?: [];
        }

        return [$name, is_array($args) ? $args : []];
    }

    // ── Confirm-card flow (Phase D2) ────────────────────────────────────────

    /** True if this tool requires user confirmation before running. */
    protected function needsConfirmation(AssistantTool $tool): bool
    {
        // Any tool that implements ConfirmableTool always confirms (e.g. booking),
        // plus anything whose category is in the confirm list (clinical/financial).
        if ($tool instanceof ConfirmableTool) {
            return true;
        }
        $confirm = (array) config('assistant.confirm_categories', ['clinical', 'financial']);
        return in_array($tool->category(), $confirm, true);
    }

    /**
     * Defer a clinical/financial action: create a pending log + a proposal
     * message, set $pendingAction, and return. Nothing is written yet.
     */
    protected function deferAction(AiConversation $conversation, User $user, AssistantTool $tool, array $args, string $model): AiMessage
    {
        $preview = $tool instanceof ConfirmableTool
            ? $tool->preview($args, $user)
            : ('perform ' . $tool->name());

        $log = AiActionLog::create([
            'user_id'               => $user->id,
            'ai_conversation_id'    => $conversation->id,
            'tool_name'             => $tool->name(),
            'category'              => $tool->category(),
            'summary'               => $preview,
            'payload'               => $args,
            'result'                => AiActionLog::RESULT_PENDING,
            'requires_confirmation' => true,
        ]);

        $msg = $conversation->messages()->create([
            'role'    => AiMessage::ROLE_ASSISTANT,
            'content' => "I'd like to: {$preview}\n\nShall I go ahead?",
            'model'   => $model,
        ]);

        $this->pendingAction = $log;
        $conversation->forceFill(['model' => $model, 'last_message_at' => now()])->save();

        return $msg;
    }

    /** Execute a previously-deferred action after the user confirms. */
    public function confirmAction(AiActionLog $log, User $user): AiMessage
    {
        $conversation = $log->conversation;
        $tool = $this->tools->get($log->tool_name);

        if (!$tool) {
            $log->update(['result' => AiActionLog::RESULT_FAILED, 'error' => 'Tool no longer available']);
            return $conversation->messages()->create([
                'role'    => AiMessage::ROLE_ASSISTANT,
                'content' => "Sorry — that action is no longer available.",
            ]);
        }

        try {
            $result = $tool->run((array) $log->payload, $user);

            $log->update([
                'result'       => AiActionLog::RESULT_SUCCESS,
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
                'summary'      => $result['summary'] ?? $log->summary,
                'target_id'    => isset($result['target']) ? $result['target']?->getKey() : null,
                'target_type'  => isset($result['target']) ? $result['target']?->getMorphClass() : null,
            ]);

            $msg = $conversation->messages()->create([
                'role'    => AiMessage::ROLE_ASSISTANT,
                'content' => $result['content'] ?? 'Done.',
            ]);
        } catch (\Throwable $e) {
            $log->update(['result' => AiActionLog::RESULT_FAILED, 'error' => $e->getMessage()]);
            $msg = $conversation->messages()->create([
                'role'    => AiMessage::ROLE_ASSISTANT,
                'content' => "That action failed: " . $e->getMessage(),
            ]);
        }

        $conversation->forceFill(['last_message_at' => now()])->save();
        return $msg;
    }

    /** Discard a deferred action after the user cancels. */
    public function rejectAction(AiActionLog $log, User $user): AiMessage
    {
        $log->update(['result' => AiActionLog::RESULT_REJECTED]);

        return $log->conversation->messages()->create([
            'role'    => AiMessage::ROLE_ASSISTANT,
            'content' => "Okay, I won't do that.",
        ]);
    }

    /** Does this message ask for the daily huddle / morning briefing? */
    protected function isHuddleRequest(string $text): bool
    {
        $t = strtolower($text);
        return str_contains($t, 'huddle')
            || str_contains($t, 'morning briefing')
            || str_contains($t, 'daily briefing');
    }

    /** Run the huddle report directly (no LLM tool-call) and persist it. */
    protected function directHuddle(AiConversation $conversation, User $user): AiMessage
    {
        $branch = $user->branch_id ?? null;
        $text   = app(\App\Services\Huddle\HuddleService::class)->render($branch);

        $msg = $conversation->messages()->create([
            'role'    => AiMessage::ROLE_ASSISTANT,
            'content' => "Here's your daily huddle:\n\n" . $text,
            'model'   => 'huddle:direct',
        ]);

        AiActionLog::create([
            'user_id'            => $user->id,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id'      => $msg->id,
            'tool_name'          => 'daily_huddle',
            'category'           => AiActionLog::CATEGORY_READ,
            'summary'            => 'Generated daily huddle (direct)',
            'result'             => AiActionLog::RESULT_SUCCESS,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
        return $msg;
    }

    /** The rolling window of recent turns, in Ollama format. */
    protected function history(AiConversation $conversation): array
    {
        $limit = (int) config('assistant.history_limit', 20);

        return $conversation->messages()
            ->latest('id')->limit($limit)->get()
            ->sortBy('id')
            ->map(fn (AiMessage $m) => $m->toApiArray())
            ->values()->all();
    }

    /** Persona + runtime context (current user, date) the model sees each call. */
    protected function systemPrompt(User $user, AiConversation $conversation): string
    {
        $base = strtr((string) config('assistant.system_prompt'), [
            '{name}'           => config('assistant.name', 'Tulip'),
            '{reply_language}' => config('assistant.reply_language', 'English'),
        ]);

        $context = "\n\n--- Current context ---\n"
            . "Staff member: " . ($user->name ?? 'Unknown') . "\n"
            . "Date & time: " . now()->format('l, d M Y H:i') . "\n";

        // If the chat is pinned to a record, tell the model.
        if ($conversation->context_type && $conversation->context_id) {
            $short = class_basename($conversation->context_type);
            $context .= "This conversation is about: {$short} #{$conversation->context_id}\n";
        }

        return $base . $context;
    }
}
