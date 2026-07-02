<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AiActionLog — audit trail for everything the assistant does.
 * Every tool run (read or write) is recorded here for full traceability.
 */
class AiActionLog extends Model
{
    public const CATEGORY_READ      = 'read';
    public const CATEGORY_WRITE     = 'write';
    public const CATEGORY_CLINICAL  = 'clinical';
    public const CATEGORY_FINANCIAL = 'financial';

    public const RESULT_PENDING  = 'pending_confirmation';
    public const RESULT_SUCCESS  = 'success';
    public const RESULT_FAILED   = 'failed';
    public const RESULT_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'ai_conversation_id',
        'ai_message_id',
        'tool_name',
        'category',
        'summary',
        'target_id',
        'target_type',
        'payload',
        'result',
        'error',
        'requires_confirmation',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'payload'               => 'array',
        'requires_confirmation' => 'boolean',
        'confirmed_at'          => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /** The record this action targeted (Patient, Consultation, …). */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->result === self::RESULT_PENDING;
    }
}
