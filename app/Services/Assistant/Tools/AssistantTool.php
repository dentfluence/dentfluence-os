<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * AssistantTool — contract every tool the assistant can use must implement.
 * ----------------------------------------------------------------------------
 * A "tool" is a capability we hand to the model: searching patients, reading a
 * schedule, (later) creating records. The model decides when to call one; the
 * AssistantService executes it and feeds the result back.
 */
interface AssistantTool
{
    /** Machine name the model calls, e.g. 'find_patient'. snake_case. */
    public function name(): string;

    /** Plain-English description so the model knows when to use it. */
    public function description(): string;

    /** JSON-schema for the arguments (Ollama/OpenAI "parameters" shape). */
    public function parameters(): array;

    /**
     * Risk category — drives the confirm-card rules:
     * 'read' | 'write' | 'clinical' | 'financial'.
     */
    public function category(): string;

    /**
     * Execute the tool.
     *
     * @return array{summary:string, content:string, target?:object|null}
     *   - summary: one-line audit description
     *   - content: text result shown to the model
     *   - target:  optional Eloquent model the action touched (for the log)
     */
    public function run(array $args, User $user): array;
}
