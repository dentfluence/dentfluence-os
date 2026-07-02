<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of guidance attached to a practice protocol:
 *   - sop_steps : ordered checklist (JSON array of strings)
 *   - file      : uploaded document
 *   - link      : external URL
 */
class PracticeProtocolMaterial extends Model
{
    protected $fillable = [
        'practice_protocol_id',
        'type',
        'title',
        'body',
        'file_path',
        'url',
        'sort_order',
    ];

    protected $casts = [
        'body'       => 'array',
        'sort_order' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(PracticeProtocol::class, 'practice_protocol_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isSop(): bool
    {
        return $this->type === 'sop_steps';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function isLink(): bool
    {
        return $this->type === 'link';
    }
}
