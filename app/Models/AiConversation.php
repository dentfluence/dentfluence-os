<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * AiConversation — one chat thread with the assistant ("Tulip").
 * Belongs to the staff member who started it (per-user memory).
 */
class AiConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'branch_id',
        'context_id',
        'context_type',
        'title',
        'model',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ordered oldest→newest so it can be fed straight to the model. */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(AiActionLog::class);
    }

    /** The record this chat is "about" (Patient, Consultation, …) if any. */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Set a short title from the first user message (called once). */
    public function titleFrom(string $text): void
    {
        if ($this->title) return;
        $this->title = Str::limit(trim(preg_replace('/\s+/', ' ', $text)), 60);
        $this->save();
    }

    public function touchLastMessage(): void
    {
        $this->forceFill(['last_message_at' => now()])->save();
    }
}
