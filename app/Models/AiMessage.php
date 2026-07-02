<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AiMessage — a single turn in a conversation.
 * Roles: system | user | assistant | tool.
 */
class AiMessage extends Model
{
    public const ROLE_SYSTEM    = 'system';
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL      = 'tool';

    protected $fillable = [
        'ai_conversation_id',
        'user_id',
        'role',
        'content',
        'tool_calls',
        'tool_name',
        'tool_result',
        'model',
        'tokens',
    ];

    protected $casts = [
        'tool_calls'  => 'array',
        'tool_result' => 'array',
        'tokens'      => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shape this row the way Ollama's chat API expects.
     * Tool turns/calls are only included when present (Phase D onward).
     */
    public function toApiArray(): array
    {
        $msg = ['role' => $this->role, 'content' => (string) $this->content];

        if (!empty($this->tool_calls)) {
            $msg['tool_calls'] = $this->tool_calls;
        }
        if ($this->role === self::ROLE_TOOL && $this->tool_name) {
            $msg['name'] = $this->tool_name;
        }

        return $msg;
    }
}
